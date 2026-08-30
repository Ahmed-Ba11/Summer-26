<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $amount مبلغ بالهللات
 * @property string $source
 * @property string|null $description
 * @property Carbon $income_date
 * @property bool $is_recurring
 * @property int|null $recurring_transaction_id
 */
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

    /** @return BelongsTo<User, Income> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<RecurringTransaction, Income> */
    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }
}
