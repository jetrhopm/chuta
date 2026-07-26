<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\DeductStockForOrder;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Domain\Shipping\Actions\CalculateShipping;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly DeductStockForOrder $deductStock,
        private readonly CalculateShipping $calculateShipping,
        private readonly StartPayment $startPayment,
        private readonly PaymentGatewayRegistry $registry,
    ) {}

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
            // Solo los metodos que de verdad estan configurados. Un proveedor sin
            // credenciales no se puede elegir, asi que no llega a fallar despues.
            'payment_method' => ['required', Rule::in($this->registry->availableProviderValues())],
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

        // Aviso temprano con un mensaje util. No sustituye a la comprobacion
        // real: entre esta lectura y el descuento pueden entrar otras compras,
        // y de eso se encarga el bloqueo de fila dentro de la transaccion.
        $this->guardAgainstUnavailableQuantities($cartItems, $products);

        try {
            $order = $this->createOrder($validated, $cartItems, $products);
        } catch (InsufficientStock $exception) {
            // El pedido ya se deshizo con la transaccion. Solo queda contarlo en
            // terminos que el cliente entienda, sin tecnicismos.
            throw ValidationException::withMessages([
                'cart_payload' => $exception->customerMessage(),
            ]);
        }

        // El cobro se pide fuera de la transaccion del pedido a proposito: una
        // llamada a un proveedor externo puede tardar, y mantener abierta la
        // transaccion que bloquea filas de inventario mientras se espera dejaria
        // el catalogo trabado para todos los demas.
        $attempt = $this->startPayment->handle(
            $order,
            PaymentProvider::from($validated['payment_method']),
        );

        // Los proveedores redireccionados cobran en su propia pantalla.
        if ($attempt->checkout_url !== null) {
            return redirect()->away($attempt->checkout_url);
        }

        return redirect()
            ->to($order->trackingUrl())
            ->with('checkout_order_code', $order->code);
    }

    /**
     * @param  Collection<int, array{id: int, quantity: int}>  $cartItems
     * @param  Collection<int, Product>  $products
     */
    private function guardAgainstUnavailableQuantities($cartItems, $products): void
    {
        foreach ($cartItems as $item) {
            $product = $products[$item['id']];

            if (! $product->canFulfill($item['quantity'])) {
                throw ValidationException::withMessages([
                    'cart_payload' => (new InsufficientStock(
                        $product,
                        $item['quantity'],
                        (int) $product->availableQuantity(),
                    ))->customerMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  Collection<int, array{id: int, quantity: int}>  $cartItems
     * @param  Collection<int, Product>  $products
     *
     * @throws InsufficientStock
     */
    private function createOrder(array $validated, $cartItems, $products): Order
    {
        return DB::transaction(function () use ($validated, $cartItems, $products): Order {
            $subtotalCents = $cartItems->sum(function (array $item) use ($products): int {
                return $products[$item['id']]->price_cents * $item['quantity'];
            });

            // El envio se calcula aqui, en el servidor. Lo que la tienda muestre
            // en el navegador es solo un adelanto para que el cliente vea el
            // total al instante; nunca es lo que se cobra.
            $quote = $this->calculateShipping->handle(
                subtotalCents: $subtotalCents,
                state: $validated['shipping_state'],
                postcode: $validated['shipping_postcode'],
            );

            if (! $quote->isAvailable()) {
                throw ValidationException::withMessages([
                    'shipping_postcode' => $quote->unavailableReason,
                ]);
            }

            $shippingCents = $quote->costCents;

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

            // Dentro de la misma transaccion: si aqui falta inventario, el
            // pedido no llega a existir. Cada descuento vuelve a leer el
            // producto con la fila bloqueada, de modo que dos compras
            // simultaneas no pueden vender la misma ultima pieza.
            $this->deductStock->handle($order->load('items.product'));

            return $order;
        });
    }

    private function makeOrderCode(): string
    {
        do {
            $code = 'CHX-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::where('code', $code)->exists());

        return $code;
    }
}
