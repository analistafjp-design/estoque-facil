<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Aba de dados do modelo. Os títulos são escritos por extenso ("Nome do
 * produto") em vez do nome técnico da coluna do banco ("nome") porque quem
 * preenche é uma pessoa olhando o Excel — e o importador reconhece os dois
 * jeitos igualmente.
 */
class ProductImportTemplateSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Produtos';
    }

    public function headings(): array
    {
        return [
            'Nome do produto',
            'Código (SKU)',
            'Categoria',
            'Quantidade atual',
            'Estoque mínimo',
        ];
    }

    /**
     * Linhas de exemplo, para o formato ficar evidente sem ler instrução
     * nenhuma. Apagar antes de importar (a tela de conferência mostra a
     * prévia, então dá pra perceber se ficaram).
     */
    public function array(): array
    {
        return [
            ['Parafuso sextavado 3/8 x 2"', 'PAR-0001', 'Fixação', 250, 50],
            ['Porca sextavada 3/8', 'PAR-0002', 'Fixação', 180, 40],
            ['Fita isolante 20m', 'ELE-0001', 'Elétrica', 12, 30],
        ];
    }

    /** Quantidades como número inteiro — evita o Excel exibir "250,00". */
    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Cabeçalho congelado: em planilha com centenas de linhas, saber em
        // qual coluna se está sem voltar ao topo é o que evita preencher
        // quantidade no campo de mínimo.
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
        ];
    }
}
