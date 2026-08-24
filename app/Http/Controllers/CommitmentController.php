<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Services\BudgetGuard;
use App\Services\CommitmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentController extends Controller
{
    /** أنواع المبالغ المعروفة — الالتزام المتغيّر مسموح للفواتير فقط. */
    private const FIXED_AMOUNT_KINDS = ['rent', 'installment', 'subscription'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();
        $commitments = $user->commitments()->active()->orderBy('kind')->orderBy('name')->get();

        return Inertia::render('Commitments', [
            'commitments' => $service->hydrate($commitments, $period),
            'income' => $service->periodIncome($period),
            'salaryDay' => (int) ($user->salary_day ?? 27),
            'periodLabel' => $period['label'],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $this->validateStore($request, $user);

        $commitment = DB::transaction(function () use ($validated, $user): Commitment {
            return $user->commitments()->create($this->payload($validated, $user));
        });

        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();
        $obligations = $service->obligationsForPeriod($period);
        $income = $service->periodIncome($period);

        $response = redirect()->back();

        // القاعدة الحاكمة: فاتورة/إيجار/اشتراك تجاوز الدخل تُحفظ، وتُحذّر فقط.
        if ($validated['kind'] !== 'installment' && $income > 0 && $obligations > $income) {
            $response->with('warnings', [[
                'severity' => 'warn',
                'title' => 'التزاماتك تجاوزت دخلك',
                'detail' => sprintf(
                    'أصبح مجموع الالتزامات %s ر.س وهو أكبر من دخلك %s ر.س.',
                    $this->riyals($obligations),
                    $this->riyals($income),
                ),
            ]]);
        }

        return $response;
    }

    public function pay(Request $request, Commitment $commitment)
    {
        $user = $request->user();
        $this->authorizeOwnership($commitment, $user->id);

        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();

        $amount = $this->resolvePayAmount($request, $commitment, $service);

        if ($commitment->payments()->where('period_key', $period['key'])->exists()) {
            throw ValidationException::withMessages([
                'amount' => 'هذا الالتزام مدفوع بالفعل في هذه الدورة.',
            ]);
        }

        DB::transaction(function () use ($commitment, $amount, $period): void {
            $commitment->payments()->create([
                'amount' => $amount,
                'paid_at' => now()->toDateString(),
                'period_key' => $period['key'],
                'source' => 'manual',
            ]);

            if ($commitment->kind === 'installment' && $commitment->months_count > 0) {
                $paid = min($commitment->months_count, $commitment->months_paid + 1);
                $commitment->update([
                    'months_paid' => $paid,
                    'is_active' => $paid < $commitment->months_count,
                ]);
            }
        });

        $response = redirect()->back();

        // تجاوز الدفع «المتبقي لك» لا يُمنع — يمرّ تحذير فقط (الالتزام واقعة).
        $income = $service->periodIncome($period);
        if ($income > 0 && $service->obligationsForPeriod($period) > $income) {
            $response->with('warnings', [[
                'severity' => 'warn',
                'title' => 'دفعت من أصل محجوز لك',
                'detail' => sprintf(
                    'التزاماتك تخطّت دخلك %s ر.س — هذا مبلغ سيخرج فعلاً أو خرج.',
                    $this->riyals($income),
                ),
            ]]);
        }

        return $response;
    }

    public function undoPay(Request $request, Commitment $commitment)
    {
        $user = $request->user();
        $this->authorizeOwnership($commitment, $user->id);

        $periodKey = CommitmentService::for($user)->currentPeriod()['key'];

        $payment = $commitment->payments()->where('period_key', $periodKey)->first();

        if ($payment) {
            DB::transaction(function () use ($commitment, $payment): void {
                if ($commitment->kind === 'installment' && $commitment->months_paid > 0) {
                    $commitment->update([
                        'months_paid' => max(0, $commitment->months_paid - 1),
                        'is_active' => true,
                    ]);
                }
                $payment->delete();
            });
        }

        return redirect()->back();
    }

    public function update(Request $request, Commitment $commitment)
    {
        $user = $request->user();
        $this->authorizeOwnership($commitment, $user->id);

        $validated = $this->validateStore($request, $user, $commitment);

        $payload = $this->payload($validated, $user);
        $payload['months_paid'] = $commitment->months_paid;
        $payload['is_active'] = $commitment->is_active;

        $commitment->update($payload);

        return redirect()->back();
    }

    /**
     * «الحذف» = أرشفة (is_active=false) — تبقى السجلّات كما هي في التقارير.
     */
    public function destroy(Request $request, Commitment $commitment)
    {
        $user = $request->user();
        $this->authorizeOwnership($commitment, $user->id);

        $commitment->update(['is_active' => false]);

        return redirect()->back();
    }

    public function edit(Request $request, Commitment $commitment)
    {
        $user = $request->user();
        $this->authorizeOwnership($commitment, $user->id);

        return redirect()->route('commitments');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStore(Request $request, $user, ?Commitment $existing = null): array
    {
        $isInstallment = $request->input('kind') === 'installment';
        $isVariable = (bool) $request->input('is_variable');

        $rules = [
            'kind' => ['required', 'in:bill,rent,installment,subscription'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'months_count' => ['nullable', 'integer'],
            'is_variable' => ['boolean'],
            'payment_method' => ['required', 'in:auto,manual'],
            'due_type' => ['required', 'in:salary_day,month_day,fixed_date'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'due_date' => ['nullable', 'date'],
            'notify_before' => ['boolean'],
            'notify_on_due' => ['boolean'],
            'notify_late' => ['boolean'],
            'reserve_in_budget' => ['boolean'],
        ];

        $request->merge([
            'amount' => $request->input('amount') ?? 0,
            'total_amount' => $request->input('total_amount') ?? 0,
            'months_count' => $request->input('months_count') ?? 0,
            'is_variable' => $request->boolean('is_variable'),
            'notify_before' => $request->has('notify_before') ? $request->boolean('notify_before') : true,
            'notify_on_due' => $request->has('notify_on_due') ? $request->boolean('notify_on_due') : true,
            'notify_late' => $request->has('notify_late') ? $request->boolean('notify_late') : true,
            'reserve_in_budget' => $request->has('reserve_in_budget') ? $request->boolean('reserve_in_budget') : true,
        ]);

        $validated = $request->validate($rules);

        if ($isInstallment) {
            $monthCount = (int) $validated['months_count'];
            if ($monthCount < 2 || $monthCount > 480) {
                throw ValidationException::withMessages([
                    'months_count' => 'عدد أشهر القسط بين 2 و 480.',
                ]);
            }
            if ((int) $validated['total_amount'] <= 0) {
                throw ValidationException::withMessages([
                    'total_amount' => 'أدخل المبلغ الكامل للقسط.',
                ]);
            }
        } elseif (in_array($validated['kind'], self::FIXED_AMOUNT_KINDS, true)) {
            if ($isVariable) {
                throw ValidationException::withMessages([
                    'is_variable' => 'المبلغ المتغيّر مسموح للفواتير فقط.',
                ]);
            }
            if ((int) $validated['amount'] <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'حدّد المبلغ الشهري.',
                ]);
            }
        } elseif ($validated['kind'] === 'bill' && ! $isVariable && (int) $validated['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'حدّد المبلغ، أو فعّل «المبلغ متغيّر».',
            ]);
        }

        $this->validateDueType($validated, $user);

        // ▸ المنع الوحيد: قسط جديد يرفع الالتزامات فوق الدخل.
        if ($isInstallment) {
            $monthly = $this->calculateInstallmentMonthly((int) $validated['total_amount'], (int) $validated['months_count']);
            BudgetGuard::for($user)->assertCommitmentFits($monthly, 0, 'amount');
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated */
    private function validateDueType(array $validated, $user): void
    {
        $type = $validated['due_type'];

        if ($type === 'month_day') {
            $day = (int) ($validated['due_day'] ?? 0);
            if ($day < 1 || $day > 31) {
                throw ValidationException::withMessages([
                    'due_day' => 'يوم الاستحقاق بين 1 و 31.',
                ]);
            }
        }

        if ($type === 'fixed_date') {
            if (empty($validated['due_date'])) {
                throw ValidationException::withMessages([
                    'due_date' => 'اختر تاريخ الاستحقاق.',
                ]);
            }
            if (CarbonImmutable::parse($validated['due_date'])->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'due_date' => 'تاريخ الاستحقاق يجب أن يكون مستقبلياً.',
                ]);
            }
        }

        if ($type === 'salary_day' && $user->salary_day === null) {
            throw ValidationException::withMessages([
                'due_type' => 'حدّد يوم نزول راتبك أولاً من الإعدادات.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated, $user): array
    {
        $kind = $validated['kind'];
        $isInstallment = $kind === 'installment';

        $monthly = $isInstallment
            ? $this->calculateInstallmentMonthly((int) $validated['total_amount'], (int) $validated['months_count'])
            : (int) $validated['amount'];

        $icon = [
            'bill' => 'receipt',
            'rent' => 'home',
            'installment' => 'credit-card',
            'subscription' => 'repeat',
        ][$kind] ?? 'ellipsis';

        return [
            'user_id' => $user->id,
            'kind' => $kind,
            'name' => $validated['name'],
            'icon' => $icon,
            'color' => null,
            'amount' => $isInstallment
                ? $monthly
                : ($kind === 'bill' && $validated['is_variable'] ? null : (int) $validated['amount']),
            'is_variable' => (bool) $validated['is_variable'],
            'total_amount' => $isInstallment ? (int) $validated['total_amount'] : 0,
            'months_count' => $isInstallment ? (int) $validated['months_count'] : 0,
            'months_paid' => 0,
            'payment_method' => $validated['payment_method'],
            'due_type' => $validated['due_type'],
            'due_day' => $validated['due_type'] === 'month_day' ? (int) $validated['due_day'] : null,
            'due_date' => $validated['due_type'] === 'fixed_date' ? $validated['due_date'] : null,
            'notify_before' => (bool) $validated['notify_before'],
            'notify_on_due' => (bool) $validated['notify_on_due'],
            'notify_late' => (bool) $validated['notify_late'],
            'reserve_in_budget' => (bool) $validated['reserve_in_budget'],
            'is_active' => true,
        ];
    }

    private function calculateInstallmentMonthly(int $total, int $months): int
    {
        return $months > 0 ? (int) ceil($total / $months) : 0;
    }

    private function resolvePayAmount(Request $request, Commitment $commitment, CommitmentService $service): int
    {
        if ($commitment->is_variable && $commitment->amount === null) {
            $amount = (int) round((float) $request->input('amount', 0));

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'سجّل مبلغ هذه الفاتورة أولاً.',
                ]);
            }

            return $amount;
        }

        return $service->expectedAmount($commitment);
    }

    private function authorizeOwnership(Commitment $commitment, int $userId): void
    {
        if ($commitment->user_id !== $userId) {
            abort(404);
        }
    }

    private function riyals(int $halalas): string
    {
        return number_format($halalas / 100, $halalas % 100 === 0 ? 0 : 2);
    }
}
