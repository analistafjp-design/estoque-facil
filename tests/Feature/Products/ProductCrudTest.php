<?php

use App\Livewire\Products\ProductIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->admin()->create(['company_id' => $this->company->id]);
});

test('cria produto', function () {
    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->call('create')
        ->set('nome', 'Caneta azul')
        ->set('sku', 'CAN-001')
        ->set('categoria', 'Papelaria')
        ->set('quantidade_atual', 50)
        ->set('estoque_minimo', 10)
        ->call('save')
        ->assertHasNoErrors();

    $produto = Product::where('sku', 'CAN-001')->firstOrFail();

    expect($produto->nome)->toBe('Caneta azul')
        ->and($produto->company_id)->toBe($this->company->id);
});

test('não cria produto com SKU duplicado na mesma empresa', function () {
    Product::factory()->create(['company_id' => $this->company->id, 'sku' => 'DUP-001']);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->call('create')
        ->set('nome', 'Outro produto')
        ->set('sku', 'DUP-001')
        ->set('quantidade_atual', 1)
        ->set('estoque_minimo', 1)
        ->call('save')
        ->assertHasErrors(['sku']);
});

test('duas empresas diferentes podem usar o mesmo SKU sem conflito', function () {
    $outraEmpresa = Company::factory()->create();
    Product::factory()->create(['company_id' => $outraEmpresa->id, 'sku' => 'REPETE-001']);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->call('create')
        ->set('nome', 'Produto da minha empresa')
        ->set('sku', 'REPETE-001')
        ->set('quantidade_atual', 1)
        ->set('estoque_minimo', 1)
        ->call('save')
        ->assertHasNoErrors();

    // withoutGlobalScopes() de propósito: o CompanyScope filtraria pela
    // empresa logada e o count() nunca chegaria a 2 mesmo se o bug de
    // isolamento existisse ao contrário (a checagem real de unicidade por
    // empresa está no teste de SKU duplicado, acima).
    expect(Product::withoutGlobalScopes()->where('sku', 'REPETE-001')->count())->toBe(2);
});

test('edita produto', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Nome antigo']);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->call('edit', $produto->id)
        ->set('nome', 'Nome novo')
        ->call('save')
        ->assertHasNoErrors();

    expect($produto->fresh()->nome)->toBe('Nome novo');
});

test('desativar produto é soft delete, não apaga de verdade', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id]);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->call('desativar', $produto->id);

    // fresh() ignora os global scopes do model (inclusive SoftDeletingScope
    // e CompanyScope), então não serve pra provar "sumiu da listagem" — só
    // pra ler o estado bruto da linha (usado logo abaixo, com trashed()).
    // Quem prova "sumiu" é uma query nova, que passa pelos scopes de novo.
    expect(Product::find($produto->id))->toBeNull() // não aparece nas queries normais...
        ->and(Product::withTrashed()->find($produto->id))->not->toBeNull() // ...mas continua no banco
        ->and(Product::withTrashed()->find($produto->id)->trashed())->toBeTrue();
});

test('produto desativado some da listagem', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Vai sumir']);
    $produto->delete();

    $this->actingAs($this->user)
        ->get(route('products.index'))
        ->assertDontSee('Vai sumir');
});

test('filtro de estoque baixo só mostra produtos no ou abaixo do mínimo', function () {
    Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Estoque ok', 'quantidade_atual' => 100, 'estoque_minimo' => 10]);
    Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Estoque baixo', 'quantidade_atual' => 2, 'estoque_minimo' => 10]);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('filtro', 'baixo')
        ->assertSee('Estoque baixo')
        ->assertDontSee('Estoque ok');
});

test('busca encontra produto por nome ou SKU', function () {
    Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Grampeador', 'sku' => 'GRA-001']);
    Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Clipes', 'sku' => 'CLI-002']);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('busca', 'GRA-001')
        ->assertSee('Grampeador')
        ->assertDontSee('Clipes');
});
