<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('new users can register and a company is created for them', function () {
    $component = Volt::test('pages.auth.register')
        ->set('company', 'Empresa Teste')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $company = Company::where('nome', 'Empresa Teste')->firstOrFail();

    expect($user->company_id)->toBe($company->id)
        ->and($user->papel)->toBe(UserRole::Admin);
});

test('registering without a company name fails validation', function () {
    $component = Volt::test('pages.auth.register')
        ->set('company', '')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertHasErrors(['company' => 'required']);
    $this->assertGuest();
});
