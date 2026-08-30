<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\CommitmentService;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;

/**
 * حذف عملية أو أكثر — بمعرّفات صريحة فقط.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  لا يوجد «احذف الكل». لا مسار، ولا معطى، ولا حالة افتراضية.
 * ══════════════════════════════════════════════════════════════════════
 *
 * أخطر ما يمكن أن يفعله وكيلٌ خُدع هو حذفٌ واسع بأمر واحد. الحماية هنا
 * بنيوية لا سلوكية: الأداة لا تقبل إلا `transactions[]` من معرّفات
 * محدّدة، فحتى لو أقنع أحدهم الموديل بأن يحذف كل شيء فعليه أن يعدّها
 * واحدةً واحدة — وسقف الـ50 يحدّ ذلك أيضاً.
 *
 * الحذف ناعم (`SoftDeletes` على الجدولين)، فالتراجع ممكن من قاعدة
 * البيانات إن لزم.
 */
final class DeleteTransactions extends TransactionTool
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
        Deletes specific financial records of the current user.

        YOU MUST KNOW THE REAL ids AND types FIRST. If the user described what
        to delete in words ("delete this week's coffee expenses"), call
        ListTransactions first, take the exact ids from its output, and delete
        those. Never guess an id.

        If what the user described matches records you are not confident about,
        list them and ask for confirmation instead of deleting.

        THERE IS NO "DELETE ALL". Every record must be named explicitly by id
        and type. Maximum 50 per call. Either all are deleted or none are.

        After deleting, state exactly how many records were removed and which
        ones — do not round or approximate.

        RETURNS: { deleted: [{ id, type }], deleted_count, not_found: [{ id,
        type }] }. An entry in `not_found` does not belong to this user or does
        not exist — report it plainly, do not retry it.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
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
                        ->description('The record type, taken from ListTransactions. REQUIRED: ids are unique only within a type.'),
                ])->withoutAdditionalProperties())
                ->required()
                ->description('The exact records to delete. There is no way to delete everything at once, and no default — name each record.'),
        ];
    }

    public function handle(Request $request): string
    {
        [$items, $error] = $this->items($request->array('transactions'), 'transactions');

        if ($error !== null) {
            return $this->fail($error);
        }

        $targets = [];
        $notFound = [];

        foreach ($items as $index => $item) {
            $type = $this->type($item['type'] ?? null);
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);

            if ($type === null || $id === false || $id < 1) {
                return $this->fail(sprintf(
                    'transactions[%d]: both `id` (positive integer) and `type` ("expense" or "income") are required.',
                    $index,
                ));
            }

            $record = $this->ownedTransaction($type, $id);

            if ($record === null) {
                $notFound[] = ['id' => $id, 'type' => $type];

                continue;
            }

            $targets[] = ['record' => $record, 'type' => $type];
        }

        $deleted = DB::transaction(fn (): array => array_map(
            fn (array $target): array => $this->delete($target),
            $targets,
        ));

        return $this->ok(
            $this->summarize(count($deleted), count($notFound)),
            [
                'deleted' => $deleted,
                'deleted_count' => count($deleted),
                'not_found' => $notFound,
            ],
        );
    }

    /**
     * @param  array{record: Expense|Income, type: string}  $target
     * @return array{id: int, type: string}
     */
    private function delete(array $target): array
    {
        $record = $target['record'];
        $id = (int) $record->id;

        if ($record instanceof Expense) {
            // حذف مصروف سدّد التزاماً يسحب السداد معه — وإلا بقي الالتزام
            // «مسدَّداً» بلا مال خرج، وهو أسوأ من عدم تسجيله أصلاً.
            // نفس ما يفعله `DELETE /expenses/{expense}` حرفياً.
            if ($record->commitment_id !== null) {
                $commitment = $this->user->commitments()->find($record->commitment_id);

                if ($commitment !== null) {
                    CommitmentService::for($this->user)->revokePaymentFromExpense($commitment, $record);
                }
            }

            $this->recurring->detachExpense($record);
        } else {
            $this->recurring->detachIncome($record);
        }

        $record->delete();

        return ['id' => $id, 'type' => $target['type']];
    }

    private function summarize(int $deleted, int $notFound): string
    {
        $summary = $deleted === 1 ? 'Deleted 1 record.' : sprintf('Deleted %d records.', $deleted);

        return $notFound === 0
            ? $summary
            : $summary.sprintf(' %d id(s) were not found and were skipped.', $notFound);
    }
}
