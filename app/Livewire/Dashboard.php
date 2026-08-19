<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
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
            'estoqueBaixo' => Product::estoqueBaixo()->orderBy('quantidade_atual')->limit(8)->get(),
            'totalEstoqueBaixo' => Product::estoqueBaixo()->count(),
            // Produto sem mínimo definido nunca dispara o alerta (a regra é
            // quantidade <= mínimo, e com mínimo 0 só entra quem zerou).
            // Importação de planilha sem coluna de mínimo deixa TODO o
            // cadastro assim — e sem este número a tela pareceria dizer
            // "está tudo bem" quando na verdade o alerta está desligado.
            'semMinimoDefinido' => Product::where('estoque_minimo', 0)->count(),
            'movimentacoesRecentes' => StockMovement::with(['product', 'user'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
