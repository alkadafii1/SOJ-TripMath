@props(['model', 'live' => false, 'disabled' => false, 'placeholder' => '', 'prefix' => 'Rp'])

<div class="relative {{ $attributes->get('class') }}"
     x-data="{
        display: '',
        clean(s) {
            // Hanya izinkan angka
            return String(s ?? '').replace(/[^0-9]/g, '');
        },
        pretty(s) {
            // Format ribuan dengan titik
            return s.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },
        fromModel(v) {
            if (v === null || v === undefined || v === '') return '';
            let s = String(v);
            // Hapus .00 di belakang kalau ada
            if (s.includes('.')) s = s.replace(/\.00$/, ''); 
            return this.pretty(s);
        },
        toModel(s) {
            // Bersihkan titik sebelum kirim ke database (Livewire)
            return s.replace(/\./g, '');
        },
        init() {
            this.display = this.fromModel($wire.$get('{{ $model }}'));
            $wire.$watch('{{ $model }}', (v) => {
                if (this.toModel(this.display) !== String(v ?? '')) {
                    this.display = this.fromModel(v);
                }
            });
        },
        typed(e) {
            const c = this.clean(e.target.value);
            this.display = this.pretty(c);
            e.target.value = this.display;
            $wire.$set('{{ $model }}', this.toModel(c), false);
        },
        flush() {
            if (@js($live)) $wire.$refresh();
        }
     }">
    
    {{-- Prefix Rp --}}
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 font-medium">
        {{ $prefix }}
    </span>
    
    {{-- Input Angka --}}
    <input type="text" inputmode="numeric" autocomplete="off"
           placeholder="{{ $placeholder }}"
           x-bind:value="display"
           x-on:input="typed($event)"
           x-on:input.debounce.500ms="flush()"
           @disabled($disabled)
           {{-- pl-10 memberi jarak agar angka tidak menabrak teks Rp --}}
           class="w-full rounded-md border-gray-300 pl-10 pr-3 text-sm shadow-sm disabled:bg-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
</div>