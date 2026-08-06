<?php

namespace App\Modules\Lender\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToLender
{
    protected static function bootBelongsToLender(): void
    {
        static::addGlobalScope('belongs_to_lender', function (Builder $builder): void {
            $user = auth('api')->user();

            if (!$user || !is_string($user->role ?? null)) {
                return;
            }

            $role = strtolower($user->role);

            if (in_array($role, ['superadmin', 'super_admin'], true)) {
                return;
            }

            if ($role !== 'lender_ops') {
                return;
            }

            $lenderId = request()->attributes->get('scoped_lender_id') ?? $user->lender_id;

            if ($lenderId && $builder->getModel()->getTable()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.lender_id',
                    (int) $lenderId
                );
            }
        });
    }
}
