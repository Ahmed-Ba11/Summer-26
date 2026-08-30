<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\ExpenseFundingService;
use App\Support\TransactionRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

/**
 * تسجيل عملية أو أكثر — أداة جماعية من الأساس.
 *
 * «أضف ٣ مصروفات» يجب أن تكون استدعاءً واحداً بثلاثة عناصر لا ثلاثة
 * استدعاءات: ثلاث جولات كاملة للمزوّد تعني ثلاثة أضعاف الزمن والتوكنات،
 * وتعني أن فشل الثالث يترك الأولين مسجَّلين بلا تراجع.
 *
 * ── تمويل المصروف ──
 * المصاريف تمرّ بـ`ExpenseFundingService` لا بـ`create()` مباشرة، وهي
 * ترفض المصروف الذي يتجاوز «المتبقي لك» ما لم يُحدَّد مصدره. هذا مقصود:
 * قاعدة «الفلوس لازم تجي من مكان» أهمّ من راحة الوكيل. عند الرفض ترجّع
 * الأداة الخطأ للموديل ليسأل المستخدم عن المصدر ثم يعيد الاستدعاء
 * بـ`funding_source` — وهو المسار الذي يسلكه المستخدم في الواجهة حرفياً.
 */
final class CreateTransactions extends TransactionTool
{
    public function description(): string
    {
        return <<<'TEXT'
        Creates one or more financial records for the current user.

        ALWAYS BULK: pass every record the user asked for in a SINGLE call, as
        several entries in `transactions`. "Add 3 expenses: 20 transport, 15
        coffee, 80 groceries" is ONE call with three entries, never three calls.
        Maximum 50 entries per call. Either all entries are saved or none are.

        BEFORE CALLING, make sure you actually have what is required. If the
        amount, the date, or (for an expense) the category is missing or
        ambiguous, ASK THE USER. Do not invent a value and do not fall back to a
        default. An expense needs `category`; an income needs `source`.

        FUNDING: if an expense exceeds what the user has left to spend, the call
        is REJECTED with `funding_required` and nothing is saved. That is not a
        bug — the app requires the money to come from somewhere. Tell the user
        the amount is over their remaining balance, ask which source applies,
        then call again with `funding_source` set to one of:
          savings         — take it from a savings goal
          unlogged_income — they received income they never recorded
          overspend       — they simply overspent (last resort)

        RETURNS: { created: [{ id, type }], count }.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
                ->min(1)
                ->max(50)
                ->items($schema->object([
                    'type' => $schema->string()
                        ->enum(self::TYPES)
                        ->required()
                        ->description('"expense" for money spent, "income" for money received.'),

                    'amount' => $schema->number()
                        ->min(0.01)
                        ->required()
                        ->description('Amount in Saudi Riyals. ALWAYS POSITIVE, even for an expense — the type already says it is money going out. At most two decimals.'),

                    'date' => $schema->string()
                        ->required()
                        ->description('The date of the record, written literally as YYYY-MM-DD (for example 2026-08-29). Relative words such as "today", "yesterday" or "أمس" are REJECTED — work out the real date from the current date given in your instructions and send that.'),

                    'category' => $schema->string()
                        ->description('REQUIRED FOR EXPENSES. One of the category names listed in your instructions, spelled exactly. Ignored for incomes.'),

                    'source' => $schema->string()
                        ->max(500)
                        ->description('REQUIRED FOR INCOMES. Where the money came from, e.g. "راتب". Ignored for expenses.'),

                    'description' => $schema->string()
                        ->max(500)
                        ->description('Short free-text note, e.g. "قهوة". Optional.'),

                    'funding_source' => $schema->string()
                        ->enum(TransactionRules::FUNDING_SOURCES)
                        ->description('Expenses only. Send it ONLY after a previous call was rejected with `funding_required` and the user told you which source to use. Never guess it.'),
                ])->withoutAdditionalProperties())
                ->required()
                ->description('The records to create. One entry per record.'),
        ];
    }

    public function handle(Request $request): string
    {
        [$items, $error] = $this->items($request->array('transactions'), 'transactions');

        if ($error !== null) {
            return $this->fail($error);
        }

        $prepared = [];

        // التحقّق كاملاً قبل أي كتابة: نريد رسالة خطأ واحدة جامعة يصحّح بها
        // الموديل نفسه، لا كتابةً جزئية ثم تراجعاً.
        foreach ($items as $index => $item) {
            [$row, $itemError] = $this->prepare($item, $index);

            if ($itemError !== null) {
                return $this->fail($itemError);
            }

            $prepared[] = $row;
        }

        try {
            $created = DB::transaction(fn (): array => array_map(
                fn (array $row): array => $this->create($row),
                $prepared,
            ));
        } catch (ValidationException $e) {
            // مصدرها `ExpenseFundingService` — المصروف تجاوز المتبقي بلا مصدر.
            // المعاملة تراجعت كاملةً، فلا شيء حُفظ.
            return $this->fail(
                'funding_required: '.implode(' ', $e->validator->errors()->all())
                .' Nothing was saved. Ask the user which funding source applies, then call again with `funding_source`.',
                ['funding_required' => true, 'allowed' => TransactionRules::FUNDING_SOURCES],
            );
        }

        return $this->ok(
            count($created) === 1
                ? 'Created 1 record.'
                : sprintf('Created %d records.', count($created)),
            ['created' => $created, 'count' => count($created)],
        );
    }

    /**
     * يحوّل عنصراً من الموديل إلى صفّ جاهز للكتابة، أو يرجّع رسالة خطأ.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function prepare(array $item, int $index): array
    {
        $label = sprintf('transactions[%d]', $index);
        $type = $this->type($item['type'] ?? null);

        if ($type === null) {
            return [[], "{$label}: `type` must be either \"expense\" or \"income\"."];
        }

        return $type === 'expense'
            ? $this->prepareExpense($item, $label)
            : $this->prepareIncome($item, $label);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function prepareExpense(array $item, string $label): array
    {
        $categoryName = trim((string) ($item['category'] ?? ''));

        if ($categoryName === '') {
            return [[], "{$label}: an expense requires `category`. ".$this->categoryHint()];
        }

        $categoryId = $this->categoryId($categoryName);

        if ($categoryId === null) {
            return [[], "{$label}: unknown category \"{$categoryName}\". ".$this->categoryHint()];
        }

        [$validated, $error] = $this->check([
            'amount' => $item['amount'] ?? null,
            'category_id' => $categoryId,
            'description' => $item['description'] ?? null,
            'expense_date' => $item['date'] ?? null,
            'funding_source' => $item['funding_source'] ?? null,
        ], TransactionRules::agentExpenseStore($this->user->id));

        if ($error !== null) {
            return [[], "{$label}: {$error}"];
        }

        return [['type' => 'expense'] + $validated, null];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function prepareIncome(array $item, string $label): array
    {
        [$validated, $error] = $this->check([
            'amount' => $item['amount'] ?? null,
            'source' => $item['source'] ?? null,
            'description' => $item['description'] ?? null,
            'income_date' => $item['date'] ?? null,
        ], TransactionRules::agentIncomeStore());

        if ($error !== null) {
            return [[], "{$label}: {$error}"];
        }

        return [['type' => 'income'] + $validated, null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int, type: string}
     */
    private function create(array $row): array
    {
        if ($row['type'] === 'expense') {
            $expense = ExpenseFundingService::for($this->user)->record([
                'amount' => $this->halalas($row['amount']),
                'category_id' => $row['category_id'],
                'description' => $row['description'] ?? null,
                'expense_date' => $row['expense_date'],
                'funding_source' => $row['funding_source'] ?? null,
                'savings_goal_id' => null,
                'income_amount' => null,
                'income_source' => null,
            ]);

            return ['id' => (int) $expense->id, 'type' => 'expense'];
        }

        $income = $this->incomes()->create([
            'amount' => $this->halalas($row['amount']),
            'source' => $row['source'],
            'description' => $row['description'] ?? null,
            'income_date' => $row['income_date'],
            'is_recurring' => false,
        ]);

        return ['id' => (int) $income->id, 'type' => 'income'];
    }
}
