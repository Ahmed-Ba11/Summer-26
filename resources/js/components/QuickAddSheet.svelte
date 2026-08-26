<script lang="ts">
    /**
     * لوح الإضافة السريعة — من 9 خطوات إلى 3.
     *
     * المبلغ هو البطل. الفئة بضغطة. الوصف اختياري — إلا لفئة «أخرى»
     * فيصير إلزامياً، لأن «أخرى بلا وصف» سجل بلا معنى بعد أسبوع.
     *
     * معاينة الأثر فوق زر الحفظ تتحدّث مع كل رقم وكل فئة — فيعرف
     * المستخدم النتيجة **قبل** الحفظ، وهذي اللحظة الوحيدة اللي يقدر
     * يغيّر فيها قراره.
     */
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Delete from 'lucide-svelte/icons/delete';
    import Ellipsis from 'lucide-svelte/icons/ellipsis';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Info from 'lucide-svelte/icons/info';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import FundingSourcePicker, {
        type Funding,
        type SavingsGoalOption,
    } from '@/components/FundingSourcePicker.svelte';
    import { formatAmount, formatCurrency } from '@/lib/format';
    import {
        checkExpense,
        checkIncome,
        isBlocked,
        needsConfirm,
        SEVERITY_STYLES,
        availableToSpend,
        type Check as RuleCheck,
        type FinancialContext,
    } from '@/lib/money-rules';

    interface Cat {
        id: number;
        name: string;
        icon: string;
        color: string;
        budget: number;
        spent: number;
        averageEntry: number;
    }

    interface Learned {
        label: string;
        amount: number;
        categoryId: number;
    }

    let {
        open = $bindable(false),
        mode = $bindable<'expense' | 'income'>('expense'),
        categories = [] as Cat[],
        context,
        lastCategoryId = null as number | null,
        learned = [] as Learned[],
        recurringIncome = 0,
        fundableGoals = [],
    }: {
        open?: boolean;
        mode?: 'expense' | 'income';
        categories?: Cat[];
        context: FinancialContext;
        lastCategoryId?: number | null;
        learned?: Learned[];
        recurringIncome?: number;
        fundableGoals?: SavingsGoalOption[];
    } = $props();

    let raw = $state('');
    let categoryId = $state<number | null>(null);
    let description = $state('');
    let confirmed = $state(false);
    let submitting = $state(false);
    let funding = $state<Funding>({
        source: null,
        savingsGoalId: null,
        incomeAmount: 0,
        incomeSource: '',
    });

    // آخر فئة استخدمها المستخدم مختارة مسبقاً — فأغلب المرات ما يلمسها
    $effect(() => {
        if (open && categoryId === null) categoryId = lastCategoryId;
    });

    const amount = $derived(Math.round((parseFloat(raw) || 0) * 100));
    const selected = $derived(categories.find((c) => c.id === categoryId) ?? null);

    /** فئة «أخرى» تُلزم بوصف — وإلا صار السجل بلا معنى بعد أسبوع. */
    const isOther = $derived(selected?.name === 'أخرى' || selected?.icon === 'ellipsis');
    const descriptionMissing = $derived(isOther && description.trim().length < 2);

    const shortfall = $derived(
        mode === 'expense' ? Math.max(0, amount - availableToSpend(context)) : 0,
    );

    const checks = $derived.by<RuleCheck[]>(() => {
        if (amount <= 0) return [];

        const list =
            mode === 'income'
                ? checkIncome(amount, recurringIncome)
                : checkExpense(
                      amount,
                      context,
                      selected
                          ? {
                                name: selected.name,
                                budget: selected.budget,
                                spent: selected.spent,
                                averageEntry: selected.averageEntry,
                            }
                          : undefined,
                  );

        if (descriptionMissing) {
            list.unshift({
                severity: 'block',
                title: 'اكتب وصفاً مختصراً',
                detail: 'فئة «أخرى» تحتاج وصفاً، وإلا ما بتعرف وش كان هذا المصروف بعد أسبوع.',
            });
        }

        // عند وجود عجز، تحذير «المتبقي بيصير سالب» يغطّيه FundingSourcePicker —
        // لا نكرّره فيتشوّش الرسالة (STAGE3-funding بند 4و).
        return shortfall > 0 ? list.filter((c) => c.severity !== 'danger') : list;
    });

    const blocked = $derived(isBlocked(checks));
    const fundingReady = $derived(
        shortfall === 0
            || (funding.source === 'savings' && funding.savingsGoalId !== null)
            || (funding.source === 'unlogged_income' && funding.incomeAmount >= shortfall)
            || funding.source === 'overspend',
    );
    const mustConfirm = $derived(needsConfirm(checks) && !confirmed);
    const canSave = $derived(amount > 0 && !blocked && fundingReady && !submitting);

    // ── معاينة الأثر ──────────────────────────────────────────────────
    const preview = $derived.by(() => {
        if (amount <= 0 || mode === 'income') return null;
        const before = availableToSpend(context);
        const after = before - amount;
        return {
            before,
            after,
            dailyBefore: context.daysUntilSalary > 0 ? Math.floor(before / context.daysUntilSalary) : 0,
            dailyAfter: context.daysUntilSalary > 0 ? Math.floor(after / context.daysUntilSalary) : 0,
        };
    });

    // ── لوحة الأرقام ──────────────────────────────────────────────────
    function press(key: string) {
        confirmed = false;
        if (key === 'del') {
            raw = raw.slice(0, -1);
            return;
        }
        if (key === '.') {
            if (!raw.includes('.')) raw = raw === '' ? '0.' : raw + '.';
            return;
        }
        const [, dec] = raw.split('.');
        if (dec !== undefined && dec.length >= 2) return;
        if (raw === '0') raw = key;
        else raw += key;
    }

    function applyLearned(l: Learned) {
        raw = (l.amount / 100).toString();
        categoryId = l.categoryId;
        description = l.label;
        confirmed = false;
    }

    function bump(riyals: number) {
        raw = (((parseFloat(raw) || 0) + riyals)).toString();
        confirmed = false;
    }

    function reset() {
        raw = '';
        description = '';
        confirmed = false;
        categoryId = lastCategoryId;
        funding = {
            source: null,
            savingsGoalId: null,
            incomeAmount: 0,
            incomeSource: '',
        };
    }

    function close() {
        open = false;
        reset();
    }

    function submit() {
        if (!canSave) return;
        if (mustConfirm) {
            confirmed = true;
            return;
        }

        submitting = true;
        const url = mode === 'income' ? '/income' : '/expenses';

        router.post(
            url,
            {
                amount: amount / 100,
                category_id: mode === 'expense' ? categoryId : undefined,
                description: description.trim() || undefined,
                expense_date: mode === 'expense' ? new Date().toISOString().slice(0, 10) : undefined,
                income_date: mode === 'income' ? new Date().toISOString().slice(0, 10) : undefined,
                funding_source: mode === 'expense' && shortfall > 0 ? funding.source : undefined,
                savings_goal_id: mode === 'expense' && shortfall > 0 ? funding.savingsGoalId ?? undefined : undefined,
                income_amount: mode === 'expense' && shortfall > 0 ? funding.incomeAmount || undefined : undefined,
                income_source: mode === 'expense' && shortfall > 0 ? funding.incomeSource || undefined : undefined,
            } as never,
            {
                preserveScroll: true,
                onSuccess: close,
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    function onKeydown(e: KeyboardEvent) {
        if (!open) return;
        if (e.key === 'Enter' && canSave) return submit();
        if (/^[0-9]$/.test(e.key)) return press(e.key);
        if (e.key === '.') return press('.');
        if (e.key === 'Backspace' && (e.target as HTMLElement)?.tagName !== 'INPUT') return press('del');
    }

    const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
    const today = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date());
</script>

<svelte:window onkeydown={onKeydown} />

<SheetShell
    bind:open
    title={mode === 'income' ? 'إضافة دخل' : 'إضافة مصروف'}
    subtitle="اليوم · {today}"
    onClose={reset}
>
    <div class="flex flex-col gap-3">
        <!-- مصروف / دخل -->
        <div class="flex rounded-2xl border border-border bg-secondary p-[3px]">
            <button
                type="button"
                aria-pressed={mode === 'expense'}
                class="min-h-10 flex-1 rounded-xl text-[12.5px] transition-colors {mode === 'expense'
                    ? 'bg-card font-semibold shadow-xs'
                    : 'text-muted-foreground'}"
                onclick={() => { mode = 'expense'; confirmed = false; }}
            >
                مصروف
            </button>
            <button
                type="button"
                aria-pressed={mode === 'income'}
                class="min-h-10 flex-1 rounded-xl text-[12.5px] transition-colors {mode === 'income'
                    ? 'bg-card font-semibold text-success-text shadow-xs'
                    : 'text-muted-foreground'}"
                onclick={() => { mode = 'income'; confirmed = false; }}
            >
                دخل
            </button>
        </div>

        <!-- المبلغ -->
        <p class="text-center text-[42px] leading-none font-semibold tracking-[-0.04em] tabular-nums">
            {#if raw}
                {formatAmount(amount)}
            {:else}
                <span class="text-input">0</span>
            {/if}
            <span class="ms-1.5 text-[15px] font-medium text-muted-foreground">ر.س</span>
        </p>

        <!-- رقائق سريعة ومتعلَّمة -->
        <div class="flex flex-wrap justify-center gap-1.5">
            {#each [10, 50, 100] as v (v)}
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-12 items-center justify-center rounded-xl border border-input px-3 text-[13px] font-medium text-foreground/85"
                    onclick={() => bump(v)}
                >
                    +{v}
                </button>
            {/each}
            {#each learned.slice(0, 2) as l (l.label)}
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-dashed border-input px-3 text-[12.5px] text-foreground/85"
                    onclick={() => applyLearned(l)}
                >
                    {formatAmount(l.amount)} · {l.label}
                </button>
            {/each}
        </div>

        <!-- الفئات -->
        {#if mode === 'expense'}
            <div class="grid grid-cols-4 gap-1.5">
                {#each categories as c (c.id)}
                    <button
                        type="button"
                        aria-pressed={categoryId === c.id}
                        onclick={() => { categoryId = c.id; confirmed = false; }}
                        class="flex min-w-0 flex-col items-center gap-1.5 rounded-2xl border px-1.5 pt-2.5 pb-2 transition-colors {categoryId === c.id
                            ? 'border-current'
                            : 'border-border'}"
                        style={categoryId === c.id ? `color:${c.color};background:color-mix(in srgb,${c.color} 7%,transparent)` : ''}
                    >
                        <CategoryIcon icon={c.icon} color={c.color} size="sm" />
                        <span class="max-w-full truncate text-[11px] {categoryId === c.id ? 'font-semibold text-foreground' : 'text-foreground/70'}">
                            {c.name}
                        </span>
                    </button>
                {/each}
            </div>
        {/if}

        <!-- الوصف -->
        <input
            bind:value={description}
            placeholder={isOther ? 'وش كان هذا المصروف؟ (مطلوب)' : 'وصف مختصر — اختياري'}
            aria-invalid={descriptionMissing}
            class="min-h-11 w-full rounded-2xl border bg-background px-3 text-[14px] outline-none focus:ring-2 focus:ring-ring {descriptionMissing
                ? 'border-destructive'
                : 'border-input'}"
        />

        <!-- لوحة الأرقام -->
        <div class="grid grid-cols-3 gap-2">
            {#each KEYS as k (k)}
                <button
                    type="button"
                    class="grid min-h-[50px] place-items-center rounded-2xl border border-border bg-secondary text-[21px] font-medium tabular-nums transition-transform active:scale-[.97]"
                    onclick={() => press(k)}
                >
                    {k}
                </button>
            {/each}
            <button
                type="button"
                class="grid min-h-[50px] place-items-center rounded-2xl border border-border bg-secondary text-[21px] text-muted-foreground transition-transform active:scale-[.97]"
                onclick={() => press('.')}
                aria-label="فاصلة عشرية">.</button
            >
            <button
                type="button"
                class="grid min-h-[50px] place-items-center rounded-2xl border border-border bg-secondary text-[21px] font-medium tabular-nums transition-transform active:scale-[.97]"
                onclick={() => press('0')}>0</button
            >
            <button
                type="button"
                class="grid min-h-[50px] place-items-center rounded-2xl border border-border bg-secondary text-muted-foreground transition-transform active:scale-[.97]"
                onclick={() => press('del')}
                aria-label="حذف"
            >
                <Delete class="size-[21px]" />
            </button>
        </div>

        <!-- التحذيرات -->
        {#each checks as c (c.title)}
            {@const st = SEVERITY_STYLES[c.severity]}
            <div class="flex items-start gap-2.5 rounded-2xl border px-3 py-2 text-[11.5px] {st.box}">
                {#if c.severity === 'info'}
                    <Info class="mt-0.5 size-3.5 shrink-0 {st.icon}" />
                {:else}
                    <TriangleAlert class="mt-0.5 size-3.5 shrink-0 {st.icon}" />
                {/if}
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{c.title}</p>
                    {#if c.detail}<p class="opacity-85">{c.detail}</p>{/if}
                </div>
                {#if c.suggestion}
                    <button
                        type="button"
                        class="inline-flex min-h-9 shrink-0 items-center rounded-lg border border-current/30 px-2 text-[11px] font-medium"
                        onclick={() => (raw = (c.suggestion!.value / 100).toString())}
                    >
                        {c.suggestion.label}
                    </button>
                {/if}
            </div>
        {/each}

        {#if shortfall > 0}
            <FundingSourcePicker {shortfall} goals={fundableGoals} bind:value={funding} />
        {/if}

        <!-- معاينة الأثر -->
        {#if preview}
            <div class="rounded-2xl border border-border bg-secondary px-3 py-2">
                <div class="flex items-center justify-between gap-2.5 py-[3px] text-[11.5px]">
                    <span class="text-muted-foreground">المتبقي لك</span>
                    <span class="whitespace-nowrap text-foreground/75">
                        <b class="font-semibold text-foreground tabular-nums">{formatAmount(preview.before)}</b>
                        <span class="mx-0.5 text-input">←</span>
                        <b class="font-semibold tabular-nums {preview.after < 0 ? 'text-destructive' : 'text-foreground'}">
                            {formatAmount(preview.after)}
                        </b> ر.س
                    </span>
                </div>
                {#if context.daysUntilSalary > 0}
                    <div class="flex items-center justify-between gap-2.5 py-[3px] text-[11.5px]">
                        <span class="text-muted-foreground">الحد اليومي الآمن</span>
                        <span class="whitespace-nowrap text-foreground/75">
                            <b class="font-semibold text-foreground tabular-nums">{formatAmount(preview.dailyBefore)}</b>
                            <span class="mx-0.5 text-input">←</span>
                            <b class="font-semibold text-foreground tabular-nums">{formatAmount(preview.dailyAfter)}</b> ر.س
                        </span>
                    </div>
                {/if}
            </div>
        {/if}
    </div>

    {#snippet footer()}
        <button
            type="button"
            disabled={!canSave}
            onclick={submit}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl text-[14.5px] font-semibold transition-transform active:scale-[.99] {mustConfirm
                ? 'bg-destructive text-white'
                : 'bg-primary text-primary-foreground'} disabled:bg-input disabled:text-muted-foreground"
        >
            {#if mustConfirm}
                <TriangleAlert class="size-[18px]" /> أكمل رغم التحذير
            {:else}
                <Check class="size-[18px]" />
                {amount > 0 ? `حفظ ${formatCurrency(amount)}` : 'حفظ'}
            {/if}
        </button>
        <button
            type="button"
            class="grid size-12 shrink-0 place-items-center rounded-2xl border border-input text-foreground/75"
            aria-label="خيارات إضافية"
            title="التاريخ · متكرر · مرفق"
        >
            <Ellipsis class="size-[18px]" />
        </button>
    {/snippet}
</SheetShell>
