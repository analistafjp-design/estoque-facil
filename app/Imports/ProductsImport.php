<?php

namespace App\Imports;

use App\Imports\Support\LeitorDeNumero;
use App\Imports\Support\MapeadorDeColunas;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use RuntimeException;

/**
 * Importa produtos de QUALQUER planilha, não só do modelo do sistema.
 *
 * O usuário normalmente não monta o arquivo: ele exporta do sistema
 * antigo/ERP/fornecedor e cada um nomeia as colunas do seu jeito. Por isso
 * aqui não há cabeçalho obrigatório — MapeadorDeColunas descobre quais
 * colunas são quais, e linhas/colunas que não se encaixam são ignoradas em
 * vez de derrubar a importação inteira.
 *
 * O único campo indispensável é o nome: produto sem nome não existe. SKU,
 * categoria e quantidades são opcionais e têm valor padrão.
 *
 * Continua valendo o upsert por SKU da versão anterior: reimportar a mesma
 * planilha depois de um inventário atualiza, não duplica.
 */
class ProductsImport implements ToCollection
{
    public int $criados = 0;

    public int $atualizados = 0;

    public int $ignorados = 0;

    /** Linhas que se juntaram a um produto já visto nesta importação. */
    public int $agrupados = 0;

    /**
     * Produtos já tocados nesta importação, por chave (SKU ou nome).
     *
     * Relatório de estoque costuma trazer o mesmo material repetido, uma
     * linha por depósito/filial — o mesmo código aparece 4, 5 vezes com
     * quantidades diferentes. Como esta v1 não tem filiais, o estoque do
     * produto é a SOMA das linhas; sobrescrever deixaria só a última, e o
     * saldo sairia menor que o real sem nenhum aviso.
     *
     * Guardar aqui também evita reconsultar o banco a cada linha repetida.
     *
     * @var array<string, Product>
     */
    private array $vistos = [];

    /** @var array<string, int> campo => índice da coluna reconhecida */
    public array $colunasDetectadas = [];

    /**
     * @param  array<string, int>|null  $mapaEscolhido  mapeamento confirmado
     *   pelo usuário na tela de conferência. Quando informado, tem
     *   precedência sobre a detecção automática — é ele quem está olhando
     *   a planilha, o palpite do sistema é só ponto de partida.
     */
    public function __construct(
        private ?array $mapaEscolhido = null,
        private ?int $linhaCabecalho = null,
    ) {}

    public function collection(Collection $linhas): void
    {
        if ($this->mapaEscolhido !== null && $this->linhaCabecalho !== null) {
            $mapa = $this->mapaEscolhido;
            $indiceCabecalho = $this->linhaCabecalho;
        } else {
            $cabecalho = (new MapeadorDeColunas)->localizarCabecalho($linhas);

            if ($cabecalho === null) {
                throw new RuntimeException(
                    'Não foi possível identificar as colunas da planilha. '
                    .'É preciso haver uma linha de cabeçalho com pelo menos uma '
                    .'coluna de nome do produto (ex.: "Nome", "Produto", '
                    .'"Descrição" ou "Item").'
                );
            }

            $mapa = $cabecalho['mapa'];
            $indiceCabecalho = $cabecalho['indice'];
        }

        if (! isset($mapa['nome'])) {
            throw new RuntimeException(
                'Escolha qual coluna da planilha tem o nome do produto.'
            );
        }

        $this->colunasDetectadas = $mapa;
        $companyId = Auth::user()->company_id;

        foreach ($linhas as $posicao => $linha) {
            // Pula tudo até o cabeçalho, inclusive (títulos, datas, etc.).
            if ($posicao <= $indiceCabecalho) {
                continue;
            }

            $this->processarLinha(
                is_array($linha) ? $linha : $linha->toArray(),
                $mapa,
                $companyId
            );
        }
    }

