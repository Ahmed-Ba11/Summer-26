<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Services\CommitmentService;
use App\Services\SalaryMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة التحكم.
 *
 * كل المبالغ بالهللات (integer). الواجهة هي المسؤولة عن التحويل والعرض
 * عبر @/lib/format — لا تُقسَم على 100 هنا.
 */
class DashboardController extends Controller
{
    /** أسماء الأشهر بالعربي للرسم البياني والفلتر. */
    private const MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    /**
     * تحويل الإيموجي القديم إلى اسم أيقونة lucide.
     * موجود كشبكة أمان حتى بعد تشغيل migration التحويل — أي قيمة غير معروفة
     * تسقط على "ellipsis" بدل أن تكسر الواجهة.
     */
    private const EMOJI_TO_ICON = [
        '🍔' => 'utensils', '🍕' => 'utensils', '☕' => 'coffee',
        '🚗' => 'car', '🚌' => 'bus', '⛽' => 'fuel',
        '🏠' => 'house', '⚡' => 'zap', '📶' => 'wifi', '📱' => 'phone',
        '💊' => 'pill', '🏥' => 'heart-pulse', '🏋' => 'dumbbell',
        '📚' => 'book-open', '🎓' => 'graduation-cap',
        '🎮' => 'gamepad-2', '✈' => 'plane', '🎁' => 'gift',
        '👕' => 'shirt', '💻' => 'laptop', '🐱' => 'cat',
        '💼' => 'briefcase', '💳' => 'credit-card', '💰' => 'banknote',
        '🔁' => 'repeat', '🛒' => 'shopping-cart', '📦' => 'ellipsis',
    ];

