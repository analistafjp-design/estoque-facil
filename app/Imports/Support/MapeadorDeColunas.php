<?php

namespace App\Imports\Support;

/**
 * Descobre, numa planilha qualquer, quais colunas correspondem aos campos
 * de produto — sem exigir que o arquivo siga o modelo do sistema.
 *
 * O usuário raramente monta a planilha: ele exporta do sistema antigo, do
 * ERP, do fornecedor, e cada um nomeia as colunas do seu jeito ("Produto",
 * "Descrição", "Item"; "Código", "Ref.", "SKU"). Exigir cabeçalho exato
 * transferia pro usuário o trabalho de reescrever o arquivo à mão.
 *
 * A comparação é feita sobre uma forma normalizada do texto (sem acento,
 * sem maiúscula, sem espaço/pontuação), então "Estoque Mínimo", "ESTOQUE
 * MINIMO" e "estoque_minimo" caem todos no mesmo lugar.
 */
final class MapeadorDeColunas
{
    /**
     * Sinônimos por campo, já normalizados.
     *
     * 'exatos' vale só para igualdade; 'parciais' também casa quando o
     * cabeçalho apenas CONTÉM o termo ("Descrição do Produto" → nome).
     * A separação é explícita, e não uma regra por tamanho, porque o risco
     * de casar dentro de outra palavra depende do termo, não do número de
     * letras: "desc" é curto e perigoso ("Desconto" viraria nome), enquanto
     * "name" é curto e seguro ("Product Name").
     *
     * A ordem das CHAVES importa: 'estoque_minimo' vem antes de
     * 'quantidade_atual' porque "estoque minimo" contém "estoque", e sem
     * essa precedência a coluna de mínimo seria capturada como quantidade.
     *
     * @var array<string, array{exatos: list<string>, parciais: list<string>}>
     */
    private const SINONIMOS = [
        'sku' => [
            'exatos' => [
                'sku', 'codigo', 'cod', 'code', 'referencia', 'ref',
                'codigoproduto', 'codproduto', 'codigointerno',
                'codigodebarras', 'codigobarras', 'ean', 'gtin', 'barcode',
                'partnumber', 'codigodoproduto', 'codigoitem',
            ],
            'parciais' => [
                'codigodoproduto', 'codigoproduto', 'codproduto',
                'codigointerno', 'codigodebarras', 'codigobarras',
                'codigoitem', 'partnumber', 'referencia', 'codigo', 'code',
                'sku',
            ],
        ],
        'estoque_minimo' => [
            'exatos' => [
                'estoqueminimo', 'minimo', 'min', 'qtdminima', 'qtdeminima',
                'quantidademinima', 'estoqueseguranca', 'estoquedeseguranca',
                'minimum', 'minstock', 'minimumstock', 'reorderpoint',
                'pontodepedido', 'pontopedido', 'estminimo', 'estoquemin',
                'nivelminimo',
            ],
            'parciais' => [
                'quantidademinima', 'estoquedeseguranca', 'estoqueseguranca',
                'minimumstock', 'reorderpoint', 'pontodepedido',
                'nivelminimo', 'estoqueminimo', 'qtdeminima', 'qtdminima',
                'minima', 'minimo', 'minimum',
            ],
        ],
        'quantidade_atual' => [
            'exatos' => [
                'quantidade', 'quantidadeatual', 'qtd', 'qtde', 'qte',
                'quant', 'estoque', 'estoqueatual', 'saldo', 'saldoatual',
                'saldoestoque', 'qty', 'quantity', 'stock', 'onhand',
                'disponivel', 'existencia', 'emestoque',
                'quantidadeemestoque', 'utilizacaolivre',
            ],
            'parciais' => [
                'quantidadeemestoque', 'quantidadeatual', 'saldoestoque',
                'estoqueatual', 'saldoatual', 'disponivel', 'existencia',
                'quantidade', 'quantity', 'estoque', 'saldo', 'stock',
                'utilizacaolivre',
            ],
        ],
        'nome' => [
            'exatos' => [
                'nome', 'produto', 'descricao', 'description', 'name',
                'item', 'material', 'mercadoria', 'artigo', 'denominacao',
                'nomeproduto', 'nomedoproduto', 'descricaoproduto',
                'descricaodoproduto', 'desc', 'descricaoitem', 'nomeitem',
                'titulo', 'textobreve', 'textobrevematerial',
            ],
            'parciais' => [
                'descricaodoproduto', 'descricaoproduto', 'nomedoproduto',
                'nomeproduto', 'descricaoitem', 'nomeitem',
                'textobrevematerial', 'textobreve',
                'mercadoria', 'descricao', 'description', 'material',
                'produto', 'artigo', 'titulo', 'name', 'item',
            ],
        ],
        'categoria' => [
            'exatos' => [
                'categoria', 'category', 'grupo', 'group', 'tipo', 'type',
                'familia', 'family', 'setor', 'departamento', 'linha',
                'classe', 'segmento', 'subgrupo', 'secao', 'divisao',
            ],
            'parciais' => [
                'departamento', 'subgrupo', 'categoria', 'category',
                'segmento', 'familia', 'divisao', 'classe', 'grupo',
                'setor',
            ],
        ],
    ];

