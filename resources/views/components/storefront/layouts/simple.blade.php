<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Chutamax' }}</title>
        {{-- Cada pagina agrega aqui sus datos para buscadores y para compartir. --}}
        @stack('head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Layout de las paginas sueltas: legales, seguimiento de pedido y avisos.
         Lee los mismos design tokens que la portada para que cambiar de tema
         alcance tambien a estas pantallas. --}}
    <body class="bg-[var(--color-surface-muted)] text-[var(--color-ink)] antialiased">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-black focus:px-4 focus:py-2 focus:text-white">
            Saltar al contenido
        </a>

        <header class="bg-black">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
                <a href="{{ route('storefront.home') }}" class="flex items-center gap-3" aria-label="Chutamax, ir al inicio">
                    <span class="display-title grid size-11 place-items-center bg-[var(--color-brand)] text-2xl text-white">C</span>
                    <span class="display-title text-2xl text-white">Chutamax</span>
                </a>
                <a href="{{ route('storefront.home') }}#productos" class="display-title bg-[var(--color-brand)] px-4 py-2 text-lg text-white transition hover:bg-[var(--color-brand-strong)]">
                    Catalogo
                </a>
            </div>
        </header>

        <main id="contenido" class="mx-auto max-w-5xl px-4 py-10">
            <div class="bg-white shadow-sm">
                {{ $slot }}
            </div>
        </main>

        <footer class="bg-black py-6 text-center text-xs uppercase tracking-[0.14em] text-white/50">
            Chutamax {{ now()->year }} &middot; Todos los derechos reservados
        </footer>
    </body>
</html>
