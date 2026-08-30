<script lang="ts">
    /**
     * بطاقة استدعاء أداة داخل مجرى المحادثة.
     *
     * لماذا تُعرَض أصلاً: المساعد يعدّل بيانات مالية حقيقية. رسالة «تم
     * حذف عمليتين» وحدها تطلب من المستخدم أن يثق بلا دليل. البطاقة تريه
     * ما نُفِّذ فعلاً — أيّ أداة، بأيّ معطيات، وبأيّ نتيجة — ويظلّ
     * التفصيل مطويّاً فلا يزاحم الرد.
     *
     * لا JSON خام في الواجهة: أسماء الأدوات والحقول تُترجَم للعربية،
     * والمبالغ تُنسَّق. الـJSON لغة الموديل لا لغة المستخدم.
     */
    import Check from 'lucide-svelte/icons/check';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import LoaderCircle from 'lucide-svelte/icons/loader-circle';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import type { ToolInvocation } from '@/types';

    let { tool }: { tool: ToolInvocation } = $props();

    let open = $state(false);

    const META = {
        ListTransactions: {
            label: 'عرض العمليات',
            icon: Search,
            running: 'يقرأ عملياتك…',
        },
        CreateTransactions: {
            label: 'تسجيل عمليات',
            icon: Plus,
            running: 'يسجّل…',
        },
        UpdateTransactions: {
            label: 'تعديل عمليات',
            icon: Pencil,
            running: 'يعدّل…',
        },
        DeleteTransactions: {
            label: 'حذف عمليات',
            icon: Trash2,
            running: 'يحذف…',
        },
    } as const;

    const meta = $derived(
        META[tool.name as keyof typeof META] ?? {
            label: tool.name,
            icon: Search,
            running: 'جارٍ التنفيذ…',
        },
    );

    const TYPES: Record<string, string> = {
        expense: 'مصروف',
        income: 'دخل',
        all: 'الكل',
    };

    const SORTS: Record<string, string> = {
        date_desc: 'الأحدث أولاً',
        date_asc: 'الأقدم أولاً',
        amount_desc: 'الأكبر مبلغاً',
        amount_asc: 'الأصغر مبلغاً',
    };

    const FIELDS: Record<string, string> = {
        type: 'النوع',
        date: 'التاريخ',
        date_from: 'من تاريخ',
        date_to: 'إلى تاريخ',
        category: 'الفئة',
        min_amount: 'أقل مبلغ',
        max_amount: 'أكبر مبلغ',
        search: 'بحث',
        sort: 'الترتيب',
        limit: 'العدد',
        amount: 'المبلغ',
        description: 'الوصف',
        source: 'المصدر',
        id: 'رقم العملية',
        funding_source: 'مصدر التمويل',
    };

    /** قيمة واحدة بصيغة يقرأها إنسان — لا JSON. */
    function readable(key: string, value: unknown): string {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (Array.isArray(value)) {
            return value.map((v) => readable(key, v)).join('، ');
        }

        if (typeof value === 'boolean') {
            return value ? 'نعم' : 'لا';
        }

        if (key === 'type') {
            return TYPES[String(value)] ?? String(value);
        }

        if (key === 'sort') {
            return SORTS[String(value)] ?? String(value);
        }

        if (key === 'amount' || key === 'min_amount' || key === 'max_amount') {
            return `${value} ر.س`;
        }

        if (typeof value === 'object') {
            return Object.entries(value as Record<string, unknown>)
                .map(([k, v]) => `${FIELDS[k] ?? k}: ${readable(k, v)}`)
                .join(' · ');
        }

        return String(value);
    }

    /** المعطيات كأزواج مقروءة، بلا الحقول الفارغة. */
    const args = $derived(
        Object.entries(tool.arguments ?? {})
            .filter(([, v]) => v !== null && v !== undefined && v !== '')
            .map(([k, v]) => ({
                label: FIELDS[k] ?? k,
                value: readable(k, v),
            })),
    );

    /** أهمّ أرقام النتيجة — لا الحمولة كاملةً. */
    const outcome = $derived.by(() => {
        const data = tool.data as Record<string, unknown> | null | undefined;

        if (!data) {
            return [];
        }

        const rows: { label: string; value: string }[] = [];
        const push = (label: string, value: unknown) => {
            if (value !== null && value !== undefined) {
                rows.push({ label, value: String(value) });
            }
        };

        push('عدد النتائج', data.total_count);
        push(
            'مجموع المصاريف',
            data.sum_expenses !== undefined
                ? `${data.sum_expenses} ر.س`
                : undefined,
        );
        push(
            'مجموع الدخل',
            data.sum_incomes !== undefined
                ? `${data.sum_incomes} ر.س`
                : undefined,
        );
        push('سُجِّل', data.count);
        push('عُدِّل', data.updated_count);
        push('حُذِف', data.deleted_count);

        const missing = (data.not_found as unknown[] | undefined)?.length ?? 0;

        if (missing > 0) {
            push('لم توجد', missing);
        }

        if (data.truncated === true) {
            rows.push({ label: 'ملاحظة', value: 'النتائج أكثر مما عُرض' });
        }

        return rows;
    });

    const hasDetail = $derived(args.length > 0 || outcome.length > 0);
