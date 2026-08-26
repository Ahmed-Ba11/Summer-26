<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * شهر الراتب — الذاكرة التاريخية للتطبيق.
 *
 * الشهر يبدأ من يوم الراتب لا من الأول من الشهر: راتب يوم 27 يعني
 * أن فترة `2026-08` تمتد من 2026-08-27 إلى 2026-09-26.
 */
class SalaryPeriod extends Model
{
    public const ACTIONS = ['saved', 'rolled', 'split', 'pending'];

    protected $fillable = [
        'user_id',
        'period_key',
        'starts_on',
        'ends_on',
        'income_total',
        'expenses_total',
        'commitments_total',
        'savings_total',
        'surplus',
        'surplus_action',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'income_total' => 'integer',
            'expenses_total' => 'integer',
            'commitments_total' => 'integer',
            'savings_total' => 'integer',
            'surplus' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** يوم الراتب مقيَّد 1..28 حتى تبقى كل الشهور قابلة للتمثيل. */
    public static function salaryDay(User $user): int
    {
        return max(1, min(28, (int) ($user->salary_day ?: 27)));
    }

    /** مفتاح فترة الراتب التي يقع فيها هذا التاريخ، مثل `2026-08`. */
    public static function keyFor(User $user, Carbon|string|null $date = null): string
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date ?? now());

        if ($date->day < self::salaryDay($user)) {
            $date->subMonthNoOverflow();
        }

        return $date->format('Y-m');
    }

    /** @return array{starts_on: Carbon, ends_on: Carbon} */
    public static function boundsFor(User $user, string $periodKey): array
    {
        $day = self::salaryDay($user);
        $starts = Carbon::createFromFormat('Y-m-d', $periodKey.'-01')->startOfDay()->setDay($day);

        return [
            'starts_on' => $starts,
            'ends_on' => $starts->copy()->addMonthNoOverflow()->subDay(),
        ];
    }
}
