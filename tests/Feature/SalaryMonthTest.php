<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\SalaryMonthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalaryMonthTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $salaryDay = 27): User
    {
        return User::factory()->create(['salary_day' => $salaryDay]);
    }

    public function test_the_first_of_september_still_belongs_to_the_august_salary(): void
    {
        $this->travelTo('2026-09-01');
        $service = SalaryMonthService::for($this->user());

        $current = $service->current();

        $this->assertSame('2026-08', $current['key']);
        $this->assertSame('راتب أغسطس', $current['label']);
        $this->assertSame('2026-08-27', $current['startsOn']->toDateString());
        $this->assertSame('2026-09-26', $current['endsOn']->toDateString());
        $this->assertSame(26, $current['daysLeft']);
        $this->assertSame(6, $current['dayIndex']);
        $this->assertSame(31, $current['totalDays']);
    }

    public function test_a_salary_on_the_first_matches_the_calendar_month_exactly(): void
    {
        $this->travelTo('2026-09-14');
        $current = SalaryMonthService::for($this->user(1))->current();

        $this->assertSame('2026-09', $current['key']);
        $this->assertSame('2026-09-01', $current['startsOn']->toDateString());
        $this->assertSame('2026-09-30', $current['endsOn']->toDateString());
    }

    public function test_previous_and_next_walk_one_salary_at_a_time(): void
    {
        $this->travelTo('2026-09-01');
        $service = SalaryMonthService::for($this->user());

        $this->assertSame('2026-07', $service->previous()['key']);
        $this->assertSame('راتب يوليو', $service->previous()['label']);
        $this->assertSame('2026-09', $service->next()['key']);
    }

    public function test_february_short_month_keeps_a_continuous_range(): void
    {
        $this->travelTo('2026-03-10');
        $service = SalaryMonthService::for($this->user(28));

        $february = $service->period('2026-02');

        $this->assertSame('2026-02-28', $february['startsOn']->toDateString());
        $this->assertSame('2026-03-27', $february['endsOn']->toDateString());
    }

    public function test_totals_are_scoped_to_the_salary_window_not_the_calendar_month(): void
    {
        $this->travelTo('2026-09-01');
        $user = $this->user();
        $service = SalaryMonthService::for($user);

        // داخل شهر الراتب رغم أنهما في شهرين تقويميين مختلفين
        $user->incomes()->create([
            'amount' => 1_000_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);
        $category = $user->categories()->first();
        $user->expenses()->create([
            'amount' => 30_000, 'category_id' => $category->id,
            'expense_date' => '2026-08-28',
        ]);
        $user->expenses()->create([
            'amount' => 20_000, 'category_id' => $category->id,
            'expense_date' => '2026-09-01',
        ]);
        // خارجها — يوم الراتب التالي
        $user->expenses()->create([
            'amount' => 99_000, 'category_id' => $category->id,
            'expense_date' => '2026-09-27',
        ]);

        $this->assertSame(1_000_000, $service->incomeFor('2026-08'));
        $this->assertSame(50_000, $service->expensesFor('2026-08'));
        $this->assertSame(99_000, $service->expensesFor('2026-09'));
        $this->assertSame(950_000, $service->surplusFor('2026-08'));
    }

    public function test_closing_into_savings_moves_the_whole_surplus_to_the_goal(): void
    {
        $this->travelTo('2026-09-27');
        $user = $this->user();
        $service = SalaryMonthService::for($user);

        $user->incomes()->create([
            'amount' => 500_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);
        $goal = $user->savingsGoals()->create([
            'name' => 'سيارة', 'target_amount' => 2_000_000,
            'current_amount' => 0, 'is_completed' => false, 'is_closed' => false,
        ]);

        $service->close('2026-08', 'saved', $goal->id);

        $this->assertSame(500_000, (int) $goal->fresh()->current_amount);
        $this->assertDatabaseHas('salary_periods', [
            'user_id' => $user->id,
            'period_key' => '2026-08',
            'surplus' => 500_000,
            'surplus_action' => 'saved',
        ]);
        $this->assertNull($service->pendingClose());
    }

    public function test_rolling_the_surplus_creates_an_explicit_income_in_the_new_salary(): void
    {
        $this->travelTo('2026-09-27');
        $user = $this->user();
        $service = SalaryMonthService::for($user);

        $user->incomes()->create([
            'amount' => 400_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);

        $service->close('2026-08', 'rolled');

        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'amount' => 400_000,
            'source' => 'فائض راتب أغسطس',
            'income_date' => '2026-09-27 00:00:00',
        ]);
        $this->assertSame(400_000, $service->incomeFor('2026-09'));
    }

    public function test_splitting_halves_the_surplus_between_savings_and_the_new_salary(): void
    {
        $this->travelTo('2026-09-27');
        $user = $this->user();
        $service = SalaryMonthService::for($user);

        $user->incomes()->create([
            'amount' => 300_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);
        $goal = $user->savingsGoals()->create([
            'name' => 'طوارئ', 'target_amount' => 1_000_000,
            'current_amount' => 0, 'is_completed' => false, 'is_closed' => false,
        ]);

        $service->close('2026-08', 'split', $goal->id);

        $this->assertSame(150_000, (int) $goal->fresh()->current_amount);
        $this->assertSame(150_000, $service->incomeFor('2026-09'));
    }

    public function test_a_deficit_is_recorded_without_creating_any_money(): void
    {
        $this->travelTo('2026-09-27');
        $user = $this->user();
        $service = SalaryMonthService::for($user);

        $user->incomes()->create([
            'amount' => 100_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);
        $user->expenses()->create([
            'amount' => 132_000,
            'category_id' => $user->categories()->first()->id,
            'expense_date' => '2026-09-02',
        ]);

        $service->close('2026-08', 'rolled');

        $this->assertDatabaseHas('salary_periods', [
            'period_key' => '2026-08',
            'surplus' => -32_000,
            'surplus_action' => 'rolled',
        ]);
        $this->assertSame(0, $service->incomeFor('2026-09'));
    }

    public function test_pending_close_stays_quiet_for_a_salary_with_no_activity(): void
    {
        $this->travelTo('2026-09-27');

        $this->assertNull(SalaryMonthService::for($this->user())->pendingClose());
    }

    public function test_saving_the_surplus_requires_a_goal(): void
    {
        $this->travelTo('2026-09-27');
        $user = $this->user();
        $user->incomes()->create([
            'amount' => 100_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);

        $this->expectException(ValidationException::class);
        SalaryMonthService::for($user)->close('2026-08', 'saved');
    }

    public function test_the_dashboard_offers_to_close_an_unclosed_previous_salary(): void
    {
        $this->travelTo('2026-09-27');
        $user = $this->user();
        $user->incomes()->create([
            'amount' => 600_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);
        $goal = $user->savingsGoals()->create([
            'name' => 'سفر', 'target_amount' => 900_000,
            'current_amount' => 100_000, 'is_completed' => false, 'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('salaryClose.key', '2026-08')
                ->where('salaryClose.label', 'راتب أغسطس')
                ->where('salaryClose.surplus', 600_000)
                ->where('salaryClose.nextLabel', 'راتب سبتمبر')
                ->where('salaryClose.goals.0.id', $goal->id)
                ->where('salaryClose.goals.0.remaining', 800_000));

        $this->actingAs($user)
            ->post(route('salary-month.close'), [
                'period_key' => '2026-08',
                'action' => 'saved',
                'savings_goal_id' => $goal->id,
            ])
            ->assertRedirect();

        $this->assertSame(700_000, (int) $goal->fresh()->current_amount);

        // بعد الإقفال لا يُسأل مرّة ثانية
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('salaryClose', null));
    }

    /**
     * الاختبار الحاسم للمرحلة.
     *
     * أول سبتمبر ويوم الراتب 27: اللوحة لازم تعرض «راتب أغسطس»، و«المتبقي لك»
     * يُحسب من 27 أغسطس لا من 1 سبتمبر — وإلا صُفِّر الرقم في منتصف شهر
     * المستخدم وصار المصروف المسجَّل يوم 28 أغسطس كأنه لم يحصل.
     */
    public function test_on_september_first_the_dashboard_still_reads_the_august_salary(): void
    {
        $this->travelTo('2026-09-01');
        $user = $this->user();
        $category = $user->categories()->firstOrFail();

        $user->incomes()->create([
            'amount' => 1_000_000, 'source' => 'راتب',
            'income_date' => '2026-08-27', 'is_recurring' => true,
        ]);
        // مصروف بعد نزول الراتب وقبل أول سبتمبر — يجب أن يبقى محسوباً
        $user->expenses()->create([
            'amount' => 120_000, 'category_id' => $category->id,
            'expense_date' => '2026-08-28',
        ]);
        $user->expenses()->create([
            'amount' => 30_000, 'category_id' => $category->id,
            'expense_date' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2026-08')
                ->where('salaryMonth.label', 'راتب أغسطس')
                ->where('salaryMonth.range', '27 أغسطس ← 26 سبتمبر')
                ->where('salaryMonth.dayIndex', 6)
                ->where('salaryMonth.daysLeft', 26)
                // الدخل والمصروف من 27 أغسطس، لا من 1 سبتمبر
                ->where('stats.totalIncome', 1_000_000)
                ->where('stats.totalExpenses', 150_000)
                ->where('navStats.remaining', 850_000)
                // الحد اليومي الآمن على أيام الراتب الباقية
                ->where('navStats.dailySafe', intdiv(850_000, 26)));
    }
}
