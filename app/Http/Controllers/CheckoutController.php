<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cart_payload' => ['required', 'json'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'shipping_street' => ['required', 'string', 'max:255'],
            'shipping_number' => ['nullable', 'string', 'max:50'],
            'shipping_neighborhood' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:255'],
            'shipping_state' => ['required', 'string', 'max:255'],
            'shipping_postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'shipping_reference' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['bank_transfer', 'cash_on_delivery', 'card_on_delivery'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cartItems = collect(json_decode($validated['cart_payload'], true, flags: JSON_THROW_ON_ERROR))
            ->map(fn (array $item): array => [
                'id' => (int) ($item['id'] ?? 0),
                'quantity' => max(1, min(99, (int) ($item['quantity'] ?? 1))),
            ])
            ->filter(fn (array $item): bool => $item['id'] > 0)
            ->values();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'cart_payload' => 'Agrega al menos un producto al carrito.',
            ]);
        }

        $products = Product::query()
            ->active()
            ->whereIn('id', $cartItems->pluck('id'))
            ->get()
            ->keyBy('id');

        if ($products->count() !== $cartItems->pluck('id')->unique()->count()) {
            throw ValidationException::withMessages([
                'cart_payload' => 'Uno o mas productos ya no estan disponibles.',
            ]);
        }

        $order = DB::transaction(function () use ($validated, $cartItems, $products): Order {
            $subtotalCents = $cartItems->sum(function (array $item) use ($products): int {
                return $products[$item['id']]->price_cents * $item['quantity'];
            });
            $shippingCents = $subtotalCents >= (int) config('store.free_shipping_threshold_cents')
                ? 0
                : (int) config('store.shipping_flat_cents');

            $order = Order::create([
                'code' => $this->makeOrderCode(),
                'status' => 'pending_confirmation',
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
                'subtotal_cents' => $subtotalCents,
                'shipping_cents' => $shippingCents,
                'total_cents' => $subtotalCents + $shippingCents,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'],
                'shipping_street' => $validated['shipping_street'],
                'shipping_number' => $validated['shipping_number'] ?? null,
                'shipping_neighborhood' => $validated['shipping_neighborhood'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_state' => $validated['shipping_state'],
                'shipping_postcode' => $validated['shipping_postcode'],
                'shipping_reference' => $validated['shipping_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($cartItems as $item) {
                $product = $products[$item['id']];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit_price_cents' => $product->price_cents,
                    'quantity' => $item['quantity'],
                    'line_total_cents' => $product->price_cents * $item['quantity'],
                ]);
            }

            return $order;
        });

        return redirect()
            ->route('storefront.home')
            ->with('checkout_order_code', $order->code);
    }

    private function makeOrderCode(): string
    {
        do {
            $code = 'CHX-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::where('code', $code)->exists());

        return $code;
    }
}
