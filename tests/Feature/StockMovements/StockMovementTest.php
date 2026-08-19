<?php

use App\Enums\MovementType;
use App\Livewire\Products\ProductShow;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->admin()->create(['company_id' => $this->company->id]);
});

test('entrada soma na quantidade atual', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'quantidade_atual' => 10]);

    Livewire::actingAs($this->user)
        ->test(ProductShow::class, ['product' => $produto])
        ->set('tipo', 'entrada')
        ->set('quantidade', 15)
        ->call('lancar')
        ->assertHasNoErrors();

    expect($produto->fresh()->quantidade_atual)->toBe(25);
    expect($produto->stockMovements()->count())->toBe(1);
    expect($produto->stockMovements()->first()->tipo)->toBe(MovementType::Entrada);
});

test('saída subtrai da quantidade atual', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'quantidade_atual' => 30]);

    Livewire::actingAs($this->user)
        ->test(ProductShow::class, ['product' => $produto])
        ->set('tipo', 'saida')
        ->set('quantidade', 12)
        ->call('lancar')
        ->assertHasNoErrors();

    expect($produto->fresh()->quantidade_atual)->toBe(18);
});

test('não permite saída maior que o estoque atual', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'quantidade_atual' => 5]);

    Livewire::actingAs($this->user)
        ->test(ProductShow::class, ['product' => $produto])
        ->set('tipo', 'saida')
        ->set('quantidade', 6)
        ->call('lancar')
        ->assertHasErrors(['quantidade']);

    expect($produto->fresh()->quantidade_atual)->toBe(5);
    expect($produto->stockMovements()->count())->toBe(0);
});

test('quantidade deve ser maior que zero', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'quantidade_atual' => 5]);

    Livewire::actingAs($this->user)
        ->test(ProductShow::class, ['product' => $produto])
        ->set('tipo', 'entrada')
        ->set('quantidade', 0)
        ->call('lancar')
        ->assertHasErrors(['quantidade']);
});

test('movimentação registra quem lançou', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'quantidade_atual' => 10]);

    Livewire::actingAs($this->user)
        ->test(ProductShow::class, ['product' => $produto])
        ->set('tipo', 'entrada')
        ->set('quantidade', 5)
        ->call('lancar');

    expect($produto->stockMovements()->first()->user_id)->toBe($this->user->id);
});

test('histórico de movimentações aparece na ficha do produto', function () {
    $produto = Product::factory()->create(['company_id' => $this->company->id, 'quantidade_atual' => 10]);

    // company_id não é fillable (mesma razão de Product) — precisa logar
    // antes de criar, pra BelongsToCompany preencher sozinho a partir do
    // usuário autenticado, igual aconteceria numa request de verdade.
    $this->actingAs($this->user);

    $produto->stockMovements()->create([
        'user_id' => $this->user->id,
        'tipo' => MovementType::Entrada,
        'quantidade' => 7,
        'observacao' => 'Compra do fornecedor X',
    ]);

    $this->get(route('products.show', $produto))
        ->assertOk()
        ->assertSee('Compra do fornecedor X');
});
