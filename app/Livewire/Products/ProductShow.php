<?php

namespace App\Livewire\Products;

use App\Enums\MovementType;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ProductShow extends Component
{
    use WithPagination;

    public Product $product;

    public string $tipo = 'entrada';

    public int $quantidade = 1;

    public string $observacao = '';

    // find-or-fail implícito do route model binding já respeita o
    // CompanyScope (Product usa BelongsToCompany) — um id de outra
    // empresa na URL cai em 404 sozinho, sem checagem manual aqui.
    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    /**
     * Lança entrada/saída e atualiza a quantidade do produto na mesma
     * transação — se uma das duas escritas falhar, a outra não fica
     * pendurada deixando o saldo do produto errado.
     */
    public function lancar(): void
    {
        $validated = $this->validate([
            'tipo' => ['required', 'in:entrada,saida'],
            'quantidade' => ['required', 'integer', 'min:1'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);

        $tipo = MovementType::from($validated['tipo']);

        if ($tipo === MovementType::Saida && $validated['quantidade'] > $this->product->quantidade_atual) {
            $this->addError('quantidade', 'Não é possível dar saída de mais do que o estoque atual ('.$this->product->quantidade_atual.').');

            return;
        }

        DB::transaction(function () use ($validated, $tipo) {
            $this->product->stockMovements()->create([
                'user_id' => Auth::id(),
                'tipo' => $tipo,
                'quantidade' => $validated['quantidade'],
                'observacao' => $validated['observacao'] ?: null,
            ]);

            $delta = $tipo === MovementType::Entrada ? $validated['quantidade'] : -$validated['quantidade'];
            $this->product->increment('quantidade_atual', $delta);
        });

        $this->product->refresh();
        $this->reset(['quantidade', 'observacao']);
        $this->resetPage();
        session()->flash('status', 'Movimentação registrada.');
    }

    public function render(): View
    {
        return view('livewire.products.product-show', [
            'movimentacoes' => $this->product->stockMovements()
                ->with('user')
                ->latest()
                ->paginate(10),
        ]);
    }
}
