<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Ai\Tools\Request;

/**
 * قراءة عمليات المستخدم — وهي الأداة التي تسبق كل تعديل وكل حذف.
 *
 * الموديل لا يعرف الـids، ويعرف أوصافاً بشرية («عملية القهوة»، «آخر
 * عملية»). محاولة تحويل الوصف إلى تعديل مباشرةً تعني تخميناً، والتخمين
 * في عمليات مالية يعني حذف الصفّ الخطأ. لذلك هذه الأداة هي المدخل:
 * تُستدعى أولاً، تُرجع ids دقيقة، ثم تُنفَّذ العملية عليها.
 */
final class ListTransactions extends TransactionTool
{
    private const SORTS = ['date_desc', 'date_asc', 'amount_desc', 'amount_asc'];

    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    public function description(): string
    {
        return <<<'TEXT'
        Reads the current user's financial records: expenses and incomes.

        USE IT:
        - To answer any question about what was spent, earned, or when. Never
          answer such a question from memory or from earlier turns.
        - ALWAYS before UpdateTransactions or DeleteTransactions when the user
          described a record in words ("the coffee one", "my last expense")
          rather than giving an id. This call is how you learn the real ids.

        DO NOT USE IT to create, change, or delete anything.

        RETURNS: { transactions: [{ id, type, date, amount, description,
        category|source }], total_count, returned, truncated, sum_expenses,
        sum_incomes, sum_amount }.

        - `id` is unique only WITHIN its `type`. Expense 5 and income 5 are two
          different records. Always carry `type` together with `id`.
        - All amounts are positive numbers in Saudi Riyals (SAR).
        - `total_count` is how many records matched in total; `returned` is how
          many are in this response. If `truncated` is true there are MORE
          matching records than shown — say so instead of implying the list is
          complete, and narrow the filters or raise `limit`.
        - `sum_expenses` / `sum_incomes` cover ALL matching records, not just
          the returned page, so they are the correct answer to "how much did I
          spend on X". `sum_amount` is set only when `type` is a single kind.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['expense', 'income', 'all'])
                ->description('Which records to read. Defaults to "all".'),

            'date_from' => $schema->string()
                ->description('Inclusive start date, literal YYYY-MM-DD. Never a relative word such as "today" or "last week" — resolve those yourself against the current date given in your instructions.'),

            'date_to' => $schema->string()
                ->description('Inclusive end date, literal YYYY-MM-DD. Same rule as date_from.'),

            'category' => $schema->array()
                ->items($schema->string())
                ->description('Filter expenses by one or more category names, exactly as listed in your instructions. Incomes have no category, so using this filter excludes them.'),

            'min_amount' => $schema->number()
                ->min(0)
                ->description('Minimum amount in riyals, inclusive.'),

            'max_amount' => $schema->number()
                ->min(0)
                ->description('Maximum amount in riyals, inclusive.'),

            'search' => $schema->string()
                ->max(100)
                ->description('Free-text match against the description, the category name (expenses) and the source (incomes).'),

            'sort' => $schema->string()
                ->enum(self::SORTS)
                ->description('Ordering. Defaults to "date_desc" — newest first, which is what "my last transaction" means.'),

            'limit' => $schema->integer()
                ->min(1)
                ->max(self::MAX_LIMIT)
                ->description('How many records to return. Defaults to 25, maximum 100.'),
        ];
    }

    public function handle(Request $request): string
    {
        $input = $request->all();

        // الموديل يرسل أحياناً `"category": "طعام"` بدل مصفوفة. رفضه يكلّف
        // جولة كاملة لتصحيح شكلٍ نعرف مقصده — نلفّه ونمضي.
        if (isset($input['category']) && is_string($input['category'])) {
            $input['category'] = [$input['category']];
        }

        [$filters, $error] = $this->check($input, [
            'type' => 'nullable|in:expense,income,all',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'category' => 'nullable|array',
            'category.*' => 'nullable|string|max:255',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:'.implode(',', self::SORTS),
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_LIMIT,
        ]);

        if ($error !== null) {
            return $this->fail($error);
        }

        $type = $filters['type'] ?? 'all';
        $sort = $filters['sort'] ?? 'date_desc';
        $limit = (int) ($filters['limit'] ?? self::DEFAULT_LIMIT);
        $categories = array_values(array_filter((array) ($filters['category'] ?? [])));

        // الدخل بلا فئة — فلترة بالفئة تعني «مصاريف» ضمناً، ولو تركناها
        // تمرّ لأرجعنا مداخيل لا علاقة لها بالسؤال.
        if ($categories !== [] && $type === 'all') {
            $type = 'expense';
        }

        $unknown = array_values(array_filter(
            $categories,
            fn (string $name): bool => $this->categoryId($name) === null,
        ));

        if ($unknown !== []) {
            return $this->fail(
                'Unknown category: '.implode(', ', $unknown).'. '.$this->categoryHint(),
            );
        }

        $rows = [];
        $sumExpenses = 0;
        $sumIncomes = 0;
        $totalCount = 0;

        if ($type !== 'income') {
            $query = $this->expenseQuery($filters, $categories);
            $totalCount += (clone $query)->count();
            $sumExpenses = (int) (clone $query)->sum('amount');
            $rows = array_merge($rows, $this->mapExpenses($query, $sort, $limit));
        }

        if ($type !== 'expense' && $categories === []) {
            $query = $this->incomeQuery($filters);
            $totalCount += (clone $query)->count();
            $sumIncomes = (int) (clone $query)->sum('amount');
            $rows = array_merge($rows, $this->mapIncomes($query, $sort, $limit));
        }

        // الترتيب النهائي في PHP: الجدولان منفصلان، فلا سبيل لترتيب موحّد
        // في SQL بلا union. الحدّ مطبَّق على كل جدول قبل الدمج، فالمجموعة
        // المرشَّحة لا تتجاوز 200 صفاً مهما كان.
        $rows = $this->sortRows($rows, $sort);
        $returned = array_slice($rows, 0, $limit);

        return $this->ok(
            $this->summarize(count($returned), $totalCount),
            [
                'transactions' => $returned,
                'total_count' => $totalCount,
                'returned' => count($returned),
                'truncated' => $totalCount > count($returned),
                'sum_expenses' => $this->riyals($sumExpenses),
                'sum_incomes' => $this->riyals($sumIncomes),
                'sum_amount' => match ($type) {
                    'expense' => $this->riyals($sumExpenses),
                    'income' => $this->riyals($sumIncomes),
                    default => null,
                },
                'currency' => 'SAR',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $categories
     * @return HasMany<Expense, User>
     */
    private function expenseQuery(array $filters, array $categories): HasMany
    {
        $query = $this->expenses()->with('category');

        $this->applyCommonFilters($query, $filters, 'expense_date');

        if ($categories !== []) {
            $ids = array_map(fn (string $name): int => (int) $this->categoryId($name), $categories);
            $query->whereIn('category_id', $ids);
        }

        if (filled($search = $filters['search'] ?? null)) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return HasMany<Income, User>
     */
    private function incomeQuery(array $filters): HasMany
    {
        $query = $this->incomes();

        $this->applyCommonFilters($query, $filters, 'income_date');

        if (filled($search = $filters['search'] ?? null)) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param  HasMany<Expense, User>|HasMany<Income, User>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(HasMany $query, array $filters, string $dateColumn): void
    {
        if (filled($from = $filters['date_from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $from);
        }

        if (filled($to = $filters['date_to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $to);
        }

        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $this->halalas($filters['min_amount']));
        }

        if (isset($filters['max_amount'])) {
            $query->where('amount', '<=', $this->halalas($filters['max_amount']));
        }
    }

    /**
     * @param  HasMany<Expense, User>  $query
     * @return list<array<string, mixed>>
     */
    private function mapExpenses(HasMany $query, string $sort, int $limit): array
    {
        return array_values((clone $query)
            ->orderBy(...$this->order($sort, 'expense_date'))
            ->limit($limit)
            ->get()
            ->map(fn (Expense $e): array => [
                'id' => $e->id,
                'type' => 'expense',
                'date' => $e->expense_date->format('Y-m-d'),
                'amount' => $this->riyals((int) $e->amount),
                'category' => $e->category?->name,
                'description' => $e->description,
            ])
            ->all());
    }

    /**
     * @param  HasMany<Income, User>  $query
     * @return list<array<string, mixed>>
     */
    private function mapIncomes(HasMany $query, string $sort, int $limit): array
    {
        return array_values((clone $query)
            ->orderBy(...$this->order($sort, 'income_date'))
            ->limit($limit)
            ->get()
            ->map(fn (Income $i): array => [
                'id' => $i->id,
                'type' => 'income',
                'date' => $i->income_date->format('Y-m-d'),
                'amount' => $this->riyals((int) $i->amount),
                'source' => $i->source,
                'description' => $i->description,
            ])
            ->all());
    }

    /** @return array{0: string, 1: 'asc'|'desc'} */
    private function order(string $sort, string $dateColumn): array
    {
        return str_starts_with($sort, 'amount')
            ? ['amount', str_ends_with($sort, 'asc') ? 'asc' : 'desc']
            : [$dateColumn, str_ends_with($sort, 'asc') ? 'asc' : 'desc'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort): array
    {
        $key = str_starts_with($sort, 'amount') ? 'amount' : 'date';
        $ascending = str_ends_with($sort, 'asc');

        usort($rows, function (array $a, array $b) use ($key, $ascending): int {
            $comparison = $key === 'amount'
                ? $a['amount'] <=> $b['amount']
                : strcmp($a['date'], $b['date']);

            return $ascending ? $comparison : -$comparison;
        });

        return $rows;
    }

    private function summarize(int $returned, int $total): string
    {
        if ($total === 0) {
            return 'No matching records.';
        }

        return $returned < $total
            ? sprintf('Showing %d of %d matching records.', $returned, $total)
            : sprintf('Found %d matching record(s).', $total);
    }
}
