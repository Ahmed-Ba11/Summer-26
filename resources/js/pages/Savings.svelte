<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الادخار', href: '/savings' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { toast } from 'svelte-sonner';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Plus from 'lucide-svelte/icons/plus';
    import Target from 'lucide-svelte/icons/target';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Vault from 'lucide-svelte/icons/vault';
    import Check from 'lucide-svelte/icons/check';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Pencil from 'lucide-svelte/icons/pencil';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import FundingSourcePicker, {
        type Funding,
    } from '@/components/FundingSourcePicker.svelte';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import SheetField from '@/components/ui/SheetField.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DateSheet from '@/components/ui/DateSheet.svelte';
    import ConfirmSheet from '@/components/ui/ConfirmSheet.svelte';
    import {
        formatAmount,
        formatCurrency,
        formatDate,
        formatFullDate,
        formatPercent,
    } from '@/lib/format';
    import {
        SEVERITY_STYLES,
        availableToSpend,
        checkSavingsGoal,
        isBlocked,
        type FinancialContext,
    } from '@/lib/money-rules';
    import type { SavingsStats, ValidationErrors } from '@/types';
    import {
        complete as completeSavings,
        destroy as destroySavings,
        store as storeSavings,
        update as updateSavings,
    } from '@/routes/savings';
    import {
        destroy as destroyDeposit,
        update as updateDeposit,
    } from '@/routes/savings/deposits';

    /** حركة على رصيد هدف: موجبة إيداع، وسالبة سحب لتمويل مصروف. */
    interface DepositItem {
        id: number;
        amount: number;
        deposited_at: string | null;
        period_key: string;
    }

    interface GoalItem {
        id: number;
        name: string;
        icon: string;
        target_amount: number;
        current_amount: number;
        target_date: string | null;
        is_completed: boolean;
        is_closed: boolean;
        deposits?: DepositItem[];
    }

    let {
        goals = [],
        stats = {
            total_saved: 0,
            monthly_income: 0,
            monthly_deposits: 0,
            savings_rate: 0,
        },
        salaryMonth = null,
    }: {
        goals?: GoalItem[];
        stats?: SavingsStats;
        salaryMonth?: {
            key: string;
            label: string;
            range: string;
            daysLeft: number;
        } | null;
    } = $props();

    /** «المُودَع» يُحسب على شهر الراتب، فيُذكر الراتب باسمه لا «هذا الشهر». */
    const periodLine = $derived(
        salaryMonth ? `أهدافك الادخارية · ${salaryMonth.label}` : 'أهدافك الادخارية',
    );

    const serverErrors = $derived((page.props.errors ?? {}) as ValidationErrors);

    function errorText(
        errors: ValidationErrors | Record<string, string>,
        key: string,
    ): string {
        const value = errors[key];

        return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
    }

    function generalError(
        errors: ValidationErrors | Record<string, string>,
    ): string {
        for (const key of ['error', 'message', 'general', '_']) {
            const message = errorText(errors, key);

            if (message) {
                return message;
            }
        }

        return '';
    }

    const EMPTY_CONTEXT: FinancialContext = {
        monthlyIncome: 0,
        obligations: 0,
        spent: 0,
        budgetedTotal: 0,
        daysUntilSalary: 0,
    };

    const context = $derived(
        (page.props.quickAdd?.context ?? EMPTY_CONTEXT) as FinancialContext,
    );
    const fundableGoals = $derived(page.props.quickAdd?.fundableGoals ?? []);
    /**
     * «المتبقي لك» — الإيداع فوقه لا يُمنع، بل يُسأل عن مصدره.
     * وبلا دخل معروف لا سقف أصلاً، فلا يُسأل — نفس قاعدة الخادم.
     */
    const availableToDeposit = $derived(
        context.monthlyIncome > 0 ? availableToSpend(context) : Number.MAX_SAFE_INTEGER,
    );

    const totalSavings = $derived(stats.total_saved ?? 0);
    const monthlyIncome = $derived(stats.monthly_income ?? 0);
    const monthlyDeposits = $derived(stats.monthly_deposits ?? 0);

    /** معدّل الادخار = إيداعات شهر الراتب ÷ دخله — لا الرصيد التراكمي. */
    const savingsRate = $derived(
        monthlyIncome > 0
            ? Math.round((monthlyDeposits / monthlyIncome) * 100)
            : null,
    );

    const completedGoals = $derived(goals.filter((g) => g.is_completed).length);

    /**
     * تقدّم الهدف الادخاري لا يُلوَّن بالأحمر أبداً.
     * الأحمر محجوز للتجاوز السلبي (صرف فوق الميزانية)؛ أمّا تجاوز هدف
     * الادخار فإنجاز — أخضر ونصّ تهنئة.
     */
    function goalBarClass(pct: number): string {
        if (pct >= 100) return 'bg-success';
        if (pct >= 50) return 'bg-emerald-500';
        return 'bg-chart-3';
    }

    // ── نموذج الهدف ───────────────────────────────────────────────────
    /** رقائق جاهزة لأشيع أربعة أهداف — كتابة الاسم من الصفر آخر الخيارات. */
    const PRESETS = [
        { name: 'طوارئ', icon: 'heart-pulse' },
        { name: 'سيارة', icon: 'car' },
        { name: 'سفر', icon: 'plane' },
        { name: 'سكن', icon: 'house' },
    ];

    /** المدة بالأشهر — و`0` تعني «تاريخ محدّد» يختاره المستخدم. */
    const DURATIONS = [
        { months: 6, label: '6 أشهر' },
        { months: 12, label: 'سنة' },
        { months: 24, label: 'سنتين' },
        { months: 0, label: 'تاريخ محدّد' },
    ];

    let showFormModal = $state(false);
    let formName = $state('');
    let formIcon = $state('banknote');
    /** المبلغ المستهدف بالهللات */
    let formTargetAmount = $state(0);
    let formMonths = $state(12);
    let formCustomDate = $state('');
    let targetAmountSheetOpen = $state(false);
    let targetDateSheetOpen = $state(false);
    let formErrors = $state<Record<string, string>>({});
    let submitting = $state(false);

    function addMonths(months: number): string {
        const d = new Date();
        d.setHours(0, 0, 0, 0);
        d.setMonth(d.getMonth() + months);

        return d.toISOString().slice(0, 10);
    }

    function monthsUntil(iso: string): number {
        if (!iso) return 0;
        const target = new Date(iso);
        const today = new Date();

        return Math.max(
            0,
            Math.round((target.getTime() - today.getTime()) / (30.44 * 86_400_000)),
        );
    }

    const formTargetDate = $derived(
        formMonths > 0 ? addMonths(formMonths) : formCustomDate,
    );
    const formMonthsToTarget = $derived(
        formMonths > 0 ? formMonths : monthsUntil(formCustomDate),
    );

    /**
     * «تحتاج تدّخر X ر.س شهرياً» — والتحذير عند تجاوز 30٪ من الدخل.
     * القاعدة نفسها المطبّقة في الخادم، فلا يختلف نصّان.
     */
    const goalChecks = $derived(
        formTargetAmount > 0 && formMonthsToTarget > 0
            ? checkSavingsGoal(formTargetAmount, 0, formMonthsToTarget, context)
            : [],
    );
    const goalBlocked = $derived(isBlocked(goalChecks));

    function openAddModal() {
        submitting = false;
        formName = '';
        formIcon = 'banknote';
        formTargetAmount = 0;
        formMonths = 12;
        formCustomDate = '';
        formErrors = {};
        showFormModal = true;
    }

    function closeFormModal() {
        showFormModal = false;
        formErrors = {};
    }

    function pickPreset(preset: (typeof PRESETS)[number]) {
        formName = preset.name;
        formIcon = preset.icon;
    }

    function submitForm() {
        formErrors = {};

        if (!formName.trim()) {
            formErrors.name = 'اسم الهدف مطلوب';
            return;
        }
        if (formTargetAmount <= 0) {
            formErrors.target_amount = 'المبلغ المستهدف مطلوب';
            return;
        }
        if (formMonths === 0 && !formCustomDate) {
            formErrors.target_date = 'اختر التاريخ المستهدف';
            return;
        }

        submitting = true;
        const name = formName.trim();
        const target = formTargetAmount;

        router.post(
            storeSavings(),
            {
                name,
                icon: formIcon,
                target_amount: target / 100,
                target_date: formTargetDate || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        `تم إنشاء هدف ${name} — ${formatAmount(target)} ر.س`,
                    );
                    closeFormModal();
                },
                onError: (errors) => {
                    formErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    // ── الإيداع ───────────────────────────────────────────────────────
    let showDepositSheet = $state(false);
    let depositGoal = $state<GoalItem | null>(null);
    /** المبلغ المُودَع بالهللات */
    let depositAmount = $state(0);
    let depositErrors = $state<Record<string, string>>({});

    /** الخطوة الثانية: «من وين جاء؟» — تظهر فقط عند تجاوز المتبقي لك. */
    let fundingOpen = $state(false);
    let funding = $state<Funding>({
        source: null,
        savingsGoalId: null,
        incomeAmount: 0,
        incomeSource: '',
    });

    const depositShortfall = $derived(
        Math.max(0, depositAmount - availableToDeposit),
    );
    /** السحب من نفس الهدف لتمويل الإيداع فيه دوران بلا أثر — يُستبعد. */
    const depositFundableGoals = $derived(
        fundableGoals.filter((g: { id: number }) => g.id !== depositGoal?.id),
    );
    const fundingReady = $derived(
        (funding.source === 'savings' && funding.savingsGoalId !== null) ||
            (funding.source === 'unlogged_income' &&
                funding.incomeAmount >= depositShortfall) ||
            funding.source === 'overspend',
    );

    function openDeposit(goal: GoalItem) {
        submitting = false;
        depositGoal = goal;
        depositAmount = 0;
        depositErrors = {};
        funding = {
            source: null,
            savingsGoalId: null,
            incomeAmount: 0,
            incomeSource: '',
        };
        showDepositSheet = true;
    }

    function onDepositAmount(halalas: number) {
        depositErrors = {};

        if (halalas <= 0) {
            depositErrors.amount = 'المبلغ مطلوب';
            return;
        }

        depositAmount = halalas;

        // الإيداع فوق «المتبقي لك» لا يُمنع — يُسأل عن مصدره أولاً.
        if (halalas > availableToDeposit) {
            fundingOpen = true;
            return;
        }

        sendDeposit();
    }

    function sendDeposit() {
        const goal = depositGoal;

        if (!goal || depositAmount <= 0) {
            return;
        }

        const amount = depositAmount;
        const needsFunding = depositShortfall > 0;
        submitting = true;

        router.put(
            updateSavings(goal.id),
            {
                amount: amount / 100,
                funding_source: needsFunding ? funding.source : undefined,
                savings_goal_id: needsFunding
                    ? (funding.savingsGoalId ?? undefined)
                    : undefined,
                income_amount: needsFunding
                    ? funding.incomeAmount || undefined
                    : undefined,
                income_source: needsFunding
                    ? funding.incomeSource || undefined
                    : undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    fundingOpen = false;
                    showDepositSheet = false;
                    toast.success(
                        `تم إيداع ${formatAmount(amount)} ر.س في ${goal.name}`,
                    );
                    depositGoal = null;
                    depositAmount = 0;
                },
                onError: (errors) => {
                    depositErrors = errors as Record<string, string>;

                    // الخطأ خارج مسار التمويل لا لوح يعرضه — نعيد فتح
                    // لوح المبلغ حتى لا يفشل الإيداع صامتاً.
                    if (!fundingOpen) {
                        showDepositSheet = true;
                    }
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    // ── تعديل حركة قائمة وحذفها ───────────────────────────────────────
    /**
     * الإيداع الخاطئ كان يبقى خاطئاً: لا تعديل ولا حذف، والمخرج الوحيد
     * حذف الهدف كلّه. المبلغ يمرّ من `AmountSheet` كبقية مبالغ التطبيق.
     *
     * السحوبات (السالبة) تُعرض ولا تُعدَّل: هي أثر تمويل مصروف في مكان
     * آخر، وتعديلها هنا وحدها يفكّ ارتباطها بذلك المصروف.
     */
    let openLedgerFor = $state<number | null>(null);
    let editingDeposit = $state<DepositItem | null>(null);
    let editDepositAmount = $state(0);
    let editDepositOpen = $state(false);
    let deleteDepositId = $state<number | null>(null);
    let deleteDepositOpen = $state(false);

    function toggleLedger(goalId: number) {
        openLedgerFor = openLedgerFor === goalId ? null : goalId;
    }

    function openDepositEdit(deposit: DepositItem) {
        editingDeposit = deposit;
        editDepositAmount = Math.abs(deposit.amount);
        editDepositOpen = true;
    }

    function saveDepositEdit(halalas: number) {
        const deposit = editingDeposit;

        if (!deposit || halalas <= 0) {
            return;
        }

        router.put(
            updateDeposit(deposit.id),
            { amount: halalas / 100 },
            {
                preserveScroll: true,
                onSuccess: () => {
                    editDepositOpen = false;
                    editingDeposit = null;
                    toast.success(`صار الإيداع ${formatAmount(halalas)} ر.س`);
                },
                onError: (errors) => {
                    toast.error(
                        errorText(errors as ValidationErrors, 'amount') ||
                            'ما قدرنا نحفظ التعديل',
                    );
                },
            },
        );
    }

    function confirmDeleteDeposit(id: number) {
        deleteDepositId = id;
        deleteDepositOpen = true;
    }

    function executeDeleteDeposit() {
        if (!deleteDepositId) return;

        router.delete(destroyDeposit(deleteDepositId), {
            preserveScroll: true,
            onSuccess: () => {
                deleteDepositId = null;
                deleteDepositOpen = false;
                toast.success('حُذف الإيداع ورجع الرصيد');
            },
        });
    }

    // ── الحذف والإقفال ────────────────────────────────────────────────
    let deleteId = $state<number | null>(null);
    let deleteOpen = $state(false);

    function confirmDelete(id: number) {
        deleteId = id;
        deleteOpen = true;
    }

    function executeDelete() {
        if (!deleteId) return;

        router.delete(destroySavings(deleteId), {
            preserveScroll: true,
            onSuccess: () => {
                deleteId = null;
                deleteOpen = false;
            },
        });
    }

    /**
     * الإقفال قبل بلوغ الهدف يستحق تأكيداً — وهو قرار المستخدم
     * («ما عدت أحتاجه»)، لا واقعة يقرّرها الرصيد.
     */
    let closeConfirmGoal = $state<GoalItem | null>(null);
    let closeConfirmOpen = $state(false);

    function completeGoal(goal: GoalItem) {
        if (goal.current_amount < goal.target_amount) {
            closeConfirmGoal = goal;
            closeConfirmOpen = true;

            return;
        }

        submitCompleteGoal(goal.id);
    }

    function submitCompleteGoal(id: number) {
        router.put(
            completeSavings(id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => (closeConfirmOpen = false),
            },
        );
    }

    onMount(() => {
        if (new URLSearchParams(window.location.search).get('new') === '1') {
            openAddModal();
            window.history.replaceState({}, '', window.location.pathname);
        }
    });
</script>

<AppHead title="الادخار" />
<MobileHeader title="الادخار" subtitle={periodLine} />

<div class="flex flex-1 flex-col gap-3 p-3 md:gap-5 md:p-6">
    <div class="hidden md:block">
        <h1 class="text-[22px] font-semibold tracking-tight">الادخار</h1>
        <p class="text-[13px] text-muted-foreground">{periodLine}</p>
    </div>

    <!-- صف «إجمالي المدخرات» المضغوط — وزر إنشاء الهدف الوحيد في الرأس -->
    <section class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6">
        <div class="flex items-center gap-3">
            <span
                class="grid size-10 shrink-0 place-items-center rounded-xl"
                style="background-color: color-mix(in srgb, var(--chart-3) 12%, transparent); color: var(--chart-3)"
            >
                <Vault class="size-[19px]" stroke-width="1.9" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[11.5px] text-muted-foreground">إجمالي المدخرات</p>
                <p class="mt-0.5 text-[24px] leading-none font-semibold tracking-tight tabular-nums md:text-[28px]">
                    {formatAmount(totalSavings)}<span
                        class="ms-1 text-[13px] font-medium text-foreground/80">ر.س</span
                    >
                </p>
                <p class="mt-1 flex flex-wrap gap-x-2 text-[11.5px] text-muted-foreground tabular-nums">
                    <span>{completedGoals} من {goals.length} هدف مكتمل</span>
                    {#if savingsRate !== null}
                        <span aria-hidden="true">·</span>
                        <span>ادّخرت {formatPercent(savingsRate)} من دخل {salaryMonth?.label ?? 'الشهر'}</span>
                    {/if}
                </p>
            </div>

            <button
                type="button"
                onclick={openAddModal}
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-2xl bg-primary px-3.5 text-[13px] font-semibold text-primary-foreground transition-transform active:scale-[.98]"
            >
                <Plus class="size-[18px]" stroke-width="1.9" />
                هدف جديد
            </button>
        </div>
    </section>

    {#if goals.length === 0}
        <div class="rounded-2xl border border-border bg-card">
            <EmptyState
                icon={Vault}
                title="ما عندك أهداف ادخار بعد"
                description="حدّد هدفاً ومبلغاً ومدة، وبنقول لك كم تحتاج تدّخر كل شهر."
                actionLabel="أنشئ أول هدف"
                onAction={openAddModal}
            />
        </div>
    {:else}
        <div class="grid gap-3 md:grid-cols-2">
            {#each goals as goal (goal.id)}
                {@const pct =
                    goal.target_amount > 0
                        ? Math.round((goal.current_amount / goal.target_amount) * 100)
                        : 0}
                {@const remaining = goal.target_amount - goal.current_amount}
                {@const ledger = goal.deposits ?? []}
                <article class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-3 shadow-xs">
                    <div class="flex items-center gap-3">
                        <CategoryIcon
                            icon={goal.icon}
                            color={pct >= 100 ? 'var(--success)' : 'var(--chart-3)'}
                            size="md"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14px] font-semibold">{goal.name}</p>
                            {#if goal.target_date}
                                <p class="text-[11.5px] text-muted-foreground tabular-nums">
                                    {formatDate(goal.target_date)}
                                </p>
                            {/if}
                        </div>

                        {#if goal.is_completed}
                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success-text"
                            >
                                <CheckCircle2 class="size-3" stroke-width="1.9" />
                                مكتمل
                            </span>
                        {:else if goal.is_closed}
                            <span
                                class="inline-flex shrink-0 items-center rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-muted-foreground"
                            >
                                مغلق
                            </span>
                        {:else}
                            <span
                                class="inline-flex shrink-0 items-center rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                            >
                                قيد الادخار
                            </span>
                        {/if}
                    </div>

                    <div>
                        <div class="h-2 overflow-hidden rounded-full border border-border bg-secondary">
                            <div
                                class="h-full rounded-full transition-[width] duration-500 {goalBarClass(pct)}"
                                style="width: {Math.min(pct, 100)}%"
                            ></div>
                        </div>
                        <p class="mt-1.5 flex items-center justify-between gap-2 text-[12.5px] tabular-nums">
                            <span class="font-semibold {pct >= 100 ? 'text-success-text' : ''}">
                                {formatPercent(pct)}
                            </span>
                            {#if remaining < 0}
                                <!-- تجاوز الهدف إنجاز لا خطأ — أخضر لا أحمر -->
                                <span class="font-medium text-success-text">
                                    تجاوزت هدفك بـ {formatAmount(-remaining)} ر.س
                                </span>
                            {:else if remaining === 0}
                                <span class="font-medium text-success-text">بلّغت هدفك</span>
                            {:else if goal.is_closed}
                                <span class="text-muted-foreground">
                                    أُقفل عند {formatAmount(goal.current_amount)} ر.س
                                </span>
                            {:else}
                                <span class="text-muted-foreground">
                                    باقي {formatAmount(remaining)} ر.س
                                </span>
                            {/if}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 rounded-xl border border-border bg-secondary p-2.5">
                        <div>
                            <p class="text-[11px] text-muted-foreground">المدخر حالياً</p>
                            <p class="mt-0.5 text-[14px] font-semibold text-success-text tabular-nums">
                                {formatAmount(goal.current_amount)} ر.س
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] text-muted-foreground">المبلغ المستهدف</p>
                            <p class="mt-0.5 text-[14px] font-semibold tabular-nums">
                                {formatAmount(goal.target_amount)} ر.س
                            </p>
                        </div>
                    </div>

                    <!-- كشف الحركات — مطويّ حتى يُطلب، فالبطاقة تبقى قصيرة -->
                    {#if ledger.length}
                        {@const isOpen = openLedgerFor === goal.id}
                        <div class="rounded-xl border border-border">
                            <button
                                type="button"
                                onclick={() => toggleLedger(goal.id)}
                                aria-expanded={isOpen}
                                class="flex min-h-11 w-full items-center justify-between gap-2 px-3 text-[12.5px] font-medium text-foreground/85"
                            >
                                <span>
                                    الحركات
                                    <span class="text-muted-foreground tabular-nums">
                                        ({ledger.length})
                                    </span>
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 text-muted-foreground transition-transform {isOpen
                                        ? 'rotate-180'
                                        : ''}"
                                />
                            </button>

                            {#if isOpen}
                                <ul class="border-t border-border">
                                    {#each ledger as entry (entry.id)}
                                        {@const isWithdrawal = entry.amount < 0}
                                        <li
                                            class="flex items-center gap-2 border-b border-border px-3 py-1.5 last:border-b-0"
                                        >
                                            <span
                                                class="grid size-7 shrink-0 place-items-center rounded-lg {isWithdrawal
                                                    ? 'bg-secondary text-muted-foreground'
                                                    : 'bg-success/10 text-success-text'}"
                                            >
                                                {#if isWithdrawal}
                                                    <ArrowUpRight class="size-3.5" />
                                                {:else}
                                                    <ArrowDownLeft class="size-3.5" />
                                                {/if}
                                            </span>

                                            <div class="min-w-0 flex-1">
                                                <p class="text-[13px] font-semibold tabular-nums">
                                                    {formatAmount(Math.abs(entry.amount))} ر.س
                                                </p>
                                                <p class="text-[11px] text-muted-foreground tabular-nums">
                                                    {entry.deposited_at
                                                        ? formatDate(entry.deposited_at)
                                                        : ''}
                                                    {#if isWithdrawal}
                                                        · سحب لتمويل مصروف
                                                    {/if}
                                                </p>
                                            </div>

                                            {#if !isWithdrawal}
                                                <button
                                                    type="button"
                                                    onclick={() => openDepositEdit(entry)}
                                                    aria-label="تعديل مبلغ الإيداع"
                                                    class="grid size-11 shrink-0 place-items-center rounded-xl border border-input text-foreground/85 transition-transform active:scale-[.98]"
                                                >
                                                    <Pencil class="size-4" stroke-width="1.9" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onclick={() => confirmDeleteDeposit(entry.id)}
                                                    aria-label="حذف الإيداع"
                                                    class="grid size-11 shrink-0 place-items-center rounded-xl border border-input text-destructive transition-transform active:scale-[.98]"
                                                >
                                                    <Trash2 class="size-4" stroke-width="1.9" />
                                                </button>
                                            {/if}
                                        </li>
                                    {/each}
                                </ul>
                            {/if}
                        </div>
                    {/if}

                    <div class="flex items-center gap-2">
                        {#if !goal.is_closed}
                            <button
                                type="button"
                                onclick={() => openDeposit(goal)}
                                class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary px-3 text-[13px] font-semibold text-primary-foreground transition-transform active:scale-[.98]"
                            >
                                <Plus class="size-[18px]" stroke-width="1.9" />
                                إيداع
                            </button>
                            <button
                                type="button"
                                onclick={() => completeGoal(goal)}
                                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-input px-3 text-[13px] font-medium text-foreground/85 transition-transform active:scale-[.98]"
                            >
                                <CheckCircle2 class="size-[18px]" stroke-width="1.9" />
                                إقفال
                            </button>
                        {/if}
                        <button
                            type="button"
                            onclick={() => confirmDelete(goal.id)}
                            aria-label="حذف هدف {goal.name}"
                            class="grid size-11 shrink-0 place-items-center rounded-xl border border-input text-destructive transition-transform active:scale-[.98] {goal.is_closed
                                ? 'ms-auto'
                                : ''}"
                        >
                            <Trash2 class="size-[18px]" stroke-width="1.9" />
                        </button>
                    </div>
                </article>
            {/each}
        </div>
    {/if}
</div>

<!-- لوح إنشاء الهدف -->
<SheetShell
    bind:open={showFormModal}
    title="وش تبي تدّخر له؟"
    subtitle="اسم وهدف ومدة — وبنحسب لك الشهري"
    onClose={closeFormModal}
>
    <div class="flex flex-col gap-3">
        {#if generalError(formErrors) || generalError(serverErrors)}
            <p
                class="flex items-start gap-2 rounded-2xl bg-destructive/10 px-3 py-2 text-[12px] text-destructive"
                role="alert"
            >
                <CircleAlert class="mt-px size-4 shrink-0" />
                {generalError(formErrors) || generalError(serverErrors)}
            </p>
        {/if}

        <div class="flex flex-wrap gap-2">
            {#each PRESETS as preset (preset.name)}
                <button
                    type="button"
                    onclick={() => pickPreset(preset)}
                    aria-pressed={formName === preset.name}
                    class="inline-flex min-h-11 items-center gap-2 rounded-2xl border px-3 text-[13px] font-medium transition-transform active:scale-[.98] {formName ===
                    preset.name
                        ? 'border-primary bg-primary/8 text-primary'
                        : 'border-input text-foreground/85'}"
                >
                    <CategoryIcon icon={preset.icon} size="sm" color="var(--chart-3)" />
                    {preset.name}
                </button>
            {/each}
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="goal-name" class="text-[11.5px] text-muted-foreground">اسم الهدف</label>
            <input
                id="goal-name"
                type="text"
                placeholder="مثال: سيارة جديدة"
                bind:value={formName}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if formErrors.name || errorText(serverErrors, 'name')}
                <p class="text-[11.5px] text-destructive">
                    {formErrors.name || errorText(serverErrors, 'name')}
                </p>
            {/if}
        </div>

        <SheetField
            label="المبلغ المستهدف"
            icon={Target}
            value={formTargetAmount > 0 ? `${formatAmount(formTargetAmount)} ر.س` : ''}
            placeholder="اضغط لإدخال المبلغ"
            error={formErrors.target_amount || errorText(serverErrors, 'target_amount')}
            onclick={() => (targetAmountSheetOpen = true)}
        />

        <div class="flex flex-col gap-1.5">
            <span class="text-[11.5px] text-muted-foreground">المدة</span>
            <div class="flex flex-wrap gap-2">
                {#each DURATIONS as duration (duration.label)}
                    <button
                        type="button"
                        onclick={() => (formMonths = duration.months)}
                        aria-pressed={formMonths === duration.months}
                        class="inline-flex min-h-11 items-center rounded-2xl border px-3.5 text-[13px] font-medium transition-transform active:scale-[.98] {formMonths ===
                        duration.months
                            ? 'border-primary bg-primary/8 text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        {duration.label}
                    </button>
                {/each}
            </div>
        </div>

        {#if formMonths === 0}
            <SheetField
                label="التاريخ المستهدف"
                icon={CalendarDays}
                value={formCustomDate ? formatFullDate(formCustomDate) : ''}
                placeholder="اختر التاريخ"
                error={formErrors.target_date || errorText(serverErrors, 'target_date')}
                onclick={() => (targetDateSheetOpen = true)}
            />
        {/if}

        {#each goalChecks as check (check.title)}
            <div class="rounded-2xl border px-3 py-2.5 {SEVERITY_STYLES[check.severity].box}">
                <p class="text-[12.5px] font-semibold">{check.title}</p>
                {#if check.detail}
                    <p class="mt-0.5 text-[11.5px] opacity-90">{check.detail}</p>
                {/if}
                {#if check.suggestion}
                    <button
                        type="button"
                        onclick={() => (formMonths = check.suggestion?.value ?? formMonths)}
                        class="mt-2 inline-flex min-h-11 items-center rounded-xl border border-current px-3 text-[12.5px] font-semibold"
                    >
                        {check.suggestion.label}
                    </button>
                {/if}
            </div>
        {/each}
    </div>

    {#snippet footer()}
        <button
            type="button"
            onclick={closeFormModal}
            disabled={submitting}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
        >
            إلغاء
        </button>
        <button
            type="button"
            onclick={submitForm}
            disabled={submitting || goalBlocked}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            <Check class="size-[18px]" />
            {submitting ? 'جارٍ الإضافة…' : 'إنشاء الهدف'}
        </button>
    {/snippet}
</SheetShell>

<AmountSheet
    bind:open={targetAmountSheetOpen}
    bind:value={formTargetAmount}
    title="المبلغ المستهدف"
    quickAdd={[500, 1000, 5000]}
/>

<DateSheet
    bind:open={targetDateSheetOpen}
    bind:value={formCustomDate}
    title="التاريخ المستهدف"
/>

<!-- الإيداع — لوح المبلغ نفسه المستعمل في كل التطبيق -->
<AmountSheet
    bind:open={showDepositSheet}
    bind:value={depositAmount}
    title={depositGoal ? `إيداع في ${depositGoal.name}` : 'إيداع'}
    subtitle={depositGoal && depositGoal.target_amount > depositGoal.current_amount
        ? `باقي ${formatCurrency(depositGoal.target_amount - depositGoal.current_amount)} على الهدف`
        : 'الهدف مكتمل — الزيادة تُحسب تجاوزاً للهدف'}
    hint={depositErrors.amount ||
        errorText(serverErrors, 'amount') ||
        errorText(serverErrors, 'funding_source') ||
        generalError(depositErrors)}
    quickAdd={[100, 500, 1000]}
    saveLabel="إيداع"
    onSave={onDepositAmount}
/>

<!-- «من وين جاء؟» — الإيداع فوق المتبقي لك لا يُمنع، يُسأل عن مصدره -->
<SheetShell
    bind:open={fundingOpen}
    title="من وين جاء المبلغ؟"
    subtitle="هذا الإيداع يتجاوز المتبقي لك"
>
    {#if generalError(depositErrors) || errorText(depositErrors, 'funding_source') || errorText(depositErrors, 'savings_goal_id') || errorText(depositErrors, 'income_amount')}
        <p
            class="mb-3 flex items-start gap-2 rounded-2xl bg-destructive/10 px-3 py-2 text-[12px] text-destructive"
            role="alert"
        >
            <CircleAlert class="mt-px size-4 shrink-0" />
            {errorText(depositErrors, 'funding_source') ||
                errorText(depositErrors, 'savings_goal_id') ||
                errorText(depositErrors, 'income_amount') ||
                generalError(depositErrors)}
        </p>
    {/if}

    <FundingSourcePicker
        amount={depositAmount}
        shortfall={depositShortfall}
        goals={depositFundableGoals}
        bind:value={funding}
    />

    {#snippet footer()}
        <button
            type="button"
            onclick={() => (fundingOpen = false)}
            disabled={submitting}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
        >
            رجوع
        </button>
        <button
            type="button"
            onclick={sendDeposit}
            disabled={submitting || !fundingReady}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            <Check class="size-[18px]" />
            {submitting ? 'جارٍ الإيداع…' : 'أودِع'}
        </button>
    {/snippet}
</SheetShell>

<!-- تعديل مبلغ إيداع — نفس لوح المبلغ المستعمل في كل التطبيق -->
<AmountSheet
    bind:open={editDepositOpen}
    bind:value={editDepositAmount}
    title="تعديل مبلغ الإيداع"
    subtitle={editingDeposit?.deposited_at
        ? `أُودع ${formatFullDate(editingDeposit.deposited_at)}`
        : ''}
    quickAdd={[100, 500, 1000]}
    saveLabel="حفظ التعديل"
    onSave={saveDepositEdit}
/>

<ConfirmSheet
    bind:open={deleteDepositOpen}
    title="حذف الإيداع"
    message="سيُحذف هذا الإيداع ويرجع رصيد الهدف كما كان قبله."
    onConfirm={executeDeleteDeposit}
/>

<ConfirmSheet
    bind:open={deleteOpen}
    message="سيُحذف هذا الهدف الادخاري نهائياً ولا يمكن التراجع."
    onConfirm={executeDelete}
/>

<!-- الإقفال قبل بلوغ الهدف — نقول الرقم صراحة بدل «هل أنت متأكّد؟» -->
<ConfirmSheet
    bind:open={closeConfirmOpen}
    title="إقفال الهدف قبل بلوغه"
    destructive={false}
    confirmLabel="أقفله"
    message={closeConfirmGoal
        ? `ادّخرت ${formatAmount(closeConfirmGoal.current_amount)} ر.س من ${formatAmount(closeConfirmGoal.target_amount)} ر.س. الإقفال يوقف الإضافة إليه، ولن يُحسب هدفاً مكتملاً.`
        : ''}
    onConfirm={() => closeConfirmGoal && submitCompleteGoal(closeConfirmGoal.id)}
/>
