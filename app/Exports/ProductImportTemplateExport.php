<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Planilha modelo para download — o formato "oficial" de importação.
 *
 * Duas abas: a primeira com os dados (é a que o importador lê, sempre a
 * primeira), a segunda explicando cada coluna. A explicação fica numa aba
 * separada de propósito: se as instruções ficassem junto dos dados, virariam
 * linhas de produto na hora de reimportar.
 *
 * Vale lembrar que usar este modelo é opcional — o importador reconhece
 * cabeçalho de ERP e planilha de fornecedor por conta própria, e ainda deixa
 * conferir o mapeamento antes de gravar. O modelo existe para quem prefere
 * partir de um formato certo em vez de conferir depois.
 */
class ProductImportTemplateExport implements Export, WithMultipleSheets
{
    use Exportable;

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            new ProductImportTemplateSheet,
            new ProductImportInstructionsSheet,
        ];
    }
}
