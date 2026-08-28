<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\User;
use App\Services\CommitmentService;
use App\Services\SalaryMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * السداد يُنسب للظهور المستحقّ فعلاً، لا لظهور نافذة الراتب مهما كان.
 *
 * فاتورة يوم 25 ونافذة الراتب تبدأ يوم 27: أقرب ظهور **داخل** النافذة هو
 * 25 من الشهر القادم، بينما الظهور المستحقّ هو 25 من هذا الشهر — وهو في
 * الفترة السابقة. نسبة السداد لفترة اليوم كانت تقلب الحالتين: المنقضي
 * «فات موعده» والمستقبلي «تم السداد».
 *
 * كل المبالغ بالهللات: 450 ر.س = 45,000.
 */
class CommitmentPaymentPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** أُضيف اليوم — استحقاقه يوم 20 وقد مضى 20 أغسطس قبل إضافته. */
    private function snapchat(User $user): Commitment
    {
        return $user->commitments()->create([
            'kind' => 'subscription',
            'name' => 'سناب',
            'icon' => 'repeat',
            'amount' => 4_000,
            'is_variable' => false,
            'total_amount' => 0,
            'months_count' => 0,
            'months_paid' => 0,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 20,
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
            'is_active' => true,
        ]);
    }

    private function electricity(User $user): Commitment
    {
        $commitment = $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'الكهرباء',
            'icon' => 'zap',
            'amount' => 45_000,
            'is_variable' => false,
            'total_amount' => 0,
            'months_count' => 0,
            'months_paid' => 0,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 25,
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
            'is_active' => true,
        ]);

        // `created_at` ليس ضمن fillable — والالتزام هنا قديم عمداً حتى
        // يكون ظهور الفترة السابقة ظهوراً حقيقياً له.
        $commitment->forceFill(['created_at' => CarbonImmutable::parse('2026-05-01')])->save();

        return $commitment->fresh();
    }

    public function test_todays_payment_settles_the_due_that_already_passed_not_next_months(): void
    {
        // 28 أغسطس · يوم الراتب 27 → نافذة اليوم: 27 أغسطس ← 26 سبتمبر
        $this->travelTo(CarbonImmutable::parse('2026-08-28'));

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->electricity($user);

        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 45_000])
            ->assertRedirect();

        // الظهور المسدَّد هو 25 أغسطس — وهو ظهور الفترة السابقة (2026-07)
        $this->assertDatabaseHas('commitment_payments', [
            'commitment_id' => $commitment->id,
            'period_key' => '2026-07',
            'amount' => 45_000,
        ]);

        $service = CommitmentService::for($user);
        $salaryMonth = SalaryMonthService::for($user);

        // لا ظهور منقضٍ يبقى «فات موعده» بعد السداد
        $passed = $service->occurrence($commitment, $salaryMonth->period('2026-07'));
        $this->assertSame('2026-08-25', $passed['due_date']);
        $this->assertSame(CommitmentService::STATUS_PAID, $passed['status']);

        // وظهور الشهر القادم يبقى قادماً لم يُدفع
        $upcoming = $service->occurrence($commitment, $salaryMonth->period('2026-08'));
        $this->assertSame('2026-09-25', $upcoming['due_date']);
        $this->assertSame(CommitmentService::STATUS_UPCOMING, $upcoming['status']);
    }

    public function test_the_commitments_page_shows_the_payable_occurrence_not_a_future_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-28'));

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->electricity($user);

        $rows = CommitmentService::for($user)->hydrate(collect([$commitment]));

        $this->assertSame('2026-08-25', $rows[0]['due_date']);
        $this->assertSame('2026-07', $rows[0]['period_key']);
        $this->assertSame(CommitmentService::STATUS_OVERDUE, $rows[0]['status']);
    }

    /**
     * (أ) الالتزام المضاف اليوم لا يُنشأ له استحقاق منقضٍ.
     *
     * «سناب» يوم 20 أُضيف في 28 أغسطس: فترة 2026-07 انتهت في 26 أغسطس قبل
     * أن يعرف التطبيق به، فظهور 20 أغسطس لم يكن مطلوباً منه قط — لا في
     * التقويم ولا في صفحة الالتزامات.
     */
    public function test_a_commitment_added_today_has_no_past_due(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-28'));

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->snapchat($user);

        $service = CommitmentService::for($user);
        $dueDates = array_column(
            $service->occurrences($commitment, $service->windowPeriods()),
            'due_date',
        );

        $this->assertNotContains('2026-08-20', $dueDates, 'استحقاق سابق لإنشاء الالتزام.');
        $this->assertSame('2026-09-20', $service->payableOccurrence($commitment)['due_date']);

        // والتقويم — المصدر نفسه — لا يعرضه في أغسطس
        $this->actingAs($user)
            ->get(route('calendar', ['month' => '2026-08']))
            ->assertInertia(function (Assert $page): void {
                $dates = collect($page->toArray()['props']['events'])
                    ->where('label', 'سناب')
                    ->pluck('date')
                    ->all();

                $this->assertNotContains('2026-08-20', $dates);
            });
    }

    /**
     * (ب) سداد استحقاق داخل الفترة الحالية يُنسب لتلك الفترة لا للتالية.
     */
    public function test_paying_a_due_inside_the_current_period_stays_in_that_period(): void
    {
        // 26 أغسطس · نافذة الراتب: 27 يوليو ← 26 أغسطس (المفتاح 2026-07)
        $this->travelTo(CarbonImmutable::parse('2026-08-26'));

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->electricity($user);
        // أُضيف داخل الفترة الحالية — فظهور الفترة السابقة (25 يوليو) لم
        // يكن مطلوباً منه، ويبقى ظهور 25 أغسطس وحده مستحقاً.
        $commitment->forceFill(['created_at' => CarbonImmutable::parse('2026-08-01')])->save();

        $service = CommitmentService::for($user);
        $this->assertSame('2026-07', $service->currentPeriod()['key']);
        $this->assertSame('2026-08-25', $service->payableOccurrence($commitment)['due_date']);

        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 45_000])
            ->assertRedirect();

        $this->assertDatabaseHas('commitment_payments', [
            'commitment_id' => $commitment->id,
            'period_key' => '2026-07',
        ]);

        $salaryMonth = SalaryMonthService::for($user);
        $service = CommitmentService::for($user);

        // الظهور المسدَّد هو 25 أغسطس — والتالي (25 سبتمبر) ما زال قادماً
        $this->assertSame(
            CommitmentService::STATUS_PAID,
            $service->occurrence($commitment, $salaryMonth->period('2026-07'))['status'],
        );
        $this->assertSame(
            CommitmentService::STATUS_UPCOMING,
            $service->occurrence($commitment, $salaryMonth->period('2026-08'))['status'],
        );
    }

    public function test_undo_removes_the_payment_that_pay_actually_wrote(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-28'));

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->electricity($user);

        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 45_000])
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('commitments.undo', $commitment))
            ->assertRedirect();

        $this->assertDatabaseCount('commitment_payments', 0);
    }

    public function test_a_commitment_created_after_the_passed_due_is_paid_for_todays_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-28'));

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->electricity($user);
        $commitment->forceFill(['created_at' => CarbonImmutable::parse('2026-08-27')])->save();

        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 45_000])
            ->assertRedirect();

        $this->assertDatabaseHas('commitment_payments', [
            'commitment_id' => $commitment->id,
            'period_key' => '2026-08',
        ]);
    }
}
