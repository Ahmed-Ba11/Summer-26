<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
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

        $month = $this->resolveMonth($request->query('month'));
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $prev = $start->subMonth()->format('Y-m');

        $isCurrentMonth = $month === now()->format('Y-m');
        $today = CarbonImmutable::today();

        // ── المبالغ الأساسية ────────────────────────────────────────────────
        $totalIncome = (int) $user->incomes()
            ->whereBetween('income_date', [$start, $end])->sum('amount');

        $totalExpenses = (int) $user->expenses()
            ->whereBetween('expense_date', [$start, $end])->sum('amount');

        $prevExpenses = (int) $user->expenses()
            ->where('expense_date', 'like', $prev.'%')->sum('amount');

        $billsDue = (int) $user->bills()
            ->where('is_paid', false)
            ->whereBetween('due_date', [$start, $end])
            ->sum('amount');

        $billsCount = $user->bills()
            ->where('is_paid', false)
            ->whereBetween('due_date', [$start, $end])
            ->count();

        $installmentsMonthly = (int) $user->installments()
            ->where('is_completed', false)->sum('monthly_amount');

        $installmentsCount = $user->installments()
            ->where('is_completed', false)->count();

        $savingsMonthly = $this->monthlySavingsNeed($user);

        // ── الأيام والمتوسطات ───────────────────────────────────────────────
        $salaryDay = (int) ($user->salary_day ?? 27);
        $daysLeft = $isCurrentMonth ? $this->daysUntilSalary($salaryDay, $today) : 0;
        $daysElapsed = $isCurrentMonth ? max(1, $today->day) : $start->daysInMonth;
        $avgDaily = (int) round($totalExpenses / $daysElapsed);

        // ── الفئات ──────────────────────────────────────────────────────────
        $budgets = $user->budgets()->where('month', $month)
            ->pluck('amount', 'category_id');

        $categories = Category::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (Category $c, int $i) use ($user, $start, $end, $budgets): array {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'icon' => $this->normalizeIcon($c->icon),
                    'color' => $this->normalizeColor($c->color, $i),
                    'amount' => (int) $user->expenses()
                        ->where('category_id', $c->id)
                        ->whereBetween('expense_date', [$start, $end])
                        ->sum('amount'),
                    'budget' => (int) ($budgets[$c->id] ?? 0),
                    'rollover' => 0,
                ];
            });

        // ── الاتجاه: آخر ٦ أشهر ─────────────────────────────────────────────
        $monthly = collect(range(5, 0))->map(function (int $back) use ($user, $start): array {
            $m = $start->subMonths($back);

            return [
                'month' => self::MONTHS[(int) $m->month],
                'income' => (int) $user->incomes()
                    ->whereBetween('income_date', [$m->startOfMonth(), $m->endOfMonth()])
                    ->sum('amount'),
                'expenses' => (int) $user->expenses()
                    ->whereBetween('expense_date', [$m->startOfMonth(), $m->endOfMonth()])
                    ->sum('amount'),
            ];
        })->values();

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
            || $user->bills()->exists()
            || $user->installments()->exists();

        return Inertia::render('Dashboard', [
            'month' => $month,
            'availableMonths' => $this->availableMonths(),
            'hasData' => $hasData,
            'onboardingComplete' => $user->onboarding_completed_at !== null,

            'stats' => [
                'totalIncome' => $totalIncome,
                'totalExpenses' => $totalExpenses,
                'prevExpenses' => $prevExpenses,
                'bills' => $billsDue,
                'installments' => $installmentsMonthly,
                'savings' => $savingsMonthly,
                'avgDaily' => $avgDaily,
                'daysLeft' => $daysLeft,
                'billsCount' => $billsCount,
                'installmentsCount' => $installmentsCount,
                'savingsRate' => $totalIncome > 0
                    ? (int) round(($savingsMonthly / $totalIncome) * 100)
                    : 0,
                'savingsTarget' => 10,
            ],

            'categories' => $categories,
            'monthly' => $monthly,
            'calendarEvents' => $this->calendarEvents($user, $salaryDay),
            'recentTransactions' => $recentTransactions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resolveMonth(?string $requested): string
    {
        if ($requested
            && preg_match('/^\d{4}-\d{2}$/', $requested)
            && checkdate((int) substr($requested, 5, 2), 1, (int) substr($requested, 0, 4))) {
            return $requested;
        }

        return now()->format('Y-m');
    }

    /** @return list<array{value: string, label: string}> */
    private function availableMonths(): array
    {
        return collect(range(0, 11))->map(function (int $back): array {
            $m = now()->subMonths($back);

            return [
                'value' => $m->format('Y-m'),
                'label' => self::MONTHS[(int) $m->month].' '.$m->year,
            ];
        })->all();
    }

    /** عدد الأيام حتى يوم الراتب القادم. */
    private function daysUntilSalary(int $salaryDay, CarbonImmutable $from): int
    {
        $day = min($salaryDay, $from->daysInMonth);
        $next = $from->setDay($day);

        if ($next->lessThanOrEqualTo($from)) {
            $nextMonth = $from->addMonth();
            $next = $nextMonth->setDay(min($salaryDay, $nextMonth->daysInMonth));
        }

        return (int) $from->diffInDays($next);
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
     * أحداث الأربعة عشر يوماً القادمة: فواتير مستحقة، أقساط، ويوم الراتب.
     *
     * @return list<array{date: string, kind: string, label: string, amount: int}>
     */
    private function calendarEvents($user, int $salaryDay): array
    {
        $today = CarbonImmutable::today();
        $horizon = $today->addDays(14);
        $events = [];

        foreach ($user->bills()->where('is_paid', false)->get() as $bill) {
            $due = CarbonImmutable::parse($bill->due_date);
            if ($due->betweenIncluded($today, $horizon)) {
                $events[] = [
                    'date' => $due->format('Y-m-d'),
                    'kind' => 'bill',
                    'label' => $bill->name,
                    'amount' => (int) $bill->amount,
                ];
            }
        }

        foreach ($user->installments()->where('is_completed', false)->get() as $inst) {
            $dueDay = (int) CarbonImmutable::parse($inst->start_date)->day;
            foreach ([$today, $today->addMonth()] as $anchor) {
                $due = $anchor->setDay(min($dueDay, $anchor->daysInMonth));
                if ($due->betweenIncluded($today, $horizon)) {
                    $events[] = [
                        'date' => $due->format('Y-m-d'),
                        'kind' => 'installment',
                        'label' => $inst->name,
                        'amount' => (int) $inst->monthly_amount,
                    ];
                }
            }
        }

        foreach ([$today, $today->addMonth()] as $anchor) {
            $pay = $anchor->setDay(min($salaryDay, $anchor->daysInMonth));
            if ($pay->betweenIncluded($today, $horizon)) {
                $events[] = [
                    'date' => $pay->format('Y-m-d'),
                    'kind' => 'salary',
                    'label' => 'الراتب',
                    'amount' => (int) $user->incomes()->where('is_recurring', true)->sum('amount'),
                ];
            }
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
