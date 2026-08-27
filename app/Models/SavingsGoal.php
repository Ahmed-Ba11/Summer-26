<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsGoal extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'target_amount',
        'current_amount',
        'target_date',
        'is_completed',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'current_amount' => 'integer',
            'target_date' => 'date',
            'is_completed' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * «مكتمل» واقعة لا رأي: الرصيد بلغ الهدف.
     *
     * كانت تُكتب مباشرة من عدّة مواضع، فصار زر «أغلق الهدف» يعلّم هدفاً
     * بـ 2,000 من 30,000 مكتملاً. المقارنة هنا وحدها، وكل من يكتب الراية
     * يسألها — فلا يقدر موضع جديد أن يخترع تعريفاً ثانياً للاكتمال.
     *
     * الطرفان بالهللات — نفس وحدة العمودين في قاعدة البيانات.
     */
    public function hasReachedTarget(?int $balance = null): bool
    {
        $target = (int) $this->target_amount;

        if ($target <= 0) {
            return false;
        }

        return ($balance ?? (int) $this->current_amount) >= $target;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(SavingsDeposit::class);
    }
}
