@props(['product'])

{{--
    Tarjeta de producto del escaparate.

    El atributo data-producto alimenta el filtro rapido del buscador: guarda en
    minusculas el nombre, la marca y la categoria para poder comparar sin
    recorrer el DOM buscando textos.
--}}
<article
    data-producto="{{ mb_strtolower($product->name.' '.$product->brand?->name.' '.$product->category?->name) }}"
    class="group flex flex-col border border-[var(--color-border)] bg-white transition hover:-translate-y-1 hover:border-[var(--color-brand)] hover:shadow-xl"
>
    <div class="relative grid aspect-square place-items-center overflow-hidden bg-white p-4">
        @if ($product->compare_at_price)
            <span class="display-title absolute left-0 top-3 bg-[var(--color-brand)] px-3 py-1 text-lg text-white">
                Oferta
            </span>
        @endif

        @if (! $product->is_in_stock)
            <span class="display-title absolute right-0 top-3 bg-black px-3 py-1 text-lg text-white">
                Agotado
            </span>
        @endif

        @if ($product->image_url)
            <img
                src="{{ $product->image_url }}"
                alt="{{ $product->name }}"
                {{-- Dimensiones explicitas para que la cuadricula no salte
                     mientras cargan las imagenes. --}}
                width="300"
                height="300"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
                {{-- Si la imagen no carga, se deja un marcador cuidado en lugar
                     de romper la alineacion de la cuadricula. --}}
                onerror="this.remove(); this.closest('div').classList.add('media-placeholder');"
            >
        @else
            <div class="media-placeholder grid h-full w-full place-items-center">
                <span class="display-title px-4 text-center text-xl text-[var(--color-ink-soft)]">
                    {{ $product->category?->name }}
                </span>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col border-t border-[var(--color-border)] p-4">
        @if ($product->brand?->name)
            <p class="text-[0.7rem] font-bold uppercase tracking-[0.14em] text-[var(--color-brand)]">
                {{ $product->brand->name }}
            </p>
        @endif

        <h3 class="display-title mt-2 text-xl text-[var(--color-ink)]">
            {{ $product->name }}
        </h3>

        <div class="mt-3 flex flex-wrap items-baseline gap-2">
            <span class="display-title text-2xl text-black">{{ $product->price }}</span>
            @if ($product->compare_at_price)
                <span class="text-sm text-[var(--color-ink-soft)] line-through">{{ $product->compare_at_price }}</span>
            @endif
        </div>

        <button
            type="button"
            class="display-title mt-4 w-full bg-[var(--color-brand)] px-4 py-3 text-lg text-white transition hover:bg-[var(--color-brand-strong)] disabled:cursor-not-allowed disabled:bg-[var(--color-border-strong)]"
            @disabled(! $product->is_in_stock)
            x-on:click="addToCart(@js([
                'id' => $product->id,
                'name' => $product->name,
                'price_cents' => $product->price_cents,
                'price' => $product->price,
                'image_url' => $product->image_url,
            ]))"
        >
            {{ $product->is_in_stock ? 'Agregar al carrito' : 'Agotado' }}
        </button>
    </div>
</article>
