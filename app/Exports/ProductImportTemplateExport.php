<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Planilha modelo para download — cabeçalho que ProductsImport espera,
 * mais uma linha de exemplo pra ninguém adivinhar o formato.
 */
class ProductImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['Caderno universitário 200fls', 'PAP-0001', 'Papelaria', 50, 10],
        ];
    }

    public function headings(): array
    {
        return ['nome', 'sku', 'categoria', 'quantidade_atual', 'estoque_minimo'];
    }
}
