<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Lê a planilha modelo (ver stubs/products-template + rota
 * products.import-template) e cria ou atualiza produto por SKU.
 *
 * Atualizar em vez de rejeitar quando o SKU já existe é de propósito:
 * é comum reimportar a mesma planilha depois de um inventário físico
 * só para corrigir a quantidade — forçar o usuário a apagar tudo e
 * recomeçar seria pior experiência que a spec não pede.
 */
class ProductsImport implements SkipsEmptyRows, ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): Product
    {
        // company_id nunca vem da planilha (não é dado que o usuário deva
        // poder falsificar); sempre a empresa de quem está importando.
        $companyId = Auth::user()->company_id;

        $produto = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('sku', $row['sku'])
            ->first();

        // company_id não entra aqui: não é fillable de propósito (ver
        // Product::$fillable) para nenhum formulário poder forjá-lo, e no
        // create BelongsToCompany já preenche sozinho a partir do usuário
        // logado — que é o mesmo $companyId calculado acima.
        $atributos = [
            'nome' => $row['nome'],
            'sku' => $row['sku'],
            'categoria' => $row['categoria'] ?? null,
            'quantidade_atual' => (int) ($row['quantidade_atual'] ?? 0),
            'estoque_minimo' => (int) ($row['estoque_minimo'] ?? 0),
        ];

        if ($produto) {
            $produto->fill($atributos)->save();

            return $produto;
        }

        return new Product($atributos);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'quantidade_atual' => ['nullable', 'integer', 'min:0'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
