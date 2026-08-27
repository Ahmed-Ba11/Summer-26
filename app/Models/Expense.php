<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'commitment_id',
        'amount',
        'description',
        'expense_date',
        'is_recurring',
        'funding_source',
        'recurring_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'integer',
            'is_recurring' => 'boolean',
            'recurring_transaction_id' => 'integer',
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

    /** الالتزام الذي سدّده هذا المصروف — `null` للمصروف العادي. */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }
}
