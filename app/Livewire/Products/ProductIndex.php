<?php

namespace App\Livewire\Products;

use App\Imports\ProductsImport;
use App\Imports\Support\AnalisadorDePlanilha;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
class ProductIndex extends Component
{
    use WithFileUploads, WithPagination;

    public string $busca = '';

    public string $filtro = ''; // '' | 'baixo'

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nome = '';

    public string $sku = '';

    public string $categoria = '';

    public int $quantidade_atual = 0;

    public int $estoque_minimo = 0;

    public bool $showImport = false;

    public $arquivoImportacao = null;

    /**
     * Estado do passo de conferência do mapeamento. A detecção automática
     * é só um palpite: quem confirma qual coluna é qual é o usuário, que
     * está com a planilha à vista. Sem esse passo, um cabeçalho ambíguo
     * ("Material" = código no SAP, = descrição em outros) enche o cadastro
     * de produto chamado "1000013" e só se descobre depois.
     */
    public bool $conferindo = false;

    /** @var array<int, string> índice da coluna => rótulo do cabeçalho */
    public array $colunasDaPlanilha = [];

    /** @var array<string, string> campo => índice da coluna (string por vir de <select>) */
    public array $mapa = [];

    /** @var list<array<int, mixed>> primeiras linhas, para o usuário ver o dado real */
    public array $amostra = [];

    public int $linhaCabecalho = 0;

    public int $totalLinhasPlanilha = 0;

