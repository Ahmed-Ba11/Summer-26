<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Commitment;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * قواعد التحقّق للمصاريف والمداخيل — مصدر واحد للحقيقة.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  القواعد هنا، والمستهلكون يستوردون. لا نسخة ثانية في أي مكان.
 * ══════════════════════════════════════════════════════════════════════
 *
 * كانت مكتوبة inline داخل closures في routes/web.php. بقاؤها هناك كان
 * يعني أن أدوات المساعد الذكي ستكتب قواعد موازية، فيتباعد المساران بأول
 * تعديل: المستخدم يُمنع من مبلغ والمساعد يمرّره، أو العكس. الآن الاثنان
 * يقرآن من هنا.
 *
 * ملاحظتان تسريان على كل ما في الملف:
 *
 *  1. **المبالغ بالريالات** في هذه القواعد لا بالهللات. الواجهة يكتب
 *     المستخدم فيها ريالات، والتحويل بـ`Money::toHalalas()` بعد التحقّق.
 *     لا تُمرّر هللات على هذه القواعد.
 *
 *  2. **`$userId` صريح لا `auth()->id()`.** أدوات الوكيل تعمل على مستخدم
 *     مُمرَّر عبر الـconstructor، وقد تُستدعى خارج سياق طلب مُصادَق.
 *     تمرير الـid صراحةً يمنع أن تتسرّب فئة مستخدم لمستخدم آخر.
 */
final class TransactionRules
{
    /** @var list<string> */
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var list<string> */
    public const FUNDING_SOURCES = ['savings', 'unlogged_income', 'overspend'];

    /**
     * تاريخ حرّ — يقبل ما يقبله `date` في Laravel.
     *
     * تستعمله الواجهة: المُدخل يأتي من `DateSheet.svelte` بصيغة سليمة أصلاً.
     */
    private const DATE = 'date';

    /**
     * تاريخ صارم — `YYYY-MM-DD` حرفياً ولا شيء غيره.
     *
     * تستعمله أدوات الوكيل. السبب عملي: الموديل يرسل `"today"` و«أمس»
     * إن سُمح له، ثم يفسّرها الـbackend تخميناً فتُسجَّل عملية بتاريخ خاطئ
     * لا يلاحظه أحد. الرفض الصريح يجعل الموديل يصحّح نفسه في الجولة التالية.
     */
    private const STRICT_DATE = 'date_format:Y-m-d';

    /**
     * قواعد إنشاء مصروف — نسخة الواجهة (`POST /expenses`).
     *
     * @return array<string, mixed>
     */
    public static function expenseStore(int $userId): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'category_id' => ['required', self::categoryRule($userId)],
            'description' => 'nullable|string|max:500',
            'expense_date' => 'required|'.self::DATE,
            'is_recurring' => 'sometimes|boolean',
            'frequency' => 'nullable|in:'.implode(',', self::FREQUENCIES),
            'next_due_date' => 'nullable|'.self::DATE,
            // المصروف المرتبط بالتزام يسدّده — يكتب صفّاً في commitment_payments
            'commitment_id' => ['nullable', self::commitmentRule($userId)],
            'funding_source' => 'nullable|in:'.implode(',', self::FUNDING_SOURCES),
            'savings_goal_id' => 'nullable|integer',
            'income_amount' => 'nullable|integer|min:0',
            'income_source' => 'nullable|string|max:255',
        ];
    }

    /**
     * قواعد تعديل مصروف — نسخة الواجهة (`PUT /expenses/{expense}`).
     *
     * أضيق من الإنشاء عمداً: التمويل والالتزام يُحدَّدان عند التسجيل ولا
     * يُعاد فتحهما بتعديل عابر.
     *
     * @return array<string, mixed>
     */
    public static function expenseUpdate(int $userId): array
    {
        return Arr::only(self::expenseStore($userId), [
            'amount', 'category_id', 'description', 'expense_date',
            'is_recurring', 'frequency', 'next_due_date',
        ]);
    }

    /**
     * قواعد إنشاء دخل — نسخة الواجهة (`POST /income`).
     *
     * @return array<string, mixed>
     */
    public static function incomeStore(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'source' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
            'income_date' => 'required|'.self::DATE,
            'is_recurring' => 'sometimes|boolean',
            'frequency' => 'nullable|in:'.implode(',', self::FREQUENCIES),
            'next_due_date' => 'nullable|'.self::DATE,
        ];
    }

    /**
     * قواعد تعديل دخل — نسخة الواجهة (`PUT /income/{income}`).
     *
     * مطابقة للإنشاء، وتبقى دالّة مستقلّة ليفترقا بلا كسر إن لزم.
     *
     * @return array<string, mixed>
     */
    public static function incomeUpdate(): array
    {
        return self::incomeStore();
    }

    /*
    |--------------------------------------------------------------------------
    | نسخ الوكيل
    |--------------------------------------------------------------------------
    |
    | نفس القواعد أعلاه حرفياً، بفارق واحد: التاريخ صارم. لا تُكتب هنا أي
    | قاعدة جديدة — كل ما تفعله هذه الدوال هو الاشتقاق مما سبق، فأي تعديل
    | على قواعد الواجهة يسري على الوكيل تلقائياً.
    |
    | الحقول المحذوفة (`is_recurring` · `frequency` · `next_due_date`) مقصودة:
    | العمليات المتكرّرة لها صفحتها ومنطقها، والوكيل لا يُنشئها ولا يحوّل
    | عمليةً عاديةً إليها.
    |
    */

    /**
     * @return array<string, mixed>
     */
    public static function agentExpenseStore(int $userId): array
    {
        return self::withStrictDates(Arr::only(self::expenseStore($userId), [
            'amount', 'category_id', 'description', 'expense_date',
            'funding_source', 'savings_goal_id',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function agentExpenseUpdate(int $userId): array
    {
        return self::withStrictDates(Arr::only(self::expenseUpdate($userId), [
            'amount', 'category_id', 'description', 'expense_date',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function agentIncomeStore(): array
    {
        return self::withStrictDates(Arr::only(self::incomeStore(), [
            'amount', 'source', 'description', 'income_date',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function agentIncomeUpdate(): array
    {
        return self::agentIncomeStore();
    }

    /**
     * الفئة موجودة **ومملوكة لهذا المستخدم**.
     *
     * `where('user_id', …)` داخل القاعدة لا بعدها: بدونه يستطيع مُدخَلٌ
     * مصنوع أن يربط مصروفاً بفئة مستخدم آخر، فتتسرّب أسماء فئاته في
     * أول صفحة تعرض المصروف.
     */
    public static function categoryRule(int $userId): Exists
    {
        return Rule::exists(Category::class, 'id')->where('user_id', $userId);
    }

    /** الالتزام موجود ومملوك لهذا المستخدم — لنفس سبب `categoryRule()`. */
    public static function commitmentRule(int $userId): Exists
    {
        return Rule::exists(Commitment::class, 'id')->where('user_id', $userId);
    }

    /**
     * يبدّل كل قاعدة تاريخ حرّة بنظيرتها الصارمة.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private static function withStrictDates(array $rules): array
    {
        foreach ($rules as $field => $rule) {
            if (! is_string($rule)) {
                continue;
            }

            // مطابقة على مستوى القاعدة الكاملة لا نصّاً جزئياً: `str_replace`
            // على «date» كانت ستمسّ `date_format` و`next_due_date` أيضاً.
            $tokens = array_map(
                fn (string $token): string => $token === self::DATE ? self::STRICT_DATE : $token,
                explode('|', $rule),
            );

            $rules[$field] = implode('|', $tokens);
        }

        return $rules;
    }
}
