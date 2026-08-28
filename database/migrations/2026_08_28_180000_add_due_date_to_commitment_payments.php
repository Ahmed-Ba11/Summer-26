<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الدفعة تُربط بتاريخ الاستحقاق الفعلي، لا بمفتاح فترة مشتقّ.
 *
 * `period_key` مشتقّ من نافذة الراتب، والظهور قد يقع خارج شهر المفتاح:
 * فاتورة يوم 20 ونافذة تبدأ يوم 27 → ظهور فترة `2026-08` موعده 20 سبتمبر،
 * وظهور 20 أغسطس يخصّ `2026-07`. المطابقة النصّية على المفتاح سمحت بأن
 * يُكتب السداد على ظهور والقراءة تقع على ظهور آخر.
 *
 * `due_date` لا لبس فيه. و`period_key` يبقى محسوباً منه للتجميع والعرض.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitment_payments', function (Blueprint $table): void {
            $table->date('due_date')->nullable()->after('paid_at');
        });

        $this->backfill();

        Schema::table('commitment_payments', function (Blueprint $table): void {
            $table->index(['commitment_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('commitment_payments', function (Blueprint $table): void {
            $table->dropIndex(['commitment_id', 'due_date']);
            $table->dropColumn('due_date');
        });
    }

    /**
     * الصفوف القائمة: تاريخ الاستحقاق يُشتقّ من `period_key` بنفس حساب
     * `CommitmentService::dueDateFor` — منسوخاً هنا عمداً، فالترحيل يجب
     * أن يبقى صحيحاً ولو تغيّرت الخدمة بعده.
     */
    private function backfill(): void
    {
        $rows = DB::table('commitment_payments as p')
            ->join('commitments as c', 'c.id', '=', 'p.commitment_id')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->select('p.id', 'p.period_key', 'c.due_type', 'c.due_day', 'u.salary_day')
            ->get();

        foreach ($rows as $row) {
            DB::table('commitment_payments')
                ->where('id', $row->id)
                ->update(['due_date' => $this->dueDateFor($row)->toDateString()]);
        }
    }

    private function dueDateFor(object $row): CarbonImmutable
    {
        $salaryDay = max(1, min(28, (int) ($row->salary_day ?: 27)));
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $row->period_key.'-01');
        $start = $start->setDay(min($salaryDay, $start->daysInMonth));

        if ($row->due_type !== 'month_day' || $row->due_day === null) {
            return $start;
        }

        $day = (int) $row->due_day;
        $candidate = $start->setDay(min($day, $start->daysInMonth));

        if ($candidate->lessThan($start)) {
            $next = $start->addMonth();
            $candidate = $next->setDay(min($day, $next->daysInMonth));
        }

        return $candidate;
    }
};
