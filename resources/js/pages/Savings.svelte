<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الادخار', href: '/savings' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Plus from 'lucide-svelte/icons/plus';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Vault from 'lucide-svelte/icons/vault';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { ICON_LABELS, ICON_PICKER } from '@/lib/category-icons';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import SheetField from '@/components/ui/SheetField.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DateSheet from '@/components/ui/DateSheet.svelte';
    import ConfirmSheet from '@/components/ui/ConfirmSheet.svelte';
    import Target from 'lucide-svelte/icons/target';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import Check from 'lucide-svelte/icons/check';
    import {
        formatAmount,
        formatCurrency,
        formatDate,
        formatFullDate,
        formatPercent,
    } from '@/lib/format';
    import type { ValidationErrors } from '@/types';
    import type { SavingsStats } from '@/types';
    import {
        complete as completeSavings,
        destroy as destroySavings,
        store as storeSavings,
        update as updateSavings,
    } from '@/routes/savings';
    import { onMount } from 'svelte';

    interface GoalItem {
        id: number;
        name: string;
        icon: string;
        target_amount: number;
        current_amount: number;
        target_date: string | null;
        is_completed: boolean;
        is_closed: boolean;
    }

    type FlashWarning =
        | string
        | {
              overage?: number;
              message?: string;
              warning?: string;
              title?: string;
              detail?: string;
              severity?: string;
          };

    let {
        goals = [],
        stats = { total_saved: 0, monthly_income: 0, monthly_deposits: 0, savings_rate: 0 },
        salaryMonth = null,
    }: {
        goals?: GoalItem[];
        stats?: SavingsStats;
        salaryMonth?: { key: string; label: string; range: string; daysLeft: number } | null;
    } = $props();

    /** «المُودَع» يُحسب على شهر الراتب، فيُذكر الراتب باسمه لا «هذا الشهر». */
    const periodLine = $derived(salaryMonth ? `أهدافك الادخارية · ${salaryMonth.label}` : 'أهدافك الادخارية');

    const serverErrors = $derived(
        (page.props.errors ?? {}) as ValidationErrors,
    );
    const flashWarnings = $derived.by(() => {
        const warnings = page.props.flash?.warnings;

        if (Array.isArray(warnings)) {
            return warnings as FlashWarning[];
        }

        return warnings ? [warnings as FlashWarning] : [];
    });

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

    function warningText(warning: FlashWarning): string {
        if (typeof warning === 'string') {
            return warning;
        }

        if (typeof warning.overage === 'number') {
            return `تجاوزت هدفك بـ ${formatCurrency(warning.overage)}`;
        }

        return (
            warning.message ??
            warning.warning ??
            [warning.title, warning.detail].filter(Boolean).join(' — ')
        );
    }

    const totalSavings = $derived(stats.total_saved ?? 0);
    const monthlyIncome = $derived(stats.monthly_income ?? 0);
    const monthlyDeposits = $derived(stats.monthly_deposits ?? 0);
    const savingsRate = $derived(
        monthlyIncome > 0
            ? Math.round((monthlyDeposits / monthlyIncome) * 100)
            : null,
    );
    const totalTarget = $derived(
        goals.reduce((sum, g) => sum + g.target_amount, 0),
    );
    const overallPct = $derived(
        totalTarget > 0
            ? Math.min(100, Math.round((totalSavings / totalTarget) * 100))
            : 0,
    );
    const completedGoals = $derived(goals.filter((goal) => goal.is_completed).length);

    function getProgressColorClass(pct: number): string {
        if (pct >= 100) return 'bg-success';
        if (pct > 90) return 'bg-destructive';
        if (pct >= 70) return 'bg-amber-500';
        return 'bg-emerald-500';
    }

    function getProgressTextClass(pct: number): string {
        if (pct >= 100) return 'text-success-text';
        if (pct > 90) return 'text-destructive';
        if (pct >= 70) return 'text-amber-600 dark:text-amber-400';
        return 'text-emerald-600 dark:text-emerald-400';
    }

    // Add goal modal
    let showFormModal = $state(false);
    let formName = $state('');
    let formIcon = $state('banknote');
    /** المبلغ المستهدف بالهللات */
    let formTargetAmount = $state(0);
    let formTargetDate = $state('');
    let targetAmountSheetOpen = $state(false);
    let targetDateSheetOpen = $state(false);
    let formErrors = $state<Record<string, string>>({});
    let submitting = $state(false);

    function openAddModal() {
        submitting = false;
        formName = '';
        formIcon = 'banknote';
        formTargetAmount = 0;
        formTargetDate = '';
        formErrors = {};
        showFormModal = true;
    }

    function closeFormModal() {
        showFormModal = false;
        formErrors = {};
    }

    function submitForm() {
        formErrors = {};
        const targetSar = formTargetAmount / 100;

        if (!formName.trim()) {
            formErrors.name = 'اسم الهدف مطلوب';
            return;
        }
        if (!targetSar || targetSar <= 0) {
            formErrors.target_amount = 'المبلغ المستهدف مطلوب';
            return;
        }

        submitting = true;

        router.post(
            storeSavings(),
            {
                name: formName.trim(),
                icon: formIcon,
                target_amount: targetSar,
                target_date: formTargetDate || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
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

    // Add amount modal
    let showAddAmountModal = $state(false);
    let selectedGoalId = $state<number | null>(null);
    let selectedGoalName = $state('');
    /** المبلغ المضاف بالهللات */
    let addAmountValue = $state(0);
    let selectedGoalRemaining = $state(0);
    let addAmountErrors = $state<Record<string, string>>({});

    function openAddAmountModal(goal: GoalItem) {
        submitting = false;
        selectedGoalId = goal.id;
        selectedGoalName = goal.name;
        selectedGoalRemaining = Math.max(0, goal.target_amount - goal.current_amount);
        addAmountValue = 0;
        addAmountErrors = {};
        showAddAmountModal = true;
    }

    function closeAddAmountModal() {
        showAddAmountModal = false;
        selectedGoalId = null;
        selectedGoalName = '';
        addAmountErrors = {};
    }

    function submitAddAmount(halalas: number) {
        addAmountErrors = {};
        const amountSar = halalas / 100;

        if (!selectedGoalId) {
            return;
        }

        if (!amountSar || amountSar <= 0) {
            addAmountErrors.amount = 'المبلغ مطلوب';
            return;
        }

        submitting = true;

        router.put(
            updateSavings(selectedGoalId),
            {
                amount: amountSar,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeAddAmountModal();
                },
                onError: (errors) => {
                    addAmountErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    // Delete
    let deleteId = $state<number | null>(null);
    let deleteOpen = $state(false);

    function confirmDelete(id: number) {
        deleteId = id;
        deleteOpen = true;
    }

    function executeDelete() {
        if (!deleteId) return;

        const id = deleteId;

        router.delete(destroySavings(id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteId = null;
                deleteOpen = false;
            },
        });
    }

    // Complete
    function completeGoal(id: number) {
        router.put(
            completeSavings(id),
            {},
            {
                preserveScroll: true,
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

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div
        class="hidden flex-col gap-4 md:flex md:flex-row md:items-center md:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">الادخار</h1>
            <p class="text-muted-foreground">{periodLine}</p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة هدف جديد
        </Button>
    </div>

    {#if flashWarnings.length > 0}
        <div class="flex flex-col gap-2" aria-live="polite">
            {#each flashWarnings as warning}
                {#if warningText(warning)}
                    {@const isSuccess = typeof warning !== 'string' && warning.severity === 'success'}
                    <p class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm {isSuccess ? 'bg-success/10 text-success-text' : 'bg-warning/15 text-warning-text'}" role="status">
                        {#if isSuccess}
                            <CheckCircle2 class="size-4 shrink-0" />
                        {:else}
                            <CircleAlert class="size-4 shrink-0" />
                        {/if}
                        {warningText(warning)}
                    </p>
                {/if}
            {/each}
        </div>
    {/if}

    <!-- Stats bar -->
    {#if goals.length > 0}
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            إجمالي المدخرات
                        </p>
                        <Vault class="size-4 text-success" />
                    </div>
                    <p
                        class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {formatCurrency(totalSavings)}
                    </p>
                </CardContent>
            </Card>
            {#if savingsRate !== null}
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-muted-foreground">
                                معدل الادخار
                            </p>
                            <TrendingUp class="size-4 text-chart-1" />
                        </div>
                        <p class="mt-2 text-xl font-bold tabular-nums">{formatPercent(savingsRate)}</p>
                        <p class="mt-1 text-xs text-muted-foreground">من إيداعات الشهر الحالي</p>
                    </CardContent>
                </Card>
            {/if}
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">الأهداف</p>
                        <CheckCircle2 class="size-4 text-amber-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">
                        {completedGoals} من {goals.length} هدف مكتمل
                    </p>
                </CardContent>
            </Card>
        </div>
    {/if}

    <!-- Big green banner -->
    {#if goals.length > 0}
        <Card
            class="border-emerald-500/30 bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-950/40 dark:to-emerald-900/20"
        >
            <CardContent class="p-6 sm:p-8">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-sm font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            إجمالي المدخرات
                        </p>
                        <p
                            class="mt-1 text-4xl font-bold text-emerald-900 dark:text-emerald-100"
                        >
                            {formatCurrency(totalSavings)}
                        </p>
                        {#if savingsRate !== null}
                            <div class="mt-2 flex items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-foreground"
                                >
                                    <TrendingUp class="size-3" />
                                    {formatPercent(savingsRate)} من إيداعات الشهر الحالي
                                </span>
                            </div>
                        {/if}
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <div
                            class="relative flex h-24 w-24 items-center justify-center"
                        >
                            <svg
                                viewBox="0 0 100 100"
                                class="h-24 w-24 -rotate-90"
                            >
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="8"
                                    class="text-emerald-200 dark:text-emerald-800"
                                />
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="8"
                                    stroke-linecap="round"
                                    class="text-emerald-600 dark:text-emerald-400 transition-all duration-1000"
                                    stroke-dasharray={`${overallPct * 2.51} 251`}
                                />
                            </svg>
                            <span
                                class="absolute text-lg font-bold text-emerald-800 dark:text-emerald-200"
                                >{overallPct}%</span
                            >
                        </div>
                        <span
                            class="text-xs text-emerald-600 dark:text-emerald-400"
                            >{completedGoals} من {goals.length} هدف مكتمل</span
                        >
                    </div>
                </div>
            </CardContent>
        </Card>
    {/if}

    <!-- Goals Section -->
    <div>
        <h2 class="mb-4 text-lg font-semibold">أهداف الادخار</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            {#each goals as goal (goal.id)}
                {@const pct =
                    goal.target_amount > 0
                        ? Math.round(
                              (goal.current_amount / goal.target_amount) * 100,
                          )
                        : 0}
                {@const remaining = goal.target_amount - goal.current_amount}
                <Card
                    class="overflow-hidden transition-all hover:shadow-md {goal.is_completed
                        ? 'opacity-70'
                        : ''}"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <CategoryIcon
                                    icon={goal.icon}
                                    color={goal.is_completed
                                        ? '#0ca30c'
                                        : '#1baf7a'}
                                    size="md"
                                />
                                <div>
                                    <CardTitle class="text-base"
                                        >{goal.name}</CardTitle
                                    >
                                    {#if goal.target_date}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {formatDate(goal.target_date)}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                            {#if goal.is_completed}
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 shrink-0"
                                >
                                    <CheckCircle2 class="size-2.5" />
                                    مكتمل
                                </span>
                            {:else}
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 shrink-0"
                                >
                                    قيد الادخار
                                </span>
                            {/if}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div>
                                <div
                                    class="relative h-3 w-full overflow-hidden rounded-full bg-secondary"
                                >
                                    <div
                                        class="absolute inset-y-0 h-full rounded-full transition-all duration-500 {getProgressColorClass(
                                            pct,
                                        )}"
                                        style="inset-inline-start: 0; width: {Math.min(
                                            pct,
                                            100,
                                        )}%"
                                    ></div>
                                </div>
                                <div
                                    class="mt-1.5 flex items-center justify-between text-sm"
                                >
                                    <span
                                        class="{getProgressTextClass(
                                            pct,
                                        )} font-medium tabular-nums"
                                        >{pct}%</span
                                    >
                                    <span
                                        class="text-muted-foreground tabular-nums"
                                    >
                                        {#if remaining > 0}
                                            متبقي: {formatAmount(remaining)} ر.س
                                        {:else}
                                            مكتمل
                                        {/if}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-muted/50 p-3 text-sm"
                            >
                                <div class="flex flex-col">
                                    <span class="text-xs text-muted-foreground"
                                        >المبلغ المستهدف</span
                                    >
                                    <span class="font-bold tabular-nums"
                                        >{formatAmount(goal.target_amount)} ر.س</span
                                    >
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-muted-foreground"
                                        >المدخر حالياً</span
                                    >
                                    <span
                                        class="font-bold tabular-nums text-emerald-600 dark:text-emerald-400"
                                        >{formatAmount(goal.current_amount)} ر.س</span
                                    >
                                </div>
                            </div>

                            <div class="flex gap-2">
                                {#if !goal.is_closed}
                                    <Button
                                        size="sm"
                                        class="flex-1 gap-1.5 text-xs"
                                        onclick={() => openAddAmountModal(goal)}
                                    >
                                        <Plus class="size-3.5" />
                                        إضافة مبلغ
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="gap-1 text-xs"
                                        onclick={() => completeGoal(goal.id)}
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                    </Button>
                                {/if}
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="حذف"
                                    class="text-destructive hover:text-destructive {goal.is_closed
                                        ? 'ms-auto'
                                        : ''}"
                                    onclick={() => confirmDelete(goal.id)}
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            {/each}

            <!-- Add goal dashed card -->
            <Card
                class="border-2 border-dashed border-border hover:border-primary/50 hover:bg-muted/30 transition-colors cursor-pointer"
                onclick={openAddModal}
            >
                <CardContent class="flex items-center justify-center py-10">
                    <div class="text-center">
                        <div
                            class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-muted"
                        >
                            <Plus class="size-6 text-muted-foreground" />
                        </div>
                        <p class="text-sm font-medium">إضافة هدف جديد</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            حدد هدفاً ادخارياً جديداً
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    {#if goals.length === 0}
        <Card>
            <CardContent
                class="flex flex-col items-center justify-center py-12 text-center"
            >
                <Vault class="size-12 text-muted-foreground" />
                <p class="mt-3 font-medium">لا توجد أهداف ادخارية</p>
                <p class="text-sm text-muted-foreground">
                    أضف هدفاً ادخارياً للبدء
                </p>
                <Button size="sm" class="mt-4 gap-1.5" onclick={openAddModal}>
                    <Plus class="size-3.5" />
                    إضافة هدف جديد
                </Button>
            </CardContent>
        </Card>
    {/if}
</div>

<!-- لوح إضافة هدف ادخاري -->
<SheetShell
    bind:open={showFormModal}
    title="إضافة هدف ادخاري"
    subtitle="اسم وأيقونة ومبلغ مستهدف"
    onClose={closeFormModal}
>
    <div class="flex flex-col gap-3">
        {#if generalError(formErrors) || generalError(serverErrors)}
            <p class="flex items-start gap-2 rounded-2xl bg-destructive/10 px-3 py-2 text-[12px] text-destructive" role="alert">
                <CircleAlert class="mt-px size-4 shrink-0" />
                {generalError(formErrors) || generalError(serverErrors)}
            </p>
        {/if}

        <SheetField
            label="المبلغ المستهدف"
            icon={Target}
            value={formTargetAmount > 0 ? `${formatAmount(formTargetAmount)} ر.س` : ''}
            placeholder="اضغط لإدخال المبلغ"
            error={formErrors.target_amount || errorText(serverErrors, 'target_amount')}
            onclick={() => (targetAmountSheetOpen = true)}
        />

        <SheetField
            label="التاريخ المستهدف (اختياري)"
            icon={CalendarDays}
            value={formTargetDate ? formatFullDate(formTargetDate) : ''}
            placeholder="بدون تاريخ"
            error={formErrors.target_date || errorText(serverErrors, 'target_date')}
            onclick={() => (targetDateSheetOpen = true)}
        />

        <div class="flex flex-col gap-1.5">
            <label for="goal-name" class="text-[11.5px] text-muted-foreground">الاسم</label>
            <input
                id="goal-name"
                type="text"
                placeholder="مثال: سيارة جديدة"
                bind:value={formName}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if formErrors.name || errorText(serverErrors, 'name')}
                <p class="text-[11.5px] text-destructive">{formErrors.name || errorText(serverErrors, 'name')}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-1.5">
            <span class="text-[11.5px] text-muted-foreground">الأيقونة</span>
            <div class="flex flex-wrap gap-2">
                {#each ICON_PICKER as key (key)}
                    <button
                        type="button"
                        class="grid size-11 place-items-center rounded-xl border transition-colors {formIcon === key
                            ? 'border-primary bg-primary/8'
                            : 'border-border'}"
                        aria-label={ICON_LABELS[key]}
                        aria-pressed={formIcon === key}
                        onclick={() => (formIcon = key)}
                    >
                        <CategoryIcon icon={key} size="sm" />
                    </button>
                {/each}
            </div>
        </div>
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
            disabled={submitting}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            <Check class="size-[18px]" />
            {submitting ? 'جارٍ الإضافة…' : 'إضافة'}
        </button>
    {/snippet}
</SheetShell>

<AmountSheet
    bind:open={targetAmountSheetOpen}
    bind:value={formTargetAmount}
    title="المبلغ المستهدف"
    quickAdd={[500, 1000, 5000]}
/>

<DateSheet bind:open={targetDateSheetOpen} bind:value={formTargetDate} title="التاريخ المستهدف" />

<!-- لوح إيداع في هدف -->
<AmountSheet
    bind:open={showAddAmountModal}
    bind:value={addAmountValue}
    title={`إيداع في ${selectedGoalName}`}
    subtitle={selectedGoalRemaining > 0 ? `المتبقي ${formatCurrency(selectedGoalRemaining)}` : 'الهدف مكتمل'}
    hint={addAmountErrors.amount || errorText(serverErrors, 'amount') || generalError(addAmountErrors)}
    quickAdd={[100, 500, 1000]}
    saveLabel="إيداع"
    onSave={submitAddAmount}
/>

<ConfirmSheet
    bind:open={deleteOpen}
    message="سيُحذف هذا الهدف الادخاري نهائياً ولا يمكن التراجع."
    onConfirm={executeDelete}
/>
