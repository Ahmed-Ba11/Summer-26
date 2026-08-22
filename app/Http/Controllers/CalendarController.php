<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    private const MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = $this->resolveMonth($request->query('month'));
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $events = collect();

        $user->bills()
            ->whereBetween('due_date', [$start, $end])
            ->orderBy('due_date')
            ->get()
            ->each(function ($bill) use ($events): void {
                $events->push([
                    'id' => $bill->id,
                    'date' => $bill->due_date->format('Y-m-d'),
                    'kind' => 'bill',
                    'label' => $bill->name,
                    'amount' => (int) ($bill->amount ?? 0),
                    'isPaid' => (bool) $bill->is_paid,
                    'canPay' => ! $bill->is_paid,
                    'editUrl' => '/bills?edit='.$bill->id,
                ]);
            });

        $user->installments()
            ->orderBy('start_date')
            ->get()
            ->each(function ($installment) use ($events, $start): void {
                $installmentStart = CarbonImmutable::parse($installment->start_date)->startOfMonth();
                $monthIndex = $installmentStart->diffInMonths($start, false);

                if ($monthIndex < 0 || $monthIndex >= $installment->total_months) {
                    return;
                }

                $day = min(CarbonImmutable::parse($installment->start_date)->day, $start->daysInMonth);
                $isPaid = $monthIndex < $installment->paid_months;

                $events->push([
                    'id' => $installment->id,
                    'date' => $start->setDay($day)->format('Y-m-d'),
                    'kind' => 'installment',
                    'label' => $installment->name,
                    'amount' => (int) $installment->monthly_amount,
                    'isPaid' => $isPaid,
                    'canPay' => ! $isPaid,
                    'editUrl' => '/installments',
                ]);
            });

        $salaryAmount = (int) $user->incomes()->where('is_recurring', true)->sum('amount');
        if ($salaryAmount > 0) {
            $salaryDay = (int) ($user->salary_day ?? 27);
            $events->push([
                'id' => null,
                'date' => ($salaryDay === 0 ? $end : $start->setDay(min($salaryDay, $start->daysInMonth)))->format('Y-m-d'),
                'kind' => 'salary',
                'label' => 'الراتب',
                'amount' => $salaryAmount,
                'isPaid' => false,
                'canPay' => false,
                'editUrl' => null,
            ]);
        }

        $user->savingsDeposits()
            ->with('savingsGoal')
            ->whereBetween('deposited_at', [$start, $end])
            ->orderBy('deposited_at')
            ->get()
            ->each(function ($deposit) use ($events): void {
                $events->push([
                    'id' => $deposit->id,
                    'date' => $deposit->deposited_at->format('Y-m-d'),
                    'kind' => 'savings',
                    'label' => 'إيداع ادخار'.($deposit->savingsGoal?->name ? ' — '.$deposit->savingsGoal->name : ''),
                    'amount' => (int) $deposit->amount,
                    'isPaid' => true,
                    'canPay' => false,
                    'editUrl' => '/savings',
                ]);
            });

        return Inertia::render('Calendar', [
            'month' => $month,
            'monthLabel' => self::MONTHS[(int) $start->month].' '.$start->year,
            'previousMonth' => $start->subMonth()->format('Y-m'),
            'nextMonth' => $start->addMonth()->format('Y-m'),
            'events' => $events->sortBy(fn (array $event): string => $event['date'].'-'.$event['kind'])->values()->all(),
        ]);
    }

    private function resolveMonth(mixed $requested): string
    {
        $month = is_string($requested) ? $requested : null;

        if ($month
            && preg_match('/^\d{4}-\d{2}$/', $month)
            && checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) {
            return $month;
        }

        return now()->format('Y-m');
    }
}
