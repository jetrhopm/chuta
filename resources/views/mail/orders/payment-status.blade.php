<x-mail::message>
# {{ $status->label() }}

Hola {{ $order->customer_name }}, hay novedades sobre el pago de tu pedido
**{{ $order->code }}**.

{{ $status->customerMessage() }}

@component('mail::panel')
Total del pedido: **{{ $order->total }}**
@endcomponent

@if ($status === App\Domain\Payments\Enums\PaymentStatus::Approved)
Ya estamos preparando tu pedido. Te avisamos cuando salga.
@elseif (in_array($status, [App\Domain\Payments\Enums\PaymentStatus::Rejected, App\Domain\Payments\Enums\PaymentStatus::Expired], true))
Puedes intentar el pago de nuevo o elegir otro metodo desde tu pedido.
@endif

<x-mail::button :url="$trackingUrl">
Ver mi pedido
</x-mail::button>

Cualquier duda, escribenos al WhatsApp {{ config('storefront.contact.whatsapp') }}.

{{ config('app.name') }}
</x-mail::message>
