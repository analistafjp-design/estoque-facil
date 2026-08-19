<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Isolamento multi-tenant automático. Toda query em um model que usa
 * BelongsToCompany passa por aqui e ganha
 * `where company_id = <empresa do usuário logado>` sem que nenhum
 * controller ou componente Livewire precise lembrar de filtrar — é
 * justamente esse "lembrar manualmente" que a spec pede para eliminar,
 * porque um único WHERE esquecido vaza dado de uma empresa pra outra.
 *
 * Sem usuário autenticado (ex.: comandos artisan, jobs, seeders), a
 * query passa sem filtro — quem chama nesse contexto é responsável por
 * restringir company_id manualmente quando fizer sentido.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check() && Auth::user()->company_id) {
            $builder->where($model->qualifyColumn('company_id'), Auth::user()->company_id);
        }
    }
}
