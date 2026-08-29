<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commitment extends Model
{
    protected $fillable = [
        'user_id',
        'kind',
        'name',
        'icon',
        'color',
        'amount',
        'is_variable',
        'total_amount',
        'months_count',
        'months_paid',
        'payment_method',
        'due_type',
        'due_day',
        'recurrence',
        'due_on',
        'ends_on',
        'notify_when',
        'reserve_in_budget',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'total_amount' => 'integer',
            'is_variable' => 'boolean',
            'months_count' => 'integer',
            'months_paid' => 'integer',
            'reserve_in_budget' => 'boolean',
            'is_active' => 'boolean',
            'due_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CommitmentPayment::class);
    }
}
