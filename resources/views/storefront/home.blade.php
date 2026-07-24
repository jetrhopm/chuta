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
                items: JSON.parse(localStorage.getItem('chutamax_cart') || '[]'),
                checkoutOrderCode: @js(session('checkout_order_code')),
                init() {
                    if (this.checkoutOrderCode) {
                        this.clearCart();
                    }
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
                    return this.items.reduce((total, item) => total + (item.price_cents * item.quantity), 0);
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

            <header class="sticky top-0 z-40 border-b border-white/10 bg-zinc-950/88 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('storefront.home') }}" class="flex items-center gap-3" aria-label="Chutamax inicio">
                        <span class="grid size-11 place-items-center rounded bg-red-600 text-xl font-black tracking-normal">C</span>
                        <span>
                            <span class="block text-lg font-black leading-none">Chutamax</span>
                            <span class="block text-xs font-semibold uppercase text-red-300">Suplementos deportivos</span>
                        </span>
                    </a>

                    <nav class="hidden items-center gap-7 text-sm font-semibold text-zinc-200 md:flex">
                        <a class="hover:text-white" href="#categorias">Categorias</a>
                        <a class="hover:text-white" href="#productos">Productos</a>
                        <a class="hover:text-white" href="#envios">Envios</a>
                    </nav>

                    <button
                        type="button"
                        class="relative rounded bg-white px-4 py-2 text-sm font-bold text-zinc-950 transition hover:bg-red-100"
                        x-on:click="cartOpen = true"
                    >
                        Carrito
                        <span
                            x-cloak
                            x-show="count > 0"
                            x-text="count"
                            class="absolute -right-2 -top-2 grid size-6 place-items-center rounded-full bg-red-600 text-xs font-black text-white"
                        ></span>
                    </button>
                </div>
            </header>

            <main>
                <section class="relative">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(220,38,38,.34),transparent_30%),radial-gradient(circle_at_80%_0%,rgba(250,204,21,.18),transparent_26%)]"></div>
                    <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.05fr_.95fr] lg:px-8 lg:py-20">
                        <div class="flex flex-col justify-center">
                            <p class="mb-4 text-sm font-black uppercase tracking-normal text-red-300">Rendimiento, fuerza y recuperacion</p>
                            <h1 class="max-w-3xl text-5xl font-black leading-none sm:text-6xl lg:text-7xl">
                                Suplementos listos para tu siguiente entrenamiento.
                            </h1>
                            <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-300">
                                Proteinas, creatinas, pre entrenos y vitaminas con carrito, datos de envio y metodos de pago para levantar tu pedido.
                            </p>
                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <a href="#productos" class="rounded bg-red-600 px-6 py-3 text-center font-black text-white transition hover:bg-red-500">
                                    Ver productos
                                </a>
                                <button type="button" class="rounded border border-white/20 px-6 py-3 text-center font-black text-white transition hover:border-white/50" x-on:click="cartOpen = true">
                                    Abrir carrito
                                </button>
                            </div>
                        </div>

                        <div class="relative min-h-[420px]">
                            <div class="absolute inset-x-8 top-8 h-72 rounded-full bg-red-600/25 blur-3xl"></div>
                            <div class="relative grid h-full place-items-center">
                                <div class="w-full max-w-md rounded-lg border border-white/10 bg-white/8 p-5 shadow-2xl backdrop-blur">
                                    <div class="aspect-[4/5] rounded bg-gradient-to-br from-red-600 via-zinc-900 to-yellow-400 p-6">
                                        <div class="flex h-full flex-col justify-between rounded border border-white/20 bg-zinc-950/45 p-6">
                                            <div>
                                                <p class="text-sm font-black uppercase text-yellow-200">Stack recomendado</p>
                                                <h2 class="mt-3 text-4xl font-black leading-none">Fuerza + recuperacion</h2>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 text-sm font-bold">
                                                <span class="rounded bg-white px-3 py-2 text-zinc-950">Whey</span>
                                                <span class="rounded bg-white px-3 py-2 text-zinc-950">Creatina</span>
                                                <span class="rounded bg-white px-3 py-2 text-zinc-950">Pre entreno</span>
                                                <span class="rounded bg-white px-3 py-2 text-zinc-950">Vitaminas</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="categorias" class="border-y border-white/10 bg-white text-zinc-950">
                    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div class="mb-7 flex items-end justify-between gap-5">
                            <div>
                                <p class="text-sm font-black uppercase text-red-600">Compra por objetivo</p>
                                <h2 class="mt-2 text-3xl font-black">Categorias principales</h2>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($featuredCategories as $category)
                                <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5 transition hover:-translate-y-1 hover:border-red-200 hover:shadow-lg">
                                    <h3 class="text-xl font-black">{{ $category->name }}</h3>
                                    <p class="mt-3 text-sm leading-6 text-zinc-600">{{ $category->description }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section id="productos" class="bg-zinc-950">
                    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                            <div>
                                <p class="text-sm font-black uppercase text-red-300">Disponibles ahora</p>
                                <h2 class="mt-2 text-3xl font-black">Productos destacados</h2>
                            </div>
                            <p class="max-w-md text-sm leading-6 text-zinc-400">Los precios se confirman al cerrar pedido. Si algo se agota, te damos una alternativa antes de cobrar.</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($featuredProducts as $product)
                                <article class="group rounded-lg border border-white/10 bg-white/[.06] p-4 transition hover:-translate-y-1 hover:border-red-400/70">
                                    <div class="mb-4 grid aspect-square place-items-center overflow-hidden rounded bg-white">
                                        @if ($product->image_url)
                                            <img
                                                src="{{ $product->image_url }}"
                                                alt="{{ $product->name }}"
                                                class="h-full w-full object-contain p-4 transition duration-300 group-hover:scale-105"
                                                loading="lazy"
                                            >
                                        @else
                                            <span class="px-4 text-center text-2xl font-black text-zinc-300">{{ $product->category->name }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs font-black uppercase text-red-300">{{ $product->brand?->name }}</p>
                                    <h3 class="mt-2 min-h-14 text-lg font-black leading-tight">{{ $product->name }}</h3>
                                    <p class="mt-2 min-h-12 text-sm leading-6 text-zinc-400">{{ $product->short_description }}</p>
                                    <div class="mt-4 flex items-center gap-3">
                                        <span class="text-2xl font-black">{{ $product->price }}</span>
                                        @if ($product->compare_at_price)
                                            <span class="text-sm font-bold text-zinc-500 line-through">{{ $product->compare_at_price }}</span>
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-4 w-full rounded bg-red-600 px-4 py-3 text-sm font-black transition hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-zinc-700"
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
                                </article>
                            @endforeach
                        </div>

                        @if ($products->isNotEmpty())
                            <div class="mt-12 grid gap-4 md:grid-cols-2">
                                @foreach ($products as $product)
                                    <article class="flex gap-4 rounded-lg border border-white/10 bg-white/[.04] p-4">
                                        <div class="grid size-24 shrink-0 place-items-center overflow-hidden rounded bg-white text-xs font-black text-zinc-300">
                                            @if ($product->image_url)
                                                <img
                                                    src="{{ $product->image_url }}"
                                                    alt="{{ $product->name }}"
                                                    class="h-full w-full object-contain p-2"
                                                    loading="lazy"
                                                >
                                            @else
                                                <span class="px-2 text-center">{{ $product->category->name }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-xs font-black uppercase text-red-300">{{ $product->category->name }}</p>
                                            <h3 class="mt-1 font-black">{{ $product->name }}</h3>
                                            <p class="mt-1 text-sm text-zinc-400">{{ $product->short_description }}</p>
                                            <p class="mt-2 font-black">{{ $product->price }}</p>
                                            <button
                                                type="button"
                                                class="mt-3 rounded bg-red-600 px-4 py-2 text-sm font-black text-white transition hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-zinc-700"
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
                                @endforeach
                            </div>

                            <div class="mt-8">
                                {{ $products->fragment('productos')->links() }}
                            </div>
                        @endif
                    </div>
                </section>

                <section id="envios" class="bg-white text-zinc-950">
                    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8">
                        <div>
                            <p class="text-sm font-black uppercase text-red-600">Compra simple</p>
                            <h2 class="mt-2 text-3xl font-black">Atencion directa para cerrar tu pedido.</h2>
                        </div>
                        <div class="rounded-lg border border-zinc-200 p-5">
                            <h3 class="font-black">Confirmacion manual</h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">Te confirmamos existencias, total y forma de entrega antes de cobrar.</p>
                        </div>
                        <div class="rounded-lg border border-zinc-200 p-5">
                            <h3 class="font-black">Listo para crecer</h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">El catalogo queda en base de datos para conectar panel, carrito y pagos.</p>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-white/10 bg-zinc-950">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-8 text-sm text-zinc-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <p class="font-semibold text-white">Chutamax</p>
                    <p>Suplementos deportivos y alimenticios.</p>
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
                            <span>Total estimado</span>
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
                class="fixed inset-0 z-[60] overflow-y-auto bg-zinc-950/85 p-4 backdrop-blur"
                x-on:click.self="checkoutOpen = false"
            >
                <form method="POST" action="{{ route('checkout.store') }}" class="mx-auto max-w-4xl rounded-lg bg-white text-zinc-950 shadow-2xl">
                    @csrf
                    <input type="hidden" name="cart_payload" x-bind:value="cartPayload">

                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <div>
                            <h2 class="text-2xl font-black">Datos de envio y pago</h2>
                            <p class="mt-1 text-sm text-zinc-600">Pedido sujeto a confirmacion de existencia y costo de envio.</p>
                        </div>
                        <button type="button" class="rounded px-3 py-2 font-black hover:bg-zinc-100" x-on:click="checkoutOpen = false">X</button>
                    </div>

                    <div class="grid gap-6 p-5 lg:grid-cols-[1fr_320px]">
                        <div class="space-y-6">
                            <section>
                                <h3 class="mb-4 text-lg font-black">Contacto</h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-black">Nombre completo</span>
                                        <input name="customer_name" value="{{ old('customer_name') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Telefono</span>
                                        <input name="customer_phone" value="{{ old('customer_phone') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Correo</span>
                                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                </div>
                            </section>

                            <section>
                                <h3 class="mb-4 text-lg font-black">Direccion de envio</h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Calle</span>
                                        <input name="shipping_street" value="{{ old('shipping_street') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Numero</span>
                                        <input name="shipping_number" value="{{ old('shipping_number') }}" class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Colonia</span>
                                        <input name="shipping_neighborhood" value="{{ old('shipping_neighborhood') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Ciudad</span>
                                        <input name="shipping_city" value="{{ old('shipping_city') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Estado</span>
                                        <input name="shipping_state" value="{{ old('shipping_state') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-black">Codigo postal</span>
                                        <input name="shipping_postcode" value="{{ old('shipping_postcode') }}" required class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-sm font-black">Referencia</span>
                                        <textarea name="shipping_reference" rows="3" class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">{{ old('shipping_reference') }}</textarea>
                                    </label>
                                </div>
                            </section>

                            <section>
                                <h3 class="mb-4 text-lg font-black">Metodo de pago</h3>
                                <div class="grid gap-3">
                                    <label class="flex gap-3 rounded border border-zinc-300 p-4">
                                        <input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method', 'bank_transfer') === 'bank_transfer')>
                                        <span>
                                            <span class="block font-black">Transferencia bancaria</span>
                                            <span class="block text-sm text-zinc-600">Se enviaran los datos bancarios al confirmar disponibilidad.</span>
                                        </span>
                                    </label>
                                    <label class="flex gap-3 rounded border border-zinc-300 p-4">
                                        <input type="radio" name="payment_method" value="card_on_delivery" @checked(old('payment_method') === 'card_on_delivery')>
                                        <span>
                                            <span class="block font-black">Tarjeta al recibir</span>
                                            <span class="block text-sm text-zinc-600">Sujeto a cobertura y confirmacion.</span>
                                        </span>
                                    </label>
                                    <label class="flex gap-3 rounded border border-zinc-300 p-4">
                                        <input type="radio" name="payment_method" value="cash_on_delivery" @checked(old('payment_method') === 'cash_on_delivery')>
                                        <span>
                                            <span class="block font-black">Efectivo al recibir</span>
                                            <span class="block text-sm text-zinc-600">Disponible solo donde aplique entrega local.</span>
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <label class="block">
                                <span class="text-sm font-black">Notas del pedido</span>
                                <textarea name="notes" rows="3" class="mt-1 w-full rounded border border-zinc-300 px-3 py-3 text-sm outline-none focus:border-red-500">{{ old('notes') }}</textarea>
                            </label>
                        </div>

                        <aside class="rounded-lg bg-zinc-100 p-5">
                            <h3 class="text-lg font-black">Resumen</h3>
                            <div class="mt-4 max-h-72 space-y-3 overflow-y-auto">
                                <template x-for="item in items" :key="item.id">
                                    <div class="flex justify-between gap-3 text-sm">
                                        <span><span x-text="item.quantity"></span> x <span x-text="item.name"></span></span>
                                        <span class="font-black" x-text="money(item.price_cents * item.quantity)"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-5 border-t border-zinc-300 pt-4">
                                <div class="flex items-center justify-between text-xl font-black">
                                    <span>Total</span>
                                    <span x-text="money(totalCents)"></span>
                                </div>
                                <p class="mt-2 text-xs leading-5 text-zinc-600">El costo de envio se confirma antes de cobrar.</p>
                            </div>
                            <button type="submit" class="mt-5 w-full rounded bg-red-600 px-5 py-3 font-black text-white hover:bg-red-500">
                                Enviar pedido
                            </button>
                        </aside>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
