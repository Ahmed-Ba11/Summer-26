<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Services\BudgetGuard;
use App\Services\CommitmentService;
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
            // بلا فترة صريحة: كل التزام يعرض ظهوره القابل للسداد
            'commitments' => $service->hydrate($commitments),
            'income' => $service->periodIncome($period),
            'salaryDay' => (int) ($user->salary_day ?? 27),
            'periodLabel' => $period['label'].' · '.$period['range'],
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

        $label = [
            'bill' => 'فاتورة',
            'rent' => 'إيجار',
            'installment' => 'قسط',
            'subscription' => 'اشتراك',
        ][$validated['kind']];
        $message = "تمت إضافة {$label} «{$validated['name']}»";
        // الصفحة تعرض إشعار النجاح في onSuccess — لا نكرّره من الخادم
        // وإلا ظهر مرّتين متطابقتين.
        $response = redirect()->back()->with('success', $message);

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
        // الظهور المستحقّ فعلاً — من مولّد الظهورات نفسه الذي يقرأ منه
        // التقويم، فالسداد يقع على ما يراه المستخدم لا على ظهور آخر.
        $occurrence = $service->payableOccurrence($commitment);

        $amount = $this->resolvePayAmount($request, $commitment, $service);

        if ($occurrence['is_paid']) {
            throw ValidationException::withMessages([
                'amount' => 'هذا الاستحقاق مدفوع بالفعل.',
            ]);
        }

        DB::transaction(function () use ($service, $commitment, $occurrence, $amount): void {
            $service->recordPayment($commitment, $occurrence, $amount);
        });

        $response = redirect()->back();

        // تجاوز الدفع «المتبقي لك» لا يُمنع — يمرّ تحذير فقط (الالتزام واقعة).
        // التحذير على ميزانية اليوم دائماً، أياً كانت فترة الظهور المسدَّد.
        $current = $service->currentPeriod();
        $income = $service->periodIncome($current);
        if ($income > 0 && $service->obligationsForPeriod($current) > $income) {
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

        // التراجع يحذف آخر سداد كُتب داخل نافذة السداد — نفس نطاق
        // `payableOccurrence` وبعكس ترتيبه.
        $service = CommitmentService::for($user);
        $dueDates = array_column(
            $service->occurrences($commitment, $service->windowPeriods()),
            'due_date',
        );

        $payment = collect($dueDates)
            ->map(fn (string $due) => $service->paymentForDue($commitment, $due))
            ->filter()
            ->sortByDesc('id')
            ->first();

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

    /**
     * @return array<string, mixed>
     */
    private function validateStore(Request $request, $user, ?Commitment $existing = null): array
    {
        $isInstallment = $request->input('kind') === 'installment';
        $isVariable = (bool) $request->input('is_variable');

        $rules = [
            'kind' => ['required', 'in:bill,rent,installment,subscription'],
            'name' => ['required', 'string', 'max:60'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'monthly_amount' => ['nullable', 'integer', 'min:0'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'months_count' => ['nullable', 'integer'],
            'is_variable' => ['boolean'],
            'payment_method' => ['required', 'in:auto,manual'],
            'due_type' => ['required', 'in:salary_day,month_day'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'notify_when' => ['required', 'in:before_3,on_due,none'],
            'reserve_in_budget' => ['boolean'],
        ];

        if ($isInstallment && ! $request->has('amount')) {
            $request->merge(['amount' => $request->input('monthly_amount')]);
        }

        $request->merge([
            'amount' => $request->input('amount') ?? 0,
            'total_amount' => $request->input('total_amount') ?? 0,
            'months_count' => $request->input('months_count') ?? 0,
            'is_variable' => $request->boolean('is_variable'),
            'notify_when' => $request->input('notify_when', 'before_3'),
            'reserve_in_budget' => $request->has('reserve_in_budget') ? $request->boolean('reserve_in_budget') : true,
        ]);

        $validated = $request->validate($rules, [
            'name.required' => 'الاسم مطلوب — بدونه لن تعرف الالتزام في القائمة.',
            'name.max' => 'الاسم يجب ألا يتجاوز 60 حرفاً.',
            'amount.integer' => 'المبلغ يجب أن يكون بالهللات.',
        ]);

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
            if ((int) $validated['amount'] <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'أدخل قيمة القسط الشهري.',
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
            BudgetGuard::for($user)->assertCommitmentFits((int) $validated['amount'], $existing?->amount ?? 0, 'amount');
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

        $monthly = (int) $validated['amount'];

        $icon = [
            'bill' => 'receipt',
            'rent' => 'house',
            'installment' => 'credit-card',
            'subscription' => 'repeat',
        ][$kind] ?? 'ellipsis';

        return [
            'user_id' => $user->id,
            'kind' => $kind,
            'name' => $validated['name'],
            'icon' => $icon,
            'color' => null,
            'amount' => $kind === 'bill' && $validated['is_variable'] ? null : $monthly,
            'is_variable' => (bool) $validated['is_variable'],
            'total_amount' => $isInstallment ? (int) $validated['total_amount'] : 0,
            'months_count' => $isInstallment ? (int) $validated['months_count'] : 0,
            'months_paid' => 0,
            'payment_method' => $validated['payment_method'],
            'due_type' => $validated['due_type'],
            'due_day' => $validated['due_type'] === 'month_day' ? (int) $validated['due_day'] : null,
            'notify_when' => $validated['notify_when'],
            'reserve_in_budget' => (bool) $validated['reserve_in_budget'],
            'is_active' => true,
        ];
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
