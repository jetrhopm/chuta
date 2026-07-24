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
            x-data="{ cartOpen: false, selectedProduct: '' }"
            class="min-h-screen overflow-hidden"
        >
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
                        class="rounded bg-white px-4 py-2 text-sm font-bold text-zinc-950 transition hover:bg-red-100"
                        x-on:click="cartOpen = true"
                    >
                        Comprar
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
                                Proteinas, creatinas, pre entrenos y vitaminas con compra rapida por WhatsApp mientras activamos el checkout completo.
                            </p>
                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <a href="#productos" class="rounded bg-red-600 px-6 py-3 text-center font-black text-white transition hover:bg-red-500">
                                    Ver productos
                                </a>
                                <a href="https://wa.me/5216441730674?text=Hola%2C%20quiero%20cotizar%20suplementos%20de%20Chutamax" class="rounded border border-white/20 px-6 py-3 text-center font-black text-white transition hover:border-white/50">
                                    Pedir por WhatsApp
                                </a>
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
                                        x-on:click="selectedProduct = @js($product->name); cartOpen = true"
                                    >
                                        {{ $product->is_in_stock ? 'Comprar ahora' : 'Agotado' }}
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
                        <h2 class="text-xl font-black">Finalizar pedido</h2>
                        <button type="button" class="rounded px-3 py-2 font-black hover:bg-zinc-100" x-on:click="cartOpen = false">X</button>
                    </div>
                    <div class="flex-1 p-5">
                        <p class="text-sm leading-6 text-zinc-600">Por ahora cerramos pedidos por WhatsApp para confirmar stock, envio y pago.</p>
                        <template x-if="selectedProduct">
                            <p class="mt-4 rounded bg-red-50 p-3 text-sm font-bold text-red-700">Producto seleccionado: <span x-text="selectedProduct"></span></p>
                        </template>
                    </div>
                    <div class="border-t border-zinc-200 p-5">
                        <a
                            class="block rounded bg-red-600 px-5 py-3 text-center font-black text-white hover:bg-red-500"
                            x-bind:href="'https://wa.me/5216441730674?text=' + encodeURIComponent('Hola, quiero comprar en Chutamax' + (selectedProduct ? ': ' + selectedProduct : ''))"
                        >
                            Continuar por WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
