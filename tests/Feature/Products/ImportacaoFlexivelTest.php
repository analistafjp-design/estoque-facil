<?php

use App\Imports\Support\LeitorDeNumero;
use App\Imports\Support\MapeadorDeColunas;
use App\Livewire\Products\ProductIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A importação precisa aceitar a planilha que o usuário TEM, não só a que
 * o sistema gera: exportação de ERP, do sistema antigo, do fornecedor —
 * cada um nomeia as colunas do seu jeito, coloca título antes do
 * cabeçalho, usa vírgula decimal e inverte a ordem das colunas.
 */
beforeEach(function () {
    $this->company = Company::create(['nome' => 'Empresa Teste']);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
});

/**
 * Gera um .xlsx de verdade a partir de linhas cruas (sem cabeçalho fixo:
 * a primeira linha do array é apenas mais uma linha, porque o objetivo é
 * justamente testar planilhas de formatos variados).
 *
 * UploadedFile::fake()->createWithContent() de propósito: é o que
 * Livewire::set() reconhece como arquivo enviado — um `new UploadedFile`
 * manual não carrega a metadata interna que o componente espera.
 */
function planilha(array $linhas, string $nome): UploadedFile
{
    $export = new class($linhas) implements Maatwebsite\Excel\Concerns\FromArray
    {
        public function __construct(private array $linhas) {}

        public function array(): array
        {
            return $this->linhas;
        }
    };

    $arquivo = uniqid('flex-', true).'.xlsx';
    Excel::store($export, $arquivo, 'local');

    return UploadedFile::fake()->createWithContent(
        $nome,
        file_get_contents(Illuminate\Support\Facades\Storage::disk('local')->path($arquivo))
    );
}

function importar(UploadedFile $arquivo, $user)
{
    return Livewire::actingAs($user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo)
        ->call('importar');
}

it('importa planilha com cabeçalhos de ERP em inglês e ordem trocada', function () {
    $arquivo = planilha([
        ['Quantity', 'Product Name', 'Code', 'Minimum Stock', 'Category'],
        [15, 'Caderno universitário', 'CAD-001', 5, 'Papelaria'],
        [3, 'Caneta azul', 'CAN-002', 10, 'Papelaria'],
    ], 'erp-ingles.xlsx');

    importar($arquivo, $this->user)->assertHasNoErrors();

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(2);

    $caderno = Product::withoutGlobalScopes()->where('sku', 'CAD-001')->first();
    expect($caderno->nome)->toBe('Caderno universitário')
        ->and($caderno->quantidade_atual)->toBe(15)
        ->and($caderno->estoque_minimo)->toBe(5)
        ->and($caderno->categoria)->toBe('Papelaria');
});

it('ignora linhas de título antes do cabeçalho real', function () {
    $arquivo = planilha([
        ['RELATÓRIO DE ESTOQUE — FILIAL CENTRO'],
        ['Emitido em 18/08/2026'],
        [],
        ['Descrição do Produto', 'Ref.', 'Saldo'],
        ['Grampeador', 'GRA-01', 7],
    ], 'com-titulo.xlsx');

    importar($arquivo, $this->user)->assertHasNoErrors();

    $produto = Product::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($produto->nome)->toBe('Grampeador')
        ->and($produto->sku)->toBe('GRA-01')
        ->and($produto->quantidade_atual)->toBe(7);
});

it('entende quantidade escrita com unidade junto', function () {
    // Só valores que continuam texto na planilha. Célula com "1.250" o
    // próprio Excel converte para número na gravação, então esse caminho
    // (string com separador de milhar) é coberto no dataset de
    // LeitorDeNumero, onde dá pra testar a string de verdade.
    $arquivo = planilha([
        ['Item', 'Estoque'],
        ['Clipe', '340 un'],
        ['Papel A4', '1.200 folhas'],
    ], 'numeros-texto.xlsx');

    importar($arquivo, $this->user)->assertHasNoErrors();

    expect(Product::withoutGlobalScopes()->where('nome', 'Clipe')->first()->quantidade_atual)->toBe(340)
        ->and(Product::withoutGlobalScopes()->where('nome', 'Papel A4')->first()->quantidade_atual)->toBe(1200);
});

