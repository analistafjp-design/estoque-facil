<?php

namespace App\Imports\Support;

use Maatwebsite\Excel\Concerns\Import as Importavel;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Lê a planilha uma vez e devolve o que é preciso para o usuário CONFERIR
 * o mapeamento antes de importar de verdade.
 *
 * Detecção automática por cabeçalho acerta na maioria dos casos, mas nunca
 * em todos: em exportação de ERP a coluna "Material" tanto pode ser o
 * código quanto a descrição, e adivinhar errado enche o cadastro de
 * produtos chamados "1000013". Por isso o palpite do sistema é só um
 * ponto de partida — quem decide é quem está olhando a planilha.
 */
final class AnalisadorDePlanilha
{
    /** Linhas de dados mostradas na conferência. */
    private const LINHAS_DE_AMOSTRA = 3;

    /**
     * @return array{
     *     colunas: array<int, string>,
     *     mapa: array<string, int>,
     *     amostra: list<array<int, mixed>>,
     *     linhaCabecalho: int,
     *     totalLinhas: int
     * }
     */
    public function analisar(string $caminho): array
    {
        $linhas = $this->lerLinhas($caminho);

        if ($linhas === []) {
            throw new RuntimeException('A planilha está vazia.');
        }

        $mapeador = new MapeadorDeColunas;
        $cabecalho = $mapeador->localizarCabecalho($linhas);

        if ($cabecalho === null) {
            // Sem cabeçalho reconhecível ainda dá pra seguir: usamos a
            // primeira linha como cabeçalho e deixamos o usuário mapear na
            // mão, em vez de simplesmente recusar o arquivo.
            $cabecalho = ['indice' => 0, 'mapa' => []];
        }

        $dados = array_slice($linhas, $cabecalho['indice'] + 1);
        $amostra = array_slice($dados, 0, self::LINHAS_DE_AMOSTRA);

        $mapa = $mapeador->ajustarPeloConteudo(
            $cabecalho['mapa'],
            array_slice($dados, 0, 20)
        );

        return [
            'colunas' => $this->rotularColunas($linhas[$cabecalho['indice']]),
            'mapa' => $mapa,
            'amostra' => $amostra,
            'linhaCabecalho' => $cabecalho['indice'],
            'totalLinhas' => count(array_filter(
                $dados,
                fn ($linha) => array_filter((array) $linha, fn ($v) => $v !== null && $v !== '') !== []
            )),
        ];
    }

    /**
     * Nome legível de cada coluna para o seletor da tela. Coluna sem
     * cabeçalho vira "Coluna 3" — continua selecionável, porque planilha
     * sem cabeçalho em alguma coluna é comum.
     *
     * @param  array<int, mixed>  $cabecalho
     * @return array<int, string>
     */
    private function rotularColunas(array $cabecalho): array
    {
        $rotulos = [];

        foreach ($cabecalho as $indice => $valor) {
            $texto = is_scalar($valor) ? trim((string) $valor) : '';
            $rotulos[$indice] = $texto !== '' ? $texto : 'Coluna '.($indice + 1);
        }

        return $rotulos;
    }

    /** @return list<array<int, mixed>> */
    private function lerLinhas(string $caminho): array
    {
        // A classe anônima precisa implementar Import — é o contrato que
        // o Excel::toArray() exige, mesmo sem nenhum comportamento.
        $planilhas = Excel::toArray(new class implements Importavel {}, $caminho);

        return array_values(array_map('array_values', $planilhas[0] ?? []));
    }
}
