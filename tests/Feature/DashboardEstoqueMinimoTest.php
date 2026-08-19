<?php

use App\Livewire\Dashboard;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/**
 * O alerta de estoque baixo compara quantidade <= mínimo. Depois de
 * importar planilha sem coluna de mínimo, TODO o cadastro fica com mínimo
 * zero e o alerta só aponta produto zerado — parecendo, de fora, que está
 * tudo certo. O aviso existe para dizer que a verificação está desligada,
 * não que o estoque está saudável.
 */
beforeEach(function () {
    $this->company = Company::create(['nome' => 'Empresa Teste']);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
});

it('conta quantos produtos estão sem estoque mínimo definido', function () {
    Product::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'estoque_minimo' => 0,
        'quantidade_atual' => 50,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'estoque_minimo' => 10,
        'quantidade_atual' => 50,
    ]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->assertViewHas('semMinimoDefinido', 3)
        ->assertSee('sem estoque mínimo definido');
});

it('não mostra o aviso quando os mínimos estão definidos', function () {
    Product::factory()->count(4)->create([
        'company_id' => $this->company->id,
        'estoque_minimo' => 10,
        'quantidade_atual' => 50,
    ]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->assertDontSee('sem estoque mínimo definido');
});

it('aponta como estoque baixo apenas quem está no ou abaixo do mínimo', function () {
    Product::factory()->create([
        'company_id' => $this->company->id,
        'nome' => 'No limite',
        'quantidade_atual' => 10,
        'estoque_minimo' => 10,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'nome' => 'Abaixo',
        'quantidade_atual' => 3,
        'estoque_minimo' => 10,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'nome' => 'Folgado',
        'quantidade_atual' => 500,
        'estoque_minimo' => 10,
    ]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->assertViewHas('totalEstoqueBaixo', 2)
        ->assertSee('No limite')
        ->assertSee('Abaixo')
        ->assertDontSee('Folgado');
});

it('com mínimo zero, só produto zerado entra no alerta', function () {
    // Reproduz o cadastro do usuário: milhares de produtos com saldo e
    // mínimo zero, e só dois zerados.
    Product::factory()->count(5)->create([
        'company_id' => $this->company->id,
        'quantidade_atual' => 100,
        'estoque_minimo' => 0,
    ]);

    Product::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'quantidade_atual' => 0,
        'estoque_minimo' => 0,
    ]);

    Livewire::actingAs($this->user)
        ->test(Dashboard::class)
        ->assertViewHas('totalProdutos', 7)
        ->assertViewHas('totalEstoqueBaixo', 2);
});
