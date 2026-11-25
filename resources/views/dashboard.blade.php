<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800">Halo, {{ auth()->user()->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">Pilih menu di sebelah kiri untuk mengelola operasional trip.</p>
            </div>

            <livewire:dashboard.overview />

        </div>
    </div>
</x-app-layout>