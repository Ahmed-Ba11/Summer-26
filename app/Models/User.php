<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'email', 'password', 'display_name', 'monthly_income', 'salary_day',
    'monthly_savings_target', 'currency', 'locale', 'theme', 'font_scale',
    'biometric_lock', 'notify_due', 'notify_budget', 'notify_salary',
    'onboarding_step', 'onboarding_completed_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->categories()->createMany(Category::defaultDefinitions());
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'monthly_income' => 'integer',
            'salary_day' => 'integer',
            'monthly_savings_target' => 'integer',
            'biometric_lock' => 'boolean',
            'notify_due' => 'boolean',
            'notify_budget' => 'boolean',
            'notify_salary' => 'boolean',
            'onboarding_step' => 'integer',
        ];
    }

    /** @return HasMany<Expense, User> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<Income, User> */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /** @return HasMany<Category, User> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function assistantMessages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function savingsDeposits(): HasMany
    {
        return $this->hasMany(SavingsDeposit::class);
    }

    public function salaryPeriods(): HasMany
    {
        return $this->hasMany(SalaryPeriod::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<Commitment, User> */
    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    public function commitmentPayments(): HasManyThrough
    {
        return $this->hasManyThrough(CommitmentPayment::class, Commitment::class);
    }
}
