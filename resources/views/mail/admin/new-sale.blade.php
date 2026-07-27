<x-mail::message>
# Venta nueva

Entro el pedido **{{ $order->code }}** por **{{ $order->total }}**.

@component('mail::table')
| Dato | Valor |
|:-----|:------|
| Cliente | {{ $order->customer_name }} |
| Telefono | {{ $order->customer_phone }} |
| Correo | {{ $order->customer_email ?? 'Sin correo' }} |
| Metodo de pago | {{ $order->payment_method }} |
| Estado del pago | {{ $order->payment_status->label() }} |
| Envio | {{ $order->shipping_cents === 0 ? 'Gratis' : '$'.number_format($order->shipping_cents / 100, 2) }} |
@endcomponent

## Entregar en

{{ $order->shipping_street }} {{ $order->shipping_number }}<br>
{{ $order->shipping_neighborhood }}, {{ $order->shipping_city }}, {{ $order->shipping_state }}<br>
C.P. {{ $order->shipping_postcode }}

@if ($order->shipping_reference)
Referencias: {{ $order->shipping_reference }}
@endif

@component('mail::table')
| Producto | Cantidad |
|:---------|---------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} ({{ $item->sku }}) | {{ $item->quantity }} |
@endforeach
@endcomponent

{{ config('app.name') }}
</x-mail::message>