it('importa mesmo sem coluna de SKU, gerando código a partir do nome', function () {
    $arquivo = planilha([
        ['Produto', 'Qtd'],
        ['Borracha branca', 12],
    ], 'sem-sku.xlsx');

    importar($arquivo, $this->user)->assertHasNoErrors();

    $produto = Product::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($produto->nome)->toBe('Borracha branca')
        ->and($produto->sku)->not->toBeEmpty();
});

it('reimportar sem SKU atualiza pelo nome em vez de duplicar', function () {
    $primeira = planilha([['Produto', 'Qtd'], ['Régua 30cm', 5]], 'reimport-a.xlsx');
    importar($primeira, $this->user)->assertHasNoErrors();

    $segunda = planilha([['Produto', 'Qtd'], ['Régua 30cm', 40]], 'reimport-b.xlsx');
    importar($segunda, $this->user)->assertHasNoErrors();

    $produtos = Product::withoutGlobalScopes()->where('company_id', $this->company->id)->get();
    expect($produtos)->toHaveCount(1)
        ->and($produtos->first()->quantidade_atual)->toBe(40);
});

it('ignora linhas de rodapé sem nome sem abortar a importação', function () {
    $arquivo = planilha([
        ['Produto', 'Qtd'],
        ['Pasta cartão', 9],
        ['', ''],
        [null, 'TOTAL: 9'],
    ], 'com-rodape.xlsx');

    importar($arquivo, $this->user)->assertHasNoErrors();

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(1);
});

it('avisa com mensagem clara quando não há coluna de nome', function () {
    $arquivo = planilha([
        ['Valor', 'Data'],
        [100, '01/01/2026'],
    ], 'sem-nome.xlsx');

    importar($arquivo, $this->user)
        ->assertHasErrors('arquivoImportacao');

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0);
});

it('não deixa a planilha definir a empresa do produto', function () {
    $outra = Company::create(['nome' => 'Empresa Rival']);

    $arquivo = planilha([
        ['Produto', 'Qtd', 'company_id'],
        ['Tesoura', 4, $outra->id],
    ], 'forja-empresa.xlsx');

    importar($arquivo, $this->user)->assertHasNoErrors();

    $produto = Product::withoutGlobalScopes()->where('nome', 'Tesoura')->first();
    expect($produto->company_id)->toBe($this->company->id);
});

it('normaliza cabeçalho ignorando acento, caixa e pontuação', function () {
    $mapeador = new MapeadorDeColunas;

    $mapa = $mapeador->mapear(['ESTOQUE MÍNIMO', 'Descrição', 'Cód.', 'Qtde.']);

    expect($mapa)->toHaveKeys(['estoque_minimo', 'nome', 'sku', 'quantidade_atual'])
        ->and($mapa['estoque_minimo'])->toBe(0)
        ->and($mapa['nome'])->toBe(1);
});

it('não confunde a coluna de estoque mínimo com a de quantidade', function () {
    $mapa = (new MapeadorDeColunas)->mapear(['Produto', 'Estoque', 'Estoque Mínimo']);

    expect($mapa['quantidade_atual'])->toBe(1)
        ->and($mapa['estoque_minimo'])->toBe(2);
});

it('converte formatos numéricos variados', function (mixed $entrada, int $esperado) {
    expect(LeitorDeNumero::paraInteiro($entrada))->toBe($esperado);
})->with([
    ['1.250', 1250],
    ['1,250', 1250],
    ['1.234,56', 1235],
    ['1,234.56', 1235],
    ['12,5', 13],
    ['340 un', 340],
    ['R$ 0', 0],
    ['', 0],
    [null, 0],
    [42, 42],
    [7.4, 7],
]);
