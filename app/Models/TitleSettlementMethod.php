<?php

namespace App\Models;

use App\Models\Scopes\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TitleSettlementMethod extends FinancialModel
{
    protected $fillable = ['value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function constrainToOwner(Builder $query, int $usuarioId): void
    {
        $baixas = TitleSettlement::withoutGlobalScope(OwnerScope::class)
            ->select('title_settlements.id');
        $baixas->getModel()->constrainToOwner($baixas, $usuarioId);
        $query->whereIn($this->qualifyColumn('settlement_id'), $baixas);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(TitleSettlement::class, 'settlement_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class)->withTrashed();
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class)->withTrashed();
    }
}
