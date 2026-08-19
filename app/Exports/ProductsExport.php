<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta o estoque atual da empresa logada. FromCollection (não Query)
 * porque a lista de produtos de uma PME cabe de sobra em memória — não
 * precisa da complexidade de streaming em chunks para este volume.
 */
class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        // Product já sai filtrado por empresa sozinho (CompanyScope).
        return Product::orderBy('nome')->get();
    }

    public function headings(): array
    {
        return ['Nome', 'SKU', 'Categoria', 'Quantidade atual', 'Estoque mínimo', 'Estoque baixo?'];
    }

    public function map($produto): array
    {
        return [
            $produto->nome,
            $produto->sku,
            $produto->categoria,
            $produto->quantidade_atual,
            $produto->estoque_minimo,
            $produto->estaComEstoqueBaixo() ? 'Sim' : 'Não',
        ];
    }
}