</script>

<div
    class="my-2 overflow-hidden rounded-2xl border border-border bg-secondary/50 text-[13px]"
    class:border-destructive={tool.status === 'failed'}
>
    <button
        type="button"
        class="flex min-h-11 w-full items-center gap-2.5 px-3 py-2 text-start transition-colors hover:bg-secondary disabled:cursor-default"
        onclick={() => (open = !open)}
        disabled={!hasDetail}
        aria-expanded={hasDetail ? open : undefined}
    >
        <span
            class="grid size-7 shrink-0 place-items-center rounded-lg"
            class:bg-accent={tool.status !== 'failed'}
            class:text-primary={tool.status !== 'failed'}
            class:bg-destructive={tool.status === 'failed'}
            class:text-white={tool.status === 'failed'}
        >
            <meta.icon class="size-4" />
        </span>

        <span class="min-w-0 flex-1">
            <b class="block font-semibold">{meta.label}</b>
            <span class="block truncate text-[11.5px] text-muted-foreground">
                {tool.status === 'running' ? meta.running : tool.summary}
            </span>
        </span>

        {#if tool.status === 'running'}
            <LoaderCircle
                class="size-4 shrink-0 animate-spin text-muted-foreground"
                aria-label="جارٍ التنفيذ"
            />
        {:else if tool.status === 'failed'}
            <TriangleAlert
                class="size-4 shrink-0 text-destructive"
                aria-label="فشل"
            />
        {:else}
            <Check
                class="size-4 shrink-0 text-[color:var(--success-text)]"
                aria-label="تم"
            />
        {/if}

        {#if hasDetail}
            <ChevronDown
                class="size-4 shrink-0 text-muted-foreground transition-transform {open
                    ? 'rotate-180'
                    : ''}"
                aria-hidden="true"
            />
        {/if}
    </button>

    {#if open && hasDetail}
        <div class="border-t border-border px-3 py-2.5">
            {#if args.length > 0}
                <p class="mb-1 text-[11px] text-muted-foreground">ما طُلب</p>
                <dl class="mb-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                    {#each args as row (row.label)}
                        <dt class="text-[11.5px] text-muted-foreground">
                            {row.label}
                        </dt>
                        <dd class="text-[12.5px] tabular-nums" dir="auto">
                            {row.value}
                        </dd>
                    {/each}
                </dl>
            {/if}

            {#if outcome.length > 0}
                <p class="mb-1 text-[11px] text-muted-foreground">النتيجة</p>
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                    {#each outcome as row (row.label)}
                        <dt class="text-[11.5px] text-muted-foreground">
                            {row.label}
                        </dt>
                        <dd class="text-[12.5px] tabular-nums" dir="auto">
                            {row.value}
                        </dd>
                    {/each}
                </dl>
            {/if}
        </div>
    {/if}
</div>
