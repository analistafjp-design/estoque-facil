<?php

namespace App\Imports\Support;

/**
 * Converte para inteiro o que vier na célula de quantidade.
 *
 * Planilha de verdade traz "1.234", "1.234,56", "1,234.56", "12 un",
 * "R$ 0", célula vazia, ou já um número do Excel. Um (int) direto em
 * "1.234" dá 1 — erro silencioso que só aparece quando o estoque estiver
 * absurdamente errado, então vale tratar cada caso explicitamente.
 */
final class LeitorDeNumero
{
    public static function paraInteiro(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        // Excel entrega número como int/float — sem texto pra interpretar.
        if (is_int($valor) || is_float($valor)) {
            return (int) round($valor);
        }

        if (! is_string($valor)) {
            return 0;
        }

        // Descarta unidade, moeda e espaço ("12 un", "R$ 1.234,00").
        $limpo = (string) preg_replace('/[^0-9,.\-]/', '', $valor);

        if ($limpo === '' || $limpo === '-') {
            return 0;
        }

        $temVirgula = str_contains($limpo, ',');
        $temPonto = str_contains($limpo, '.');

        if ($temVirgula && $temPonto) {
            // O separador decimal é o que aparece por último:
            // "1.234,56" (pt-BR) vs "1,234.56" (en-US).
            $decimal = strrpos($limpo, ',') > strrpos($limpo, '.') ? ',' : '.';
            $milhar = $decimal === ',' ? '.' : ',';

            $limpo = str_replace($milhar, '', $limpo);
            $limpo = str_replace($decimal, '.', $limpo);
        } elseif ($temVirgula) {
            // Só vírgula: decimal ("12,5") ou milhar ("1,234")? Três dígitos
            // depois dela, sem mais nada, indica milhar.
            $limpo = preg_match('/,\d{3}$/', $limpo)
                ? str_replace(',', '', $limpo)
                : str_replace(',', '.', $limpo);
        } elseif ($temPonto) {
            // Mesma lógica para "1.234" (milhar) vs "12.5" (decimal).
            if (preg_match('/\.\d{3}$/', $limpo)) {
                $limpo = str_replace('.', '', $limpo);
            }
        }

        return is_numeric($limpo) ? (int) round((float) $limpo) : 0;
    }
}
