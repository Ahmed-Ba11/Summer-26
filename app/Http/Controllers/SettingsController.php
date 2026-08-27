<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommitmentPayment;
use App\Services\SalaryMonthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الإعدادات — صفحة واحدة بخمس مجموعات، بلا زر «حفظ».
 *
 * كل مفتاح يُرسل تغييره فور لمسه ويرجع بـ`toast`. زر الحفظ في شاشة إعدادات
 * جوال يعني أن نصف التغييرات تضيع: المستخدم يبدّل مفتاحاً ثم يرجع بالإيماءة
 * ولا يمرّ على الزر أصلاً.
 */
class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = SalaryMonthService::for($user)->current();

        return Inertia::render('Settings', [
            'settings' => [
                'name' => $user->display_name ?: $user->name,
                'email' => $user->email,
                'monthly_income' => (int) $user->monthly_income,
                'salary_day' => (int) ($user->salary_day ?? 27),
                'monthly_savings_target' => (int) $user->monthly_savings_target,
                'locale' => $user->locale ?: 'ar',
                'theme' => $user->theme ?: 'system',
                'font_scale' => $user->font_scale ?: 'md',
                'biometric_lock' => (bool) $user->biometric_lock,
                'notify_due' => (bool) $user->notify_due,
                'notify_budget' => (bool) $user->notify_budget,
                'notify_salary' => (bool) $user->notify_salary,
            ],
            'salaryMonth' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'range' => $period['range'],
            ],
        ]);
    }

    /**
     * تحديث تفضيل واحد — الطلب يحمل الحقل الذي تغيّر فقط.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'monthly_income' => ['sometimes', 'integer', 'min:0'],
            'salary_day' => ['sometimes', 'integer', 'between:1,31'],
            'monthly_savings_target' => ['sometimes', 'integer', 'min:0'],
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'font_scale' => ['sometimes', Rule::in(['sm', 'md', 'lg'])],
            'biometric_lock' => ['sometimes', 'boolean'],
            'notify_due' => ['sometimes', 'boolean'],
            'notify_budget' => ['sometimes', 'boolean'],
            'notify_salary' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if (array_key_exists('name', $validated)) {
            $validated['display_name'] = $validated['name'];
            unset($validated['name']);
        }

        $user->update($validated);

        // زر المظهر في الرأس يبدّل عدّة مرّات في الدقيقة — «تم الحفظ» بعد كل
        // ضغطة ضجيج لا تأكيد، فيمرّ صامتاً.
        if ($request->boolean('silent')) {
            return redirect()->back();
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'تم الحفظ',
        ]);
    }

    /** نسخة احتياطية — كل بياناتك المالية في ملف JSON واحد. */
    public function backup(Request $request): JsonResponse
    {
        $user = $request->user();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'currency' => 'SAR',
            'note' => 'كل المبالغ بالهللات (100 هللة = 1 ريال).',
            'profile' => [
                'name' => $user->display_name ?: $user->name,
                'monthly_income' => (int) $user->monthly_income,
                'salary_day' => (int) ($user->salary_day ?? 27),
                'monthly_savings_target' => (int) $user->monthly_savings_target,
            ],
            'categories' => $user->categories()->get(['id', 'name', 'icon', 'color'])->all(),
            'incomes' => $user->incomes()->get(['id', 'amount', 'source', 'description', 'income_date'])->all(),
            'expenses' => $user->expenses()->get(['id', 'amount', 'category_id', 'description', 'expense_date'])->all(),
            'commitments' => $user->commitments()->get(['id', 'kind', 'name', 'amount', 'due_type', 'due_day', 'months_count', 'months_paid'])->all(),
            'budgets' => $user->budgets()->get(['id', 'category_id', 'month', 'amount'])->all(),
            'savings_goals' => $user->savingsGoals()->get(['id', 'name', 'target_amount', 'current_amount', 'target_date'])->all(),
        ];

        return response()
            ->json($payload, 200, [
                'Content-Disposition' => 'attachment; filename="muwaffir-backup-'.now()->format('Y-m-d').'.json"',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * حذف كل البيانات المالية — الحساب يبقى، والإعداد يبدأ من أوّله.
     *
     * التأكيد بكتابة الاسم لا بضغطة «متأكّد؟»: الضغطة تُنفَّذ بالعادة،
     * وكتابة الاسم تتطلّب قراءة ما هو مكتوب فوقها.
     */
    public function destroyData(Request $request): RedirectResponse
    {
        $user = $request->user();
        $expected = $user->display_name ?: $user->name;

        $request->validate(['confirm' => ['required', 'string']]);

        if (trim((string) $request->input('confirm')) !== trim($expected)) {
            throw ValidationException::withMessages([
                'confirm' => 'الاسم غير مطابق — اكتبه كما هو أعلاه.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            CommitmentPayment::whereIn('commitment_id', $user->commitments()->pluck('id'))->delete();
            $user->commitments()->delete();
            $user->savingsDeposits()->delete();
            $user->savingsGoals()->delete();
            $user->salaryPeriods()->delete();
            $user->budgets()->delete();
            $user->assistantMessages()->delete();

            // «حذف كل البيانات» يعني الحذف فعلاً — الحذف الناعم يترك الصفوف
            // في الجدول، فيرجع المستخدم لواجهة فاضية وقاعدة بيانات ممتلئة.
            $user->expenses()->withTrashed()->forceDelete();
            $user->incomes()->withTrashed()->forceDelete();
            $user->recurringTransactions()->withTrashed()->forceDelete();
            $user->bills()->withTrashed()->forceDelete();
            $user->installments()->withTrashed()->forceDelete();

            $user->update([
                'monthly_income' => 0,
                'monthly_savings_target' => 0,
                'onboarding_step' => 0,
                'onboarding_completed_at' => null,
            ]);
        });

        return redirect()->route('welcome')->with('toast', [
            'type' => 'success',
            'message' => 'حُذفت كل بياناتك — ابدأ من جديد',
        ]);
    }
}
