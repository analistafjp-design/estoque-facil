<?php

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Livewire\Products\ProductIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Livewire\Livewire;

/**
 * Exclusão em massa existe para desfazer importação que entrou errada.
 * É destrutiva de verdade — por isso os testes cobrem tanto o que ela
 * apaga quanto o que ela NÃO pode encostar (dado de outra empresa) e
 * quem não pode chamá-la.
 */
beforeEach(function () {
    $this->company = Company::create(['nome' => 'Minha Empresa']);
    $this->admin = User::factory()->create([
        'company_id' => $this->company->id,
        'papel' => UserRole::Admin,
    ]);
});

it('apaga produtos e movimentações da empresa', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id]);

    StockMovement::factory()->create([
        'company_id' => $this->company->id,
        'product_id' => $produto->id,
        'user_id' => $this->admin->id,
        'tipo' => MovementType::Entrada,
        'quantidade' => 5,
    ]);

    Livewire::actingAs($this->admin)
        ->test(ProductIndex::class)
        ->set('confirmacaoExclusao', 'EXCLUIR')
        ->call('excluirTodos')
        ->assertHasNoErrors();

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0)
        ->and(StockMovement::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0);
});

it('apaga também produto já desativado, liberando o SKU', function () {
    $produto = Product::factory()->create([
        'company_id' => $this->company->id,
        'sku' => 'REPETIDO',
    ]);
    $produto->delete(); // soft delete

    Livewire::actingAs($this->admin)
        ->test(ProductIndex::class)
        ->set('confirmacaoExclusao', 'EXCLUIR')
        ->call('excluirTodos');

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0);
});

it('não apaga nada sem a confirmação digitada', function () {
    Product::factory()->count(3)->create(['company_id' => $this->company->id]);

    Livewire::actingAs($this->admin)
        ->test(ProductIndex::class)
        ->set('confirmacaoExclusao', '')
        ->call('excluirTodos')
        ->assertHasErrors('confirmacaoExclusao');

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(3);
});

it('não apaga com confirmação escrita errado', function () {
    Product::factory()->count(2)->create(['company_id' => $this->company->id]);

    Livewire::actingAs($this->admin)
        ->test(ProductIndex::class)
        ->set('confirmacaoExclusao', 'apagar')
        ->call('excluirTodos')
        ->assertHasErrors('confirmacaoExclusao');

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(2);
});

it('operador não pode excluir tudo', function () {
    $operador = User::factory()->create([
        'company_id' => $this->company->id,
        'papel' => UserRole::Operador,
    ]);

    Product::factory()->count(2)->create(['company_id' => $this->company->id]);

    Livewire::actingAs($operador)
        ->test(ProductIndex::class)
        ->set('confirmacaoExclusao', 'EXCLUIR')
        ->call('excluirTodos')
        ->assertStatus(403);

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(2);
});

it('nunca encosta em produto de outra empresa', function () {
    $outra = Company::create(['nome' => 'Empresa Vizinha']);
    $produtoAlheio = Product::factory()->create(['company_id' => $outra->id]);

    Product::factory()->count(2)->create(['company_id' => $this->company->id]);

    Livewire::actingAs($this->admin)
        ->test(ProductIndex::class)
        ->set('confirmacaoExclusao', 'EXCLUIR')
        ->call('excluirTodos');

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0)
        ->and(Product::withoutGlobalScopes()->find($produtoAlheio->id))->not->toBeNull();
});
