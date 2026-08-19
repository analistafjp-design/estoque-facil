<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'nome',
        'sku',
        'categoria',
        'quantidade_atual',
        'estoque_minimo',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_atual' => 'integer',
            'estoque_minimo' => 'integer',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Estoque no ou abaixo do mínimo — é a régua do alerta visual.
     *
     * Nome diferente do scope de propósito: um método de instância e um
     * scope (Product::estoqueBaixo()) com o mesmo nome colidem — PHP
     * encontra o método de instância primeiro e nem chega a cair no
     * __callStatic mágico do Eloquent que resolveria pro scope.
     */
    public function estaComEstoqueBaixo(): bool
    {
        return $this->quantidade_atual <= $this->estoque_minimo;
    }

    public function scopeEstoqueBaixo(Builder $query): Builder
    {
        return $query->whereColumn('quantidade_atual', '<=', 'estoque_minimo');
    }
}
