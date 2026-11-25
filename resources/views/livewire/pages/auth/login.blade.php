<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest'), Title('Masuk')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md mx-auto p-6 sm:p-8 bg-white rounded-2xl shadow-sm border border-slate-200">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Selamat Datang</h1>
        <p class="mt-2 text-sm text-slate-500">Silakan masuk ke akun anda</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
            <input wire:model="form.email" id="email" name="email" type="email"
                   required autofocus autocomplete="username" placeholder="nama@perusahaan.com"
                   @class([
                       'block w-full rounded-xl border bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors',
                       'focus:bg-white focus:ring-2',
                       'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500/20' => ! $errors->has('form.email'),
                       'border-red-300 focus:border-red-500 focus:ring-red-500/20' => $errors->has('form.email'),
                   ])>
            @error('form.email')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
            <div class="relative" x-data="{ show: false }">
                <input wire:model="form.password" id="password" name="password" type="password" x-bind:type="show ? 'text' : 'password'"
                       required autocomplete="current-password" placeholder="Masukkan kata sandi"
                       @class([
                           'block w-full rounded-xl border bg-slate-50 pl-4 pr-12 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors',
                           'focus:bg-white focus:ring-2',
                           'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500/20' => ! $errors->has('form.password'),
                           'border-red-300 focus:border-red-500 focus:ring-red-500/20' => $errors->has('form.password'),
                       ])>
                <button type="button" x-on:click="show = ! show" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 hover:text-slate-600 focus:outline-none">
                    <svg x-show="! show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <svg x-show="show" style="display: none;" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                </button>
            </div>
            @error('form.password')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me + Forgot Password -->
        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center gap-2 cursor-pointer">
                <input wire:model="form.remember" id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-slate-700">Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <!-- Tombol Submit yang Sudah Diperbaiki -->
        <button type="submit" wire:loading.attr="disabled" wire:target="login"
                class="w-full flex items-center justify-center rounded-xl bg-[#5038ED] px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#4330c6] transition-all disabled:opacity-70 disabled:cursor-not-allowed mt-2">
            
            <!-- Teks Normal -->
            <span wire:loading.remove wire:target="login">Masuk</span>
            
            <!-- Teks Loading (Dipaksa sembunyi pakai 'hidden' bawaan class) -->
            <span wire:loading.class.remove="hidden" wire:target="login" class="hidden items-center justify-center gap-2">
                <svg class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memproses...
            </span>
        </button>
    </form>
</div>