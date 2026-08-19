<?php

use App\Enums\UserRole;
use App\Exports\ProductImportTemplateExport;
use App\Livewire\Products\ProductIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/**
 * O modelo é o formato "oficial" de importação. O teste que importa é o
 * de ida e volta: baixar o modelo e reimportá-lo tem que funcionar sem
 * ajuste nenhum — se um dia alguém renomear uma coluna do modelo sem
 * mexer nos sinônimos do importador, é aqui que quebra.
 */
beforeEach(function () {
    $this->company = Company::create(['nome' => 'Empresa Teste']);
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'papel' => UserRole::Admin,
    ]);
});

function modeloBaixado(): string
{
    $nome = uniqid('modelo-', true).'.xlsx';
    Excel::store(new ProductImportTemplateExport, $nome, 'local');

    return Storage::disk('local')->path($nome);
}

it('gera o modelo com duas abas: dados primeiro, instruções depois', function () {
    $abas = Excel::toArray(new class implements Maatwebsite\Excel\Concerns\Import {}, modeloBaixado());

    expect($abas)->toHaveCount(2)
        // A aba de dados PRECISA ser a primeira: é a que o importador lê.
        ->and($abas[0][0])->toBe([
            'Nome do produto', 'Código (SKU)', 'Categoria',
            'Quantidade atual', 'Estoque mínimo',
        ])
        ->and($abas[1][0])->toBe(['Coluna', 'Obrigatória?', 'O que preencher']);
});

it('reimporta o próprio modelo sem nenhum ajuste', function () {
    $caminho = modeloBaixado();
    $arquivo = UploadedFile::fake()->createWithContent('modelo.xlsx', file_get_contents($caminho));

    $componente = Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo);

    // Todas as cinco colunas do modelo têm que ser reconhecidas sozinhas.
    $mapa = $componente->get('mapa');
    expect($mapa['nome'])->toBe('0')
        ->and($mapa['sku'])->toBe('1')
        ->and($mapa['categoria'])->toBe('2')
        ->and($mapa['quantidade_atual'])->toBe('3')
        ->and($mapa['estoque_minimo'])->toBe('4');

    $componente->call('importar')->assertHasNoErrors();

    $produto = Product::withoutGlobalScopes()->where('sku', 'PAR-0001')->first();

    expect($produto)->not->toBeNull()
        ->and($produto->nome)->toBe('Parafuso sextavado 3/8 x 2"')
        ->and($produto->categoria)->toBe('Fixação')
        ->and($produto->quantidade_atual)->toBe(250)
        ->and($produto->estoque_minimo)->toBe(50);
});

it('a aba de instruções não vira produto', function () {
    $caminho = modeloBaixado();
    $arquivo = UploadedFile::fake()->createWithContent('modelo.xlsx', file_get_contents($caminho));

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo)
        ->call('importar');

    // Só as 3 linhas de exemplo da aba "Produtos" — nada da aba de texto.
    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(3)
        ->and(Product::withoutGlobalScopes()->pluck('nome')->all())
        ->not->toContain('Nome do produto');
});

it('a rota do modelo devolve um arquivo para download', function () {
    $this->actingAs($this->user)
        ->get(route('products.import-template'))
        ->assertOk()
        ->assertDownload('modelo-importacao-produtos.xlsx');
});
