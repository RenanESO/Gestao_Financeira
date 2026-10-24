<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends FinancialModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'initial_balance', 'color', 'icon', 'active'];

    protected function casts(): array
    {
        return ['initial_balance' => 'decimal:2', 'active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function titles(): HasMany
    {
        return $this->hasMany(Title::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(TitleInstallment::class);
    }

    public function settlementMethods(): HasMany
    {
        return $this->hasMany(TitleSettlementMethod::class);
    }
}
