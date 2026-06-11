@props(['title' => '', 'subtitle' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }}@if($title) - {{ $title }}@endif</title>

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50">
<div class="min-h-full">
    <nav class="border-b border-gray-200 bg-white shadow-sm ">
        <div class="mx-auto max-w-7xl py-2 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between">
                <img src="{{ asset('images/logo.png')  }}" alt="Watersportvereniging Almere Centraal" class="h-18 w-auto" />
            </div>
        </div>
    </nav>

    <div class="py-10">
        @if($title)
        <header>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-title tracking-tight text-secondary-500">{{ $title }}</h1>
                <h2 class="text-lg tracking-tight text-gray-600">{{ $subtitle  }}</h2>
            </div>
        </header>
        @endif
        <main class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
