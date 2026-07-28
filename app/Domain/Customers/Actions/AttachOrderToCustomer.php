<?php

namespace App\Domain\Customers\Actions;

use App\Models\Customer;
use App\Models\Order;

class AttachOrderToCustomer
{
    public function handle(Order $order): Customer
    {
        $email = $this->normalizeEmail($order->customer_email);

        $customer = $email !== null
            ? Customer::firstOrNew(['normalized_email' => $email])
            : Customer::firstOrNew(['phone' => $order->customer_phone]);

        $customer->fill([
            'name' => $order->customer_name,
            'email' => $order->customer_email,
            'normalized_email' => $email,
            'phone' => $order->customer_phone,
            'last_order_at' => $order->created_at ?? now(),
        ]);

        $customer->save();

        $order->forceFill(['customer_id' => $customer->id])->save();

        $this->syncDefaultAddress($customer, $order);
        $this->refreshStats($customer);

        return $customer;
    }

    private function syncDefaultAddress(Customer $customer, Order $order): void
    {
        $customer->addresses()->updateOrCreate(
            [
                'postcode' => $order->shipping_postcode,
                'street' => $order->shipping_street,
                'number' => $order->shipping_number,
            ],
            [
                'label' => 'Principal',
                'recipient_name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'neighborhood' => $order->shipping_neighborhood,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'reference' => $order->shipping_reference,
                'is_default' => true,
            ],
        );
    }

    private function refreshStats(Customer $customer): void
    {
        $customer->forceFill([
            'orders_count' => $customer->orders()->count(),
            'lifetime_value_cents' => (int) $customer->orders()->sum('total_cents'),
            'last_order_at' => $customer->orders()->max('created_at'),
        ])->save();
    }

    private function normalizeEmail(?string $email): ?string
    {
        return filled($email) ? mb_strtolower(trim($email)) : null;
    }
}
