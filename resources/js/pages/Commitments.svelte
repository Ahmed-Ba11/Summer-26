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
    import {
        formatAmount,
        formatCurrency,
        formatRelativeDays,
    } from '@/lib/format';
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
    /** الالتزام المفتوح للتعديل — `null` يعني إضافة جديدة. */
    let editing = $state<Commitment | null>(null);

    const totals = $derived(totalsOf(commitments));

    /** ترتيب مقصود: المتأخّر أولاً، ثم الأقرب استحقاقاً، والمدفوع أخيراً. */
    const visible = $derived.by(() => {
        const list = filter
            ? commitments.filter((c) => c.kind === filter)
            : commitments;
        const rank = { overdue: 0, upcoming: 1, paid: 2 } as const;
        return [...list].sort((a, b) => {
            const ra = rank[stateOf(a)];
            const rb = rank[stateOf(b)];
            return ra !== rb
                ? ra - rb
                : daysUntil(a.due_date) - daysUntil(b.due_date);
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
                const soonest = unpaid.reduce((a, b) =>
                    daysUntil(a.due_date) <= daysUntil(b.due_date) ? a : b,
                );
                note =
                    t.paidCount > 0
                        ? `دُفع ${t.paidCount} · باقي ${unpaid.length}`
                        : `${list.length > 1 ? 'أقربها' : 'يستحق'} ${formatRelativeDays(soonest.due_date)}`;
            }
        }
        return { count: list.length, total: t.total, paid: t.paid, note };
    }

    const freedom = $derived(
        freedomDay(commitments.filter((c) => c.kind === 'installment')),
    );

    function pay(c: Commitment) {
        router.post(
            `/commitments/${c.id}/pay`,
            { amount: expectedAmount(c) },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        `تم دفع ${KIND_LABEL[c.kind]} ${c.name} ${formatCurrency(expectedAmount(c))}`,
                        {
                            action: { label: 'تراجع', onClick: () => undo(c) },
                            duration: 5000,
                        },
                    ),
            },
        );
    }

    function undo(c: Commitment) {
        router.delete(`/commitments/${c.id}/pay`, {
            preserveScroll: true,
            onSuccess: () => toast.success(`رجّعنا ${c.name} إلى «محجوز»`),
        });
    }

    /**
     * التعديل يفتح نفس اللوح مُعبّأً بالالتزام.
     *
     * كان يستدعي `GET /commitments/{id}/edit`، والـcontroller هناك يعيد
     * التحويل إلى القائمة فوراً — فلا لوح يُفتح ولا شيء يُحفظ، ومسار
     * `PUT /commitments/{id}` الموجود أصلاً لم يكن يُستدعى من الواجهة.
     */
    function edit(c: Commitment) {
        editing = c;
        sheetOpen = true;
    }

    /**
     * «تستحق بعد يومين» — الإشعار يذكر الموعد لا مجرّد «تمت الإضافة»،
     * فأول سؤال بعد تسجيل التزام هو متى يُسحب المبلغ.
     */
    function dueNote(payload: Record<string, unknown>): string {
        const day = Number(payload.due_day ?? 0);

        if (!day) {
            return '';
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const next = new Date(today.getFullYear(), today.getMonth(), day);

        if (next < today) {
            next.setMonth(next.getMonth() + 1);
        }

        return ` — تستحق ${formatRelativeDays(next.toISOString().slice(0, 10))}`;
    }

    function openAdd() {
        editing = null;
        sheetOpen = true;
    }

    function save(payload: Record<string, unknown>) {
        processing = true;
        const label = KIND_LABEL[payload.kind as CommitmentKind];
        const target = editing;

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                sheetOpen = false;
                editing = null;
                toast.success(
                    target
                        ? `حُفظ تعديل ${payload.name}`
                        : `تمت إضافة ${label} ${payload.name}${dueNote(payload)}`,
                );
            },
            onFinish: () => (processing = false),
        };

        // الحمولة كائن عادي؛ `RequestPayload` يشمل FormData فيمنع قراءة حقوله.
        const body = payload as Parameters<typeof router.post>[1];

        if (target) {
            router.put(`/commitments/${target.id}`, body, options);

            return;
        }

        router.post('/commitments', body, options);
    }
</script>

<AppLayout>
    <div class="mx-auto w-full max-w-3xl p-3 md:p-6">
        <!-- الرأس -->
        <header class="mb-3">
            <h1 class="truncate text-[17px] font-semibold tracking-tight">
                التزاماتي
            </h1>
            <p class="mt-0.5 truncate text-[11.5px] text-muted-foreground">
                {commitments.length} التزام
                {#if totals.overdueCount}· <span
                        class="font-medium text-destructive"
                        >{totals.overdueCount} متأخّر</span
                    >{/if}
                {#if periodLabel}· {periodLabel}{/if}
            </p>
        </header>

        <div class="space-y-3">
            <CommitmentHealth
                total={totals.total}
                paid={totals.paid}
                reserved={totals.reserved}
                {income}
            />

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

            <!--
                زر الإضافة داخل التدفّق تحت بطاقات الأنواع مباشرة: نصّ صريح،
                لا أيقونة مبهمة ولا زر عائم يغطّي البطاقة التي خلفه.
            -->
            <button
                type="button"
                onclick={openAdd}
                class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-input bg-card text-[12.5px] font-semibold text-primary transition-colors hover:bg-secondary"
            >
                <Plus class="size-4" /> إضافة التزام
            </button>

            {#if filter}
                <div class="flex items-center justify-between gap-2 px-0.5">
                    <p class="text-[11.5px] text-muted-foreground">
                        تعرض: <b class="font-semibold text-foreground"
                            >{KIND_LABEL_PLURAL[filter]}</b
                        >
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
                        <CommitmentCard
                            commitment={c}
                            onPay={pay}
                            onEdit={edit}
                            onUndo={undo}
                        />
                    {/each}
                </div>
            {:else}
                <EmptyState
                    icon="receipt"
                    title={filter
                        ? `ما عندك ${KIND_LABEL_PLURAL[filter]}`
                        : 'ما أضفت التزامات بعد'}
                    description="الفواتير والإيجارات والأقساط والاشتراكات تُحجز من ميزانيتك تلقائياً، فتعرف كم يصفى لك فعلاً."
                    actionLabel="أضف أول التزام"
                    onAction={openAdd}
                />
            {/if}

            {#if !filter || filter === 'installment'}
                <FreedomCard
                    label={freedom?.label ?? ''}
                    monthly={freedom?.monthly ?? 0}
                />
            {/if}
        </div>
    </div>

    <AddCommitmentSheet
        bind:open={sheetOpen}
        {income}
        {salaryDay}
        {editing}
        currentObligations={editing
            ? totals.total - expectedAmount(editing)
            : totals.total}
        {processing}
        onSave={save}
    />
</AppLayout>
