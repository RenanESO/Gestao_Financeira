<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentType extends FinancialModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settlementMethods(): HasMany
    {
        return $this->hasMany(TitleSettlementMethod::class);
    }
}
