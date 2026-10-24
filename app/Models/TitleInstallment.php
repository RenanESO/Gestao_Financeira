<?php

namespace App\Models;

use App\Models\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TitleInstallment extends FinancialModel
{
    protected $fillable = ['installment_number', 'value', 'due_date', 'status'];

    protected function casts(): array
    {
        return ['installment_number' => 'integer', 'value' => 'decimal:2', 'due_date' => 'date'];
    }

    public function constrainToOwner(Builder $query, int $usuarioId): void
    {
        $query->whereIn($this->qualifyColumn('title_id'),
            Title::withoutGlobalScope(OwnerScope::class)->withTrashed()
                ->select('titles.id')->where('titles.user_id', $usuarioId));
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class)->withTrashed();
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class)->withTrashed();
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(TitleSettlement::class);
    }
}
