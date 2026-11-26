    <div class="min-h-screen bg-[#f8f9fc] py-8 font-sans text-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Header Halaman -->
            <div>
                <a href="{{ $isEdit ? route('trips.show', $trip->id) : route('trips.index') }}" wire:navigate
                class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-indigo-600 transition-colors mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Kembali
                </a>
                <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $isEdit ? 'Ubah Perjalanan' : 'Buat Perjalanan' }}</h1>
                <p class="mt-1 text-sm text-slate-500">Isi detail perjalanan di bawah ini untuk membuat trip baru.</p>
            </div>

            <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Kolom Kiri: Form Input -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- KARTU 1: Informasi Perjalanan -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <h3 class="text-base font-semibold text-slate-900">1. Informasi Perjalanan</h3>
                        </div>

                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            @unless ($isEdit)
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Mulai dari paket (opsional)</label>
                                    <select wire:model="packageVersionId" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                        <option value="">-- trip kosong / custom --</option>
                                        @foreach ($versionOptions as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs text-slate-500">Komponen paket disalin ke trip dengan tarif saat ini.</p>
                                    @error('packageVersionId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            @endunless

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nama Perjalanan <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="form.trip_name" placeholder="Contoh: Paket Bali 3 Hari 2 Malam" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                @error('form.trip_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Customer <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="form.customer_name" placeholder="Nama customer" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                @error('form.customer_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Kontak Customer</label>
                                <input type="text" wire:model="form.customer_contact" placeholder="No. HP atau Email" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                @error('form.customer_contact')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tipe Trip <span class="text-red-500">*</span></label>
                                <select wire:model.live="form.trip_type" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                    @foreach (\App\Support\Options::TRIP_TYPES as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('form.trip_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Jumlah Peserta (Pax) <span class="text-red-500">*</span></label>
                                <input type="number" min="1" wire:model="form.pax" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                @error('form.pax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Mulai <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="date" wire:model.live="form.start_date" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                </div>
                                @error('form.start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Selesai <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="date" wire:model.live="form.end_date" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                </div>
                                @error('form.end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Jumlah Malam</label>
                                <input type="number" min="0" max="60" wire:model="form.nights" placeholder="Otomatis" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3.5 transition-colors">
                                <p class="mt-1.5 text-xs text-slate-500">
                                    {{ (! empty($form['start_date']) && ! empty($form['end_date']))
                                        ? 'Kosongkan untuk otomatis: '.rescue(fn () => max(\App\Models\Trip::daysBetween($form['start_date'], $form['end_date']) - 1, 0), '-').' malam. Isi sendiri bila berbeda.'
                                        : 'Kosongkan untuk otomatis (jumlah hari dikurangi 1).' }}
                                </p>
                                @error('form.nights')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            @if (($form['trip_type'] ?? '') === 'open_trip')
                                <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-5 p-4 bg-indigo-50/50 rounded-xl border border-indigo-100">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Minimum Pax</label>
                                        <input type="number" min="1" wire:model="form.min_pax" class="w-full rounded-xl border-slate-300 text-sm focus:ring-indigo-500 py-2 px-3">
                                        @error('form.min_pax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Kapasitas Maks.</label>
                                        <input type="number" min="1" wire:model="form.max_capacity" class="w-full rounded-xl border-slate-300 text-sm focus:ring-indigo-500 py-2 px-3">
                                        @error('form.max_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmed Pax</label>
                                        <input type="number" min="0" wire:model="form.confirmed_pax" class="w-full rounded-xl border-slate-300 text-sm focus:ring-indigo-500 py-2 px-3">
                                        @error('form.confirmed_pax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- KARTU 2: Penugasan & Destinasi -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <h3 class="text-base font-semibold text-slate-900">2. Penugasan & Destinasi</h3>
                        </div>

                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Staff Ditugaskan</label>
                                <select wire:model="form.assigned_staff_id" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5">
                                    <option value="">-- belum ditentukan --</option>
                                    @foreach ($staffOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Operational Partner</label>
                                <select wire:model="form.assigned_partner_id" class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 py-2.5 px-3.5">
                                    <option value="">-- belum ditentukan --</option>
                                    @foreach ($partnerOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sm:col-span-2 mt-2">
                                <label class="block text-sm font-medium text-slate-700 mb-2">Destinasi</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
                                    @foreach ($destinationOptions as $id => $name)
                                        <label class="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                                            <input type="checkbox" wire:model="destinationIds" value="{{ $id }}" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ $name }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('destinationIds.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="sm:col-span-2 mt-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Catatan Internal</label>
                                <textarea wire:model="form.notes" rows="4" placeholder="Tambahkan instruksi khusus, permintaan customer, dll..." class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:bg-white focus:ring-indigo-500 focus:border-indigo-500 py-3 px-3.5 transition-colors"></textarea>
                                @error('form.notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Kolom Kanan: Ringkasan & Tombol Aksi (Sticky) -->
                <div class="lg:col-span-1 space-y-6 lg:sticky lg:top-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-semibold text-slate-900">Ringkasan</h3>
                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10">Draft</span>
                        </div>
                        
                        <div class="space-y-4 text-sm">
                            <div>
                                <span class="text-slate-500 block text-xs mb-1">Nama Perjalanan</span>
                                <span class="font-medium text-slate-900">{{ $form['trip_name'] ?: 'Belum diisi' }}</span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-slate-500 block text-xs mb-1">Customer</span>
                                    <span class="font-medium text-slate-900">{{ $form['customer_name'] ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-xs mb-1">Tipe Trip</span>
                                    <span class="font-medium text-slate-900 capitalize">{{ str_replace('_', ' ', $form['trip_type']) }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-slate-500 block text-xs mb-1">Tanggal Mulai</span>
                                    <span class="font-medium text-slate-900">{{ $form['start_date'] ? \Carbon\Carbon::parse($form['start_date'])->format('d M Y') : '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-xs mb-1">Tanggal Selesai</span>
                                    <span class="font-medium text-slate-900">{{ $form['end_date'] ? \Carbon\Carbon::parse($form['end_date'])->format('d M Y') : '-' }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 pb-4 border-b border-slate-100">
                                <div>
                                    <span class="text-slate-500 block text-xs mb-1">Pax</span>
                                    <span class="font-medium text-slate-900">{{ $form['pax'] }} Orang</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-xs mb-1">Durasi</span>
                                    <span class="font-medium text-slate-900">{{ $form['nights'] ? $form['nights'].' Malam' : 'Otomatis' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6">
                            <!-- TOMBOL SIMPAN TERPADU -->
                            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="w-full flex items-center justify-center rounded-xl bg-[#5038ED] px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#4330c6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
                                
                                <!-- State 1: Normal -->
                                <span wire:loading.remove wire:target="save" class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                                    </svg>
                                    Simpan Perjalanan
                                </span>

                                <!-- State 2: Loading (Gunakan class 'hidden' & wire:loading.class.remove) -->
                                <span wire:loading.class.remove="hidden" wire:target="save" class="hidden items-center justify-center gap-2">
                                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Menyimpan...
                                </span>

                            </button>
                            
                            <a href="{{ $isEdit ? route('trips.show', $trip->id) : route('trips.index') }}" wire:navigate class="mt-3 flex w-full items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                Batal
                            </a>
                        </div>
                    </div>
                </div>
                
            </form>
        </div>
    </div>