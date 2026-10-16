<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Title extends FinancialModel
{
    use SoftDeletes;

    protected $fillable = [
        'title_number', 'title_type', 'description', 'document_number', 'original_value',
        'installments_count', 'current_installment', 'issue_date', 'due_date', 'status',
        'is_recurring', 'recurrence_type', 'recurrence_day', 'recurrence_month', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_value' => 'decimal:2',
            'installments_count' => 'integer',
            'current_installment' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'is_recurring' => 'boolean',
            'recurrence_day' => 'integer',
            'recurrence_month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_title_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_title_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(TitleInstallment::class)->orderBy('installment_number');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(TitleSettlement::class);
    }

    public function generatedBySettlement(): BelongsTo
    {
        return $this->belongsTo(TitleSettlement::class, 'generated_by_settlement_id');
    }
}
