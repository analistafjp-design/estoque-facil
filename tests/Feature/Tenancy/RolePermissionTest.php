<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

test('operador não acessa a tela de convites e recebe 403', function () {
    $company = Company::factory()->create();
    $operador = User::factory()->create(['company_id' => $company->id, 'papel' => UserRole::Operador]);

    $this->actingAs($operador)
        ->get(route('invitations.index'))
        ->assertForbidden();
});

test('admin acessa a tela de convites normalmente', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->admin()->create(['company_id' => $company->id]);

    $this->actingAs($admin)
        ->get(route('invitations.index'))
        ->assertOk();
});

test('operador não vê o link de Convites na navegação', function () {
    $company = Company::factory()->create();
    $operador = User::factory()->create(['company_id' => $company->id, 'papel' => UserRole::Operador]);

    $this->actingAs($operador)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('invitations.index'));
});

test('visitante não logado é redirecionado ao tentar ver produtos', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});
