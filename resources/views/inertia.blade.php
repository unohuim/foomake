<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.google-analytics')

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <link rel="icon" href="/img/foomake_fav.ico?v=3" type="image/x-icon">

        @vite(['resources/css/app.css', 'resources/js/inertia-app.js'])
        <x-inertia::head />
    </head>
    <body class="bg-[#f7f9fc] font-sans text-slate-950 antialiased">
        <x-inertia::app />
    </body>
</html>
