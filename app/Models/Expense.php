<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property int|null $commitment_id
 * @property int $amount مبلغ بالهللات
 * @property string|null $description
 * @property Carbon $expense_date
 * @property bool $is_recurring
 * @property string|null $funding_source
 * @property int|null $recurring_transaction_id
 */
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

    /** @return BelongsTo<User, Expense> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, Expense> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * الالتزام الذي سدّده هذا المصروف — `null` للمصروف العادي.
     *
     * @return BelongsTo<Commitment, Expense>
     */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    /** @return BelongsTo<RecurringTransaction, Expense> */
    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }
}
