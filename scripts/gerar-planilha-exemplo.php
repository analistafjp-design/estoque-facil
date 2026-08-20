<?php

/**
 * Gera uma planilha de exemplo no formato oficial de importação, para
 * testar o app com um cadastro realista em vez de três linhas.
 *
 * Os itens são de operação de saneamento/manutenção (tratamento de água,
 * hidráulica, elétrica, EPI, ferramentas, laboratório), e as quantidades
 * são escolhidas de propósito para exercitar o que o app mostra: uns
 * zerados, uns abaixo do mínimo, uns exatamente no mínimo e a maioria
 * saudável. Sem isso, o dashboard abriria todo verde e não daria para ver
 * o alerta funcionando.
 *
 * Uso: php scripts/gerar-planilha-exemplo.php [caminho-de-saida.xlsx]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var array<string, array{prefixo: string, itens: list<string>}> */
$catalogo = [
    'Produtos Químicos' => [
        'prefixo' => 'QUI',
        'itens' => [
            'Sulfato de alumínio líquido isento',
            'Cloreto férrico solução 38%',
            'Policloreto de alumínio (PAC) 18%',
            'Hipoclorito de sódio 12% p/v',
            'Cal hidratada CH-I saco 20kg',
            'Ácido fluossilícico 20%',
            'Pastilha de tricloro isocianúrico 200g',
            'Polímero catiônico em pó alta carga',
            'Polímero aniônico emulsão',
            'Carvão ativado em pó vegetal',
            'Permanganato de potássio grau técnico',
            'Barrilha leve (carbonato de sódio)',
            'Sulfato de cobre pentahidratado',
            'Antiespumante à base de silicone',
            'Bissulfito de sódio 38%',
        ],
    ],
    'Hidráulica' => [
        'prefixo' => 'HID',
        'itens' => [
            'Tubo PVC soldável 100mm barra 6m',
            'Tubo PVC soldável 50mm barra 6m',
            'Tubo PEAD PE100 PN10 63mm',
            'Joelho 90° PVC soldável 100mm',
            'Luva de correr PVC 100mm',
            'Registro de gaveta bronze 2"',
            'Registro esfera PVC 50mm',
            'Válvula de retenção vertical 2"',
            'Ventosa tríplice função 2"',
            'Hidrômetro classe B 3/4"',
            'Adaptador flange PVC 100mm',
            'Colar de tomada PVC 100x3/4"',
            'Anel de borracha EPDM 100mm',
            'Selo mecânico simples AISI 304 3/4"',
            'Rotor de bomba centrífuga 5CV',
            'Gaxeta grafitada 10mm rolo',
            'Mangueira de sucção 3" espiralada',
            'Abraçadeira rosca sem-fim 4"',
        ],
    ],
    'Elétrica' => [
        'prefixo' => 'ELE',
        'itens' => [
            'Cabo flexível 2,5mm² 750V rolo 100m',
            'Cabo flexível 6,0mm² 750V rolo 100m',
            'Disjuntor tripolar 63A curva C',
            'Disjuntor monopolar 20A curva C',
            'Contator tripolar 32A bobina 220V',
            'Relé térmico ajustável 12-18A',
            'Inversor de frequência 5CV 220V',
            'Fita isolante antichama 20m',
            'Reator eletrônico 2x32W',
            'Lâmpada LED bulbo 15W 6500K',
            'Refletor LED 100W IP66',
            'Sensor de nível tipo boia elétrica',
            'Quadro de comando metálico 60x40cm',
            'Terminal tubular pré-isolado 2,5mm²',
            'Eletroduto galvanizado 1" barra 3m',
        ],
    ],
    'EPI' => [
        'prefixo' => 'EPI',
        'itens' => [
            'Capacete classe B com jugular branco',
            'Óculos de proteção incolor antiembaçante',
            'Protetor auricular plug silicone',
            'Luva nitrílica cano longo tam. M',
            'Luva de vaqueta reforçada tam. G',
            'Máscara PFF2 sem válvula',
            'Respirador semifacial com filtro VO/GA',
            'Bota de segurança PVC cano longo 42',
            'Cinto paraquedista 5 pontos',
            'Talabarte duplo em Y com absorvedor',
            'Colete refletivo classe 2',
            'Avental de PVC forrado',
            'Protetor facial em policarbonato 8"',
        ],
    ],
    'Ferramentas' => [
        'prefixo' => 'FER',
        'itens' => [
            'Chave de grifo 24"',
            'Chave combinada 19mm',
            'Jogo de chave allen 1,5 a 10mm',
            'Alicate universal 8" isolado 1000V',
            'Martelo unha 27mm cabo fibra',
            'Marreta 5kg cabo madeira',
            'Serra copo bimetal 100mm',
            'Furadeira de impacto 800W',
            'Esmerilhadeira angular 4.1/2" 850W',
            'Trena de fita 30m fibra de vidro',
            'Paquímetro universal 150mm',
            'Torquímetro de estalo 1/2" 210Nm',
            'Escada extensível fibra 7,2m',
        ],
    ],
    'Laboratório' => [
        'prefixo' => 'LAB',
        'itens' => [
            'Turbidímetro portátil 0-1000 NTU',
            'pHmetro de bancada com eletrodo',
            'Colorímetro para cloro residual',
            'Reagente DPD nº1 cx 100 sachês',
            'Solução tampão pH 7,00 500ml',
            'Solução tampão pH 4,00 500ml',
            'Proveta graduada vidro 1000ml',
            'Bureta graduada 50ml torneira teflon',
            'Placa de Petri descartável 90mm',
            'Membrana filtrante 0,45µm cx 100',
            'Cone Imhoff 1000ml com suporte',
            'Estufa de secagem 100L 200°C',
        ],
    ],
    'Limpeza e Consumo' => [
        'prefixo' => 'LIM',
        'itens' => [
            'Detergente neutro concentrado 5L',
            'Desinfetante quaternário 5L',
            'Saco de lixo reforçado 100L pct',
            'Papel toalha interfolhas 1000fls',
            'Papel higiênico rolão 300m',
            'Vassoura de nylon com cabo',
            'Rodo de alumínio 60cm',
            'Pano de chão alvejado',
            'Balde plástico 20L com alça',
        ],
    ],
    'Escritório' => [
        'prefixo' => 'ESC',
        'itens' => [
            'Papel sulfite A4 75g resma',
            'Caneta esferográfica azul cx 50',
            'Toner compatível para impressora mono',
            'Pasta suspensa kraft com grampo',
            'Grampeador de mesa 26/6',
            'Bloco autoadesivo 76x76mm',
        ],
    ],
];