    /** ألوان البالتة الفئوية — الترتيب ثابت ولا يُدوَّر (آلية أمان ضد عمى الألوان). */
    private const PALETTE = [
        '#2a78d6', '#eb6834', '#1baf7a', '#eda100',
        '#e87ba4', '#008300', '#4a3aa7',
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $salaryMonth = SalaryMonthService::for($user);
        $month = $this->resolveMonth($request->query('month'), $salaryMonth);
        $period = $salaryMonth->period($month);
        $range = $salaryMonth->rangeFor($month);

        $commitmentService = CommitmentService::for($user);
        $commitmentPeriod = $salaryMonth->current();

        $prev = $salaryMonth->periodFor($period['startsOn']->subDay())['key'];
        $isCurrentMonth = $month === $commitmentPeriod['key'];

        // ── المبالغ الأساسية ────────────────────────────────────────────────
        $totalIncome = $salaryMonth->incomeFor($month);
        $totalExpenses = $salaryMonth->expensesFor($month);
        $prevExpenses = $salaryMonth->expensesFor($prev);

        $commitmentsTotal = $commitmentService->obligationsForPeriod($commitmentPeriod);
        $commitmentsReserved = $commitmentService->reservedForPeriod($commitmentPeriod);
        $commitmentsPaid = $commitmentService->paidForPeriod($commitmentPeriod);
        $commitmentsDueSoon = $commitmentService->dueSoonCount(7, $commitmentPeriod);

        $savingsMonthly = $this->monthlySavingsNeed($user);

        // ── الأيام والمتوسطات ───────────────────────────────────────────────
        // كلها على أيام **شهر الراتب**: المتوسط اليومي في اليوم الثالث من
        // الراتب يُقسَم على 3 لا على رقم اليوم في التقويم.
        $daysLeft = $isCurrentMonth ? $period['daysLeft'] : 0;
        $daysElapsed = $isCurrentMonth ? max(1, $period['dayIndex']) : $period['totalDays'];
        $avgDaily = (int) round($totalExpenses / $daysElapsed);

        // ── الفئات ──────────────────────────────────────────────────────────
        $budgets = $user->budgets()->where('month', $month)
            ->pluck('amount', 'category_id');

        $categories = Category::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (Category $c, int $i) use ($user, $range, $budgets): array {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'icon' => $this->normalizeIcon($c->icon),
                    'color' => $this->normalizeColor($c->color, $i),
                    'amount' => (int) $user->expenses()
                        ->where('category_id', $c->id)
                        ->whereBetween('expense_date', $range)
                        ->sum('amount'),
                    'budget' => (int) ($budgets[$c->id] ?? 0),
                    'rollover' => 0,
                ];
            });

        // ── الاتجاه: آخر ٦ رواتب ────────────────────────────────────────────
        $monthly = collect($salaryMonth->lastPeriods(6, $month))
            ->map(fn (array $p): array => [
                'month' => self::MONTHS[(int) substr($p['key'], 5, 2)],
                'income' => $salaryMonth->incomeFor($p['key']),
                'expenses' => $salaryMonth->expensesFor($p['key']),
            ])->values();

        // ── آخر المعاملات ───────────────────────────────────────────────────
        $expenseTxns = $user->expenses()->with('category')
            ->latest('expense_date')->limit(6)->get()
            ->map(fn ($e) => [
                'id' => 'e'.$e->id,
                'type' => 'expense',
                'desc' => $e->description ?: ($e->category?->name ?? 'مصروف'),
                'category' => $e->category?->name ?? 'أخرى',
                'icon' => $this->normalizeIcon($e->category?->icon),
                'color' => $this->normalizeColor($e->category?->color, (int) ($e->category_id ?? 0)),
                'amount' => (int) $e->amount,
                'date' => $e->expense_date->format('Y-m-d'),
            ]);

        $incomeTxns = $user->incomes()
            ->latest('income_date')->limit(4)->get()
            ->map(fn ($i) => [
                'id' => 'i'.$i->id,
                'type' => 'income',
                'desc' => $i->description ?: $i->source,
                'category' => $i->source ?? 'دخل',
                'icon' => 'banknote',
                'color' => '#0ca30c',
                'amount' => (int) $i->amount,
                'date' => $i->income_date->format('Y-m-d'),
            ]);

        $recentTransactions = $expenseTxns->concat($incomeTxns)
            ->sortByDesc('date')->take(6)->values();

        // ── هل عنده بيانات أصلاً؟ ───────────────────────────────────────────
        $hasData = $totalIncome > 0
            || $totalExpenses > 0
            || $user->budgets()->exists()
            || $user->commitments()->exists();

        return Inertia::render('Dashboard', [
            'month' => $month,
            'availableMonths' => $this->availableMonths($salaryMonth),
            'hasData' => $hasData,
            'onboardingComplete' => $user->onboarding_completed_at !== null,

            'salaryMonth' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'range' => $period['range'],
                'daysLeft' => $period['daysLeft'],
                'dayIndex' => $period['dayIndex'],
                'totalDays' => $period['totalDays'],
                'isCurrent' => $isCurrentMonth,
            ],

            // إقفال الراتب السابق — يُعرض مرة واحدة، والفائض بقرار المستخدم.
            'salaryClose' => $isCurrentMonth ? $salaryMonth->pendingClose() : null,

            'stats' => [
                'totalIncome' => $totalIncome,
                'totalExpenses' => $totalExpenses,
                'prevExpenses' => $prevExpenses,
                'commitmentsTotal' => $commitmentsTotal,
                'commitmentsReserved' => $commitmentsReserved,
                'commitmentsPaid' => $commitmentsPaid,
                'commitmentsDueSoon' => $commitmentsDueSoon,
                'savings' => $savingsMonthly,
                'avgDaily' => $avgDaily,
                'daysLeft' => $daysLeft,
                'savingsRate' => $totalIncome > 0
                    ? (int) round(($savingsMonthly / $totalIncome) * 100)
                    : 0,
                'savingsTarget' => 10,
            ],

            'categories' => $categories,
            'monthly' => $monthly,
            'calendarEvents' => $this->calendarEvents($user),
            'recentTransactions' => $recentTransactions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resolveMonth(?string $requested, SalaryMonthService $salaryMonth): string
    {
        if ($requested
            && preg_match('/^\d{4}-\d{2}$/', $requested)
            && checkdate((int) substr($requested, 5, 2), 1, (int) substr($requested, 0, 4))) {
            return $requested;
        }

        return $salaryMonth->current()['key'];
    }

    /**
     * آخر اثني عشر راتباً — «راتب أغسطس 2026» لا «أغسطس 2026».
     *
     * @return list<array{value: string, label: string}>
     */
    private function availableMonths(SalaryMonthService $salaryMonth): array
    {
        return collect($salaryMonth->lastPeriods(12))
            ->reverse()
            ->map(fn (array $p): array => [
                'value' => $p['key'],
                'label' => $p['label'].' '.substr($p['key'], 0, 4),
            ])
            ->values()
            ->all();
    }

    /**
     * المبلغ المطلوب ادخاره شهرياً للوصول لأهداف الادخار النشطة في وقتها.
     * الأهداف بلا تاريخ مستهدف تُهمل (لا يمكن اشتقاق مبلغ شهري منها).
     */
    private function monthlySavingsNeed($user): int
    {
        return (int) $user->savingsGoals()
            ->where('is_completed', false)
            ->whereNotNull('target_date')
            ->get()
            ->sum(function ($goal): float {
                $remaining = max(0, (int) $goal->target_amount - (int) $goal->current_amount);
                $months = max(1, now()->diffInMonths($goal->target_date, false));

                return $remaining / $months;
            });
    }

    /**
     * أحداث فترة الراتب: الالتزامات غير المدفوعة (المتأخّرة والقادمة)
     * حتى أربعة عشر يوماً أمام اليوم، ويوم الراتب القادم.
     *
     * @return list<array{date: string, kind: string, label: string, amount: int}>
     */
    private function calendarEvents(User $user): array
    {
        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();
        $today = CarbonImmutable::today();
        $horizon = $today->addDays(14);
        $events = [];

        foreach ($user->commitments()->active()->get() as $commitment) {
            if ($commitment->payments()->where('period_key', $period['key'])->exists()) {
                continue;
            }

            $due = $service->dueDateFor($commitment, $period);

            if ($due->betweenIncluded($period['salaryDate'], $horizon)) {
                $events[] = [
                    'date' => $due->format('Y-m-d'),
                    'kind' => $commitment->kind,
                    'label' => $commitment->name,
                    'amount' => $service->expectedAmount($commitment),
                ];
            }
        }

        $salaryAmount = (int) $user->incomes()->where('is_recurring', true)->sum('amount');
        if ($salaryAmount > 0 && $period['nextSalary']->betweenIncluded($today, $horizon)) {
            $events[] = [
                'date' => $period['nextSalary']->format('Y-m-d'),
                'kind' => 'salary',
                'label' => 'الراتب',
                'amount' => $salaryAmount,
            ];
        }

        return $events;
    }

    private function normalizeIcon(?string $icon): string
    {
        if (! $icon) {
            return 'ellipsis';
        }

        // قيمة lucide صحيحة أصلاً (حروف وشرطات فقط)
        if (preg_match('/^[a-z0-9-]+$/', $icon)) {
            return $icon;
        }

        foreach (self::EMOJI_TO_ICON as $emoji => $name) {
            if (str_contains($icon, $emoji)) {
                return $name;
            }
        }

        return 'ellipsis';
    }

    private function normalizeColor(?string $color, int $index): string
    {
        if ($color && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return $color;
        }

        return self::PALETTE[$index % count(self::PALETTE)];
    }
}
