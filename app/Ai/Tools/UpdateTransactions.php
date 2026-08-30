<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\RecurringTransactionService;
use App\Support\TransactionRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;

/**
 * تعديل عملية أو أكثر — تعديل جزئي بالمعرّف.
 *
 * ── الملكية ──
 * `ownedTransaction()` هو المدخل الوحيد، وهو `$this->user->expenses()`.
 * `id` لمستخدم آخر يرجّع `null` فيُصنَّف `not_found` — لا 403 ولا رسالة
 * تكشف أن الصفّ موجود عند غيره. الموديل قد يُخدع فيرسل مثل هذا الـid؛
 * الاستعلام نفسه لا يراه أصلاً.
 *
 * ── جزئي فعلاً ──
 * ما لا يُرسَل لا يتغيّر. الموديل يعرف حقلاً واحداً يريد تغييره غالباً
 * («غيّر تصنيف آخر عملية»)؛ لو كتبنا الحقول غير المرسَلة بقيم افتراضية
 * لمحونا وصفاً أو تاريخاً لم يطلب أحد تغييره.
 */
final class UpdateTransactions extends TransactionTool
{
    public function __construct(
        User $user,
        private readonly RecurringTransactionService $recurring,
    ) {
        parent::__construct($user);
    }

    public function description(): string
    {
        return <<<'TEXT'
        Changes existing financial records of the current user.

        YOU MUST KNOW THE REAL id AND type FIRST. If the user described the
        record in words ("the coffee one", "my last expense"), call
        ListTransactions first and take the id and type from its output. Never
        guess an id, and never reuse an id from an earlier turn without
        re-checking — the record may have changed since.

        If the description matches MORE THAN ONE record and the user's intent is
        unclear, do NOT pick one. Show the candidates and ask which they meant.

        PARTIAL UPDATE: send only the fields you are changing. Every field you
        omit keeps its current value. Sending a field you were not asked to
        change will overwrite real data.

        Maximum 50 entries per call. Either all updates apply or none do.

        RETURNS: { updated: [{ id, type, changed: [...] }], not_found: [{ id,
        type }], updated_count }. An entry in `not_found` does not belong to
        this user or does not exist — report it plainly, do not retry it.

        NOT FOR: creating records (use CreateTransactions), deleting them (use
        DeleteTransactions), or changing anything to do with recurring
        transactions — those are managed on their own page.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'updates' => $schema->array()
                ->min(1)
                ->max(50)
                ->items($schema->object([
                    'id' => $schema->integer()
                        ->min(1)
                        ->required()
                        ->description('The record id, taken from ListTransactions.'),

                    'type' => $schema->string()
                        ->enum(self::TYPES)
                        ->required()
                        ->description('The record type, taken from ListTransactions. REQUIRED: ids are unique only within a type, so id 5 without a type is ambiguous.'),

                    'amount' => $schema->number()
                        ->min(0.01)
                        ->description('New amount in riyals, always positive. Omit to keep the current amount.'),

                    'date' => $schema->string()
                        ->description('New date, literal YYYY-MM-DD. Relative words are REJECTED. Omit to keep the current date.'),

                    'category' => $schema->string()
                        ->description('Expenses only. New category name, exactly as listed in your instructions. Omit to keep the current category.'),

                    'source' => $schema->string()
                        ->max(500)
                        ->description('Incomes only. New source. Omit to keep the current source.'),

                    'description' => $schema->string()
                        ->max(500)
                        ->description('New note. Omit to keep the current note.'),
                ])->withoutAdditionalProperties())
                ->required()
                ->description('The changes to apply. One entry per record.'),
        ];
    }

    public function handle(Request $request): string
    {
        [$items, $error] = $this->items($request->array('updates'), 'updates');

        if ($error !== null) {
            return $this->fail($error);
        }

        $plans = [];
        $notFound = [];

        foreach ($items as $index => $item) {
            $label = sprintf('updates[%d]', $index);
            $type = $this->type($item['type'] ?? null);
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);

            if ($type === null || $id === false || $id < 1) {
                return $this->fail("{$label}: both `id` (positive integer) and `type` (\"expense\" or \"income\") are required.");
            }

            $record = $this->ownedTransaction($type, $id);

            if ($record === null) {
                $notFound[] = ['id' => $id, 'type' => $type];

                continue;
            }

            [$changes, $itemError] = $this->changes($item, $type, $label);

            if ($itemError !== null) {
                return $this->fail($itemError);
            }

            if ($changes === []) {
                return $this->fail("{$label}: no fields to change. Send at least one of amount, date, category, source or description.");
            }

            $plans[] = ['record' => $record, 'type' => $type, 'changes' => $changes];
        }

        $updated = DB::transaction(fn (): array => array_map(
            fn (array $plan): array => $this->apply($plan),
            $plans,
        ));

        return $this->ok(
            $this->summarize(count($updated), count($notFound)),
            [
                'updated' => $updated,
                'updated_count' => count($updated),
                'not_found' => $notFound,
            ],
        );
    }

    /**
     * الحقول المرسَلة فقط، بعد التحقّق بنفس قواعد الواجهة.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function changes(array $item, string $type, string $label): array
    {
        $dateField = $type === 'expense' ? 'expense_date' : 'income_date';
        $input = [];

        if (array_key_exists('amount', $item) && $item['amount'] !== null) {
            $input['amount'] = $item['amount'];
        }

        if (array_key_exists('date', $item) && $item['date'] !== null) {
            $input[$dateField] = $item['date'];
        }

        if (array_key_exists('description', $item) && $item['description'] !== null) {
            $input['description'] = $item['description'];
        }

        if ($type === 'expense' && filled($name = trim((string) ($item['category'] ?? '')))) {
            $categoryId = $this->categoryId($name);

            if ($categoryId === null) {
                return [[], "{$label}: unknown category \"{$name}\". ".$this->categoryHint()];
            }

            $input['category_id'] = $categoryId;
        }

        if ($type === 'income' && filled($source = trim((string) ($item['source'] ?? '')))) {
            $input['source'] = $source;
        }

        if ($input === []) {
            return [[], null];
        }

        $rules = $type === 'expense'
            ? TransactionRules::agentExpenseUpdate($this->user->id)
            : TransactionRules::agentIncomeUpdate();

        // القواعد مقصورة على الحقول المرسَلة: الباقي غائب عمداً، وتركُ
        // قواعده لكانت `required` تُفشل كل تعديل جزئي.
        [$validated, $error] = $this->check($input, array_intersect_key($rules, $input));

        return $error !== null ? [[], "{$label}: {$error}"] : [$validated, null];
    }

    /**
     * @param  array{record: Expense|Income, type: string, changes: array<string, mixed>}  $plan
     * @return array<string, mixed>
     */
    private function apply(array $plan): array
    {
        $record = $plan['record'];
        $changes = $plan['changes'];

        if (array_key_exists('amount', $changes)) {
            $changes['amount'] = $this->halalas($changes['amount']);
        }

        $record->update($changes);

        // مزامنة القالب المتكرّر بعد التعديل — تماماً كما يفعل مسار الواجهة.
        // بدونها يبقى قالب العملية المتكرّرة على المبلغ القديم فتتولّد
        // النسخة القادمة بقيمة صحّحها المستخدم للتوّ.
        if ($record instanceof Expense) {
            $this->recurring->syncExpense($record->refresh());
        } else {
            $this->recurring->syncIncome($record->refresh());
        }

        return [
            'id' => (int) $record->id,
            'type' => $plan['type'],
            'changed' => array_keys($plan['changes']),
        ];
    }

    private function summarize(int $updated, int $notFound): string
    {
        $summary = $updated === 1 ? 'Updated 1 record.' : sprintf('Updated %d records.', $updated);

        return $notFound === 0
            ? $summary
            : $summary.sprintf(' %d id(s) were not found and were skipped.', $notFound);
    }
}
