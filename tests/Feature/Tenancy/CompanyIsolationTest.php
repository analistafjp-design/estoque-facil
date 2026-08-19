<?php

/**
 * O requisito não-negociável da spec: usuário da Empresa A nunca lê,
 * edita ou apaga dado da Empresa B — nem manipulando o ID na URL. Este
 * arquivo é a prova disso, tabela por tabela (products, stock_movements,
 * invitations) e camada por camada (Eloquent puro e HTTP fim-a-fim).
 */

use App\Models\Company;
use App\Models\Invitation;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function duasEmpresas(): array
{
    $empresaA = Company::factory()->create(['nome' => 'Empresa A']);
    $empresaB = Company::factory()->create(['nome' => 'Empresa B']);

    $usuarioA = User::factory()->admin()->create(['company_id' => $empresaA->id]);
    $usuarioB = User::factory()->admin()->create(['company_id' => $empresaB->id]);

    return [$empresaA, $empresaB, $usuarioA, $usuarioB];
}

// -------------------------------------------------------------- Eloquent

test('CompanyScope filtra Product no nível do Eloquent, sem where manual', function () {
    [$empresaA, $empresaB, $usuarioA] = duasEmpresas();

    $produtoA = Product::factory()->create(['company_id' => $empresaA->id]);
    Product::factory()->create(['company_id' => $empresaB->id]);

    Auth::login($usuarioA);

    $visiveis = Product::all();

    expect($visiveis)->toHaveCount(1)
        ->and($visiveis->first()->id)->toBe($produtoA->id);

    // find() de um id que existe, só que na outra empresa: não acha.
    $produtoDeB = Product::withoutGlobalScopes()->where('company_id', $empresaB->id)->first();
    expect(Product::find($produtoDeB->id))->toBeNull();
});

test('criar Product sem informar company_id usa a empresa do usuário logado', function () {
    [$empresaA, , $usuarioA] = duasEmpresas();

    Auth::login($usuarioA);

    $produto = Product::create([
        'nome' => 'Produto novo',
        'sku' => 'NEW-001',
        'quantidade_atual' => 10,
        'estoque_minimo' => 2,
    ]);

    expect($produto->company_id)->toBe($empresaA->id);
});

test('sem usuário logado, CompanyScope não filtra nada (uso administrativo/CLI)', function () {
    [$empresaA, $empresaB] = duasEmpresas();
    Product::factory()->create(['company_id' => $empresaA->id]);
    Product::factory()->create(['company_id' => $empresaB->id]);

    expect(Product::count())->toBe(2);
});

// ------------------------------------------------------------------ HTTP

test('produto de outra empresa não aparece na listagem', function () {
    [$empresaA, $empresaB, $usuarioA] = duasEmpresas();

    Product::factory()->create(['company_id' => $empresaA->id, 'nome' => 'Produto da Empresa A']);
    Product::factory()->create(['company_id' => $empresaB->id, 'nome' => 'Produto da Empresa B']);

    $this->actingAs($usuarioA)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Produto da Empresa A')
        ->assertDontSee('Produto da Empresa B');
});

test('abrir a ficha de um produto de outra empresa pela URL devolve 404, não os dados', function () {
    [, $empresaB, $usuarioA] = duasEmpresas();

    $produtoDeB = Product::factory()->create(['company_id' => $empresaB->id]);

    $this->actingAs($usuarioA)
        ->get(route('products.show', $produtoDeB))
        ->assertNotFound();
});

