@php
    $seoTitle = $product->seo_title ?: $product->name;
    $seoDescription = $product->seo_description ?: Str::limit(strip_tags((string) ($product->short_description ?? $product->description)), 155);
    $gallery = $product->images->isNotEmpty() ? $product->images : collect();
@endphp

<x-storefront.layouts.simple :title="$seoTitle.' | Chutamax'">
    {{-- Datos para buscadores y para compartir en redes. --}}
    @push('head')
        <meta name="description" content="{{ $seoDescription }}">
        <link rel="canonical" href="{{ route('products.show', ['slug' => $product->slug]) }}">
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ route('products.show', ['slug' => $product->slug]) }}">
        @if ($product->image_url)
            <meta property="og:image" content="{{ $product->image_url }}">
        @endif
        <script>
            window.chutamaxMetaTrack('ViewContent', {
                content_ids: [@js((string) $product->id)],
                content_name: @js($product->name),
                content_type: 'product',
                value: @js($product->price_cents / 100),
                currency: 'MXN',
            });
        </script>
    @endpush

    <div
        class="p-5 sm:p-8"
        x-data="{
            cantidad: 1,
            maximo: @js($product->track_inventory ? max(1, $product->stock) : 99),
            agregar() {
                {{-- Se reutiliza el carrito de la portada guardando en el mismo
                     almacenamiento, para no tener dos carritos distintos. --}}
                const carrito = JSON.parse(localStorage.getItem('chutamax_cart') || '[]');
                const producto = @js([
                    'id' => $product->id,
                    'name' => $product->name,
                    'price_cents' => $product->price_cents,
                    'price' => $product->price,
                    'image_url' => $product->image_url,
                ]);

                const existente = carrito.find((item) => item.id === producto.id);

                if (existente) {
                    existente.quantity = Math.min(99, existente.quantity + this.cantidad);
                } else {
                    carrito.push({ ...producto, quantity: this.cantidad });
                }

                localStorage.setItem('chutamax_cart', JSON.stringify(carrito));

                window.chutamaxMetaTrack('AddToCart', {
                    content_ids: [String(producto.id)],
                    content_name: producto.name,
                    content_type: 'product',
                    value: producto.price_cents / 100,
                    currency: 'MXN',
                });

                window.Swal.fire({
                    title: 'Agregado al carrito',
                    text: producto.name,
                    icon: 'success',
                    confirmButtonText: 'Seguir comprando',
                    showDenyButton: true,
                    denyButtonText: 'Ir al carrito',
                }).then((resultado) => {
                    if (resultado.isDenied) {
                        window.location.href = @js(route('storefront.home')) + '#productos';
                    }
                });
            },
        }"
    >
        <nav aria-label="Ruta de navegacion" class="mb-6 text-sm text-[var(--color-ink-soft)]">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--color-brand)]">Inicio</a>
            <span aria-hidden="true"> / </span>
            <span>{{ $product->category?->name }}</span>
        </nav>

        <div class="grid gap-8 md:grid-cols-2">
            <div class="border border-[var(--color-border)] p-4">
                @if ($product->image_url)
                    <img
                        src="{{ $product->image_url }}"
                        alt="{{ $product->name }}"
                        width="600"
                        height="600"
                        class="mx-auto h-auto w-full max-w-md object-contain"
                    >
                @else
                    <div class="media-placeholder grid aspect-square place-items-center">
                        <span class="display-title text-2xl text-[var(--color-ink-soft)]">{{ $product->category?->name }}</span>
                    </div>
                @endif

                @if ($gallery->count() > 1)
                    <div class="mt-4 grid grid-cols-4 gap-2">
                        @foreach ($gallery as $image)
                            <img
                                src="{{ $image->url }}"
                                alt="{{ $image->alt ?: $product->name }}"
                                width="120"
                                height="120"
                                class="aspect-square border border-[var(--color-border)] object-contain p-1"
                            >
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                @if ($product->brand?->name)
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--color-brand)]">
                        {{ $product->brand->name }}
                    </p>
                @endif

                <h1 class="impact-title mt-2 text-3xl uppercase leading-tight text-black sm:text-4xl">
                    {{ $product->name }}
                </h1>

                <div class="mt-4 flex flex-wrap items-baseline gap-3">
                    <span class="display-title text-4xl text-black">{{ $product->price }}</span>
                    @if ($product->compare_at_price)
                        <span class="text-lg text-[var(--color-ink-soft)] line-through">{{ $product->compare_at_price }}</span>
                        <span class="display-title bg-[var(--color-brand)] px-3 py-1 text-lg text-white">Oferta</span>
                    @endif
                </div>

                <p class="mt-4 text-sm font-bold">
                    @if ($product->is_in_stock)
                        <span class="text-[var(--color-success)]">Disponible</span>
                        @if ($product->track_inventory && $product->stock <= 5)
                            <span class="text-[var(--color-warning)]">&middot; Ultimas {{ $product->stock }} piezas</span>
                        @endif
                    @else
                        <span class="text-[var(--color-danger)]">Agotado por ahora</span>
                    @endif
                </p>

                @if ($product->short_description)
                    <p class="mt-4 leading-7 text-[var(--color-ink-soft)]">{{ $product->short_description }}</p>
                @endif

                @if ($product->tags->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($product->tags as $tag)
                            <span class="border border-[var(--color-border)] px-2 py-1 text-xs font-bold uppercase text-[var(--color-ink-soft)]">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if ($product->variants->isNotEmpty())
                    <div class="mt-6 border border-[var(--color-border)] p-4">
                        <p class="text-sm font-black uppercase text-black">Presentaciones disponibles</p>
                        <div class="mt-3 grid gap-2">
                            @foreach ($product->variants as $variant)
                                <div class="flex items-center justify-between gap-4 border border-[var(--color-border)] px-3 py-2 text-sm">
                                    <div>
                                        <p class="font-bold">{{ $variant->name }}</p>
                                        <p class="text-xs text-[var(--color-ink-soft)]">SKU {{ $variant->sku }}</p>
                                    </div>
                                    <p class="display-title text-lg text-black">{{ $variant->price }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($product->is_in_stock)
                    <div class="mt-6 flex flex-wrap items-end gap-3">
                        <label class="block">
                            <span class="text-sm font-bold">Cantidad</span>
                            <input
                                type="number"
                                x-model.number="cantidad"
                                min="1"
                                :max="maximo"
                                class="mt-1 w-24 border border-[var(--color-border)] px-3 py-3 text-sm"
                            >
                        </label>

                        <button
                            type="button"
                            x-on:click="agregar()"
                            class="display-title flex-1 bg-[var(--color-brand)] px-8 py-3 text-xl text-white transition hover:bg-[var(--color-brand-strong)]"
                        >
                            Agregar al carrito
                        </button>
                    </div>
                @else
                    <p class="mt-6 border-l-4 border-[var(--color-warning)] bg-[var(--color-surface-muted)] p-4 text-sm">
                        Escribenos al WhatsApp {{ config('storefront.contact.whatsapp') }} y te avisamos en cuanto llegue.
                    </p>
                @endif

                <dl class="mt-8 grid gap-2 border-t border-[var(--color-border)] pt-6 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-[var(--color-ink-soft)]">SKU</dt>
                        <dd class="font-bold">{{ $product->sku }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-[var(--color-ink-soft)]">Categoria</dt>
                        <dd class="font-bold">{{ $product->category?->name }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if ($product->description)
            <section class="mt-12 border-t border-[var(--color-border)] pt-8">
                <h2 class="impact-title text-2xl uppercase text-black">Descripcion</h2>
                <div class="mt-4 leading-7 text-[var(--color-ink-soft)]">
                    {{-- Texto plano a proposito: el contenido viene de una
                         migracion y no se ha saneado como HTML confiable. --}}
                    {!! nl2br(e($product->description)) !!}
                </div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="mt-12 border-t border-[var(--color-border)] pt-8">
                <h2 class="impact-title text-2xl uppercase text-black">Tambien te puede servir</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $otro)
                        <x-storefront.product-card :product="$otro" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-storefront.layouts.simple>
