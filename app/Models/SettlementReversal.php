<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementReversal extends FinancialModel
{
    protected $guarded = ['*'];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['reversal_date' => 'date'];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(TitleSettlement::class, 'settlement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
