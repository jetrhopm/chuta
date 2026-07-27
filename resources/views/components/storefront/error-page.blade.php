@props([
    'code',
    'title',
    'message',
    'showSearch' => true,
])

{{--
    Base de todas las paginas de error.

    Usa los mismos design tokens que la tienda para que un error no parezca otro
    sitio, y nunca muestra detalles internos: ni trazas, ni consultas, ni nombres
    de clase. Siempre ofrece una salida, porque un error sin salida es una venta
    perdida.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title }} | Chutamax</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="grid min-h-screen place-items-center bg-[var(--color-surface-muted)] px-4 py-12 text-[var(--color-ink)]">
        <main class="w-full max-w-2xl text-center">
            <a href="{{ route('storefront.home') }}" class="display-title inline-block bg-black px-4 py-2 text-2xl text-white">
                Chutamax
            </a>

            <p class="impact-title mt-8 text-6xl text-[var(--color-brand)] sm:text-7xl">{{ $code }}</p>
            <h1 class="impact-title mt-2 text-2xl uppercase text-black sm:text-3xl">{{ $title }}</h1>
            <p class="mt-4 leading-7 text-[var(--color-ink-soft)]">{{ $message }}</p>

            @if ($showSearch)
                <form method="GET" action="{{ route('catalog.index') }}" role="search" class="mx-auto mt-8 flex max-w-md">
                    <label class="sr-only" for="buscar-error">Buscar productos</label>
                    <input
                        id="buscar-error"
                        type="search"
                        name="q"
                        placeholder="Busca lo que necesitas"
                        class="w-full border border-[var(--color-border-strong)] bg-white px-3 py-3 text-sm"
                    >
                    <button type="submit" class="display-title bg-[var(--color-brand)] px-5 text-lg text-white transition hover:bg-[var(--color-brand-strong)]">
                        Buscar
                    </button>
                </form>
            @endif

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('storefront.home') }}" class="display-title bg-black px-6 py-3 text-lg text-white transition hover:bg-[var(--color-brand)]">
                    Ir al inicio
                </a>
                <a href="{{ route('catalog.index') }}" class="display-title border border-[var(--color-border-strong)] px-6 py-3 text-lg transition hover:border-[var(--color-brand)]">
                    Ver catalogo
                </a>
            </div>

            <p class="mt-8 text-sm text-[var(--color-ink-soft)]">
                Si necesitas ayuda, escribenos al WhatsApp {{ config('storefront.contact.whatsapp') }}.
            </p>
        </main>
    </body>
</html>
