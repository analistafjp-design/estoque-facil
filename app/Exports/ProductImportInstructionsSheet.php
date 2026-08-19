<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Aba de instruções. Separada dos dados porque o importador lê sempre a
 * PRIMEIRA aba — texto explicativo junto dos produtos viraria linha de
 * cadastro na reimportação.
 */
class ProductImportInstructionsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Instruções';
    }

    public function headings(): array
    {
        return ['Coluna', 'Obrigatória?', 'O que preencher'];
    }

    public function array(): array
    {
        return [
            [
                'Nome do produto',
                'Sim',
                'Descrição do item como ele é conhecido no dia a dia. É a única coluna obrigatória.',
            ],
            [
                'Código (SKU)',
                'Não',
                'Código interno, referência do fornecedor ou código de barras. É por ele que o sistema '
                .'reconhece um produto já cadastrado: reimportar a planilha atualiza em vez de duplicar. '
                .'Se deixar em branco, o sistema identifica pelo nome e gera um código automático.',
            ],
            [
                'Categoria',
                'Não',
                'Agrupamento livre (ex.: Fixação, Elétrica, Limpeza). Serve para organizar a listagem.',
            ],
            [
                'Quantidade atual',
                'Não',
                'Saldo em estoque hoje. Em branco ou vazio entra como 0. Aceita 1.234, 1.234,56 e "250 un".',
            ],
            [
                'Estoque mínimo',
                'Não',
                'A partir de quanto o produto deve aparecer no alerta de estoque baixo. Em branco entra como 0.',
            ],
            ['', '', ''],
            [
                'Observações',
                '',
                'Apague as linhas de exemplo da aba "Produtos" antes de importar.',
            ],
            [
                '',
                '',
                'A ordem das colunas não importa, e colunas a mais são ignoradas.',
            ],
            [
                '',
                '',
                'Se o mesmo código aparecer em várias linhas (ex.: um relatório com uma linha por depósito), '
                .'as quantidades são somadas.',
            ],
            [
                '',
                '',
                'Este modelo é opcional: o sistema também entende planilha exportada de ERP ou de fornecedor, '
                .'e mostra o mapeamento das colunas para conferência antes de gravar.',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('C')->setAutoSize(false);
        $sheet->getColumnDimension('C')->setWidth(95);
        $sheet->getStyle('C')->getAlignment()->setWrapText(true);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
            'A' => ['font' => ['bold' => true]],
        ];
    }
}
