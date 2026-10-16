<?php

namespace App\Models;

use App\Models\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class FinancialModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new OwnerScope);
    }

    public function constrainToOwner(Builder $query, int $usuarioId): void
    {
        $query->where($this->qualifyColumn('user_id'), $usuarioId);
    }

    public function belongsToOwner(User $usuario): bool
    {
        if (! $this->exists) {
            return false;
        }

        $query = $this->newQuery()->withoutGlobalScope(OwnerScope::class);

        if (in_array(SoftDeletes::class, class_uses_recursive($this), true)) {
            $query->withTrashed();
        }

        // Verifica a propriedade persistida, nao IDs modificados no objeto em memoria.
        $this->constrainToOwner($query, (int) $usuario->getKey());

        return $query->whereKey($this->getKey())->exists();
    }
}
