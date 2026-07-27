<x-storefront.layouts.simple title="Catalogo | Chutamax">
    <div class="p-5 sm:p-8">
        <h1 class="impact-title text-3xl uppercase text-black sm:text-4xl">
            @if ($filters->hasTerm())
                Resultados para "{{ $filters->term }}"
            @else
                Catalogo
            @endif
        </h1>

        <p class="mt-2 text-sm text-[var(--color-ink-soft)]">
            {{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}
        </p>

        {{-- Los filtros viajan por GET para que la busqueda viva en la direccion
             y se pueda compartir o volver a abrir. --}}
        <form method="GET" action="{{ route('catalog.index') }}" class="mt-6 border border-[var(--color-border)] p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block sm:col-span-2">
                    <span class="text-sm font-bold">Buscar</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $filters->term }}"
                        placeholder="Proteina, creatina, marca, SKU..."
                        class="mt-1 w-full border border-[var(--color-border)] px-3 py-2 text-sm"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-bold">Categoria</span>
                    <select name="categoria" class="mt-1 w-full border border-[var(--color-border)] px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(in_array($category->id, $filters->categoryIds, false))>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-bold">Marca</span>
                    <select name="marca" class="mt-1 w-full border border-[var(--color-border)] px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(in_array($brand->id, $filters->brandIds, false))>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-bold">Precio desde</span>
                    <input
                        type="number"
                        name="precio_min"
                        min="0"
                        step="1"
                        value="{{ $filters->minPriceCents === null ? '' : $filters->minPriceCents / 100 }}"
                        class="mt-1 w-full border border-[var(--color-border)] px-3 py-2 text-sm"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-bold">Precio hasta</span>
                    <input
                        type="number"
                        name="precio_max"
                        min="0"
                        step="1"
                        value="{{ $filters->maxPriceCents === null ? '' : $filters->maxPriceCents / 100 }}"
                        class="mt-1 w-full border border-[var(--color-border)] px-3 py-2 text-sm"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-bold">Ordenar por</span>
                    <select name="orden" class="mt-1 w-full border border-[var(--color-border)] px-3 py-2 text-sm">
                        <option value="relevance" @selected($filters->sort === 'relevance')>Relevancia</option>
                        <option value="price_asc" @selected($filters->sort === 'price_asc')>Precio: menor a mayor</option>
                        <option value="price_desc" @selected($filters->sort === 'price_desc')>Precio: mayor a menor</option>
                        <option value="newest" @selected($filters->sort === 'newest')>Mas recientes</option>
                        <option value="name" @selected($filters->sort === 'name')>Nombre</option>
                    </select>
                </label>

                <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" name="disponibles" value="1" @checked($filters->onlyAvailable)>
                        Solo disponibles
                    </label>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" name="ofertas" value="1" @checked($filters->onlyOnSale)>
                        Solo ofertas
                    </label>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="submit" class="display-title bg-[var(--color-brand)] px-6 py-2 text-lg text-white transition hover:bg-[var(--color-brand-strong)]">
                    Aplicar
                </button>

                @unless ($filters->isEmpty())
                    <a href="{{ route('catalog.index') }}" class="display-title border border-[var(--color-border-strong)] px-6 py-2 text-lg text-[var(--color-ink)] transition hover:border-[var(--color-brand)]">
                        Limpiar filtros
                    </a>
                @endunless
            </div>
        </form>

        @if ($products->isEmpty())
            <div class="mt-10 border border-dashed border-[var(--color-border-strong)] p-10 text-center">
                <p class="display-title text-2xl text-black">No encontramos nada con esos filtros</p>
                <p class="mt-2 text-sm text-[var(--color-ink-soft)]">
                    Prueba con otra palabra, quita algun filtro o escribenos al WhatsApp
                    {{ config('storefront.contact.whatsapp') }} y lo buscamos por ti.
                </p>
                <a href="{{ route('catalog.index') }}" class="display-title mt-6 inline-block bg-black px-6 py-2 text-lg text-white transition hover:bg-[var(--color-brand)]">
                    Ver todo el catalogo
                </a>
            </div>
        @else
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</x-storefront.layouts.simple>
