<script module lang="ts">
    export const layout = null;
</script>

<script lang="ts">
    /**
     * الإعداد — أربع خطوات بشريط تقدّم.
     *
     *   1) الراتب ويومه            2) الالتزامات الثابتة
     *   3) الادخار والميزانية      4) الملخّص
     *
     * كل خطوة تُحفظ لحظة إنهائها و`onboarding_step` يتقدّم معها، فمن يخرج
     * في الخطوة الثانية يرجع إليها لا إلى الأولى. وكل خطوة قابلة للتخطّي
     * عدا الراتب: بدونه لا يوجد رقم يُبنى عليه شيء.
     *
     * كل مبلغ هنا يمرّ من `AmountSheet` — لا حقول نصّية للمبالغ.
     */
    import { router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Bell from 'lucide-svelte/icons/bell';
    import Check from 'lucide-svelte/icons/check';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import Plus from 'lucide-svelte/icons/plus';
    import Repeat from 'lucide-svelte/icons/repeat';
    import Wallet from 'lucide-svelte/icons/wallet';
    import AppHead from '@/components/AppHead.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DayOfMonthPicker from '@/components/ui/DayOfMonthPicker.svelte';
    import ToggleRow from '@/components/ui/ToggleRow.svelte';
    import { iconFor } from '@/lib/category-icons';
    import { formatAmount } from '@/lib/format';
    import { obligationHealth, suggestBudgets } from '@/lib/money';

    interface Preset {
        key: string;
        name: string;
        kind: string;
        icon: string;
    }

    interface SetupCategory {
        id: number;
        name: string;
        icon: string;
        color: string;
    }

    let {
        step: initialStep = 1,
        presets = [] as Preset[],
        categories = [] as SetupCategory[],
        saved,
        salaryMonth,
    }: {
        step?: number;
        presets?: Preset[];
        categories?: SetupCategory[];
        saved: {
            income: number;
            salaryDay: number;
            savingsTarget: number;
            notifyDue: boolean;
            biometricLock: boolean;
            commitmentsTotal: number;
            commitmentNames: string[];
            budgetsTotal: number;
        };
        salaryMonth: {
            key: string;
            label: string;
            range: string;
            totalDays: number;
        };
    } = $props();

    const TOTAL_STEPS = 4;
    const STEP_TITLES = ['راتبك', 'التزاماتك', 'ادخارك وميزانيتك', 'ملخّصك'];

    let step = $state(Math.min(TOTAL_STEPS, Math.max(1, initialStep)));
    let submitting = $state(false);

    // ── الخطوة 1 ──────────────────────────────────────────────────────
    let salary = $state(saved.income);
    let salaryDay = $state(saved.salaryDay || 27);
    let salaryRecurring = $state(true);
    let extraAmount = $state(0);
    let extraSource = $state('');
    let showExtra = $state(false);

    // ── الخطوة 2 ──────────────────────────────────────────────────────
    /** المختار من البطاقات الجاهزة: المفتاح → المبلغ بالهللات */
    let picked = $state<Record<string, number>>({});
    let installmentMonths = $state(12);

    const commitmentsTotal = $derived(
        Object.values(picked).reduce((sum, amount) => sum + amount, 0) ||
            saved.commitmentsTotal,
    );

    /**
     * التزام حُفظ في زيارة سابقة لا يُعرض قابلاً للاختيار من جديد — وإلا
     * أنشأ من رجع للخطوة 2 نسخة ثانية من إيجاره بلا ما ينتبه.
     */
    const isSaved = (preset: Preset) =>
        saved.commitmentNames.includes(preset.name);

    const health = $derived(obligationHealth(commitmentsTotal, salary));
    const healthColor = $derived(
        health.level === 'bad'
            ? 'var(--destructive)'
            : health.level === 'warn'
              ? 'var(--warning)'
              : 'var(--success)',
    );

    // ── الخطوة 3 ──────────────────────────────────────────────────────
    const SAVINGS_CHIPS = [
        { label: 'لا شيء', pct: 0 },
        { label: '5٪', pct: 5 },
        { label: '10٪', pct: 10 },
        { label: '20٪', pct: 20 },
    ];

    let savingsPct = $state(10);
    /** المبلغ بالهللات لكل فئة */
    let budgets = $state<Record<number, number>>({});
    let budgetsTouched = $state(false);

    const income = $derived(salary + extraAmount);
    const savingsTarget = $derived(
        Math.round((income * savingsPct) / 100 / 100) * 100,
    );
    const spendable = $derived(
        Math.max(0, income - commitmentsTotal - savingsTarget),
    );

    /**
     * الفئات المقترحة = تقاطع قائمة `DEFAULT_SPLIT` مع فئات المستخدم الفعلية،
     * مطابقةً بالأيقونة لا بالاسم (اسم الفئة قابل للتعديل، والأيقونة ثابتة).
     */
    const suggestedRows = $derived.by(() => {
        const { rows } = suggestBudgets(spendable);

        return rows
            .map((row) => {
                const category =
                    categories.find((c) => c.icon === row.icon) ??
                    categories.find((c) => c.name === row.name);

                return category ? { category, amount: row.amount } : null;
            })
            .filter(
                (row): row is { category: SetupCategory; amount: number } =>
                    row !== null,
            );
    });

    // الاقتراح يُعاد حسابه ما دام المستخدم لم يعدّل أي رقم بيده.
    $effect(() => {
        if (budgetsTouched) {
            return;
        }

        const next: Record<number, number> = {};

        for (const row of suggestedRows) {
            next[row.category.id] = row.amount;
        }

        budgets = next;
    });

    const allocated = $derived(
        Object.values(budgets).reduce((sum, amount) => sum + amount, 0),
    );
    const unallocated = $derived(Math.max(0, spendable - allocated));

    // ── الخطوة 4 ──────────────────────────────────────────────────────
    let notifyDue = $state(saved.notifyDue);
    let biometricLock = $state(saved.biometricLock);

    const dailySafe = $derived(
        Math.floor(spendable / Math.max(1, salaryMonth.totalDays) / 100) * 100,
    );

    // ── لوح المبلغ المشترك ────────────────────────────────────────────
    let sheet = $state({
        open: false,
        value: 0,
        title: '',
        subtitle: '',
        hint: '',
        quickAdd: [] as number[],
        apply: (_halalas: number) => {},
    });

    function askAmount(options: {
        value: number;
        title: string;
        subtitle?: string;
        hint?: string;
        quickAdd?: number[];
        apply: (halalas: number) => void;
    }) {
        sheet = {
            open: true,
            value: options.value,
            title: options.title,
            subtitle: options.subtitle ?? '',
            hint: options.hint ?? '',
            quickAdd: options.quickAdd ?? [],
            apply: options.apply,
        };
    }

    // ── الحفظ ─────────────────────────────────────────────────────────
    function post(
        url: string,
        data: Parameters<typeof router.post>[1],
        nextStep: number,
    ) {
        submitting = true;

        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => {
                step = nextStep;
            },
            onFinish: () => {
                submitting = false;
            },
        });
    }

    function saveSalary() {
        if (salary <= 0) {
            return;
        }

        post(
            '/setup/salary',
            {
                amount: salary,
                salary_day: salaryDay,
                is_recurring: salaryRecurring,
                extra_amount: extraAmount,
                extra_source: extraSource,
            },
            2,
        );
    }

    function saveCommitments() {
        const rows = Object.entries(picked)
            .filter(([, amount]) => amount > 0)
            .map(([key, amount]) => {
                const preset = presets.find((p) => p.key === key);

                return {
                    key,
                    name: preset?.name ?? key,
                    amount,
                    months_count:
                        preset?.kind === 'installment'
                            ? installmentMonths
                            : null,
                };
            });

        post('/setup/commitments', { commitments: rows }, 3);
    }

    function saveBudget() {
        post(
            '/setup/budget',
            {
                savings_target: savingsTarget,
                budgets: Object.entries(budgets)
                    .filter(([, amount]) => amount > 0)
                    .map(([id, amount]) => ({
                        category_id: Number(id),
                        amount,
                    })),
            },
            4,
        );
    }

    function finish() {
        submitting = true;
        router.post(
            '/setup/finish',
            { notify_due: notifyDue, biometric_lock: biometricLock },
            { onFinish: () => (submitting = false) },
        );
    }

    /** تخطّي — يقدّم المؤشّر على الخادم بلا حفظ بيانات الخطوة. */
    function skip() {
        const next = Math.min(TOTAL_STEPS, step + 1);
        post('/setup/step', { step: next }, next);
    }

    function back() {
        if (step > 1) {
            step -= 1;
        }
    }

    const canContinue = $derived(step !== 1 || salary > 0);
