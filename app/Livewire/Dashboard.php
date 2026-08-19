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
            'movimentacoesRecentes' => StockMovement::with(['product', 'user'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
