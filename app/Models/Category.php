<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends FinancialModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'color', 'icon', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function titles(): HasMany
    {
        return $this->hasMany(Title::class);
    }
}
