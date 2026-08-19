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

it('pede para escolher a coluna de nome quando não reconhece nenhuma', function () {
    // Sem cabeçalho reconhecível o arquivo não é recusado: a conferência
    // abre assim mesmo, com as colunas disponíveis, e o usuário indica qual
    // é o nome. Recusar seria pior — a planilha pode estar perfeita, só com
    // cabeçalho que o sistema não conhece.
    $arquivo = planilha([
        ['Valor', 'Data'],
        [100, '01/01/2026'],
    ], 'sem-nome.xlsx');

    $componente = Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo);

    expect($componente->get('conferindo'))->toBeTrue()
        ->and($componente->get('mapa')['nome'])->toBe('');

    $componente->call('importar')->assertHasErrors('mapa.nome');

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

it('explica em português quando o arquivo ainda não chegou', function () {
    // Cenário real: usuário clica em Importar antes do upload terminar
    // (conexão lenta). A propriedade chega vazia, e a mensagem precisa
    // dizer o que fazer em vez do "field is required" padrão em inglês.
    $erro = Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->call('importar')
        ->errors()
        ->first('arquivoImportacao');

    // Asserção pela MENSAGEM, não pelo nome da regra: o error bag é
    // reposto à mão em validarArquivo(), e nesse caminho o metadado da
    // regra não sobrevive — a mensagem, que é o que o usuário lê, sim.
    expect($erro)->toContain('aguarde o envio')
        ->and($erro)->not->toContain('field is required');
});

it('recusa arquivo que não é planilha, com mensagem em português', function () {
    $naoPlanilha = UploadedFile::fake()->createWithContent('foto.png', 'conteudo qualquer');

    $erro = Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $naoPlanilha)
        ->call('importar')
        ->errors()
        ->first('arquivoImportacao');

    expect($erro)->toContain('.xlsx');
});

/**
 * Regressão do caso real que sujou o cadastro do usuário: exportação de
 * ERP onde "Material" é o CÓDIGO (1000013) e a descrição está noutra
 * coluna. Só pelo nome do cabeçalho, "Material" parece nome de produto —
 * é o conteúdo (coluna 100% numérica) que denuncia.
 */
it('não usa coluna de código como nome, mesmo com cabeçalho enganoso', function () {
    $arquivo = planilha([
        ['Material', 'Texto breve material', 'Grp.mercadorias', 'Estoque disponivel', 'Estoque minimo'],
        [1000013, 'PARAFUSO SEXTAVADO M8', 100201000, 250, 50],
        [1000024, 'PORCA SEXTAVADA M8', 100201000, 180, 40],
    ], 'sap.xlsx');

    $componente = Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo);

    // A coluna numérica virou SKU; a descrição virou nome.
    expect($componente->get('mapa')['nome'])->toBe('1')
        ->and($componente->get('mapa')['sku'])->toBe('0');

    $componente->call('importar')->assertHasNoErrors();

    $produto = Product::withoutGlobalScopes()->where('sku', '1000013')->first();
    expect($produto->nome)->toBe('PARAFUSO SEXTAVADO M8')
        ->and($produto->quantidade_atual)->toBe(250);
});

it('abre a conferência com colunas, prévia e total de linhas', function () {
    $arquivo = planilha([
        ['Produto', 'Qtd'],
        ['Caneta', 10],
        ['Lápis', 20],
    ], 'conferencia.xlsx');

    $componente = Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo);

    expect($componente->get('conferindo'))->toBeTrue()
        ->and($componente->get('colunasDaPlanilha'))->toBe([0 => 'Produto', 1 => 'Qtd'])
        ->and($componente->get('totalLinhasPlanilha'))->toBe(2)
        ->and($componente->get('amostra'))->toHaveCount(2);
});

it('respeita o mapeamento corrigido à mão pelo usuário', function () {
    // Detecção automática colocaria "Referencia" como nome; o usuário
    // corrige para a segunda coluna antes de importar.
    $arquivo = planilha([
        ['Referencia', 'Especificacao tecnica', 'Qtd'],
        ['ABC-9', 'Cabo flexível 2,5mm', 44],
    ], 'manual.xlsx');

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo)
        ->set('mapa.nome', '1')
        ->set('mapa.sku', '0')
        ->set('mapa.quantidade_atual', '2')
        ->call('importar')
        ->assertHasNoErrors();

    $produto = Product::withoutGlobalScopes()->where('sku', 'ABC-9')->first();
    expect($produto->nome)->toBe('Cabo flexível 2,5mm')
        ->and($produto->quantidade_atual)->toBe(44);
});

it('exige que o usuário escolha a coluna de nome', function () {
    $arquivo = planilha([
        ['Produto', 'Qtd'],
        ['Caneta', 10],
    ], 'sem-nome-escolhido.xlsx');

    Livewire::actingAs($this->user)
        ->test(ProductIndex::class)
        ->set('arquivoImportacao', $arquivo)
        ->set('mapa.nome', '')
        ->call('importar')
        ->assertHasErrors('mapa.nome');

    expect(Product::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0);
});
