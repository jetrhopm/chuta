<x-storefront.layouts.simple :title="'Pedido '.$order->code">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        @if (session('receipt_uploaded'))
            <p class="mb-6 border-l-4 border-[var(--color-success)] bg-[var(--color-surface-muted)] p-4 text-sm">
                Recibimos tu comprobante. Lo revisamos y te confirmamos el pedido en cuanto validemos el pago.
            </p>
        @endif

        @if (session('payment_cancelled'))
            <p class="mb-6 border-l-4 border-[var(--color-warning)] bg-[var(--color-surface-muted)] p-4 text-sm">
                Parece que no completaste el pago. Puedes intentarlo de nuevo desde aqui.
            </p>
        @endif

        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--color-brand)]">Pedido</p>
        <h1 class="impact-title mt-1 text-3xl uppercase text-black sm:text-4xl">{{ $order->code }}</h1>

        <dl class="mt-6 grid gap-4 border border-[var(--color-border)] p-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-[var(--color-ink-soft)]">Estado del pago</dt>
                <dd class="display-title mt-1 text-2xl text-black">{{ $order->payment_status->label() }}</dd>
                <p class="mt-1 text-sm text-[var(--color-ink-soft)]">{{ $order->payment_status->customerMessage() }}</p>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-[var(--color-ink-soft)]">Total</dt>
                <dd class="display-title mt-1 text-2xl text-black">{{ $order->total }}</dd>
                <p class="mt-1 text-sm text-[var(--color-ink-soft)]">
                    Envio: {{ $order->shipping_cents === 0 ? 'gratis' : '$'.number_format($order->shipping_cents / 100, 2) }}
                </p>
            </div>
        </dl>

        {{-- Instrucciones de transferencia. Se generan con la configuracion
             vigente, no se guardan con el pedido, para que un cambio de datos
             bancarios no deje instrucciones viejas circulando. --}}
        @if ($instructions)
            <section class="mt-8 border-l-4 border-[var(--color-brand)] bg-[var(--color-surface-muted)] p-5">
                <h2 class="display-title text-2xl text-black">Como pagar</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[var(--color-ink)]">{{ $instructions }}</p>
            </section>
        @endif

        @if ($canUploadReceipt)
            <section class="mt-6 border border-[var(--color-border)] p-5">
                <h2 class="display-title text-2xl text-black">Sube tu comprobante</h2>
                <p class="mt-2 text-sm text-[var(--color-ink-soft)]">Imagen o PDF, hasta 5 MB.</p>

                <form
                    method="POST"
                    action="{{ route('receipts.store', ['code' => $order->code]) }}"
                    enctype="multipart/form-data"
                    class="mt-4 flex flex-col gap-3 sm:flex-row"
                >
                    @csrf
                    <label class="sr-only" for="receipt">Comprobante</label>
                    <input
                        id="receipt"
                        type="file"
                        name="receipt"
                        required
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="w-full border border-[var(--color-border)] bg-white p-2 text-sm"
                    >
                    <button type="submit" class="display-title bg-[var(--color-brand)] px-6 py-2 text-lg text-white transition hover:bg-[var(--color-brand-strong)]">
                        Enviar
                    </button>
                </form>

                @error('receipt')
                    <p class="mt-2 text-sm text-[var(--color-danger)]">{{ $message }}</p>
                @enderror
            </section>
        @endif

        @if ($order->receipts->isNotEmpty())
            <section class="mt-6">
                <h2 class="display-title text-2xl text-black">Comprobantes enviados</h2>
                <ul class="mt-3 grid gap-2">
                    @foreach ($order->receipts as $receipt)
                        <li class="flex flex-wrap items-center justify-between gap-2 border border-[var(--color-border)] p-3 text-sm">
                            <span>{{ $receipt->created_at->format('d/m/Y H:i') }}</span>
                            <span class="font-bold">{{ $receipt->statusLabel() }}</span>
                            @if ($receipt->review_comment)
                                <span class="w-full text-[var(--color-ink-soft)]">{{ $receipt->review_comment }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-8">
            <h2 class="display-title text-2xl text-black">Lo que pediste</h2>
            <ul class="mt-3 divide-y divide-[var(--color-border)] border border-[var(--color-border)]">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4 p-3 text-sm">
                        <span>{{ $item->quantity }} &times; {{ $item->product_name }}</span>
                        <span class="font-bold">${{ number_format($item->line_total_cents / 100, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="mt-8 text-sm text-[var(--color-ink-soft)]">
            Guarda este enlace para volver a consultar tu pedido.
            Cualquier duda, escribenos al WhatsApp {{ config('storefront.contact.whatsapp') }}.
        </p>

        <a href="{{ route('storefront.home') }}" class="display-title mt-6 inline-block bg-black px-6 py-3 text-lg text-white transition hover:bg-[var(--color-brand)]">
            Volver a la tienda
        </a>
    </div>
</x-storefront.layouts.simple>