/**
 * Situações que a planilha precisa cobrir para o app ter o que mostrar.
 * A escolha é por posição (determinística), não sorteio: assim o
 * resultado da importação é sempre o mesmo e dá para conferir os números.
 */
$situacao = function (int $indice): array {
    return match ($indice % 12) {
        0 => ['qtd' => 0, 'min' => 10],        // zerado, com mínimo
        1 => ['qtd' => 2, 'min' => 15],        // abaixo do mínimo
        2 => ['qtd' => 8, 'min' => 8],         // exatamente no mínimo
        3 => ['qtd' => 45, 'min' => 20],       // saudável
        4 => ['qtd' => 320, 'min' => 100],
        5 => ['qtd' => 12, 'min' => 25],       // abaixo do mínimo
        6 => ['qtd' => 1500, 'min' => 400],
        7 => ['qtd' => 76, 'min' => 30],
        8 => ['qtd' => 5, 'min' => 12],        // abaixo do mínimo
        9 => ['qtd' => 240, 'min' => 60],
        10 => ['qtd' => 18, 'min' => 5],
        default => ['qtd' => 90, 'min' => 40],
    };
};

$linhas = [];
$indice = 0;

foreach ($catalogo as $categoria => $grupo) {
    $sequencial = 1;

    foreach ($grupo['itens'] as $nome) {
        $s = $situacao($indice);

        $linhas[] = [
            $nome,
            sprintf('%s-%04d', $grupo['prefixo'], $sequencial),
            $categoria,
            $s['qtd'],
            $s['min'],
        ];

        $indice++;
        $sequencial++;
    }
}

$saida = $argv[1] ?? 'planilha-exemplo-estoque-facil.xlsx';

$export = new class($linhas) implements
    Maatwebsite\Excel\Concerns\FromArray,
    Maatwebsite\Excel\Concerns\ShouldAutoSize,
    Maatwebsite\Excel\Concerns\WithHeadings,
    Maatwebsite\Excel\Concerns\WithStyles,
    Maatwebsite\Excel\Concerns\WithTitle
{
    public function __construct(private array $linhas) {}

    public function array(): array
    {
        return $this->linhas;
    }

    public function headings(): array
    {
        // Mesmos títulos do modelo baixável — é o formato que o app
        // reconhece sem precisar de ajuste na tela de conferência.
        return ['Nome do produto', 'Código (SKU)', 'Categoria', 'Quantidade atual', 'Estoque mínimo'];
    }

    public function title(): string
    {
        return 'Produtos';
    }

    public function styles(PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
        ];
    }
};

Maatwebsite\Excel\Facades\Excel::store($export, basename($saida), 'local');
copy(storage_path('app/private/'.basename($saida)), $saida);

// Resumo do que foi gerado, para conferir contra o dashboard depois.
$zerados = $abaixo = $noMinimo = 0;
$totalItens = 0;

foreach ($linhas as [$nome, $sku, $cat, $qtd, $min]) {
    $totalItens += $qtd;
    if ($qtd <= 0) {
        $zerados++;
    } elseif ($qtd < $min) {
        $abaixo++;
    } elseif ($qtd === $min) {
        $noMinimo++;
    }
}

echo 'Arquivo: ', $saida, ' (', round(filesize($saida) / 1024, 1), " KB)\n";
echo 'Produtos: ', count($linhas), ' em ', count($catalogo), " categorias\n";
echo 'Sem estoque: ', $zerados, "\n";
echo 'Abaixo do mínimo (inclui os que estão exatamente no mínimo): ', $abaixo + $noMinimo + $zerados, "\n";
echo 'Itens somados: ', number_format($totalItens, 0, ',', '.'), "\n";
