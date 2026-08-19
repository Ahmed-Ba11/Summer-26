<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'category_id',
        'source',
        'amount',
        'description',
        'frequency',
        'next_due_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('next_due_date', '<=', now()->toDateString());
    }
}
