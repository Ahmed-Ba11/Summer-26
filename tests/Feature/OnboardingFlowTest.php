<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unonboarded_user_is_redirected_to_welcome_from_any_path(): void
    {
        $user = User::factory()->onboarding()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('welcome'));
        $this->actingAs($user)->get('/budgets')->assertRedirect(route('welcome'));
        $this->actingAs($user)->get('/settings')->assertRedirect(route('welcome'));

        // مسارات الإعداد نفسها لا تُحوَّل — وإلا دار المستخدم في حلقة.
        $this->actingAs($user)->get(route('welcome'))->assertOk();
        $this->actingAs($user)->get(route('setup'))->assertOk();
    }

    public function test_onboarded_user_reaches_the_dashboard_directly(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_setup_saves_each_step_and_resumes_where_it_stopped(): void
    {
        $user = User::factory()->onboarding()->create();
        $category = $user->categories()->firstOrFail();

        // 1 · الراتب
        $this->actingAs($user)->post(route('setup.salary'), [
            'amount' => 800_000,
            'salary_day' => 27,
            'is_recurring' => true,
            'extra_amount' => 50_000,
            'extra_source' => 'عمل حر',
        ])->assertRedirectToRoute('setup');

        $user->refresh();
        $this->assertSame(800_000, (int) $user->monthly_income);
        $this->assertSame(27, (int) $user->salary_day);
        $this->assertSame(2, (int) $user->onboarding_step);
        $this->assertDatabaseHas('incomes', ['user_id' => $user->id, 'amount' => 800_000, 'source' => 'الراتب']);
        $this->assertDatabaseHas('incomes', ['user_id' => $user->id, 'amount' => 50_000, 'source' => 'عمل حر']);
        $this->assertDatabaseHas('recurring_transactions', [
            'user_id' => $user->id,
            'type' => 'income',
            'amount' => 800_000,
        ]);

        // الخروج والعودة يستأنفان من الخطوة الثانية لا الأولى
        $this->actingAs($user)
            ->get(route('setup'))
            ->assertInertia(fn (Assert $page) => $page->component('Setup')->where('step', 2));

        // 2 · الالتزامات
        $this->actingAs($user)->post(route('setup.commitments'), [
            'commitments' => [
                ['key' => 'rent', 'name' => 'إيجار', 'amount' => 200_000],
                ['key' => 'installment', 'name' => 'قسط', 'amount' => 100_000, 'months_count' => 24],
            ],
        ])->assertRedirectToRoute('setup');

        $this->assertDatabaseHas('commitments', ['user_id' => $user->id, 'kind' => 'rent', 'amount' => 200_000]);
        $this->assertDatabaseHas('commitments', [
            'user_id' => $user->id,
            'kind' => 'installment',
            'amount' => 100_000,
            'months_count' => 24,
            'total_amount' => 2_400_000,
        ]);
        $this->assertSame(3, (int) $user->fresh()->onboarding_step);

        // 3 · الادخار والميزانية
        $this->actingAs($user)->post(route('setup.budget'), [
            'savings_target' => 80_000,
            'budgets' => [['category_id' => $category->id, 'amount' => 120_000]],
        ])->assertRedirectToRoute('setup');

        $user->refresh();
        $this->assertSame(80_000, (int) $user->monthly_savings_target);
        $this->assertSame(4, (int) $user->onboarding_step);
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 120_000,
        ]);

        // 4 · الملخّص — يُقفل الإعداد ويفتح اللوحة
        $this->actingAs($user)->post(route('setup.finish'), [
            'notify_due' => true,
            'biometric_lock' => false,
        ])->assertRedirectToRoute('dashboard');

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_salary_step_requires_an_amount_and_skipping_only_advances_the_marker(): void
    {
        $user = User::factory()->onboarding()->create();

        $this->actingAs($user)
            ->post(route('setup.salary'), ['salary_day' => 27])
            ->assertInvalid('amount');

        $this->actingAs($user)->post(route('setup.step'), ['step' => 3])->assertRedirectToRoute('setup');

        $this->assertSame(3, (int) $user->fresh()->onboarding_step);
        $this->assertDatabaseCount('commitments', 0);
    }

    public function test_budget_step_ignores_categories_owned_by_someone_else(): void
    {
        $user = User::factory()->onboarding()->create();
        $foreignCategory = User::factory()->create()->categories()->firstOrFail();

        $this->actingAs($user)->post(route('setup.budget'), [
            'budgets' => [['category_id' => $foreignCategory->id, 'amount' => 50_000]],
        ])->assertRedirectToRoute('setup');

        $this->assertDatabaseCount('budgets', 0);
    }
}
