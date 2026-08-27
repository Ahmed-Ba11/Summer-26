<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_the_current_preferences(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 800_000,
            'salary_day' => 27,
            'theme' => 'dark',
            'font_scale' => 'lg',
        ]);

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings')
                ->where('settings.monthly_income', 800_000)
                ->where('settings.salary_day', 27)
                ->where('settings.theme', 'dark')
                ->where('settings.font_scale', 'lg'));
    }

    public function test_each_preference_is_saved_on_its_own_with_a_confirmation_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.preferences'), ['theme' => 'dark'])
            ->assertSessionHas('toast');

        $this->assertSame('dark', $user->fresh()->theme);

        // زر المظهر في الرأس يمرّ صامتاً — لا toast بعد كل ضغطة
        $this->actingAs($user)
            ->patch(route('settings.preferences'), ['theme' => 'light', 'silent' => true])
            ->assertSessionMissing('toast');

        $this->actingAs($user)
            ->patch(route('settings.preferences'), ['font_scale' => 'x'])
            ->assertInvalid('font_scale');
    }

    public function test_deleting_all_data_requires_the_exact_name_and_reopens_onboarding(): void
    {
        $user = User::factory()->create(['display_name' => 'أحمد']);
        $user->incomes()->create(['amount' => 1000, 'source' => 'راتب', 'income_date' => now()]);

        $this->actingAs($user)
            ->delete(route('settings.data.destroy'), ['confirm' => 'اسم غلط'])
            ->assertInvalid('confirm');

        $this->assertDatabaseCount('incomes', 1);

        $this->actingAs($user)
            ->delete(route('settings.data.destroy'), ['confirm' => 'أحمد'])
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseCount('incomes', 0);
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_backup_returns_every_record_as_a_downloadable_file(): void
    {
        $user = User::factory()->create();
        $user->incomes()->create(['amount' => 250_000, 'source' => 'راتب', 'income_date' => now()]);

        $this->actingAs($user)
            ->get(route('settings.backup'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="muwaffir-backup-'.now()->format('Y-m-d').'.json"')
            ->assertJsonPath('incomes.0.amount', 250_000);
    }
}
