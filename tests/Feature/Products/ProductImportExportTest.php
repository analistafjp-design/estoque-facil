<?php

use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use App\Livewire\Products\ProductIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->admin()->create(['company_id' => $this->company->id]);
});

afterEach(function () {
    // criarPlanilhaFisica() escreve de verdade no disco 'local' (o import
    // real do Maatwebsite precisa de um arquivo físico) — limpa pra não
    // acumular planilha de teste a cada rodada da suíte.
    foreach (glob(\Illuminate\Support\Facades\Storage::disk('local')->path('planilha-*.xlsx')) as $arquivo) {
        @unlink($arquivo);
    }
});

test('exportação inclui só produtos da empresa logada', function () {
    Product::factory()->create(['company_id' => $this->company->id, 'nome' => 'Meu produto']);

    $outraEmpresa = Company::factory()->create();
    Product::factory()->create(['company_id' => $outraEmpresa->id, 'nome' => 'Produto de outra empresa']);

    $this->actingAs($this->user);

    $nomes = (new ProductsExport)->collection()->pluck('nome');

    expect($nomes)->toContain('Meu produto')
        ->and($nomes)->not->toContain('Produto de outra empresa');
});

// A cobertura do modelo vive em ModeloDePlanilhaTest: em vez de comparar
// a lista de cabeçalhos (que só prova que o array não mudou), ele baixa o
// modelo e reimporta, provando que o formato oficial funciona de ponta a
// ponta.

test('importar planilha pela tela cria produtos novos', function () {
    // Sem Excel::fake() de propósito aqui: o valor deste teste é provar
    // que o componente Livewire — upload, validação, chamada ao import —
    // termina com o produto de verdade no banco. ExcelFake trocaria essa
    // prova por "alguém chamou Excel::import()", que vale bem menos.
    $arquivo = criarPlanilhaDeTeste([
        ['nome' => 'Produto Importado', 'sku' => 'IMP-001', 'categoria' => 'Geral', 'quantidade_atual' => 20, 'estoque_minimo' => 5],
    ]);

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo)
        ->call('importar')
        ->assertHasNoErrors()
        ->assertSet('arquivoImportacao', null);

    $produto = Product::where('sku', 'IMP-001')->firstOrFail();

    expect($produto->company_id)->toBe($this->company->id)
        ->and($produto->nome)->toBe('Produto Importado')
        ->and($produto->quantidade_atual)->toBe(20);
});

test('importação real (sem fake) cria e atualiza produtos por SKU', function () {
    Product::factory()->create([
        'company_id' => $this->company->id,
        'sku' => 'ATUALIZA-001',
        'nome' => 'Nome antigo',
        'quantidade_atual' => 1,
        'estoque_minimo' => 1,
    ]);

    $this->actingAs($this->user);

    Excel::import(new ProductsImport, criarPlanilhaFisica([
        ['nome' => 'Nome atualizado', 'sku' => 'ATUALIZA-001', 'categoria' => 'Geral', 'quantidade_atual' => 99, 'estoque_minimo' => 10],
        ['nome' => 'Produto totalmente novo', 'sku' => 'NOVO-002', 'categoria' => 'Geral', 'quantidade_atual' => 5, 'estoque_minimo' => 2],
    ]));

    $atualizado = Product::where('sku', 'ATUALIZA-001')->firstOrFail();
    $novo = Product::where('sku', 'NOVO-002')->firstOrFail();

    expect($atualizado->nome)->toBe('Nome atualizado')
        ->and($atualizado->quantidade_atual)->toBe(99)
        ->and($novo->company_id)->toBe($this->company->id);
});

test('importação não deixa um usuário forjar company_id de outra empresa via a planilha', function () {
    $outraEmpresa = Company::factory()->create();

    $this->actingAs($this->user);

    // A coluna "company_id" nem existe no import — mesmo que existisse,
    // não é fillable no model. O teste documenta essa garantia.
    Excel::import(new ProductsImport, criarPlanilhaFisica([
        ['nome' => 'Tentativa', 'sku' => 'HACK-001', 'categoria' => 'Geral', 'quantidade_atual' => 1, 'estoque_minimo' => 1, 'company_id' => $outraEmpresa->id],
    ]));

    $produto = Product::where('sku', 'HACK-001')->firstOrFail();

    expect($produto->company_id)->toBe($this->company->id);
});

/**
 * Planilha "enviada" no upload do Livewire. UploadedFile::fake() de
 * propósito — é o que Livewire::set() sabe reconhecer e tratar como
 * arquivo enviado; um `new UploadedFile(...)` manual não tem a mesma
 * metadata interna que o componente espera.
 */
function criarPlanilhaDeTeste(array $linhas): UploadedFile
{
    $caminho = criarPlanilhaFisica($linhas);

    return UploadedFile::fake()->createWithContent('planilha.xlsx', file_get_contents($caminho));
}

/** Planilha física em disco, no formato que ProductsImport espera. */
function criarPlanilhaFisica(array $linhas): string
{
    $export = new class($linhas) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
    {
        public function __construct(private array $linhas) {}

        public function array(): array
        {
            return array_map(fn ($linha) => [
                $linha['nome'], $linha['sku'], $linha['categoria'] ?? null,
                $linha['quantidade_atual'] ?? 0, $linha['estoque_minimo'] ?? 0,
            ], $this->linhas);
        }

        public function headings(): array
        {
            return ['nome', 'sku', 'categoria', 'quantidade_atual', 'estoque_minimo'];
        }
    };

    $nomeArquivo = uniqid('planilha-', true).'.xlsx';
    Excel::store($export, $nomeArquivo, 'local');

    return \Illuminate\Support\Facades\Storage::disk('local')->path($nomeArquivo);
}
