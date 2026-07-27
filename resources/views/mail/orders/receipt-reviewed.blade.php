<x-mail::message>
@if ($accepted)
# Confirmamos tu pago

Hola {{ $order->customer_name }}, revisamos tu comprobante y todo esta correcto.
Tu pedido **{{ $order->code }}** ya quedo confirmado y lo estamos preparando.
@else
# Necesitamos otro comprobante

Hola {{ $order->customer_name }}, revisamos el comprobante que subiste para el
pedido **{{ $order->code }}** y no pudimos validarlo.
@endif

@if ($receipt->review_comment)
@component('mail::panel')
{{ $receipt->review_comment }}
@endcomponent
@endif

@unless ($accepted)
Tu pedido sigue reservado. Puedes subir otro comprobante desde el enlace de abajo.
@endunless

<x-mail::button :url="$trackingUrl">
Ver mi pedido
</x-mail::button>

Cualquier duda, escribenos al WhatsApp {{ config('storefront.contact.whatsapp') }}.

{{ config('app.name') }}
</x-mail::message>