    /**
     * @param  array<int, mixed>  $linha
     * @param  array<string, int>  $mapa
     */
    private function processarLinha(array $linha, array $mapa, int $companyId): void
    {
        $nome = $this->valor($linha, $mapa, 'nome');

        // Linha em branco ou de rodapé ("Total:", "Fim do relatório") não é
        // erro do usuário — é ruído normal de planilha exportada. Ignorar em
        // silêncio é melhor que abortar tudo por causa dela.
        if ($nome === '') {
            $this->ignorados++;

            return;
        }

        $atributos = [
            'nome' => Str::limit($nome, 255, ''),
            'categoria' => Str::limit($this->valor($linha, $mapa, 'categoria'), 255, '') ?: null,
            'quantidade_atual' => max(0, LeitorDeNumero::paraInteiro($this->bruto($linha, $mapa, 'quantidade_atual'))),
            'estoque_minimo' => max(0, LeitorDeNumero::paraInteiro($this->bruto($linha, $mapa, 'estoque_minimo'))),
        ];

        $sku = Str::limit($this->valor($linha, $mapa, 'sku'), 50, '');

        // Sem coluna de SKU (ou célula vazia), o identificador vira o próprio
        // nome: a planilha ainda importa, e reimportá-la atualiza em vez de
        // duplicar. O SKU gerado é só um rótulo interno, editável depois.
        $chave = $sku !== '' ? 'sku:'.$sku : 'nome:'.mb_strtolower($atributos['nome']);

        // Mesma chave já vista nesta importação: é outra linha do mesmo
        // material (outro depósito). Soma em vez de sobrescrever.
        if (isset($this->vistos[$chave])) {
            $produto = $this->vistos[$chave];
            $produto->quantidade_atual += $atributos['quantidade_atual'];
            $produto->save();
            $this->agrupados++;

            return;
        }

        $existente = $sku !== ''
            ? $this->buscarPorSku($companyId, $sku)
            : $this->buscarPorNome($companyId, $atributos['nome']);

        if ($existente) {
            $existente->fill($atributos + ['sku' => $sku !== '' ? $sku : $existente->sku])->save();
            $this->vistos[$chave] = $existente;
            $this->atualizados++;

            return;
        }

        $atributos['sku'] = $sku !== '' ? $sku : $this->gerarSku($companyId, $atributos['nome']);

        // company_id não entra aqui de propósito: não é fillable (ver
        // Product::$fillable) para nenhuma planilha poder forjá-lo, e a
        // trait BelongsToCompany já preenche a partir do usuário logado.
        $this->vistos[$chave] = Product::create($atributos);
        $this->criados++;
    }

    private function buscarPorSku(int $companyId, string $sku): ?Product
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('sku', $sku)
            ->first();
    }

    private function buscarPorNome(int $companyId, string $nome): ?Product
    {
        return Product::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('nome', $nome)
            ->first();
    }

    /**
     * Deriva um SKU legível do nome, garantindo unicidade dentro da empresa
     * (a tabela tem índice único em company_id + sku).
     */
    private function gerarSku(int $companyId, string $nome): string
    {
        $base = Str::upper(Str::limit(Str::slug($nome, '-'), 40, '')) ?: 'PRODUTO';

        $sku = $base;
        $sufixo = 2;

        while ($this->buscarPorSku($companyId, $sku)) {
            $sku = Str::limit($base, 45, '').'-'.$sufixo;
            $sufixo++;
        }

        return $sku;
    }

    /**
     * @param  array<int, mixed>  $linha
     * @param  array<string, int>  $mapa
     */
    private function valor(array $linha, array $mapa, string $campo): string
    {
        $bruto = $this->bruto($linha, $mapa, $campo);

        return is_scalar($bruto) ? trim((string) $bruto) : '';
    }

    /**
     * @param  array<int, mixed>  $linha
     * @param  array<string, int>  $mapa
     */
    private function bruto(array $linha, array $mapa, string $campo): mixed
    {
        if (! isset($mapa[$campo])) {
            return null;
        }

        return $linha[$mapa[$campo]] ?? null;
    }
}
