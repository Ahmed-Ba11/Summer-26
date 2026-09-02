<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Commitment;
use App\Services\CommitmentService;
use App\Services\RecurringTransactionService;
use App\Services\SalaryMonthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الإعداد في أربع خطوات — الراتب · الالتزامات · الادخار والميزانية · الملخّص.
 *
 * كل خطوة تُحفظ لحظة إنهائها، و`onboarding_step` يتقدّم معها. لماذا الحفظ
 * التدريجي بدل إرسال واحد في النهاية: الإعداد يُقطع فعلاً — مكالمة، بطارية،
 * تبديل تطبيق — ومن يعود ليجد نفسه في الخطوة الأولى من جديد لا يكمل مرّتين.
 */
class SetupController extends Controller
{
    /** الالتزامات الجاهزة في الخطوة 2 — تغطّي أغلب ميزانيات الموظّف. */
    private const PRESETS = [
        'rent' => ['name' => 'إيجار', 'kind' => 'rent', 'icon' => 'house'],
        'power' => ['name' => 'كهرباء', 'kind' => 'bill', 'icon' => 'zap'],
        'internet' => ['name' => 'إنترنت', 'kind' => 'bill', 'icon' => 'wifi'],
        'installment' => ['name' => 'قسط', 'kind' => 'installment', 'icon' => 'credit-card'],
    ];

    public function show(Request $request): Response
    {
        $user = $request->user();

        $step = $user->onboarding_completed_at !== null
            ? 4
            : max(1, min(4, (int) $user->onboarding_step ?: 1));

        return Inertia::render('Setup', $this->props($request, $step));
    }

