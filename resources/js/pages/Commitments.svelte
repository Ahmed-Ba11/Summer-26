<script lang="ts">
    /**
     * صفحة الالتزامات — فواتير · إيجارات · أقساط · اشتراكات.
     *
     * البنية: شريط صحة → أربع بطاقات نوع (ملخّص + فلتر) → القائمة.
     * لا تبويبات: بطاقات الأنواع تؤدّي وظيفتها وتبقي أرقام كل نوع ظاهرة.
     *
     * زر «إضافة» في رأس الصفحة — لا زر عائم يغطّي المحتوى.
     */
    import Plus from 'lucide-svelte/icons/plus';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AddCommitmentSheet from '@/components/AddCommitmentSheet.svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import CommitmentCard from '@/components/CommitmentCard.svelte';
    import CommitmentHealth from '@/components/CommitmentHealth.svelte';
    import CommitmentTypeCard from '@/components/CommitmentTypeCard.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import FreedomCard from '@/components/FreedomCard.svelte';
    import { formatAmount, formatCurrency, formatRelativeDays } from '@/lib/format';
    import {
        type Commitment,
        type CommitmentKind,
        daysUntil,
        expectedAmount,
        freedomDay,
        KIND_COLOR,
        KIND_LABEL,
        KIND_LABEL_PLURAL,
        KIND_ORDER,
        stateOf,
        totalsOf,
    } from '@/lib/commitments';

    let {
        commitments = [],
        income = 0,
        salaryDay = 27,
        periodLabel = '',
    }: {
        commitments?: Commitment[];
        income?: number;
        salaryDay?: number;
        /** «راتب أغسطس · 27 أغسطس ← 26 سبتمبر» */
        periodLabel?: string;
    } = $props();

    let filter = $state<CommitmentKind | null>(null);
    let sheetOpen = $state(false);
    let processing = $state(false);

    const totals = $derived(totalsOf(commitments));

    /** ترتيب مقصود: المتأخّر أولاً، ثم الأقرب استحقاقاً، والمدفوع أخيراً. */
    const visible = $derived.by(() => {
        const list = filter ? commitments.filter((c) => c.kind === filter) : commitments;
        const rank = { overdue: 0, due_soon: 1, reserved: 2, paid: 3 } as const;
        return [...list].sort((a, b) => {
            const ra = rank[stateOf(a)];
            const rb = rank[stateOf(b)];
            return ra !== rb ? ra - rb : daysUntil(a.due_date) - daysUntil(b.due_date);
        });
    });

    function summaryFor(kind: CommitmentKind) {
        const list = commitments.filter((c) => c.kind === kind);
        const t = totalsOf(list);
        const unpaid = list.filter((c) => !c.is_paid_this_month);

        let note = 'ما أضفت شي';
        if (list.length) {
            if (!unpaid.length) note = 'كلها مدفوعة';
            else {
                const soonest = unpaid.reduce((a, b) => (daysUntil(a.due_date) <= daysUntil(b.due_date) ? a : b));
                note =
                    t.paidCount > 0
                        ? `دُفع ${t.paidCount} · باقي ${unpaid.length}`
                        : `${list.length > 1 ? 'أقربها' : 'يستحق'} ${formatRelativeDays(soonest.due_date)}`;
            }
        }
        return { count: list.length, total: t.total, paid: t.paid, note };
    }

    const freedom = $derived(freedomDay(commitments.filter((c) => c.kind === 'installment')));

    function pay(c: Commitment) {
        router.post(
            `/commitments/${c.id}/pay`,
            { amount: expectedAmount(c) },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(`تم دفع ${c.name} — ${formatCurrency(expectedAmount(c))}`, {
                        action: { label: 'تراجع', onClick: () => undo(c) },
                        duration: 5000,
                    }),
            },
        );
    }

    function undo(c: Commitment) {
        router.delete(`/commitments/${c.id}/pay`, {
            preserveScroll: true,
            onSuccess: () => toast.success(`رجّعنا ${c.name} إلى «محجوز»`),
        });
    }

    function edit(c: Commitment) {
        router.get(`/commitments/${c.id}/edit`);
    }

    function save(payload: Record<string, unknown>) {
        processing = true;
        router.post('/commitments', payload, {
            preserveScroll: true,
            onSuccess: () => {
                sheetOpen = false;
                const label = KIND_LABEL[payload.kind as CommitmentKind];
                toast.success(`تمت إضافة ${label} «${payload.name}»`);
            },
            onFinish: () => (processing = false),
        });
    }
</script>

<AppLayout>
    <div class="mx-auto w-full max-w-3xl p-3 md:p-6">
        <!-- الرأس -->
        <header class="mb-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="truncate text-[17px] font-semibold tracking-tight">التزاماتي</h1>
                <p class="mt-0.5 truncate text-[11.5px] text-muted-foreground">
                    {commitments.length} التزام
                    {#if totals.overdueCount}· <span class="font-medium text-destructive">{totals.overdueCount} متأخّر</span>{/if}
                    {#if periodLabel}· {periodLabel}{/if}
                </p>
            </div>
            <button
                type="button"
                onclick={() => (sheetOpen = true)}
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl bg-primary px-3.5 text-[12.5px] font-semibold text-primary-foreground transition-transform active:scale-[.98]"
            >
                <Plus class="size-4" /> إضافة
            </button>
        </header>

        <div class="space-y-3">
            <CommitmentHealth total={totals.total} paid={totals.paid} reserved={totals.reserved} {income} />

            <!-- بطاقات الأنواع = الفلتر -->
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                {#each KIND_ORDER as kind (kind)}
                    {@const s = summaryFor(kind)}
                    <CommitmentTypeCard
                        {kind}
                        color={KIND_COLOR[kind]}
                        count={s.count}
                        total={s.total}
                        paid={s.paid}
                        note={s.note}
                        active={filter === kind}
                        onToggle={(k) => (filter = filter === k ? null : k)}
                    />
                {/each}
            </div>

            {#if filter}
                <div class="flex items-center justify-between gap-2 px-0.5">
                    <p class="text-[11.5px] text-muted-foreground">
                        تعرض: <b class="font-semibold text-foreground">{KIND_LABEL_PLURAL[filter]}</b>
                    </p>
                    <button
                        type="button"
                        onclick={() => (filter = null)}
                        class="min-h-9 text-[11.5px] font-medium text-primary underline-offset-2 hover:underline"
                    >
                        عرض الكل
                    </button>
                </div>
            {/if}

            <!-- القائمة -->
            {#if visible.length}
                <div class="grid gap-2 md:grid-cols-2">
                    {#each visible as c (c.id)}
                        <CommitmentCard commitment={c} onPay={pay} onEdit={edit} onUndo={undo} />
                    {/each}
                </div>
            {:else}
                <EmptyState
                    icon="receipt"
                    title={filter ? `ما عندك ${KIND_LABEL_PLURAL[filter]}` : 'ما أضفت التزامات بعد'}
                    description="الفواتير والإيجارات والأقساط والاشتراكات تُحجز من ميزانيتك تلقائياً، فتعرف كم يصفى لك فعلاً."
                    actionLabel="أضف أول التزام"
                    onAction={() => (sheetOpen = true)}
                />
            {/if}

            {#if !filter || filter === 'installment'}
                <FreedomCard label={freedom?.label ?? ''} monthly={freedom?.monthly ?? 0} />
            {/if}
        </div>
    </div>

    <AddCommitmentSheet
        bind:open={sheetOpen}
        {income}
        {salaryDay}
        currentObligations={totals.total}
        {processing}
        onSave={save}
    />
</AppLayout>