</script>

<AppHead title="الإعداد" />

<AmountSheet
    bind:open={sheet.open}
    bind:value={sheet.value}
    title={sheet.title}
    subtitle={sheet.subtitle}
    hint={sheet.hint}
    quickAdd={sheet.quickAdd}
    onSave={(halalas) => sheet.apply(halalas)}
/>

<div class="flex min-h-svh flex-col bg-background text-foreground">
    <!-- الرأس: شريط التقدّم -->
    <header
        class="sticky top-0 z-30 shrink-0 border-b border-border bg-card/95 px-4 pb-3 backdrop-blur-sm"
        style="padding-top: calc(0.75rem + env(safe-area-inset-top))"
    >
        <div class="mx-auto flex w-full max-w-xl items-center gap-2.5">
            <button
                type="button"
                onclick={back}
                disabled={step === 1}
                aria-label="رجوع"
                class="grid size-9 shrink-0 place-items-center rounded-xl border border-input text-muted-foreground transition-colors hover:bg-secondary disabled:opacity-0"
            >
                <ArrowRight class="size-[17px]" stroke-width="1.9" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <b class="truncate text-[14px] font-semibold"
                        >{STEP_TITLES[step - 1]}</b
                    >
                    <span
                        class="shrink-0 text-[11.5px] text-muted-foreground tabular-nums"
                    >
                        الخطوة {step} من {TOTAL_STEPS}
                    </span>
                </div>
                <div class="mt-2 flex gap-1.5">
                    {#each Array(TOTAL_STEPS) as _, i (i)}
                        <span
                            class="h-[4px] flex-1 rounded-full transition-colors {i <
                            step
                                ? 'bg-primary'
                                : 'bg-border'}"
                        ></span>
                    {/each}
                </div>
            </div>
        </div>
    </header>

    <!-- الجسم -->
    <main class="flex-1 overflow-y-auto px-4 py-4">
        <div class="mx-auto flex w-full max-w-xl flex-col gap-3">
            {#if step === 1}
                <!-- ═══ الراتب ═══ -->
                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <p class="text-[11.5px] text-muted-foreground">
                        كم راتبك الشهري؟
                    </p>

                    <button
                        type="button"
                        onclick={() =>
                            askAmount({
                                value: salary,
                                title: 'راتبك الشهري',
                                subtitle: 'المبلغ الذي ينزل حسابك كل شهر',
                                quickAdd: [1000, 5000],
                                apply: (halalas) => (salary = halalas),
                            })}
                        class="mt-1 flex w-full items-end gap-1.5 text-start transition-transform active:scale-[.99]"
                    >
                        <span
                            class="text-[40px] leading-none font-semibold tracking-[-0.04em] tabular-nums {salary >
                            0
                                ? ''
                                : 'text-muted-foreground/45'}"
                        >
                            {formatAmount(salary)}
                        </span>
                        <span
                            class="pb-1 text-[15px] font-medium text-muted-foreground"
                            >ر.س</span
                        >
                    </button>

                    <div class="mt-3 border-t border-border pt-1">
                        <ToggleRow
                            bind:checked={salaryRecurring}
                            icon={Repeat}
                            label="يتكرّر تلقائياً كل شهر"
                            detail="ينزل الراتب في حسابك بلا ما تسجّله كل شهر بيدك."
                        />
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <p class="mb-2 text-[14px] font-semibold">
                        أي يوم ينزل راتبك؟
                    </p>
                    <DayOfMonthPicker
                        bind:value={salaryDay}
                        showLastDay={false}
                        hint="شهرك يبدأ من {salaryDay} لا من 1 — كل الأرقام في التطبيق تُحسب على هذا اليوم."
                    />
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    {#if showExtra}
                        <div class="flex flex-col gap-2.5">
                            <p class="text-[14px] font-semibold">دخل إضافي</p>

                            <button
                                type="button"
                                onclick={() =>
                                    askAmount({
                                        value: extraAmount,
                                        title: 'الدخل الإضافي',
                                        subtitle:
                                            'عمل حر · إيجار · مكافأة شهرية',
                                        apply: (halalas) =>
                                            (extraAmount = halalas),
                                    })}
                                class="inline-flex min-h-11 w-full items-center justify-between gap-2 rounded-2xl border border-input px-3 text-start transition-transform active:scale-[.99]"
                            >
                                <span
                                    class="text-[11.5px] text-muted-foreground"
                                    >المبلغ</span
                                >
                                <span
                                    class="text-[14px] font-semibold tabular-nums"
                                >
                                    {formatAmount(extraAmount)} ر.س
                                </span>
                            </button>

                            <label class="flex flex-col gap-1.5">
                                <span
                                    class="text-[11.5px] text-muted-foreground"
                                    >مصدره</span
                                >
                                <input
                                    type="text"
                                    bind:value={extraSource}
                                    maxlength="60"
                                    placeholder="عمل حر"
                                    class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] outline-none focus:border-ring"
                                />
                            </label>
                        </div>
                    {:else}
                        <button
                            type="button"
                            onclick={() => (showExtra = true)}
                            class="inline-flex min-h-11 w-full items-center gap-2 text-[13px] font-medium text-primary"
                        >
                            <Plus class="size-[18px]" stroke-width="1.9" />
                            عندك دخل إضافي؟ أضفه (اختياري)
                        </button>
                    {/if}
                </section>
            {:else if step === 2}
                <!-- ═══ الالتزامات ═══ -->
                <p class="px-1 text-[11.5px] text-muted-foreground">
                    اختر التزاماتك الثابتة واكتب مبلغ كل واحد — تُحجز قبل ما
                    تصرف.
                </p>

                <div class="grid grid-cols-2 gap-3">
                    {#each presets as preset (preset.key)}
                        {@const Icon = iconFor(preset.icon)}
                        {@const saved_ = isSaved(preset)}
                        {@const amount = picked[preset.key] ?? 0}
                        {@const active = amount > 0 || saved_}
                        <button
                            type="button"
                            aria-pressed={active}
                            disabled={saved_}
                            onclick={() =>
                                askAmount({
                                    value: amount,
                                    title: preset.name,
                                    subtitle:
                                        preset.kind === 'installment'
                                            ? 'قيمة القسط الشهري'
                                            : 'المبلغ الشهري',
                                    quickAdd: [100, 500],
                                    apply: (halalas) => {
                                        if (halalas > 0) {
                                            picked[preset.key] = halalas;
                                        } else {
                                            delete picked[preset.key];
                                        }
                                    },
                                })}
                            class="flex min-h-[104px] flex-col items-start gap-2 rounded-2xl border p-3 text-start shadow-xs transition-transform active:scale-[.98] disabled:opacity-60 {active
                                ? 'border-primary bg-primary/6'
                                : 'border-border bg-card'}"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl {active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-secondary text-muted-foreground'}"
                            >
                                <Icon class="size-5" stroke-width="1.9" />
                            </span>
                            <b class="text-[14px] font-semibold"
                                >{preset.name}</b
                            >
                            <span
                                class="text-[11.5px] tabular-nums {active
                                    ? 'text-primary'
                                    : 'text-muted-foreground'}"
                            >
                                {#if saved_}
                                    مضاف
                                {:else if amount > 0}
                                    {formatAmount(amount)} ر.س
                                {:else}
                                    اضغط لتحديد المبلغ
                                {/if}
                            </span>
                        </button>
                    {/each}
                </div>

                {#if picked.installment}
                    <section
                        class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                    >
                        <p class="mb-2 text-[14px] font-semibold">
                            كم شهراً باقٍ على القسط؟
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            {#each [6, 12, 24, 36, 48, 60] as months (months)}
                                <button
                                    type="button"
                                    aria-pressed={installmentMonths === months}
                                    onclick={() => (installmentMonths = months)}
                                    class="inline-flex min-h-11 min-w-12 items-center justify-center rounded-xl border px-3 text-[13px] tabular-nums transition-colors {installmentMonths ===
                                    months
                                        ? 'border-primary bg-primary/8 font-semibold text-primary'
                                        : 'border-input text-foreground/85'}"
                                >
                                    {months}
                                </button>
                            {/each}
                        </div>
                    </section>
                {/if}

                <!-- المؤشّر: نسبة الالتزامات من الراتب -->
                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-[14px] font-semibold">
                            مجموع التزاماتك {formatAmount(commitmentsTotal)} ر.س
                        </span>
                        <span
                            class="shrink-0 text-[14px] font-semibold tabular-nums"
                            style="color: {healthColor}"
                        >
                            {Math.round(health.pct)}٪
                        </span>
                    </div>

                    <div
                        class="mt-2 h-[6px] overflow-hidden rounded-full bg-secondary"
                    >
                        <span
                            class="block h-full rounded-full transition-[width]"
                            style="width: {Math.min(
                                100,
                                health.pct,
                            )}%; background-color: {healthColor}"
                        ></span>
                    </div>

                    <p
                        class="mt-2 text-[11.5px] leading-relaxed text-muted-foreground"
                    >
                        {#if health.level === 'bad'}
                            التزاماتك أكثر من 70٪ من راتبك — يبقى لك القليل جداً
                            للصرف.
                        {:else if health.level === 'warn'}
                            أي {Math.round(health.pct)}٪ من راتبك — مرتفعة
                            قليلاً لكنها مُدارة.
                        {:else}
                            أي {Math.round(health.pct)}٪ من راتبك — مساحة مريحة
                            للصرف والادخار.
                        {/if}
                    </p>
                </section>
            {:else if step === 3}
                <!-- ═══ الادخار والميزانية ═══ -->
                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <p class="text-[14px] font-semibold">
                        كم تبي تدّخر من راتبك؟
                    </p>
                    <p class="mt-0.5 text-[11.5px] text-muted-foreground">
                        المقترح 10٪ — يُحجز أول الشهر لا آخره.
                    </p>

                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        {#each SAVINGS_CHIPS as chip (chip.pct)}
                            <button
                                type="button"
                                aria-pressed={savingsPct === chip.pct}
                                onclick={() => {
                                    savingsPct = chip.pct;
                                    budgetsTouched = false;
                                }}
                                class="inline-flex min-h-11 min-w-14 items-center justify-center rounded-xl border px-3 text-[13px] transition-colors {savingsPct ===
                                chip.pct
                                    ? 'border-primary bg-primary/8 font-semibold text-primary'
                                    : 'border-input text-foreground/85'}"
                            >
                                {chip.label}
                            </button>
                        {/each}
                    </div>

                    {#if savingsTarget > 0}
                        <p
                            class="mt-2.5 rounded-xl bg-secondary px-3 py-2 text-[11.5px] text-foreground/85"
                        >
                            يعني <b class="font-semibold tabular-nums"
                                >{formatAmount(savingsTarget)} ر.س</b
                            > كل شهر.
                        </p>
                    {/if}
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="text-[14px] font-semibold">
                            توزيع الباقي على فئاتك
                        </p>
                        <span
                            class="shrink-0 text-[11.5px] text-muted-foreground tabular-nums"
                        >
                            {formatAmount(spendable)} ر.س
                        </span>
                    </div>
                    <p class="mt-0.5 text-[11.5px] text-muted-foreground">
                        اقتراح للبداية — عدّل أي رقم بضغطة.
                    </p>

                    <ul class="mt-2 flex flex-col">
                        {#each suggestedRows as row (row.category.id)}
                            {@const amount = budgets[row.category.id] ?? 0}
                            <li class="border-b border-border last:border-b-0">
                                <button
                                    type="button"
                                    onclick={() =>
                                        askAmount({
                                            value: amount,
                                            title: row.category.name,
                                            subtitle: `ميزانية ${salaryMonth.label}`,
                                            quickAdd: [50, 100],
                                            apply: (halalas) => {
                                                budgetsTouched = true;
                                                budgets[row.category.id] =
                                                    halalas;
                                            },
                                        })}
                                    class="flex min-h-11 w-full items-center gap-2.5 py-2 text-start transition-transform active:scale-[.99]"
                                >
                                    <CategoryIcon
                                        icon={row.category.icon}
                                        color={row.category.color}
                                        size="lg"
                                    />
                                    <span
                                        class="min-w-0 flex-1 truncate text-[14px] font-medium"
                                    >
                                        {row.category.name}
                                    </span>
                                    <span
                                        class="shrink-0 text-[14px] font-semibold tabular-nums"
                                    >
                                        {formatAmount(amount)}
                                        <span
                                            class="text-[11.5px] font-normal text-muted-foreground"
                                            >ر.س</span
                                        >
                                    </span>
                                </button>
                            </li>
                        {/each}
                    </ul>

                    <div
                        class="mt-2.5 flex items-center justify-between gap-2 rounded-xl px-3 py-2.5"
                        style="background-color: color-mix(in srgb, var(--success) 10%, transparent)"
                    >
                        <span
                            class="text-[11.5px] font-medium"
                            style="color: var(--success-text)">غير مخصّص</span
                        >
                        <b
                            class="text-[14px] font-semibold tabular-nums"
                            style="color: var(--success-text)"
                        >
                            {formatAmount(unallocated)} ر.س
                        </b>
                    </div>
                </section>
            {:else}
                <!-- ═══ الملخّص ═══ -->
                <section
                    class="rounded-2xl border border-border p-3 shadow-xs md:p-6"
                    style="background: linear-gradient(160deg, color-mix(in srgb, var(--primary) 8%, var(--card)), var(--card))"
                >
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-primary text-primary-foreground"
                    >
                        <Wallet class="size-5" stroke-width="1.9" />
                    </span>

                    <p class="mt-3 text-[11.5px] text-muted-foreground">
                        يصفى لك للصرف
                    </p>
                    <p
                        class="mt-0.5 text-[36px] leading-none font-semibold tracking-[-0.04em] tabular-nums"
                    >
                        {formatAmount(spendable)}<span
                            class="ms-1.5 text-[15px] font-medium text-muted-foreground"
                            >ر.س</span
                        >
                    </p>
                    <p class="mt-2 text-[11.5px] text-foreground/85">
                        يعني <b class="font-semibold tabular-nums"
                            >{formatAmount(dailySafe)} ر.س</b
                        >
                        في اليوم بأمان على مدى {salaryMonth.totalDays} يوماً.
                    </p>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <ul class="flex flex-col text-[13.5px]">
                        <li
                            class="flex min-h-11 items-center justify-between gap-2 border-b border-border"
                        >
                            <span class="text-muted-foreground"
                                >الراتب والدخل</span
                            >
                            <b class="font-semibold tabular-nums"
                                >{formatAmount(income)} ر.س</b
                            >
                        </li>
                        <li
                            class="flex min-h-11 items-center justify-between gap-2 border-b border-border"
                        >
                            <span class="text-muted-foreground"
                                >− الالتزامات</span
                            >
                            <b class="font-semibold tabular-nums"
                                >{formatAmount(commitmentsTotal)} ر.س</b
                            >
                        </li>
                        <li
                            class="flex min-h-11 items-center justify-between gap-2 border-b border-border"
                        >
                            <span class="text-muted-foreground">− الادخار</span>
                            <b class="font-semibold tabular-nums"
                                >{formatAmount(savingsTarget)} ر.س</b
                            >
                        </li>
                        <li
                            class="flex min-h-11 items-center justify-between gap-2"
                        >
                            <span class="font-medium">= يصفى لك</span>
                            <b class="font-semibold text-primary tabular-nums"
                                >{formatAmount(spendable)} ر.س</b
                            >
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6"
                >
                    <ToggleRow
                        bind:checked={notifyDue}
                        icon={Bell}
                        label="ذكّرني بالالتزامات"
                        detail="تنبيه قبل الاستحقاق بثلاثة أيام."
                    />
                    <div class="border-t border-border"></div>
                    <ToggleRow
                        bind:checked={biometricLock}
                        icon={Fingerprint}
                        label="اقفل التطبيق ببصمتك"
                        detail="أرقامك المالية لا تُفتح إلا ببصمتك."
                    />
                </section>
            {/if}
        </div>
    </main>

    <!-- التذييل الثابت -->
    <footer
        class="sticky bottom-0 shrink-0 border-t border-border bg-card px-4 pt-3"
        style="padding-bottom: calc(0.85rem + env(safe-area-inset-bottom))"
    >
        <div class="mx-auto flex w-full max-w-xl items-center gap-2">
            {#if step > 1 && step < TOTAL_STEPS}
                <button
                    type="button"
                    onclick={skip}
                    disabled={submitting}
                    class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
                >
                    تخطّي
                </button>
            {/if}

            {#if step === TOTAL_STEPS}
                <button
                    type="button"
                    onclick={finish}
                    disabled={submitting}
                    class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
                >
                    <Check class="size-[18px]" stroke-width="1.9" />
                    افتح لوحتي
                </button>
            {:else}
                <button
                    type="button"
                    onclick={step === 1
                        ? saveSalary
                        : step === 2
                          ? saveCommitments
                          : saveBudget}
                    disabled={submitting || !canContinue}
                    class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
                >
                    متابعة
                    <ArrowLeft class="size-[18px]" stroke-width="1.9" />
                </button>
            {/if}
        </div>

        {#if step === 1 && salary <= 0}
            <p
                class="mx-auto mt-2 w-full max-w-xl text-center text-[11.5px] text-muted-foreground"
            >
                أدخل راتبك للمتابعة — بقيّة الخطوات قابلة للتخطّي.
            </p>
        {/if}
    </footer>
</div>