    /**
     * Reduz um cabeçalho à forma comparável: sem acento, minúsculo, só
     * letras e números. "Estoque Mínimo" e "ESTOQUE_MINIMO" viram
     * "estoqueminimo".
     */
    public static function normalizar(mixed $texto): string
    {
        if (! is_scalar($texto)) {
            return '';
        }

        $texto = (string) $texto;

        $comAcento = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
        $semAcento = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];

        $texto = str_replace($comAcento, $semAcento, mb_strtolower($texto, 'UTF-8'));

        return (string) preg_replace('/[^a-z0-9]/', '', $texto);
    }

    /**
     * Mapeia uma linha de cabeçalho para {campo => índice da coluna}.
     *
     * Duas passadas: primeiro tudo que bate exatamente com um sinônimo
     * (mais confiável), depois o que apenas contém um sinônimo — assim
     * "Estoque" exato vence "Saldo em Estoque" na disputa por
     * quantidade_atual, em vez de depender de qual apareceu primeiro.
     *
     * @param  array<int, mixed>  $cabecalho
     * @return array<string, int>
     */
    public function mapear(array $cabecalho): array
    {
        $normalizado = [];
        foreach ($cabecalho as $indice => $valor) {
            $normalizado[$indice] = self::normalizar($valor);
        }

        $mapa = [];

        // 1ª passada: correspondência exata.
        foreach (self::SINONIMOS as $campo => $sinonimos) {
            foreach ($normalizado as $indice => $texto) {
                if ($texto !== '' && in_array($texto, $sinonimos['exatos'], true) && ! in_array($indice, $mapa, true)) {
                    $mapa[$campo] = $indice;
                    break;
                }
            }
        }

        // 2ª passada: o cabeçalho contém o sinônimo ("Descrição do Produto").
        // Só para campos ainda não resolvidos e colunas ainda não usadas.
        foreach (self::SINONIMOS as $campo => $sinonimos) {
            if (isset($mapa[$campo])) {
                continue;
            }

            foreach ($normalizado as $indice => $texto) {
                if ($texto === '' || in_array($indice, $mapa, true)) {
                    continue;
                }

                foreach ($sinonimos['parciais'] as $sinonimo) {
                    if (str_contains($texto, $sinonimo)) {
                        $mapa[$campo] = $indice;

                        continue 3;
                    }
                }
            }
        }

        return $mapa;
    }

    /**
     * Corrige o mapa olhando o CONTEÚDO das colunas, não só o cabeçalho.
     *
     * Cabeçalho sozinho não basta: numa exportação SAP a coluna "Material"
     * é o CÓDIGO (1000013), enquanto em outros sistemas "Material" é o
     * nome do produto — o mesmo texto, significados opostos. E na mesma
     * planilha existem várias colunas de texto que NÃO são o nome
     * ("Centro", "Denominação depósito"), então não serve pegar a
     * primeira que aparecer.
     *
     * Critério: a coluna de nome é a mais DESCRITIVA — texto longo e
     * variado. "RJB1" repetido em toda linha descreve pouco;
     * "COAGULANTE FLOCUL ION/ANION" descreve muito. Cabeçalho que sugere
     * nome vale um bônus, mas não decide sozinho.
     *
     * @param  array<string, int>  $mapa
     * @param  list<array<int, mixed>>  $amostra  linhas de dados (sem o cabeçalho)
     * @param  array<int, mixed>  $cabecalho
     * @return array<string, int>
     */
    public function ajustarPeloConteudo(array $mapa, array $amostra, array $cabecalho = []): array
    {
        if ($amostra === [] || ! isset($mapa['nome'])) {
            return $mapa;
        }

        if ($this->serveComoNome($amostra, $mapa['nome'])) {
            return $mapa;
        }

        $ocupadas = array_values(array_diff($mapa, [$mapa['nome']]));
        $melhor = $this->colunaMaisDescritiva($amostra, $ocupadas, $cabecalho);

        if ($melhor === null) {
            return $mapa;
        }

        // A coluna numérica que estava como nome é, quase sempre, o código.
        if (! isset($mapa['sku']) && $this->colunaEhMajoritariamenteNumerica($amostra, $mapa['nome'])) {
            $mapa['sku'] = $mapa['nome'];
        }

        $mapa['nome'] = $melhor;

        return $mapa;
    }

    /**
     * Serve como nome se não for coluna numérica e tiver variedade — uma
     * coluna com o mesmo valor em toda linha (o nome da empresa, o código
     * do centro) identifica a planilha, não o produto.
     *
     * @param  list<array<int, mixed>>  $amostra
     */
    private function serveComoNome(array $amostra, int $indice): bool
    {
        if ($this->colunaEhMajoritariamenteNumerica($amostra, $indice)) {
            return false;
        }

        return $this->pontuar($amostra, $indice) > 0
            && $this->proporcaoDistinta($amostra, $indice) >= 0.15;
    }

    /**
     * @param  list<array<int, mixed>>  $amostra
     * @param  list<int>  $ocupadas
     * @param  array<int, mixed>  $cabecalho
     */
    private function colunaMaisDescritiva(array $amostra, array $ocupadas, array $cabecalho): ?int
    {
        $indices = [];
        foreach ($amostra as $linha) {
            foreach (array_keys($linha) as $indice) {
                $indices[$indice] = true;
            }
        }

        $melhor = null;
        $melhorNota = 0.0;

        foreach (array_keys($indices) as $indice) {
            if (in_array($indice, $ocupadas, true)) {
                continue;
            }

            $nota = $this->pontuar($amostra, $indice);

            if ($nota <= 0) {
                continue;
            }

            // Bônus quando o cabeçalho também sugere nome: desempata entre
            // duas colunas de texto igualmente ricas ("Denominação
            // depósito" x "Texto breve material").
            if ($this->cabecalhoSugereNome($cabecalho[$indice] ?? null)) {
                $nota *= 3;
            }

            if ($nota > $melhorNota) {
                $melhorNota = $nota;
                $melhor = $indice;
            }
        }

        return $melhor;
    }

    /**
     * Nota de "quão descritiva" a coluna é: comprimento médio do texto
     * vezes a proporção de valores distintos. Zero se for numérica, se
     * não tiver letra ou se for curta demais (coluna de unidade "KG",
     * marcador "X").
     *
     * @param  list<array<int, mixed>>  $amostra
     */
    private function pontuar(array $amostra, int $indice): float
    {
        if ($this->colunaEhMajoritariamenteNumerica($amostra, $indice)) {
            return 0.0;
        }

        $comprimentos = [];
        $comLetra = 0;

        foreach ($amostra as $linha) {
            $valor = $linha[$indice] ?? null;

            if (! is_scalar($valor) || trim((string) $valor) === '') {
                continue;
            }

            $texto = trim((string) $valor);
            $comprimentos[] = mb_strlen($texto);

            if (preg_match('/\p{L}/u', $texto)) {
                $comLetra++;
            }
        }

        if ($comprimentos === [] || $comLetra === 0) {
            return 0.0;
        }

        $media = array_sum($comprimentos) / count($comprimentos);

        if ($media < 4) {
            return 0.0;
        }

        return $media * $this->proporcaoDistinta($amostra, $indice);
    }

    /** @param  list<array<int, mixed>>  $amostra */
    private function proporcaoDistinta(array $amostra, int $indice): float
    {
        $valores = [];

        foreach ($amostra as $linha) {
            $valor = $linha[$indice] ?? null;

            if (is_scalar($valor) && trim((string) $valor) !== '') {
                $valores[] = trim((string) $valor);
            }
        }

        return $valores === [] ? 0.0 : count(array_unique($valores)) / count($valores);
    }

    private function cabecalhoSugereNome(mixed $cabecalho): bool
    {
        $texto = self::normalizar($cabecalho);

        if ($texto === '') {
            return false;
        }

        foreach (self::SINONIMOS['nome']['parciais'] as $sinonimo) {
            if (str_contains($texto, $sinonimo)) {
                return true;
            }
        }

        return in_array($texto, self::SINONIMOS['nome']['exatos'], true);
    }

    /** @param  list<array<int, mixed>>  $amostra */
    private function colunaEhMajoritariamenteNumerica(array $amostra, int $indice): bool
    {
        $preenchidas = 0;
        $numericas = 0;

        foreach ($amostra as $linha) {
            $valor = $linha[$indice] ?? null;

            if ($valor === null || $valor === '' || ! is_scalar($valor)) {
                continue;
            }

            $preenchidas++;

            if (is_numeric(str_replace([' ', '.', ','], '', (string) $valor))) {
                $numericas++;
            }
        }

        return $preenchidas > 0 && ($numericas / $preenchidas) >= 0.8;
    }

    /**
     * Acha qual linha é o cabeçalho: muitos relatórios exportados trazem
     * título, data e linhas em branco antes da tabela de verdade, então
     * assumir "linha 1" quebraria nesses arquivos.
     *
     * Vence a linha que reconhecer mais campos; empate fica com a de cima.
     * Exige reconhecer 'nome' porque produto sem nome não existe — se
     * nenhuma linha tiver isso, não há o que importar.
     *
     * @param  iterable<int, array<int, mixed>>  $linhas
     * @return array{indice: int, mapa: array<string, int>}|null
     */
    public function localizarCabecalho(iterable $linhas, int $limite = 25): ?array
    {
        $melhor = null;
        $posicao = 0;

        foreach ($linhas as $linha) {
            if ($posicao >= $limite) {
                break;
            }

            $mapa = $this->mapear(is_array($linha) ? $linha : iterator_to_array($linha));

            if (isset($mapa['nome']) && (! $melhor || count($mapa) > count($melhor['mapa']))) {
                $melhor = ['indice' => $posicao, 'mapa' => $mapa];
            }

            $posicao++;
        }

        return $melhor;
    }
}
