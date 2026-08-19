<?php

namespace App\Livewire\Categories;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Renomear categorias em lote.
 *
 * Planilha de ERP traz a categoria como CÓDIGO ("500292000"), porque a
 * descrição costuma morar noutra tabela que não vem na exportação. Sem
 * isso, o cadastro e o dashboard ficam cheios de número que não diz nada
 * a quem opera. Aqui a pessoa nomeia o código uma vez e todos os produtos
 * daquele grupo passam a mostrar o nome.
 *
 * Renomear para um nome que já existe funde as duas categorias — é o
 * comportamento útil (juntar "Químicos" e "QUIMICOS"), não um acidente.
 */
#[Layout('layouts.app')]
class CategoryIndex extends Component
{
    use WithPagination;

    public string $busca = '';

    /** Categoria em edição (o valor atual, que serve de identificador). */
    public ?string $editando = null;

    public string $novoNome = '';

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    public function editar(string $categoria): void
    {
        $this->editando = $categoria;
        $this->novoNome = $categoria;
        $this->resetErrorBag();
    }

    public function cancelar(): void
    {
        $this->editando = null;
        $this->novoNome = '';
    }

    public function salvar(): void
    {
        $this->validate([
            'novoNome' => ['required', 'string', 'max:255'],
        ], [
            'novoNome.required' => 'Digite um nome para a categoria.',
            'novoNome.max' => 'O nome da categoria passa de 255 caracteres.',
        ]);

        $antiga = $this->editando;

        if ($antiga === null) {
            return;
        }

        $nova = trim($this->novoNome);

        if ($nova !== $antiga) {
            // Sem where('company_id') à mão: o CompanyScope já limita o
            // update à empresa de quem está logado.
            Product::where('categoria', $antiga)->update(['categoria' => $nova]);
        }

        $this->cancelar();
        session()->flash('status', 'Categoria renomeada.');
    }

    public function render(): View
    {
        $categorias = Product::query()
            ->select('categoria', DB::raw('COUNT(*) AS total'), DB::raw('SUM(quantidade_atual) AS itens'))
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->when($this->busca !== '', fn ($q) => $q->where('categoria', 'like', '%'.$this->busca.'%'))
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->paginate(20);

        return view('livewire.categories.category-index', [
            'categorias' => $categorias,
            'semCategoria' => Product::query()
                ->where(fn ($q) => $q->whereNull('categoria')->orWhere('categoria', ''))
                ->count(),
        ]);
    }
}
