<script lang="ts">
    /**
     * لوح الإضافة السريعة — خطوتان لا شاشة واحدة مكدّسة.
     *
     * الخطوة 1 · الإدخال: المبلغ بطل الشاشة، الفئة بضغطة، الوصف اختياري —
     * إلا لفئة «أخرى» فيصير إلزامياً، لأن «أخرى بلا وصف» سجل بلا معنى.
     *
     * الخطوة 2 · التمويل: تظهر **فقط** لما يتجاوز المصروف المتبقي للصرف.
     * فصلها عن الإدخال ضروري — السؤال «من وين جاء المبلغ؟» يستحق شاشة
     * كاملة، وحشره تحت لوحة الأرقام كان يطلع به عن حدود الشاشة.
     *
     * التحذيرات: **سطر واحد** الأشدّ خطورة فوق لوحة الأرقام ليبقى قريباً
     * من الإبهام، والبقيّة خلف «التفاصيل». تكديس أربع بطاقات تحذير يجعل
     * المستخدم يتجاهلها كلها.
     */
    import { router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Check from 'lucide-svelte/icons/check';
    import Delete from 'lucide-svelte/icons/delete';
    import Ellipsis from 'lucide-svelte/icons/ellipsis';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Info from 'lucide-svelte/icons/info';
    import Wand from 'lucide-svelte/icons/wand-sparkles';
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
        type Severity,
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
    /**
     * محاولة حفظ بلا فئة — أسوأ خطأ في التطبيق كان هنا: الطلب يُرسَل،
     * الخادم يردّه بخطأ تحقّق، ولا `onError` يلتقطه ولا شيء يظهر. المستخدم
     * يظنّ أنه سجّل مصروفه وهو لم يُسجَّل. صار التحقّق قبل الإرسال.
     */
    let categoryError = $state(false);
    /** خطأ ردّه الخادم — لا يُبتلع بعد اليوم. */
    let serverError = $state('');
    /** 1 = الإدخال · 2 = «من وين جاء المبلغ؟» */
    let step = $state<1 | 2>(1);
    let showDetails = $state(false);
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

    /** المصروف بلا فئة لا يُقبل في الخادم — فلا يُرسَل أصلاً. */
    const categoryMissing = $derived(mode === 'expense' && categoryId === null);
    /**
     * الفئة وحدها هي ما ينقص؟ نُبقي الزر حيّاً ليقول السبب عند الضغط.
     * زر ميت بلا تفسير هو نفسه الصمت الذي نصلحه هنا.
     */
    const onlyCategoryMissing = $derived(categoryMissing && amount > 0);

    // الخطأ يزول لحظة تصحيحه، لا عند المحاولة التالية.
    $effect(() => {
        if (!categoryMissing) categoryError = false;
    });

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

        // عند وجود عجز، تحذير «المتبقي بيصير سالب» تغطّيه الخطوة 2 —
        // لا نكرّره فيتشوّش الرسالة (STAGE3-funding بند 4و).
        return shortfall > 0 ? list.filter((c) => c.severity !== 'danger') : list;
    });

    /**
     * اقتراح التصحيح يُعرض رقاقةً تحت الرقم — نفس مكانه في `AmountSheet` —
     * لا بطاقة تحذير مستقلة، فهو دعوة لتصحيح لا إنذار.
     */
    const suggestion = $derived(checks.find((c) => c.suggestion) ?? null);

    const SEVERITY_ORDER: Severity[] = ['block', 'danger', 'warn', 'info'];

    /** التحذيرات مرتّبة بالأشدّ أولاً — الأول وحده يظهر، والباقي خلف «التفاصيل». */
    const warnings = $derived(
        [...checks.filter((c) => c !== suggestion)].sort(
            (a, b) => SEVERITY_ORDER.indexOf(a.severity) - SEVERITY_ORDER.indexOf(b.severity),
        ),
    );

    /** ما يُعرض في الخطوة 2 أسفل الخيارات — خبر لا منافس على الانتباه. */
    const fundingNotes = $derived(warnings.map((c) => ({ title: c.title, detail: c.detail })));

    const blocked = $derived(isBlocked(checks));
    const fundingReady = $derived(
        shortfall === 0
            || (funding.source === 'savings' && funding.savingsGoalId !== null)
            || (funding.source === 'unlogged_income' && funding.incomeAmount >= shortfall)
            || funding.source === 'overspend',
    );
    const mustConfirm = $derived(needsConfirm(checks) && !confirmed);
    /** يكفي لعبور الخطوة 1 — لا يشترط اختيار مصدر تمويل بعد. */
    const canProceed = $derived(amount > 0 && !blocked && !submitting);
    const canSave = $derived(canProceed && fundingReady);

    // المبلغ نزل تحت المتبقي بعد الرجوع؟ ما عاد فيه خطوة ثانية.
    $effect(() => {
        if (shortfall === 0 && step === 2) step = 1;
    });

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
        categoryError = false;
        serverError = '';
        step = 1;
        showDetails = false;
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

    /**
     * بوّابة واحدة لكل انتقال إلى الأمام (الحفظ أو «التالي»): بلا فئة لا
     * نتقدّم، ويظهر الخطأ تحت شبكة الفئات ويبقى اللوح مفتوحاً.
     */
    function guardCategory(): boolean {
        if (!categoryMissing) return true;

        categoryError = true;
        serverError = '';

        return false;
    }

    function next() {
        if (!guardCategory() || !canProceed) return;

        step = 2;
    }

    function submit() {
        if (!guardCategory()) return;
        if (!canSave) return;
        if (mustConfirm) {
            confirmed = true;
            return;
        }

        serverError = '';
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
                // بلا هذا كان الخطأ يُبتلع واللوح يبقى مفتوحاً بلا سبب ظاهر.
                onError: (errors) => {
                    const first = Object.values(
                        errors as Record<string, string | string[]>,
                    )[0];
                    serverError = Array.isArray(first)
                        ? (first[0] ?? 'ما قدرنا نحفظ — راجع الحقول')
                        : (first ?? 'ما قدرنا نحفظ — راجع الحقول');
                    categoryError = 'category_id' in (errors ?? {});
                    step = 1;
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    function onKeydown(e: KeyboardEvent) {
        if (!open || step !== 1) return;
        if (e.key === 'Enter') {
            if (shortfall > 0) return next();
            return submit();
        }
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
    title={step === 2 ? 'من وين جاء المبلغ؟' : mode === 'income' ? 'إضافة دخل' : 'إضافة مصروف'}
    subtitle={step === 2 ? (selected?.name ?? '') : `اليوم · ${today}`}
    showBack={step === 2}
    steps={shortfall > 0 ? 2 : 0}
    currentStep={step}
    stepLabel={shortfall > 0 ? `الخطوة ${step} من 2` : ''}
    onBack={() => (step = 1)}
    onClose={reset}
>
    {#if step === 2}
        <FundingSourcePicker
            {amount}
            {shortfall}
            goals={fundableGoals}
            notes={fundingNotes}
            bind:value={funding}
        />
    {:else}
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
        <div class="text-center">
            <p class="text-[42px] leading-none font-semibold tracking-[-0.04em] tabular-nums">
                {#if raw}
                    {formatAmount(amount)}
                {:else}
                    <span class="text-input">0</span>
                {/if}
                <span class="ms-1.5 text-[15px] font-medium text-muted-foreground">ر.س</span>
            </p>

            <!-- اقتراح التصحيح — رقاقة تحت الرقم لا بطاقة تحذير -->
            {#if suggestion?.suggestion}
                <button
                    type="button"
                    onclick={() => {
                        const v = suggestion?.suggestion?.value;
                        if (v !== undefined) raw = (v / 100).toString();
                    }}
                    class="mt-2 inline-flex min-h-11 items-center gap-1.5 rounded-full border border-primary/25 bg-primary/8 px-3 text-[11.5px] font-semibold text-primary transition-transform active:scale-[.98]"
                >
                    <Wand class="size-[17px]" stroke-width="1.9" />
                    {suggestion.suggestion.label}
                </button>
            {/if}
        </div>

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
            <div
                class="grid grid-cols-4 gap-1.5 {categoryError
                    ? 'rounded-2xl outline-2 outline-offset-4 outline-destructive'
                    : ''}"
                role="group"
                aria-label="فئة المصروف"
                aria-invalid={categoryError}
            >
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

            {#if categoryError}
                <p
                    class="-mt-1 flex items-start gap-2 text-[11.5px] font-semibold text-destructive"
                    role="alert"
                >
                    <TriangleAlert class="mt-px size-4 shrink-0" />
                    اختر فئة للمصروف — أو «أخرى» إن ما كانت من الفئات.
                </p>
            {/if}
        {/if}

        <!-- خطأ ردّه الخادم -->
        {#if serverError}
            <p
                class="flex items-start gap-2 rounded-2xl border border-destructive/35 bg-destructive/8 px-3 py-2 text-[11.5px] font-medium text-destructive"
                role="alert"
            >
                <TriangleAlert class="mt-px size-4 shrink-0" />
                {serverError}
            </p>
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

        <!-- معاينة الأثر — سطر واحد مضغوط، النتيجة قبل الحفظ -->
        {#if preview}
            <div class="flex items-center justify-between gap-2.5 rounded-2xl border border-border bg-secondary px-3 py-2 text-[11.5px]">
                <span class="text-muted-foreground">المتبقي لك</span>
                <span class="whitespace-nowrap text-foreground/75">
                    <b class="font-semibold text-foreground tabular-nums">{formatAmount(preview.before)}</b>
                    <span class="mx-0.5 text-input">←</span>
                    <b class="font-semibold tabular-nums {preview.after < 0 ? 'text-destructive' : 'text-foreground'}">
                        {formatAmount(preview.after)}
                    </b> ر.س
                </span>
            </div>
        {/if}

        <!-- التحذيرات — الأشدّ وحده فوق لوحة الأرقام، والباقي خلف «التفاصيل» -->
        {#if warnings.length}
            {@const top = warnings[0]}
            {@const st = SEVERITY_STYLES[top.severity]}
            <div class="rounded-2xl border px-3 py-2 text-[11.5px] {st.box}">
                <div class="flex items-start gap-2.5">
                    {#if top.severity === 'info'}
                        <Info class="mt-0.5 size-[17px] shrink-0 {st.icon}" stroke-width="1.9" />
                    {:else}
                        <TriangleAlert class="mt-0.5 size-[17px] shrink-0 {st.icon}" stroke-width="1.9" />
                    {/if}
                    <p class="min-w-0 flex-1 font-semibold">{top.title}</p>
                    {#if warnings.length > 1 || top.detail}
                        <button
                            type="button"
                            onclick={() => (showDetails = !showDetails)}
                            aria-expanded={showDetails}
                            class="-my-2 inline-flex min-h-11 shrink-0 items-center px-1.5 text-[11.5px] font-medium underline underline-offset-2"
                        >
                            {showDetails ? 'إخفاء' : 'التفاصيل'}
                        </button>
                    {/if}
                </div>

                {#if showDetails}
                    {#if top.detail}<p class="mt-1 opacity-85">{top.detail}</p>{/if}
                    {#each warnings.slice(1) as c (c.title)}
                        <div class="mt-2 border-t border-current/15 pt-2">
                            <p class="font-semibold">{c.title}</p>
                            {#if c.detail}<p class="opacity-85">{c.detail}</p>{/if}
                        </div>
                    {/each}
                {/if}
            </div>
        {/if}

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

    </div>
    {/if}

    {#snippet footer()}
        {#if step === 2}
            <button
                type="button"
                onclick={() => (step = 1)}
                class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 transition-transform active:scale-[.98]"
            >
                رجوع
            </button>
            <button
                type="button"
                disabled={!canSave}
                onclick={submit}
                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:bg-input disabled:text-muted-foreground"
            >
                <Check class="size-[18px]" stroke-width="1.9" />
                حفظ {formatCurrency(amount)}
            </button>
        {:else if shortfall > 0}
            <button
                type="button"
                disabled={!canProceed && !onlyCategoryMissing}
                onclick={next}
                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:bg-input disabled:text-muted-foreground"
            >
                التالي
                <ArrowLeft class="size-[18px]" stroke-width="1.9" />
            </button>
            <button
                type="button"
                class="grid size-12 shrink-0 place-items-center rounded-2xl border border-input text-foreground/75"
                aria-label="خيارات إضافية"
                title="التاريخ · متكرر · مرفق"
            >
                <Ellipsis class="size-[18px]" stroke-width="1.9" />
            </button>
        {:else}
            <!--
                الزر يبقى قابلاً للضغط حين تنقص الفئة: زر ميت بلا تفسير هو
                نفسه الصمت الذي نصلحه هنا.
            -->
            <button
                type="button"
                disabled={!canSave && !onlyCategoryMissing}
                onclick={submit}
                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl text-[14.5px] font-semibold transition-transform active:scale-[.99] {mustConfirm
                    ? 'bg-destructive text-white'
                    : 'bg-primary text-primary-foreground'} disabled:bg-input disabled:text-muted-foreground"
            >
                {#if mustConfirm}
                    <TriangleAlert class="size-[18px]" stroke-width="1.9" /> أكمل رغم التحذير
                {:else}
                    <Check class="size-[18px]" stroke-width="1.9" />
                    {amount > 0 ? `حفظ ${formatCurrency(amount)}` : 'حفظ'}
                {/if}
            </button>
            <button
                type="button"
                class="grid size-12 shrink-0 place-items-center rounded-2xl border border-input text-foreground/75"
                aria-label="خيارات إضافية"
                title="التاريخ · متكرر · مرفق"
            >
                <Ellipsis class="size-[18px]" stroke-width="1.9" />
            </button>
        {/if}
    {/snippet}
</SheetShell>
