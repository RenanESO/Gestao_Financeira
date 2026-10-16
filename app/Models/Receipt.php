<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends FinancialModel
{
    protected $fillable = ['receipt_number', 'issue_date', 'pdf_path'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'voided_at' => 'datetime', 'snapshot' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(TitleSettlement::class, 'settlement_id');
    }
}
