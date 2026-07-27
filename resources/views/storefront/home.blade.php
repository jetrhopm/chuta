<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Chutamax: suplementos deportivos, proteinas, creatinas, pre entrenos y vitaminas.">

        <title>Chutamax | Suplementos deportivos</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-950 text-white antialiased">
        <div
            x-data="{
                cartOpen: false,
                checkoutOpen: false,
                cartPayload: '',
                {{-- Adelanto para que el total se vea al instante. Lo que se
                     cobra lo calcula el servidor al confirmar el pedido. --}}
                shippingEnabled: @js($shipping->enabled),
                shippingFlatCents: @js($shipping->flatCents),
                freeShippingEnabled: @js($shipping->freeShippingEnabled),
                freeShippingThresholdCents: @js($shipping->freeShippingThresholdCents),
                items: JSON.parse(localStorage.getItem('chutamax_cart') || '[]'),
                checkoutOrderCode: @js(session('checkout_order_code')),
                init() {
                    if (this.checkoutOrderCode) {
                        this.clearCart();
                    }
                },
                {{-- El alta real al boletin se conecta con el modulo de correos. --}}
                avisoBoletin() {
                    window.Swal.fire({
                        title: 'Casi listo',
                        text: 'El alta al boletin se activa junto con el envio de correos. Mientras tanto escribenos por WhatsApp.',
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                    });
                },
                addToCart(product) {
                    const item = this.items.find((cartItem) => cartItem.id === product.id);

                    if (item) {
                        item.quantity++;
                    } else {
                        this.items.push({ ...product, quantity: 1 });
                    }

                    this.persist();
                    this.cartOpen = true;
                },
                increment(id) {
                    this.items = this.items.map((item) => item.id === id ? { ...item, quantity: item.quantity + 1 } : item);
                    this.persist();
                },
                decrement(id) {
                    this.items = this.items
                        .map((item) => item.id === id ? { ...item, quantity: item.quantity - 1 } : item)
                        .filter((item) => item.quantity > 0);
                    this.persist();
                },
                remove(id) {
                    this.items = this.items.filter((item) => item.id !== id);
                    this.persist();
                },
                clearCart() {
                    this.items = [];
                    this.persist();
                },
                persist() {
                    localStorage.setItem('chutamax_cart', JSON.stringify(this.items));
                },
                money(cents) {
                    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(cents / 100);
                },
                get count() {
                    return this.items.reduce((total, item) => total + item.quantity, 0);
                },
                get totalCents() {
                    return this.subtotalCents + this.shippingCents;
                },
                get subtotalCents() {
                    return this.items.reduce((total, item) => total + (item.price_cents * item.quantity), 0);
                },
                get shippingCents() {
                    if (this.items.length === 0 || ! this.shippingEnabled) {
                        return 0;
                    }

                    if (this.freeShippingEnabled && this.subtotalCents >= this.freeShippingThresholdCents) {
                        return 0;
                    }

                    return this.shippingFlatCents;
                },
                get freeShippingRemainingCents() {
                    if (! this.freeShippingEnabled || ! this.shippingEnabled) {
                        return 0;
                    }

                    return Math.max(0, this.freeShippingThresholdCents - this.subtotalCents);
                },
                openCheckout() {
                    if (this.items.length === 0) {
                        return;
                    }

                    this.cartPayload = JSON.stringify(this.items.map((item) => ({
                        id: item.id,
                        quantity: item.quantity,
                    })));
                    this.checkoutOpen = true;
                },
            }"
            class="min-h-screen overflow-hidden"
        >
            @if (session('checkout_order_code'))
                <div class="border-b border-emerald-400/30 bg-emerald-500 px-4 py-3 text-center text-sm font-black text-emerald-950">
                    Pedido recibido: {{ session('checkout_order_code') }}. Te contactaremos para confirmar existencias, envio y pago.
                </div>
            @endif

            @if ($errors->any())
                <div class="border-b border-red-400/30 bg-red-600 px-4 py-3 text-center text-sm font-black text-white">
                    Revisa los datos del pedido. Hay campos pendientes o el carrito cambio.
                </div>
            @endif

            {{-- Barra de anuncios --}}
            <div class="bg-[var(--color-brand)] px-4 py-2 text-center">
                <p class="display-title text-base text-white sm:text-lg">
                    Envio gratis en compras desde <span x-text="money(freeShippingThresholdCents)"></span> &middot; Cd. Obregon, Sonora
                </p>
            </div>

            <header class="sticky top-0 z-40 bg-black shadow-lg">
                {{-- Fila principal: logo, buscador y carrito --}}
                <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ route('storefront.home') }}" class="flex items-center gap-3" aria-label="Chutamax, ir al inicio">
                        <span class="display-title grid size-12 place-items-center bg-[var(--color-brand)] text-3xl text-white">C</span>
                        <span class="leading-none">
                            <span class="display-title block text-2xl text-white">Chutamax</span>
                            <span class="block text-[0.65rem] font-bold uppercase tracking-[0.18em] text-white/70">Vitaminas y suplementos</span>
                        </span>
                    </a>

                    {{-- Busca en todo el catalogo, no solo en lo que se ve en
                         pantalla: con casi mil productos, filtrar lo visible
                         dejaria fuera casi todo. --}}
                    <form
                        role="search"
                        method="GET"
                        action="{{ route('catalog.index') }}"
                        class="order-3 w-full sm:order-none sm:ml-auto sm:w-auto sm:flex-1 sm:max-w-md"
                    >
                        <label class="sr-only" for="buscador">Buscar productos</label>
                        <div class="flex overflow-hidden rounded border-2 border-white/15 bg-white focus-within:border-[var(--color-brand)]">
                            <input
                                id="buscador"
                                type="search"
                                name="q"
                                placeholder="Busca proteina, creatina, marca..."
                                class="w-full px-3 py-2 text-sm text-[var(--color-ink)] outline-none"
                            >
                            <button type="submit" class="display-title bg-[var(--color-brand)] px-4 text-lg text-white transition hover:bg-[var(--color-brand-strong)]">
                                Buscar
                            </button>
                        </div>
                    </form>

                    <button
                        type="button"
                        class="display-title relative ml-auto flex items-center gap-2 bg-white px-4 py-2 text-lg text-black transition hover:bg-[var(--color-surface-muted)] sm:ml-0"
                        x-on:click="cartOpen = true"
                    >
                        Carrito
                        <span
                            x-cloak
                            x-show="count > 0"
                            x-text="count"
                            class="grid size-6 place-items-center rounded-full bg-[var(--color-brand)] text-xs font-bold text-white"
                        ></span>
                    </button>
                </div>

                {{-- Menu de secciones --}}
                <nav aria-label="Secciones de la tienda" class="border-t border-white/10 bg-black">
                    <div class="mx-auto flex max-w-7xl gap-6 overflow-x-auto px-4 py-2 sm:px-6 lg:px-8">
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="{{ route('storefront.home') }}">Inicio</a>
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="{{ route('catalog.index') }}">Catalogo</a>
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="{{ route('catalog.index', ['ofertas' => 1]) }}">Ofertas</a>
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="#categorias">Categorias</a>
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="#como-comprar">Como comprar</a>
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="{{ route('pages.shipping') }}">Envios</a>
                        <a class="display-title shrink-0 text-lg text-white transition hover:text-[var(--color-brand)]" href="{{ route('pages.contact') }}">Contacto</a>
                    </div>
                </nav>
            </header>

            <main>
                {{-- Carrusel principal --}}
                @if ($banners->isNotEmpty())
                    <section aria-label="Promociones" class="bg-black">
                        <div
                            class="swiper mx-auto max-w-7xl"
                            x-data="carrusel()"
                            x-init="iniciar($el)"
                        >
                            <div class="swiper-wrapper">
                                @foreach ($banners as $banner)
                                    <div class="swiper-slide">
                                        <a href="{{ $banner['url'] }}" class="block">
                                            <img
                                                src="{{ $banner['image'] }}"
                                                alt="{{ $banner['alt'] }}"
                                                {{-- Dimensiones explicitas: evitan que la pagina salte
                                                     mientras la imagen carga. --}}
                                                width="1536"
                                                height="560"
                                                loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                                fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                                                class="h-auto w-full"
                                            >
                                        </a>
                                    </div>
                                @endforeach
                            </div>

                            <div class="swiper-pagination"></div>
                            <button type="button" class="swiper-button-prev" aria-label="Promocion anterior"></button>
                            <button type="button" class="swiper-button-next" aria-label="Promocion siguiente"></button>
                        </div>
                    </section>
                @endif

                {{-- Accesos rapidos por categoria --}}
                @if ($categoryShortcuts->isNotEmpty())
                    <section id="categorias" class="bg-[var(--color-surface-muted)]">
                        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                            <h2 class="impact-title text-center text-3xl uppercase text-black sm:text-4xl">
                                Compra por categoria
                            </h2>
                            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                                @foreach ($categoryShortcuts as $category)
                                    <a
                                        href="#productos"
                                        class="group grid place-items-center gap-2 border-2 border-transparent bg-black p-4 text-center transition hover:-translate-y-1 hover:border-[var(--color-brand)]"
                                    >
                                        <span class="display-title text-lg leading-tight text-white transition group-hover:text-[var(--color-brand)]">
                                            {{ $category->name }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                @if ($featuredCategories->isNotEmpty())
                    <section class="bg-white">
                        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                            <h2 class="impact-title text-3xl uppercase text-black sm:text-4xl">Categorias destacadas</h2>
                            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($featuredCategories as $category)
                                    <article class="border-l-4 border-[var(--color-brand)] bg-[var(--color-surface-muted)] p-5 transition hover:-translate-y-1 hover:shadow-lg">
                                        <h3 class="display-title text-2xl text-black">{{ $category->name }}</h3>
                                        <p class="mt-2 text-sm leading-6 text-[var(--color-ink-soft)]">{{ $category->description }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                @if ($featuredProducts->isNotEmpty())
                    <section class="bg-black">
                        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                            <h2 class="impact-title text-3xl uppercase text-white sm:text-4xl">Los mas vendidos</h2>
                            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($featuredProducts as $product)
                                    <x-storefront.product-card :product="$product" />
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                <section id="productos" class="bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                            <h2 class="impact-title text-3xl uppercase text-black sm:text-4xl">Todos los productos</h2>
                            <p class="text-sm text-[var(--color-ink-soft)]">
                                {{ $products->total() }} productos &middot; existencias y envio se confirman al cerrar el pedido.
                            </p>
                        </div>

                        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($products as $product)
                                <x-storefront.product-card :product="$product" />
                            @endforeach
                        </div>

                        {{-- Estado vacio del filtro rapido del buscador. --}}
                        <p
                            data-sin-resultados
                            hidden
                            class="mt-10 border border-dashed border-[var(--color-border-strong)] p-8 text-center text-[var(--color-ink-soft)]"
                        >
                            No encontramos nada con ese termino en esta pagina.
                            Prueba con otra palabra o revisa las demas paginas del catalogo.
                        </p>

                        @if ($products->hasPages())
                            <div class="mt-10">
                                {{ $products->fragment('productos')->links() }}
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Como comprar --}}
                <section id="como-comprar" class="bg-[var(--color-surface-muted)]">
                    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <h2 class="impact-title text-center text-3xl uppercase text-black sm:text-4xl">
                            &iquest;Como comprar?
                        </h2>

                        <ol class="mt-10 grid gap-6 md:grid-cols-3">
                            @foreach ($howToBuy as $paso)
                                <li class="relative border-t-4 border-[var(--color-brand)] bg-white p-6 text-center shadow-sm">
                                    <span class="display-title mx-auto grid size-14 place-items-center rounded-full bg-black text-3xl text-white">
                                        {{ $loop->iteration }}
                                    </span>
                                    <h3 class="display-title mt-4 text-2xl text-black">{{ $paso['title'] }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-[var(--color-ink-soft)]">{{ $paso['text'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </section>

                {{-- Quienes somos --}}
                <section class="bg-white">
                    <div class="mx-auto max-w-3xl px-4 py-12 text-center sm:px-6 lg:px-8">
                        <h2 class="impact-title text-3xl uppercase text-black sm:text-4xl">
                            Los mejores productos para tu salud
                        </h2>
                        <p class="mt-5 leading-7 text-[var(--color-ink-soft)]">
                            Distribuimos las mejores marcas de suplementacion deportiva desde 2016, con
                            asesoria real sobre cada producto y su uso para que alcances los resultados
                            que buscas.
                        </p>
                        <a
                            href="{{ route('pages.contact') }}"
                            class="display-title mt-7 inline-block bg-[var(--color-brand)] px-8 py-3 text-xl text-white transition hover:bg-[var(--color-brand-strong)]"
                        >
                            Contactanos
                        </a>
                    </div>
                </section>
            </main>

            <footer class="bg-black">
                <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8">
                    <div>
                        <p class="display-title text-3xl text-white">Chutamax</p>
                        <p class="mt-3 text-sm leading-6 text-white/70">
                            {{ config('storefront.contact.address') }}
                        </p>
                        <p class="mt-3 text-sm text-white/70">
                            WhatsApp: <span class="font-bold text-white">{{ config('storefront.contact.whatsapp') }}</span>
                        </p>
                    </div>

                    <nav aria-label="Informacion">
                        <h2 class="display-title text-2xl text-white">Informacion</h2>
                        <ul class="mt-3 grid gap-2 text-sm text-white/70">
                            <li><a class="transition hover:text-[var(--color-brand)]" href="{{ route('pages.contact') }}">Contacto</a></li>
                            <li><a class="transition hover:text-[var(--color-brand)]" href="{{ route('pages.faq') }}">Preguntas frecuentes</a></li>
                            <li><a class="transition hover:text-[var(--color-brand)]" href="{{ route('pages.shipping') }}">Politica de envios</a></li>
                            <li><a class="transition hover:text-[var(--color-brand)]" href="{{ route('pages.returns') }}">Cambios y devoluciones</a></li>
                            <li><a class="transition hover:text-[var(--color-brand)]" href="{{ route('pages.terms') }}">Terminos y condiciones</a></li>
                            <li><a class="transition hover:text-[var(--color-brand)]" href="{{ route('pages.privacy') }}">Aviso de privacidad</a></li>
                        </ul>
                    </nav>

                    <div>
                        <h2 class="display-title text-2xl text-white">Recibe promociones</h2>
                        <p class="mt-3 text-sm text-white/70">Te avisamos de ofertas y productos nuevos.</p>
                        {{-- El alta al boletin se conecta en la etapa de correos. --}}
                        <form class="mt-4 flex gap-2" x-on:submit.prevent="avisoBoletin()">
                            <label class="sr-only" for="boletin">Tu correo electronico</label>
                            <input
                                id="boletin"
                                type="email"
                                required
                                placeholder="Tu correo electronico"
                                class="w-full border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/50"
                            >
                            <button type="submit" class="display-title bg-[var(--color-brand)] px-4 text-lg text-white transition hover:bg-[var(--color-brand-strong)]">
                                Enviar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="border-t border-white/10 py-5 text-center text-xs uppercase tracking-[0.14em] text-white/50">
                    Chutamax {{ now()->year }} &middot; Todos los derechos reservados
                </div>
            </footer>

            <div
                x-cloak
                x-show="cartOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 bg-zinc-950/80 p-4 backdrop-blur"
                x-on:click.self="cartOpen = false"
            >
                <div class="ml-auto flex h-full max-w-md flex-col rounded-lg bg-white text-zinc-950 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <h2 class="text-xl font-black">Carrito</h2>
                        <button type="button" class="rounded px-3 py-2 font-black hover:bg-zinc-100" x-on:click="cartOpen = false">X</button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-5">
                        <p class="text-sm leading-6 text-zinc-600">Agrega productos al carrito. Al finalizar, captura tus datos de envio y el metodo de pago.</p>

                        <div x-show="items.length === 0" class="mt-5 rounded border border-dashed border-zinc-300 p-5 text-center text-sm font-bold text-zinc-500">
                            Tu carrito esta vacio.
                        </div>

                        <div x-show="items.length > 0" class="mt-5 space-y-4">
                            <template x-for="item in items" :key="item.id">
                                <article class="flex gap-3 rounded border border-zinc-200 p-3">
                                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded bg-zinc-100">
                                        <img x-show="item.image_url" :src="item.image_url" :alt="item.name" class="h-full w-full object-contain p-1">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-black leading-tight" x-text="item.name"></h3>
                                        <p class="mt-1 text-sm font-bold text-red-600" x-text="money(item.price_cents)"></p>
                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <div class="inline-flex items-center rounded border border-zinc-300">
                                                <button type="button" class="px-3 py-1 font-black" x-on:click="decrement(item.id)">-</button>
                                                <span class="min-w-8 text-center text-sm font-black" x-text="item.quantity"></span>
                                                <button type="button" class="px-3 py-1 font-black" x-on:click="increment(item.id)">+</button>
                                            </div>
                                            <button type="button" class="text-xs font-black text-zinc-500 hover:text-red-600" x-on:click="remove(item.id)">Quitar</button>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </div>
                    <div class="border-t border-zinc-200 p-5">
                        <div class="mb-4 flex items-center justify-between text-lg font-black">
                            <span>Subtotal</span>
                            <span x-text="money(subtotalCents)"></span>
                        </div>
                        <div class="mb-4 rounded bg-zinc-100 p-3 text-sm font-bold text-zinc-600">
                            <template x-if="freeShippingRemainingCents > 0">
                                <span>Te faltan <span x-text="money(freeShippingRemainingCents)"></span> para envio gratis.</span>
                            </template>
                            <template x-if="freeShippingRemainingCents === 0 && items.length > 0">
                                <span class="text-emerald-700">Tu pedido ya tiene envio gratis.</span>
                            </template>
                        </div>
                        <div class="mb-4 flex items-center justify-between text-lg font-black">
                            <span>Total</span>
                            <span x-text="money(totalCents)"></span>
                        </div>
                        <button
                            type="button"
                            class="block w-full rounded bg-red-600 px-5 py-3 text-center font-black text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                            x-bind:disabled="items.length === 0"
                            x-on:click="openCheckout()"
                        >
                            Continuar al checkout
                        </button>
                        <button
                            type="button"
                            class="mt-3 w-full rounded border border-zinc-300 px-5 py-3 text-center text-sm font-black text-zinc-700 hover:bg-zinc-50"
                            x-show="items.length > 0"
                            x-on:click="clearCart()"
                        >
                            Vaciar carrito
                        </button>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="checkoutOpen"
                x-transition.opacity
                class="fixed inset-0 z-[60] overflow-y-auto bg-[radial-gradient(circle_at_20%_10%,rgba(220,38,38,.32),transparent_28%),radial-gradient(circle_at_80%_0%,rgba(250,204,21,.18),transparent_24%),rgba(9,9,11,.88)] p-4 backdrop-blur"
                x-on:click.self="checkoutOpen = false"
            >
                <form method="POST" action="{{ route('checkout.store') }}" class="checkout-panel mx-auto my-4 max-w-5xl overflow-hidden rounded-lg border border-white/15 bg-white text-zinc-950 shadow-2xl">
                    @csrf
                    <input type="hidden" name="cart_payload" x-bind:value="cartPayload">

                    <div class="relative overflow-hidden bg-zinc-950 p-5 text-white sm:p-7">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_10%,rgba(220,38,38,.42),transparent_28%)]"></div>
                        <div class="relative flex items-start justify-between gap-5">
                            <div>
                                <p class="text-xs font-black uppercase text-red-300">Checkout seguro</p>
                                <h2 class="mt-2 text-3xl font-black leading-tight sm:text-4xl">Datos de envio y pago</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-300">Captura tu informacion para levantar el pedido. Revisamos existencias y costo de envio antes de cobrar.</p>
                            </div>
                            <button type="button" class="grid size-10 shrink-0 place-items-center rounded border border-white/15 bg-white/10 font-black text-white transition hover:bg-white/20" x-on:click="checkoutOpen = false">X</button>
                        </div>

                        <div class="relative mt-6 grid gap-3 text-xs font-black uppercase text-zinc-300 sm:grid-cols-3">
                            <div class="rounded border border-white/15 bg-white/10 px-4 py-3">
                                <span class="text-red-300">01</span> Contacto
                            </div>
                            <div class="rounded border border-white/15 bg-white/10 px-4 py-3">
                                <span class="text-red-300">02</span> Envio
                            </div>
                            <div class="rounded border border-white/15 bg-white/10 px-4 py-3">
                                <span class="text-red-300">03</span> Pago
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 bg-zinc-100 p-4 sm:p-6 lg:grid-cols-[1fr_360px]">
                        <div class="space-y-5">
                            <section class="checkout-section rounded-lg border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded bg-red-600 text-sm font-black text-white">1</span>
                                    <h3 class="text-lg font-black">Contacto</h3>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-black">Nombre completo</span>
                                        <input name="customer_name" value="{{ old('customer_name') }}" required class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Telefono</span>
                                        <input name="customer_phone" value="{{ old('customer_phone') }}" required class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Correo</span>
                                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                </div>
                            </section>

                            <section class="checkout-section rounded-lg border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded bg-red-600 text-sm font-black text-white">2</span>
                                    <h3 class="text-lg font-black">Direccion de envio</h3>
                                </div>
                                {{--
                                    Captura de direccion asistida por el catalogo
                                    de codigos postales.

                                    El codigo postal va primero porque de el sale
                                    el resto. Si la consulta falla o el codigo no
                                    existe, se habilita la captura manual: una
                                    direccion que no se puede escribir es una
                                    venta perdida, asi que el catalogo ayuda pero
                                    nunca bloquea.
                                --}}
                                <div
                                    class="grid gap-4 sm:grid-cols-2"
                                    x-data="{
                                        endpoint: @js(url('codigo-postal')),
                                        postcode: @js(old('shipping_postcode', '')),
                                        neighborhood: @js(old('shipping_neighborhood', '')),
                                        city: @js(old('shipping_city', '')),
                                        state: @js(old('shipping_state', '')),
                                        settlements: [],
                                        status: 'idle',
                                        manual: @js(old('shipping_neighborhood') !== null),
                                        get digits() {
                                            return this.postcode.replace(/\D/g, '');
                                        },
                                        onPostcodeInput() {
                                            this.postcode = this.digits.slice(0, 5);

                                            if (this.postcode.length === 5) {
                                                this.lookup();
                                                return;
                                            }

                                            this.settlements = [];
                                            this.status = 'idle';
                                        },
                                        async lookup() {
                                            this.status = 'loading';

                                            try {
                                                const response = await fetch(`${this.endpoint}/${this.postcode}`, {
                                                    headers: { 'Accept': 'application/json' },
                                                });

                                                if (! response.ok) {
                                                    this.settlements = [];
                                                    this.status = 'notfound';
                                                    this.manual = true;
                                                    return;
                                                }

                                                const payload = await response.json();

                                                this.settlements = payload.data.settlements;
                                                this.state = payload.data.state;
                                                this.city = payload.data.city || payload.data.municipality;
                                                this.neighborhood = this.settlements.length === 1
                                                    ? this.settlements[0].name
                                                    : '';
                                                this.status = 'found';
                                                this.manual = false;
                                            } catch (error) {
                                                // Una consulta caida no puede dejar
                                                // al cliente sin poder comprar.
                                                this.settlements = [];
                                                this.status = 'error';
                                                this.manual = true;
                                            }
                                        },
                                    }"
                                >
                                    <label class="block">
                                        <span class="text-sm font-black">Codigo postal</span>
                                        <input
                                            name="shipping_postcode"
                                            x-model="postcode"
                                            @input="onPostcodeInput()"
                                            inputmode="numeric"
                                            autocomplete="postal-code"
                                            maxlength="5"
                                            required
                                            aria-describedby="cp-ayuda"
                                            class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100"
                                        >
                                        <p id="cp-ayuda" class="mt-1 text-xs" aria-live="polite">
                                            <span x-show="status === 'loading'" class="text-zinc-500">Buscando tu colonia...</span>
                                            <span x-show="status === 'found'" class="text-emerald-700" x-text="`Encontramos ${settlements.length} ${settlements.length === 1 ? 'colonia' : 'colonias'} para este codigo.`"></span>
                                            <span x-show="status === 'notfound'" class="text-amber-700">No encontramos ese codigo postal; puedes escribir tu direccion manualmente.</span>
                                            <span x-show="status === 'error'" class="text-amber-700">No pudimos consultar el codigo postal. Escribe tu direccion manualmente.</span>
                                            <span x-show="status === 'idle'" class="text-zinc-500">Cinco digitos. Completamos colonia, ciudad y estado.</span>
                                        </p>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-black">Colonia</span>

                                        {{-- Selector cuando el catalogo respondio. --}}
                                        <select
                                            x-show="! manual && settlements.length > 0"
                                            x-model="neighborhood"
                                            :name="(! manual && settlements.length > 0) ? 'shipping_neighborhood' : ''"
                                            :required="! manual && settlements.length > 0"
                                            class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100"
                                        >
                                            <option value="">Selecciona tu colonia</option>
                                            <template x-for="settlement in settlements" :key="settlement.name">
                                                <option :value="settlement.name" x-text="settlement.type ? `${settlement.name} (${settlement.type})` : settlement.name"></option>
                                            </template>
                                        </select>

                                        {{-- Respaldo manual. --}}
                                        <input
                                            x-show="manual || settlements.length === 0"
                                            x-model="neighborhood"
                                            :name="(manual || settlements.length === 0) ? 'shipping_neighborhood' : ''"
                                            :required="manual || settlements.length === 0"
                                            class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100"
                                        >

                                        <button
                                            type="button"
                                            x-show="settlements.length > 0"
                                            @click="manual = ! manual"
                                            class="mt-1 text-xs font-bold text-red-700 underline transition hover:text-red-900"
                                            x-text="manual ? 'Elegir de la lista' : 'Mi colonia no aparece'"
                                        ></button>
                                    </label>

                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Calle</span>
                                        <input name="shipping_street" value="{{ old('shipping_street') }}" autocomplete="address-line1" required class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Numero</span>
                                        <input name="shipping_number" value="{{ old('shipping_number') }}" class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Ciudad</span>
                                        <input name="shipping_city" x-model="city" autocomplete="address-level2" required class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Estado</span>
                                        <input name="shipping_state" x-model="state" autocomplete="address-level1" required class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Referencia</span>
                                        <textarea name="shipping_reference" rows="3" class="mt-1 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">{{ old('shipping_reference') }}</textarea>
                                    </label>
                                </div>
                            </section>

                            <section class="checkout-section rounded-lg border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded bg-red-600 text-sm font-black text-white">3</span>
                                    <h3 class="text-lg font-black">Metodo de pago</h3>
                                </div>
                                <div class="grid gap-3">
                                    <label class="group flex cursor-pointer gap-3 rounded border border-zinc-300 bg-zinc-50 p-4 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-white hover:shadow-md has-[:checked]:border-red-600 has-[:checked]:bg-red-50 has-[:checked]:shadow-md">
                                        <input type="radio" name="payment_method" value="bank_transfer" class="mt-1 accent-red-600" @checked(old('payment_method', 'bank_transfer') === 'bank_transfer')>
                                        <span>
                                            <span class="block font-black">Transferencia bancaria</span>
                                            <span class="block text-sm text-zinc-600">Se enviaran los datos bancarios al confirmar disponibilidad.</span>
                                        </span>
                                    </label>
                                    <label class="group flex cursor-pointer gap-3 rounded border border-zinc-300 bg-zinc-50 p-4 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-white hover:shadow-md has-[:checked]:border-red-600 has-[:checked]:bg-red-50 has-[:checked]:shadow-md">
                                        <input type="radio" name="payment_method" value="card_on_delivery" class="mt-1 accent-red-600" @checked(old('payment_method') === 'card_on_delivery')>
                                        <span>
                                            <span class="block font-black">Tarjeta al recibir</span>
                                            <span class="block text-sm text-zinc-600">Sujeto a cobertura y confirmacion.</span>
                                        </span>
                                    </label>
                                    <label class="group flex cursor-pointer gap-3 rounded border border-zinc-300 bg-zinc-50 p-4 transition hover:-translate-y-0.5 hover:border-red-300 hover:bg-white hover:shadow-md has-[:checked]:border-red-600 has-[:checked]:bg-red-50 has-[:checked]:shadow-md">
                                        <input type="radio" name="payment_method" value="cash_on_delivery" class="mt-1 accent-red-600" @checked(old('payment_method') === 'cash_on_delivery')>
                                        <span>
                                            <span class="block font-black">Efectivo al recibir</span>
                                            <span class="block text-sm text-zinc-600">Disponible solo donde aplique entrega local.</span>
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <label class="checkout-section block rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                                <span class="text-sm font-black">Notas del pedido</span>
                                <textarea name="notes" rows="3" class="mt-2 w-full rounded border border-zinc-300 bg-zinc-50 px-3 py-3 text-sm outline-none transition focus:border-red-500 focus:bg-white focus:ring-4 focus:ring-red-100">{{ old('notes') }}</textarea>
                            </label>
                        </div>

                        <aside class="checkout-section h-fit rounded-lg border border-zinc-800 bg-zinc-950 p-5 text-white shadow-xl lg:sticky lg:top-5">
                            <p class="text-xs font-black uppercase text-red-300">Resumen del pedido</p>
                            <h3 class="mt-1 text-2xl font-black">Total estimado</h3>
                            <div class="mt-4 max-h-80 space-y-3 overflow-y-auto pr-1">
                                <template x-for="item in items" :key="item.id">
                                    <div class="rounded border border-white/10 bg-white/8 p-3 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <span class="font-bold"><span x-text="item.quantity"></span> x <span x-text="item.name"></span></span>
                                            <span class="font-black text-red-200" x-text="money(item.price_cents * item.quantity)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-5 border-t border-white/10 pt-4">
                                <div class="mb-2 flex items-center justify-between text-sm font-bold text-zinc-300">
                                    <span>Subtotal</span>
                                    <span x-text="money(subtotalCents)"></span>
                                </div>
                                <div class="mb-2 flex items-center justify-between text-sm font-bold text-zinc-300">
                                    <span>Envio</span>
                                    <span x-text="shippingCents === 0 ? 'Gratis' : money(shippingCents)"></span>
                                </div>
                                <div class="flex items-center justify-between text-xl font-black">
                                    <span>Total</span>
                                    <span class="text-red-200" x-text="money(totalCents)"></span>
                                </div>
                                <p class="mt-2 text-xs leading-5 text-zinc-400">Envio nacional de $99 MXN; gratis desde $800 MXN.</p>
                            </div>
                            <button type="submit" class="mt-5 w-full rounded bg-red-600 px-5 py-3 font-black text-white shadow-lg shadow-red-950/30 transition hover:-translate-y-0.5 hover:bg-red-500">
                                Enviar pedido
                            </button>
                        </aside>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
