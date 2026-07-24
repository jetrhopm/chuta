<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Chutamax' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-950 text-white antialiased">
        <header class="border-b border-white/10 bg-zinc-950">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                <a href="{{ route('storefront.home') }}" class="text-xl font-black">Chutamax</a>
                <a href="{{ route('storefront.home') }}#productos" class="rounded bg-red-600 px-4 py-2 text-sm font-black">Catalogo</a>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 py-12">
            <div class="rounded-lg border border-white/10 bg-white p-6 text-zinc-950 shadow-2xl sm:p-8">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
