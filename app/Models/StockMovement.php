<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /** @use HasFactory<\Database\Factories\StockMovementFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'tipo',
        'quantidade',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => MovementType::class,
            'quantidade' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