    /** الخطوة 1 — الراتب ويومه ودخل إضافي اختياري. */
    public function salary(
        Request $request,
        RecurringTransactionService $recurring,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'salary_day' => ['required', 'integer', 'between:1,31'],
            'is_recurring' => ['boolean'],
            'extra_amount' => ['nullable', 'integer', 'min:0'],
            'extra_source' => ['nullable', 'string', 'max:60'],
        ], [
            'amount.required' => 'أدخل راتبك — بدونه لا يقدر التطبيق يحسب لك شيئاً.',
            'amount.min' => 'أدخل راتبك — بدونه لا يقدر التطبيق يحسب لك شيئاً.',
        ]);

        $user = $request->user();
        $salaryDay = (int) $validated['salary_day'];

        DB::transaction(function () use (
            $user,
            $validated,
            $salaryDay,
            $recurring,
        ): void {
            $user->update([
                'monthly_income' => (int) $validated['amount'],
                'salary_day' => $salaryDay,
                'onboarding_step' => max(
                    2,
                    (int) $user->onboarding_step,
                ),
            ]);

            $incomeDate = $this->lastSalaryDate($salaryDay);

            $income = $user->incomes()->create([
                'amount' => (int) $validated['amount'],
                'source' => 'الراتب',
                'income_date' => $incomeDate,
                'is_recurring' => (bool) ($validated['is_recurring'] ?? true),
            ]);

            if ($income->is_recurring) {
                $recurring->createFromIncome(
                    $income,
                    'monthly',
                    $incomeDate,
                );
            }

            $extra = (int) ($validated['extra_amount'] ?? 0);

            if ($extra > 0) {
                $user->incomes()->create([
                    'amount' => $extra,
                    'source' => $validated['extra_source'] ?: 'دخل إضافي',
                    'income_date' => $incomeDate,
                    'is_recurring' => false,
                ]);
            }
        });

        return redirect()->route('setup');
    }

    /**
     * الخطوة 2 — الالتزامات الثابتة.
     *
     * لكل التزام مبلغ ويوم استحقاق شهري يحدده المستخدم.
     */
    public function commitments(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'commitments' => ['array'],
            'commitments.*.key' => ['required', 'string', 'max:30'],
            'commitments.*.name' => ['required', 'string', 'max:60'],
            'commitments.*.amount' => ['required', 'integer', 'min:1'],
            'commitments.*.due_day' => [
                'required',
                'integer',
                'between:1,31',
            ],
            'commitments.*.months_count' => [
                'nullable',
                'integer',
                'between:2,480',
            ],
        ], [
            'commitments.*.due_day.required' =>
                'حدد يوم استحقاق الالتزام.',
            'commitments.*.due_day.between' =>
                'يوم الاستحقاق يجب أن يكون بين 1 و31.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            foreach ($validated['commitments'] ?? [] as $row) {
                $preset = self::PRESETS[$row['key']]
                    ?? ['kind' => 'bill', 'icon' => 'receipt'];

                $kind = $preset['kind'];
                $amount = (int) $row['amount'];
                $months = (int) ($row['months_count'] ?? 12);
                $dueDay = (int) $row['due_day'];

                $user->commitments()->create([
                    'kind' => $kind,
                    'name' => $row['name'],
                    'icon' => $preset['icon'],
                    'amount' => $amount,
                    'is_variable' => false,
                    'total_amount' =>
                        $kind === 'installment'
                            ? $amount * $months
                            : 0,
                    'months_count' =>
                        $kind === 'installment'
                            ? $months
                            : 0,
                    'months_paid' => 0,
                    'payment_method' => 'manual',

                    // المستخدم يحدد يوم الاستحقاق أثناء الإعداد.
                    'due_type' => 'month_day',
                    'due_day' => $dueDay,

                    'notify_when' => 'before_3',
                    'reserve_in_budget' => true,
                    'is_active' => true,
                ]);
            }

            $user->update([
                'onboarding_step' => max(
                    3,
                    (int) $user->onboarding_step,
                ),
            ]);
        });

        return redirect()->route('setup');
    }

    /** الخطوة 3 — هدف الادخار وتوزيع الميزانية على الفئات. */
    public function budget(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'savings_target' => ['nullable', 'integer', 'min:0'],
            'budgets' => ['array'],
            'budgets.*.category_id' => ['required', 'integer'],
            'budgets.*.amount' => ['required', 'integer', 'min:0'],
        ]);

        $owned = $user->categories()->pluck('id')->all();
        $month = SalaryMonthService::for($user)->current()['key'];

        DB::transaction(function () use (
            $user,
            $validated,
            $owned,
            $month,
        ): void {
            foreach ($validated['budgets'] ?? [] as $row) {
                if (
                    ! in_array(
                        (int) $row['category_id'],
                        $owned,
                        true,
                    )
                    || (int) $row['amount'] <= 0
                ) {
                    continue;
                }

                $user->budgets()->updateOrCreate(
                    [
                        'category_id' =>
                            (int) $row['category_id'],
                        'month' => $month,
                    ],
                    [
                        'amount' => (int) $row['amount'],
                        'alert_percentage' => 80,
                    ],
                );
            }

            $user->update([
                'monthly_savings_target' =>
                    (int) ($validated['savings_target'] ?? 0),
                'onboarding_step' => max(
                    4,
                    (int) $user->onboarding_step,
                ),
            ]);
        });

        return redirect()->route('setup');
    }

    /** الخطوة 4 — التنبيهات، ثم فتح اللوحة. */
    public function finish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notify_due' => ['boolean'],
        ]);

        $user = $request->user();

        $user->update([
            'notify_due' =>
                (bool) ($validated['notify_due'] ?? true),
            'onboarding_step' => 4,
            'onboarding_completed_at' =>
                $user->onboarding_completed_at ?? now(),
        ]);

        return redirect()->route('dashboard');
    }

    /** تخطّي خطوة — يقدّم المؤشّر فقط بلا حفظ بيانات. */
    public function step(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'between:1,4'],
        ]);

        $user = $request->user();

        $user->update([
            'onboarding_step' => max(
                (int) $validated['step'],
                (int) $user->onboarding_step,
            ),
        ]);

        return redirect()->route('setup');
    }

    /** @return array<string, mixed> */
    private function props(Request $request, int $step): array
    {
        $user = $request->user();
        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();

        $commitments = $user
            ->commitments()
            ->active()
            ->get();

        return [
            'step' => $step,

            'presets' => collect(self::PRESETS)
                ->map(
                    fn (
                        array $preset,
                        string $key,
                    ): array => ['key' => $key] + $preset,
                )
                ->values()
                ->all(),

            'categories' => $user
                ->categories()
                ->orderBy('id')
                ->get()
                ->map(
                    fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'icon' =>
                            $category->icon ?: 'ellipsis',
                        'color' =>
                            $category->color
                            ?: 'var(--chart-7)',
                    ],
                )
                ->values()
                ->all(),

            'saved' => [
                'income' =>
                    (int) $user->monthly_income,

                'salaryDay' =>
                    (int) ($user->salary_day ?? 27),

                'savingsTarget' =>
                    (int) $user->monthly_savings_target,

                'notifyDue' =>
                    (bool) $user->notify_due,

                'commitmentsTotal' =>
                    (int) $commitments->sum(
                        fn (Commitment $c): int =>
                            (int) $c->amount,
                    ),

                'commitmentNames' =>
                    $commitments
                        ->pluck('name')
                        ->values()
                        ->all(),

                'budgetsTotal' =>
                    (int) $user
                        ->budgets()
                        ->where(
                            'month',
                            $period['key'],
                        )
                        ->sum('amount'),
            ],

            'salaryMonth' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'range' => $period['range'],
                'totalDays' =>
                    (int) ($period['totalDays'] ?? 30),
            ],

            'completed' =>
                $user->onboarding_completed_at !== null,
        ];
    }

    /** تاريخ آخر نزول راتب — الدخل يُسجَّل فيه لا في تاريخ اليوم. */
    private function lastSalaryDate(int $salaryDay): string
    {
        $today = now()->startOfDay();

        $date = $today
            ->copy()
            ->setDay(
                min(
                    $salaryDay,
                    (int) $today
                        ->copy()
                        ->endOfMonth()
                        ->day,
                ),
            );

        if ($date->greaterThan($today)) {
            $prev = $today
                ->copy()
                ->subMonthNoOverflow()
                ->startOfMonth();

            $date = $prev->setDay(
                min(
                    $salaryDay,
                    (int) $prev
                        ->copy()
                        ->endOfMonth()
                        ->day,
                ),
            );
        }

        return $date->toDateString();
    }
}