    public function mount(): void
    {
        $this->filtro = request()->query('filtro', '') === 'baixo' ? 'baixo' : '';
    }

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'nome', 'sku', 'categoria', 'quantidade_atual', 'estoque_minimo']);
        $this->showForm = true;
    }

    public function edit(int $productId): void
    {
        // find() aqui já vem filtrado pelo CompanyScope — se o id for de
        // outra empresa (alguém tentando adivinhar/editar a URL), o
        // Eloquent simplesmente não encontra nada e devolve 404.
        $produto = Product::findOrFail($productId);

        $this->editingId = $produto->id;
        $this->nome = $produto->nome;
        $this->sku = $produto->sku;
        $this->categoria = (string) $produto->categoria;
        $this->quantidade_atual = $produto->quantidade_atual;
        $this->estoque_minimo = $produto->estoque_minimo;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where('company_id', Auth::user()->company_id)
                    ->ignore($this->editingId),
            ],
            'categoria' => ['nullable', 'string', 'max:255'],
            'quantidade_atual' => ['required', 'integer', 'min:0'],
            'estoque_minimo' => ['required', 'integer', 'min:0'],
        ]);
        $validated['categoria'] = $validated['categoria'] ?: null;

        if ($this->editingId) {
            Product::findOrFail($this->editingId)->update($validated);
        } else {
            // company_id não entra aqui: BelongsToCompany preenche sozinho
            // a partir do usuário logado.
            Product::create($validated);
        }

        $this->showForm = false;
        $this->reset(['editingId', 'nome', 'sku', 'categoria', 'quantidade_atual', 'estoque_minimo']);
        session()->flash('status', 'Produto salvo.');
    }

    public function desativar(int $productId): void
    {
        Product::findOrFail($productId)->delete();
        session()->flash('status', 'Produto desativado.');
    }

    /**
     * Assim que o upload termina, lê a planilha e monta a conferência —
     * sem esperar clique, porque conferir o mapeamento É o passo, não um
     * extra opcional.
     */
    public function updatedArquivoImportacao(): void
    {
        $this->resetErrorBag('arquivoImportacao');
        $this->conferindo = false;

        if (! $this->validarArquivo()) {
            return;
        }

        try {
            $analise = (new AnalisadorDePlanilha)->analisar($this->arquivoImportacao->getRealPath());
        } catch (Throwable $e) {
            $this->addError('arquivoImportacao', 'Não foi possível ler a planilha: '.$e->getMessage());

            return;
        }

        $this->colunasDaPlanilha = $analise['colunas'];
        $this->amostra = $analise['amostra'];
        $this->linhaCabecalho = $analise['linhaCabecalho'];
        $this->totalLinhasPlanilha = $analise['totalLinhas'];

        // <select> devolve string; guardar já como string evita o valor
        // selecionado "sumir" na comparação do Blade.
        $this->mapa = [];
        foreach (['nome', 'sku', 'categoria', 'quantidade_atual', 'estoque_minimo'] as $campo) {
            $this->mapa[$campo] = isset($analise['mapa'][$campo]) ? (string) $analise['mapa'][$campo] : '';
        }

        $this->conferindo = true;
    }

    public function importar(): void
    {
        if (! $this->validarArquivo()) {
            return;
        }

        if (($this->mapa['nome'] ?? '') === '') {
            $this->addError('mapa.nome', 'Escolha qual coluna tem o nome do produto.');

            return;
        }

        $mapa = [];
        foreach ($this->mapa as $campo => $indice) {
            if ($indice !== '') {
                $mapa[$campo] = (int) $indice;
            }
        }

        $importacao = new ProductsImport($mapa, $this->linhaCabecalho);

        try {
            Excel::import($importacao, $this->arquivoImportacao->getRealPath());
        } catch (RuntimeException $e) {
            $this->addError('arquivoImportacao', $e->getMessage());

            return;
        }

        $this->showImport = false;
        $this->conferindo = false;
        $this->reset(['arquivoImportacao', 'mapa', 'amostra', 'colunasDaPlanilha']);
        session()->flash('status', $this->resumoDaImportacao($importacao));
    }

    /**
     * Mensagens explícitas em português: a padrão do Laravel sairia como
     * "The arquivo importacao field is required", que além de inglês nomeia
     * a propriedade interna e não diz o que fazer. O caso "required" aqui
     * quase nunca é "esqueci de escolher" — é ter clicado antes do upload
     * terminar, então a mensagem aponta justamente para isso.
     */
    private function validarArquivo(): bool
    {
        try {
            $this->validate([
                'arquivoImportacao' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            ], [
                'arquivoImportacao.required' => 'Escolha uma planilha e aguarde o envio terminar antes de importar.',
                'arquivoImportacao.file' => 'O envio do arquivo não foi concluído. Escolha a planilha novamente.',
                'arquivoImportacao.mimes' => 'Formato não suportado. Envie um arquivo .xlsx, .xls ou .csv.',
                'arquivoImportacao.max' => 'A planilha passa de 5 MB. Divida em arquivos menores e importe um por vez.',
            ]);
        } catch (ValidationException $e) {
            // validate() preenche o error bag e lança; capturando aqui para
            // não abortar a ação, o bag precisa ser reposto na mão — sem
            // isso a tela ficaria sem nenhuma mensagem, só sem reagir.
            $this->setErrorBag($e->validator->errors());

            return false;
        }

        return true;
    }

    /**
     * Conta o que aconteceu e diz quais colunas foram reconhecidas. Como a
     * planilha é de formato livre, o usuário precisa conseguir conferir se
     * o sistema entendeu as colunas como ele esperava — sem isso, uma
     * coluna lida errado só apareceria muito depois, como estoque furado.
     */
    private function resumoDaImportacao(ProductsImport $importacao): string
    {
        $partes = [];

        if ($importacao->criados) {
            $partes[] = $importacao->criados.' '.($importacao->criados === 1 ? 'produto criado' : 'produtos criados');
        }

        if ($importacao->atualizados) {
            $partes[] = $importacao->atualizados.' '.($importacao->atualizados === 1 ? 'atualizado' : 'atualizados');
        }

        if ($importacao->ignorados) {
            $partes[] = $importacao->ignorados.' '.($importacao->ignorados === 1 ? 'linha ignorada' : 'linhas ignoradas');
        }

        $rotulos = [
            'nome' => 'nome',
            'sku' => 'SKU',
            'categoria' => 'categoria',
            'quantidade_atual' => 'quantidade',
            'estoque_minimo' => 'estoque mínimo',
        ];

        $detectadas = array_values(array_intersect_key($rotulos, $importacao->colunasDetectadas));

        return ($partes ? implode(', ', $partes) : 'Nenhuma linha importada')
            .'. Colunas reconhecidas: '.implode(', ', $detectadas).'.';
    }

    public function render(): View
    {
        $produtos = Product::query()
            ->when($this->busca, function ($query) {
                $termo = '%'.$this->busca.'%';
                $query->where(fn ($q) => $q->where('nome', 'like', $termo)->orWhere('sku', 'like', $termo));
            })
            ->when($this->filtro === 'baixo', fn ($query) => $query->estoqueBaixo())
            ->orderBy('nome')
            ->paginate(15);

        return view('livewire.products.product-index', ['produtos' => $produtos]);
    }
}
