<?php

namespace App\Models;

use App\Models\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TitleSettlement extends FinancialModel
{
    protected $fillable = [
        'settlement_date', 'original_value', 'discount', 'interest', 'fine', 'total_value', 'notes',
    ];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'original_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'interest' => 'decimal:2',
            'fine' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
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

    public function installment(): BelongsTo
    {
        return $this->belongsTo(TitleInstallment::class, 'title_installment_id');
    }

    public function methods(): HasMany
    {
        return $this->hasMany(TitleSettlementMethod::class, 'settlement_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class, 'settlement_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(SettlementReversal::class, 'settlement_id');
    }

    public function generatedTitle(): HasOne
    {
        return $this->hasOne(Title::class, 'generated_by_settlement_id')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereDoesntHave('reversal');
    }
}
