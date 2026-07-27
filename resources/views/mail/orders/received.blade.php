<x-mail::message>
# Recibimos tu pedido

Hola {{ $order->customer_name }}, tu pedido **{{ $order->code }}** quedo registrado.

@component('mail::table')
| Concepto | Importe |
|:---------|--------:|
@foreach ($order->items as $item)
| {{ $item->quantity }} × {{ $item->product_name }} | ${{ number_format($item->line_total_cents / 100, 2) }} |
@endforeach
| Envio | {{ $order->shipping_cents === 0 ? 'Gratis' : '$'.number_format($order->shipping_cents / 100, 2) }} |
| **Total** | **{{ $order->total }}** |
@endcomponent

@if ($instructions)
## Como pagar

{{ $instructions }}
@endif

<x-mail::button :url="$trackingUrl">
Ver mi pedido
</x-mail::button>

Guarda ese enlace: con el puedes consultar tu pedido y subir tu comprobante en
cualquier momento, sin necesidad de crear una cuenta.

Cualquier duda, escribenos al WhatsApp {{ config('storefront.contact.whatsapp') }}.

Gracias por tu compra,<br>
{{ config('app.name') }}
</x-mail::message>
