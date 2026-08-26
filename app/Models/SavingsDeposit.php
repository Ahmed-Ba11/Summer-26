<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsDeposit extends Model
{
    protected $fillable = [
        'user_id',
        'savings_goal_id',
        'amount',
        'deposited_at',
        'period_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'deposited_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }
}
