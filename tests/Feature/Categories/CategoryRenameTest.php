<?php

use App\Livewire\Categories\CategoryIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/**
 * Exportação de ERP traz a categoria como código ("500292000") porque a
 * descrição mora noutra tabela que não vem no arquivo. Renomear dentro do
 * app é o que transforma isso em informação legível — e precisa valer para
 * todos os produtos do grupo de uma vez.
 */
beforeEach(function () {
    $this->company = Company::create(['nome' => 'Minha Empresa']);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
});

it('lista categorias com contagem de produtos e de itens', function () {
    Product::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'categoria' => '500292000',
        'quantidade_atual' => 10,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'categoria' => '700012000',
        'quantidade_atual' => 5,
    ]);

    Livewire::actingAs($this->user)
        ->test(CategoryIndex::class)
        ->assertSee('500292000')
        ->assertSee('700012000')
        ->assertViewHas('categorias', function ($categorias) {
            $primeira = $categorias->first();

            return $primeira->categoria === '500292000'
                && (int) $primeira->total === 3
                && (int) $primeira->itens === 30;
        });
});

it('renomear atinge todos os produtos daquela categoria', function () {
    Product::factory()->count(4)->create([
        'company_id' => $this->company->id,
        'categoria' => '500292000',
    ]);

    Livewire::actingAs($this->user)
        ->test(CategoryIndex::class)
        ->call('editar', '500292000')
        ->set('novoNome', 'Produtos Químicos')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Product::where('categoria', 'Produtos Químicos')->count())->toBe(4)
        ->and(Product::where('categoria', '500292000')->count())->toBe(0);
});

it('renomear para um nome existente funde as categorias', function () {
    Product::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'categoria' => 'QUIMICOS',
    ]);

    Product::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'categoria' => 'Químicos',
    ]);

    Livewire::actingAs($this->user)
        ->test(CategoryIndex::class)
        ->call('editar', 'QUIMICOS')
        ->set('novoNome', 'Químicos')
        ->call('salvar');

    expect(Product::where('categoria', 'Químicos')->count())->toBe(5)
        ->and(Product::where('categoria', 'QUIMICOS')->count())->toBe(0);
});

it('exige um nome ao renomear', function () {
    Product::factory()->create([
        'company_id' => $this->company->id,
        'categoria' => '500292000',
    ]);

    Livewire::actingAs($this->user)
        ->test(CategoryIndex::class)
        ->call('editar', '500292000')
        ->set('novoNome', '')
        ->call('salvar')
        ->assertHasErrors('novoNome');

    expect(Product::where('categoria', '500292000')->count())->toBe(1);
});

it('renomear nunca alcança produto de outra empresa', function () {
    $outra = Company::create(['nome' => 'Empresa Vizinha']);

    $alheio = Product::factory()->create([
        'company_id' => $outra->id,
        'categoria' => '500292000',
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'categoria' => '500292000',
    ]);

    Livewire::actingAs($this->user)
        ->test(CategoryIndex::class)
        ->call('editar', '500292000')
        ->set('novoNome', 'Renomeada')
        ->call('salvar');

    // fresh() ignora global scopes, então lê o registro alheio de verdade.
    expect($alheio->fresh()->categoria)->toBe('500292000');
});

it('não lista categoria de outra empresa', function () {
    $outra = Company::create(['nome' => 'Empresa Vizinha']);

    Product::factory()->create([
        'company_id' => $outra->id,
        'categoria' => 'SEGREDO-ALHEIO',
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'categoria' => 'MINHA',
    ]);

    Livewire::actingAs($this->user)
        ->test(CategoryIndex::class)
        ->assertSee('MINHA')
        ->assertDontSee('SEGREDO-ALHEIO');
});
