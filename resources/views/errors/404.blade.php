<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pagina no encontrada | Chutamax</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="grid min-h-screen place-items-center bg-zinc-950 px-4 text-white">
        <main class="max-w-2xl text-center">
            <p class="text-sm font-black uppercase text-red-300">404</p>
            <h1 class="mt-3 text-5xl font-black">No encontramos esa pagina.</h1>
            <p class="mt-5 text-lg leading-8 text-zinc-300">Puede que el producto o enlace haya cambiado. Vuelve al catalogo para seguir comprando.</p>
            <a href="{{ route('storefront.home') }}#productos" class="mt-8 inline-flex rounded bg-red-600 px-6 py-3 font-black text-white">Ver catalogo</a>
        </main>
    </body>
</html>
