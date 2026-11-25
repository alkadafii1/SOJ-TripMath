<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}SOJ System</title>

        <!-- Fonts: Menggunakan Inter -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        
        <style>
            /* Memastikan font diterapkan */
            body { font-family: 'Inter', sans-serif; }
        </style>
    </head>
    <!-- Background dibuat sangat lembut, layout dibuat center -->
    <body class="min-h-screen bg-[#fafafa] text-slate-700 antialiased flex items-center justify-center p-4 sm:p-6">

        <!-- Navbar sudah dihapus, langsung ke konten utama (Card) -->
        <main class="w-full max-w-[440px] rounded-xl bg-white px-8 py-10 shadow-[0_4px_24px_rgba(0,0,0,0.03)] border border-gray-200">
            
            {{-- Identitas aplikasi diganti dengan Logo --}}
            <div class="text-center mb-6 flex flex-col items-center">
                <!-- Gambar logo -->
                <img src="{{ asset('images/soj-bg-transparent-1781074723.png') }}" alt="Sunrise of Java Logo" class="w-56 sm:w-64 h-auto object-contain mb-2">
                
                <!-- Judul dan subjudul dirapatkan -->
                <h2 class="text-[22px] font-medium tracking-tight text-gray-900 leading-none">TripMath</h2>
                <p class="mt-2 text-[11px] font-medium tracking-[0.1em] text-gray-400 uppercase">Crafting Journeys, Creating Memories</p>
            </div>

            <div>
                {{ $slot }}
            </div>

            {{-- Catatan akses --}}
            <p class="mt-10 flex items-center justify-center gap-1.5 text-[12px] text-gray-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                Sistem Terbatas & Hak Akses Khusus
            </p>
        </main>

        @livewireScripts
    </body>
</html>