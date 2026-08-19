<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Usada por Product, StockMovement e Invitation — as três tabelas de dado
 * de negócio da spec. Faz duas coisas:
 *
 *   1. Registra o CompanyScope, então SELECT/UPDATE/DELETE já saem
 *      filtrados por empresa sozinhos.
 *   2. Preenche company_id sozinho na criação, a partir do usuário
 *      logado — então também não tem como um Livewire component
 *      "esquecer" de setar company_id manualmente.
 *
 * Isolamento continua garantido mesmo se alguém manipular o ID na URL:
 * find($id) de um registro de outra empresa aplica o mesmo WHERE e
 * simplesmente não acha nada (404), porque o scope entra em qualquer
 * query, não só nas de listagem.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (! $model->company_id && Auth::check()) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
