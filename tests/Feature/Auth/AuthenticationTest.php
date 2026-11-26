<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('wire:submit="login"', false);
    }

    public function test_login_screen_uses_guest_layout_without_sidebar(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Selamat Datang')
            ->assertSee('Silakan masuk ke akun anda')
            ->assertSee('max-w-md')
            ->assertDontSee('sidebar-collapsed')
            ->assertSee('livewire.js', false)
            ->assertDontSee('Buka menu'); // tombol sidebar milik layout aplikasi
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_failed_login_shows_indonesian_error_message(): void
    {
        app()->setLocale('id');
        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'kata-sandi-salah')
            ->call('login')
            ->assertHasErrors(['form.email'])
            ->assertSee('Email atau kata sandi salah.');
    }

    public function test_login_is_rate_limited_with_a_translated_message(): void
    {
        app()->setLocale('id');
        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'throttle@example.test')
            ->set('form.password', 'wrong-password');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('login')->assertHasErrors(['form.email']);
        }

        $component->call('login')
            ->assertHasErrors(['form.email'])
            ->assertSee('Terlalu banyak percobaan masuk.')
            ->assertDontSee('auth.throttle')
            ->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_failed_login_uses_the_selected_locale(): void
    {
        app()->setLocale('en');
        Volt::test('pages.auth.login')
            ->set('form.email', 'unknown@example.test')
            ->set('form.password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['form.email'])
            ->assertSee(trans('auth.failed'))
            ->assertNoRedirect();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('wire:click="logout"', false);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}