// findOrFail() dentro de save()/desativar()/cancelar() já usa o Product
// (ou Invitation) com CompanyScope aplicado — um id de outra empresa não
// "erra o dado", ele nem é encontrado: ModelNotFoundException, a mesma
// exceção que o Laravel converte em 404 numa request HTTP de verdade.
// É esse throw, não uma mensagem de validação, que prova o isolamento.
test('editar produto de outra empresa via Livewire não altera o registro', function () {
    [, $empresaB, $usuarioA] = duasEmpresas();

    $produtoDeB = Product::factory()->create(['company_id' => $empresaB->id, 'nome' => 'Original']);

    $tentativa = fn () => \Livewire\Livewire::actingAs($usuarioA)
        ->test(\App\Livewire\Products\ProductIndex::class)
        ->set('editingId', $produtoDeB->id)
        ->set('nome', 'Nome forjado pela Empresa A')
        ->set('sku', $produtoDeB->sku)
        ->set('quantidade_atual', 0)
        ->set('estoque_minimo', 0)
        ->call('save');

    expect($tentativa)->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($produtoDeB->fresh()->nome)->toBe('Original');
});

test('desativar produto de outra empresa por id forjado não move nada', function () {
    [, $empresaB, $usuarioA] = duasEmpresas();

    $produtoDeB = Product::factory()->create(['company_id' => $empresaB->id]);

    $tentativa = fn () => \Livewire\Livewire::actingAs($usuarioA)
        ->test(\App\Livewire\Products\ProductIndex::class)
        ->call('desativar', $produtoDeB->id);

    expect($tentativa)->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($produtoDeB->fresh()->trashed())->toBeFalse();
});

// Não existe um teste separado de "lançar movimentação em produto de
// outra empresa" via Livewire::test(ProductShow::class, ['product' => ...])
// de propósito: passar o model direto pro test() pula o route model
// binding, que é exatamente a camada que bloqueia isso (ver o teste de
// 404 na ficha do produto, acima). Testar dessa forma não provaria nada
// que o 404 da rota já não prove.

test('histórico de movimentações não mistura empresas', function () {
    [$empresaA, $empresaB, $usuarioA] = duasEmpresas();

    $produtoA = Product::factory()->create(['company_id' => $empresaA->id]);
    $produtoB = Product::factory()->create(['company_id' => $empresaB->id]);

    StockMovement::factory()->create([
        'company_id' => $empresaA->id,
        'product_id' => $produtoA->id,
        'observacao' => 'Movimento da Empresa A',
    ]);
    StockMovement::factory()->create([
        'company_id' => $empresaB->id,
        'product_id' => $produtoB->id,
        'observacao' => 'Movimento da Empresa B',
    ]);

    $this->actingAs($usuarioA);

    expect(StockMovement::count())->toBe(1)
        ->and(StockMovement::first()->observacao)->toBe('Movimento da Empresa A');
});

test('convite de outra empresa não aparece na listagem nem pode ser cancelado por id forjado', function () {
    [, $empresaB, $usuarioA] = duasEmpresas();

    $conviteDeB = Invitation::factory()->create(['company_id' => $empresaB->id, 'email' => 'alguem@empresab.com']);

    $this->actingAs($usuarioA)
        ->get(route('invitations.index'))
        ->assertOk()
        ->assertDontSee('alguem@empresab.com');

    $tentativa = fn () => \Livewire\Livewire::actingAs($usuarioA)
        ->test(\App\Livewire\Invitations\InvitationIndex::class)
        ->call('cancelar', $conviteDeB->id);

    expect($tentativa)->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($conviteDeB->fresh()->status)->toBe(\App\Enums\InvitationStatus::Pendente);
});

test('exportação de estoque só inclui produtos da própria empresa', function () {
    [$empresaA, $empresaB, $usuarioA] = duasEmpresas();

    Product::factory()->create(['company_id' => $empresaA->id, 'nome' => 'Visível']);
    Product::factory()->create(['company_id' => $empresaB->id, 'nome' => 'Não deveria aparecer']);

    $export = new \App\Exports\ProductsExport;

    $this->actingAs($usuarioA);

    $nomes = $export->collection()->pluck('nome');

    expect($nomes)->toContain('Visível')
        ->and($nomes)->not->toContain('Não deveria aparecer');
});
