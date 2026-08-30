<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;

/**
 * الأساس المشترك لأدوات المساعد المالي.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  كل استعلام يبدأ من `$this->user->…()`. بلا استثناء واحد.
 * ══════════════════════════════════════════════════════════════════════
 *
 * الافتراض العامل هنا: **الموديل قابل للخداع.** رسالة مستخدم مصنوعة قد
 * تُقنعه بإرسال `id` يخصّ مستخدماً آخر، أو `history` مزوّرة قد تجعله يظنّ
 * أنه أُذن له بذلك. لا شيء من هذا يهمّ إن كان الاستعلام نفسه لا يرى غير
 * صفوف صاحب الجلسة. لذلك:
 *
 *  - لا `Expense::query()` ولا `Income::query()` في أي أداة — الدخول
 *    الوحيد هو `expenses()` و`incomes()` أدناه، وهما `$this->user->…`.
 *  - عند التعديل والحذف، الملكية جزء من الاستعلام نفسه لا شرطاً بعده:
 *    `$this->expenses()->whereKey($id)` يرجّع `null` لعملية غيره، فتُصنَّف
 *    `not_found` بلا تسريب وجودها من عدمه.
 *  - `whereIn('id', $ids)` وحده ممنوع. الملكية أولاً دائماً.
 *
 * التعليمات (system prompt) ليست طبقة حماية، وليست ضمن حساب الأمان هنا
 * إطلاقاً. هي توجيه سلوكي فقط.
 *
 * ── وحدة المبالغ ──
 * قاعدة البيانات بالهللات. الأدوات تتكلّم مع الموديل بـ**الريالات** لأن
 * المستخدم يتكلّم بها، والتحويل في الحدود فقط: `Money::toHalalas()` عند
 * الدخول، و`riyals()` عند الخروج.
 */
abstract class TransactionTool implements Tool
{
    /** النوعان الوحيدان — «العملية» في هذا التطبيق إمّا مصروف أو دخل. */
    public const TYPES = ['expense', 'income'];

    /** السقف الأقصى للعناصر في الاستدعاء الواحد. */
    protected const MAX_ITEMS = 50;

    /** @var array<string, int>|null خريطة اسم الفئة ← id، محمّلة مرة واحدة. */
    private ?array $categoryMap = null;

    public function __construct(protected readonly User $user) {}

    /**
     * مصاريف هذا المستخدم — نقطة الدخول الوحيدة لجدول `expenses`.
     *
     * @return HasMany<Expense, User>
     */
    protected function expenses(): HasMany
    {
        return $this->user->expenses();
    }

    /**
     * مداخيل هذا المستخدم — نقطة الدخول الوحيدة لجدول `incomes`.
     *
     * @return HasMany<Income, User>
     */
    protected function incomes(): HasMany
    {
        return $this->user->incomes();
    }

    /**
     * يجلب عمليةً واحدة يملكها المستخدم، أو `null`.
     *
     * الملكية مفروضة بالعلاقة لا بشرط لاحق. `null` تعني «غير موجودة عندك»
     * ولا تفرّق بين «لا وجود لها» و«ليست لك» — وهذا مقصود.
     */
    protected function ownedTransaction(string $type, int $id): Expense|Income|null
    {
        return $type === 'expense'
            ? $this->expenses()->whereKey($id)->first()
            : $this->incomes()->whereKey($id)->first();
    }

    /**
     * أسماء فئات المستخدم — تُحقن في التعليمات وتُستخدم في التحقّق.
     *
     * @return list<string>
     */
    protected function categoryNames(): array
    {
        return array_keys($this->categories());
    }

    /**
     * يحوّل اسم فئة إلى `id` مملوك لهذا المستخدم.
     *
     * الموديل يرى أسماءً لا أرقاماً — الأرقام تفاصيل schema لا تُكشف له،
     * وأسماء الفئات تختلف بين المستخدمين فلا معنى لتمرير id بينهم.
     */
    protected function categoryId(string $name): ?int
    {
        return $this->categories()[$this->normalize($name)] ?? null;
    }

    /** @return array<string, int> */
    private function categories(): array
    {
        return $this->categoryMap ??= $this->user->categories()
            ->orderBy('id')
            ->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [$this->normalize($name) => $id])
            ->all();
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * تحقّق يرجّع رسالة خطأ بدل أن يرمي.
     *
     * رمي الاستثناء داخل أداة يقطع الـstream في منتصفه ويترك المستخدم أمام
     * فقاعة نصف مكتوبة. إرجاع الخطأ **للموديل** يجعله يصحّح نفسه في الجولة
     * التالية — وهو المسار الذي يجعل «التاريخ لازم YYYY-MM-DD» يعمل فعلاً.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array{0: array<string, mixed>, 1: string|null} [المُتحقَّق، الخطأ]
     */
    protected function check(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return [[], implode(' · ', $validator->errors()->all())];
        }

        return [$validator->validated(), null];
    }

    /**
     * يتحقّق من `type` ويرجّعه، أو `null` إن كان غير مقبول.
     */
    protected function type(mixed $value): ?string
    {
        return in_array($value, self::TYPES, true) ? $value : null;
    }

    /** ريالات (من الموديل) ← هللات (لقاعدة البيانات). */
    protected function halalas(int|float|string $riyals): int
    {
        return Money::toHalalas($riyals);
    }

    /** هللات (من قاعدة البيانات) ← ريالات (للموديل). */
    protected function riyals(int $halalas): float
    {
        return round($halalas / 100, 2);
    }

    /**
     * نجاح — البنية الموحّدة التي تتوقّعها الواجهة وبطاقة الأداة.
     *
     * @param  array<string, mixed>  $data
     */
    protected function ok(string $summary, array $data = []): string
    {
        return $this->encode(['ok' => true, 'summary' => $summary, 'data' => $data]);
    }

    /**
     * فشل — نفس البنية، `ok=false`، ورسالة صالحة لأن يقرأها الموديل ويتصرّف.
     *
     * @param  array<string, mixed>  $data
     */
    protected function fail(string $summary, array $data = []): string
    {
        return $this->encode(['ok' => false, 'summary' => $summary, 'data' => $data]);
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new InvalidArgumentException('Tool result could not be encoded.');
        }

        return $json;
    }

    /**
     * يستخرج قائمة العناصر من مُدخل الأداة ويطبّق سقف الـ50.
     *
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    protected function items(mixed $raw, string $field): array
    {
        if (! is_array($raw) || $raw === []) {
            return [[], "The [{$field}] array is required and must contain at least one item."];
        }

        if (count($raw) > self::MAX_ITEMS) {
            return [[], sprintf(
                'Too many items: %d. A single call accepts at most %d. Split the work across several calls.',
                count($raw),
                self::MAX_ITEMS,
            )];
        }

        foreach ($raw as $item) {
            if (! is_array($item)) {
                return [[], "Every entry in [{$field}] must be an object."];
            }
        }

        return [array_values($raw), null];
    }

    /** الفئات المتاحة كنصّ واحد — يُلحق برسائل الخطأ ليصحّح الموديل نفسه. */
    protected function categoryHint(): string
    {
        return 'Available categories: '.implode(', ', $this->categoryNames()).'.';
    }
}
