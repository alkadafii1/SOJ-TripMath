<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100"
          x-data="{ open: false, collapsed: localStorage.getItem('sidebar-collapsed') === '1' }"
          x-init="$watch('collapsed', v => localStorage.setItem('sidebar-collapsed', v ? '1' : '0'))">

        <livewire:layout.navigation />

        <div class="min-h-screen pt-14 transition-[padding] duration-200"
             :class="collapsed ? 'lg:pl-0' : 'lg:pt-0 lg:pl-64'">
            @isset($header)
                <header class="bg-white shadow">
                    <div class="px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="px-4 sm:px-0">
                {{ $slot }}
            </main>
        </div>

        @livewireScriptConfig
    </body>
</html>