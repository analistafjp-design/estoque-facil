<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(): View
    {
        // Product/StockMovement já saem filtrados por empresa sozinhos —
        // é o CompanyScope (BelongsToCompany) entrando aqui, não um
        // ->where('company_id', ...) escrito à mão.
        return view('livewire.dashboard', [
            'totalProdutos' => Product::count(),
            'semEstoque' => Product::semEstoque()->count(),
            'abaixoDoMinimo' => Product::abaixoDoMinimo()->count(),
            'totalEmEstoque' => (int) Product::sum('quantidade_atual'),

            // Produto sem mínimo definido nunca dispara o alerta (a regra é
            // quantidade <= mínimo). Importação de planilha sem coluna de
            // mínimo deixa TODO o cadastro assim — e sem este número a tela
            // pareceria dizer "está tudo bem" quando o alerta está desligado.
            'semMinimoDefinido' => Product::where('estoque_minimo', 0)->count(),

            'faixas' => $this->distribuicaoPorFaixa(),
            'categorias' => $this->principaisCategorias(),

            'precisaAtencao' => Product::query()
                ->where(fn ($q) => $q->semEstoque()->orWhere(
                    fn ($sub) => $sub->where('estoque_minimo', '>', 0)
                        ->whereColumn('quantidade_atual', '<=', 'estoque_minimo')
                ))
                ->orderBy('quantidade_atual')
                ->limit(6)
                ->get(),

            'recemCadastrados' => Product::latest('id')->limit(6)->get(),

            'movimentacoesRecentes' => StockMovement::with(['product', 'user'])
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }

    /**
     * Quantos produtos há em cada faixa de saldo.
     *
     * Uma consulta só, com CASE, em vez de quatro COUNT: com milhares de
     * produtos a diferença aparece, e a sintaxe funciona igual em MySQL
     * (produção) e SQLite (testes).
     *
     * @return array<string, int>
     */
    private function distribuicaoPorFaixa(): array
    {
        $linha = Product::query()
            ->selectRaw('SUM(CASE WHEN quantidade_atual <= 0 THEN 1 ELSE 0 END) AS zerado')
            ->selectRaw('SUM(CASE WHEN quantidade_atual BETWEEN 1 AND 9 THEN 1 ELSE 0 END) AS pouco')
            ->selectRaw('SUM(CASE WHEN quantidade_atual BETWEEN 10 AND 99 THEN 1 ELSE 0 END) AS medio')
            ->selectRaw('SUM(CASE WHEN quantidade_atual >= 100 THEN 1 ELSE 0 END) AS muito')
            ->first();

        return [
            'Sem estoque' => (int) ($linha->zerado ?? 0),
            '1 a 9' => (int) ($linha->pouco ?? 0),
            '10 a 99' => (int) ($linha->medio ?? 0),
            '100 ou mais' => (int) ($linha->muito ?? 0),
        ];
    }

    /**
     * Categorias com mais produtos. Produto sem categoria fica de fora da
     * lista, mas é contado à parte — depois de importar planilha sem essa
     * coluna, "nenhuma categoria" é a informação mais honesta a dar.
     *
     * @return array{itens: \Illuminate\Support\Collection<int, object>, semCategoria: int}
     */
    private function principaisCategorias(): array
    {
        return [
            'itens' => Product::query()
                ->select('categoria', DB::raw('COUNT(*) AS total'))
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->groupBy('categoria')
                ->orderByDesc('total')
                ->limit(6)
                ->get(),
            'semCategoria' => Product::query()
                ->where(fn ($q) => $q->whereNull('categoria')->orWhere('categoria', ''))
                ->count(),
        ];
    }
}
