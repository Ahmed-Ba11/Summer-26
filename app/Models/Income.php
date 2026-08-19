<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'description',
        'income_date',
        'is_recurring',
        'recurring_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'amount' => 'integer',
            'is_recurring' => 'boolean',
            'recurring_transaction_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }
}
