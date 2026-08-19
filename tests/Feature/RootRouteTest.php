<?php

use App\Models\Company;
use App\Models\User;

/**
 * A raiz não serve página de conteúdo: encaminha quem chega para o lugar
 * certo conforme já estar logado ou não. Cobrir os dois ramos evita que
 * alguém "conserte" a rota mais tarde e mande visitante anônimo direto
 * pro dashboard (que devolveria 403/redirect de auth, não erro óbvio).
 */

it('manda visitante anônimo para o login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('manda usuário logado para o dashboard', function () {
    $company = Company::create(['nome' => 'Empresa Teste']);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});
