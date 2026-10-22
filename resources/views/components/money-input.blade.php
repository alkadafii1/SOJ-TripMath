@props(['model', 'live' => false, 'disabled' => false, 'placeholder' => '', 'prefix' => 'Rp'])

<div class="relative {{ $attributes->get('class') }}"
     x-data="{
        display: '',
        clean(s) {
            s = String(s ?? '').replace(/[^0-9,]/g, '');
            const i = s.indexOf(',');
            if (i !== -1) {
                s = s.slice(0, i + 1) + s.slice(i + 1).replace(/,/g, '').slice(0, 2);
            }
            return s;
        },
        pretty(s) {
            const parts = s.split(',');
            const whole = parts[0].replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return parts.length > 1 ? whole + ',' + parts[1] : whole;
        },
        fromModel(v) {
            if (v === null || v === undefined || v === '') return '';
            let s = String(v);
            if (s.includes('.')) s = s.replace(/0+$/, '').replace(/\.$/, '');
            return this.pretty(s.replace('.', ','));
        },
        toModel(s) {
            return s.replace(',', '.');
        },
        init() {
            this.display = this.fromModel($wire.$get('{{ $model }}'));
            $wire.$watch('{{ $model }}', (v) => {
                if (this.toModel(this.clean(this.display)) !== String(v ?? '')) {
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
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500">{{ $prefix }}</span>
    <input type="text" inputmode="decimal" autocomplete="off"
           placeholder="{{ $placeholder }}"
           x-bind:value="display"
           x-on:input="typed($event)"
           x-on:input.debounce.500ms="flush()"
           @disabled($disabled)
           class="w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm disabled:bg-gray-100">
</div>