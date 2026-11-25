<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest'), Title('Masuk')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="text-center">
        <h1 class="text-[17px] font-semibold text-slate-900">Selamat Datang</h1>
        <p class="mt-1 text-xs text-slate-500">Silakan masuk ke akun anda</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mt-5" :status="session('status')" />

    <form wire:submit="login" class="mt-7 space-y-5">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-semibold text-slate-700">Email</label>
            <input wire:model="form.email" id="email" name="email" type="email"
                   required autofocus autocomplete="username" placeholder="Masukkan email anda"
                   @class([
                       'mt-1.5 block w-full rounded-lg border bg-slate-50 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400',
                       'focus:bg-white focus:ring-2',
                       'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/20' => ! $errors->has('form.email'),
                       'border-red-300 focus:border-red-500 focus:ring-red-500/20' => $errors->has('form.email'),
                   ])
                   aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}"
                   @if ($errors->has('form.email')) aria-describedby="email-error" @endif>
            @error('form.email')
                <p id="email-error" class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-xs font-semibold text-slate-700">Password</label>
            <div class="relative mt-1.5" x-data="{ show: false }">
                <input wire:model="form.password" id="password" name="password" type="password" x-bind:type="show ? 'text' : 'password'"
                       required autocomplete="current-password" placeholder="Masukkan kata sandi"
                       @class([
                           'block w-full rounded-lg border bg-slate-50 py-2.5 pl-3 pr-10 text-sm text-slate-900 placeholder:text-slate-400',
                           'focus:bg-white focus:ring-2',
                           'border-slate-200 focus:border-indigo-500 focus:ring-indigo-500/20' => ! $errors->has('form.password'),
                           'border-red-300 focus:border-red-500 focus:ring-red-500/20' => $errors->has('form.password'),
                       ])
                       aria-invalid="{{ $errors->has('form.password') ? 'true' : 'false' }}"
                       @if ($errors->has('form.password')) aria-describedby="password-error" @endif>

                <button type="button" x-on:click="show = ! show"
                        x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        x-bind:aria-pressed="show"
                        aria-label="Tampilkan kata sandi" aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-slate-400 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    {{-- Tersembunyi: ikon mata dicoret --}}
                    <svg x-show="! show" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                    {{-- Terlihat: ikon mata --}}
                    <svg x-show="show" style="display: none" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </div>
            @error('form.password')
                <p id="password-error" class="mt-1.5 text-xs text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me + Forgot Password -->
        <div class="flex items-center justify-between gap-3 text-xs">
            <label for="remember" class="inline-flex items-center gap-2 text-slate-700">
                <input wire:model="form.remember" id="remember" name="remember" type="checkbox"
                       class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Ingat saya
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate
                   class="rounded text-slate-500 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="login"
                class="flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-600/30 transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses…</span>
        </button>
    </form>
</div>