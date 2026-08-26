<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الأقساط', href: '/installments' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Clock from 'lucide-svelte/icons/clock';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Wallet from 'lucide-svelte/icons/wallet';
    import Check from 'lucide-svelte/icons/check';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
        CardDescription,
    } from '@/components/ui/card';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { ICON_LABELS, ICON_PICKER } from '@/lib/category-icons';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import SheetField from '@/components/ui/SheetField.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DateSheet from '@/components/ui/DateSheet.svelte';
    import ConfirmSheet from '@/components/ui/ConfirmSheet.svelte';
    import { formatAmount, formatCurrency, formatDate } from '@/lib/format';
    import {
        destroy as destroyInstallment,
        pay as payInstallmentRoute,
        store as storeInstallment,
    } from '@/routes/installments';
    import type { ValidationErrors } from '@/types';

    interface InstallmentItem {
        id: number;
        name: string;
        reason: string;
        icon: string;
        monthly_amount: number;
        total_amount: number;
        paid_months: number;
        total_months: number;
        start_date: string;
        is_completed: boolean;
    }

    interface InstallmentStats {
        active_count: number;
        total_monthly: number;
        completed_count: number;
    }

    let {
        installments = [],
        stats = { active_count: 0, total_monthly: 0, completed_count: 0 },
    }: {
        installments?: InstallmentItem[];
        stats?: InstallmentStats;
    } = $props();

    const serverErrors = $derived(
        (page.props.errors ?? {}) as ValidationErrors,
    );

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

    function remainingMonths(item: InstallmentItem): number {
        return Math.max(0, item.total_months - item.paid_months);
    }

    function remainingAmount(item: InstallmentItem): number {
        return remainingMonths(item) * item.monthly_amount;
    }

    function paidAmount(item: InstallmentItem): number {
        return item.paid_months * item.monthly_amount;
    }

    let activeTab = $state<'active' | 'completed' | 'all'>('active');

    const filteredInstallments = $derived.by(() => {
        if (activeTab === 'all') return installments;
        return installments.filter((i) =>
            activeTab === 'active' ? !i.is_completed : i.is_completed,
        );
    });

    const activeCount = $derived(stats.active_count);
    const completedCount = $derived(stats.completed_count);
    const totalMonthlyCommitment = $derived(stats.total_monthly);

    // Details modal
    let selectedInstallment = $state<InstallmentItem | null>(null);
    let showDetailsModal = $state(false);

    function openDetailsModal(item: InstallmentItem) {
        selectedInstallment = item;
        showDetailsModal = true;
    }

    function closeDetailsModal() {
        showDetailsModal = false;
        selectedInstallment = null;
    }

    function payInstallment() {
        if (!selectedInstallment) return;

        router.put(
            payInstallmentRoute(selectedInstallment.id),
            {},
            {
                preserveScroll: true,
                onSuccess: closeDetailsModal,
            },
        );
    }

    // Add modal
    let showFormModal = $state(false);
    let formName = $state('');
    let formReason = $state('');
    let formIcon = $state('ellipsis');
    /** القسط الشهري بالهللات */
    let formMonthly = $state(0);
    let formTotalMonths = $state('');
    /** ISO كامل — يُقصّ إلى Y-m عند الإرسال */
    let formStartDate = $state('');
    let monthlySheetOpen = $state(false);
    let startDateSheetOpen = $state(false);
    let formErrors = $state<Record<string, string>>({});
    let submitting = $state(false);

    function openAddModal() {
        submitting = false;
        formName = '';
        formReason = '';
        formIcon = 'ellipsis';
        formMonthly = 0;
        formTotalMonths = '';
        formStartDate = new Date().toISOString().slice(0, 10);
        formErrors = {};
        showFormModal = true;
    }

    function closeFormModal() {
        showFormModal = false;
        formErrors = {};
    }

    function submitForm() {
        formErrors = {};
        const monthlySar = formMonthly / 100;
        const totalMonthsNum = parseInt(formTotalMonths);

        if (!formName.trim()) {
            formErrors.name = 'اسم القسط مطلوب';
            return;
        }
        if (!monthlySar || monthlySar <= 0) {
            formErrors.monthly_amount = 'القسط الشهري مطلوب';
            return;
        }
        if (!totalMonthsNum || totalMonthsNum < 1) {
            formErrors.total_months = 'عدد الأشهر مطلوب';
            return;
        }
        if (!formStartDate) {
            formErrors.start_date = 'تاريخ البداية مطلوب';
            return;
        }

        submitting = true;

        router.post(
            storeInstallment(),
            {
                name: formName.trim(),
                reason: formReason.trim(),
                icon: formIcon,
                monthly_amount: monthlySar,
                total_amount: monthlySar * totalMonthsNum,
                total_months: totalMonthsNum,
                start_date: formStartDate.slice(0, 7),
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

        router.delete(destroyInstallment(id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteId = null;
                deleteOpen = false;
                if (showDetailsModal && selectedInstallment?.id === id) {
                    closeDetailsModal();
                }
            },
        });
    }

    const tabs = $derived([
        { key: 'active' as const, label: 'نشطة', count: activeCount },
        { key: 'completed' as const, label: 'منتهية', count: completedCount },
        { key: 'all' as const, label: 'الكل', count: installments.length },
    ]);
</script>

<AppHead title="الأقساط" />
<MobileHeader title="الأقساط" subtitle="التزاماتك المالية" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div
        class="hidden flex-col gap-4 md:flex md:flex-row md:items-center md:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">الأقساط</h1>
            <p class="text-muted-foreground">التزاماتك المالية</p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة قسط جديد
        </Button>
    </div>

    {#if installments.length > 0}
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            الأقساط النشطة
                        </p>
                        <Clock class="size-4 text-blue-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{activeCount}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            الالتزام الشهري
                        </p>
                        <CalendarClock class="size-4 text-orange-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold text-destructive">
                        {formatCurrency(totalMonthlyCommitment)}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">المنتهية</p>
                        <CheckCircle2 class="size-4 text-green-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{completedCount}</p>
                </CardContent>
            </Card>
        </div>
    {/if}

    <!-- Tabs -->
    <div class="flex gap-1 rounded-lg bg-muted p-1">
        {#each tabs as tab}
            <button
                class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-all {activeTab ===
                tab.key
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'}"
                onclick={() => (activeTab = tab.key)}
            >
                {tab.label}
                <span class="ms-1 text-xs text-muted-foreground"
                    >({tab.count})</span
                >
            </button>
        {/each}
    </div>

    <!-- Cards -->
    {#if filteredInstallments.length === 0}
        <Card>
            <CardContent
                class="flex flex-col items-center justify-center py-12 text-center"
            >
                <CreditCard class="size-12 text-muted-foreground" />
                <p class="mt-3 font-medium">لا توجد أقساط</p>
                <p class="text-sm text-muted-foreground">
                    {activeTab === 'active'
                        ? 'لا توجد أقساط نشطة حالياً'
                        : activeTab === 'completed'
                          ? 'لا توجد أقساط منتهية'
                          : 'أضف قسطاً جديداً للبدء'}
                </p>
                {#if activeTab === 'all'}
                    <Button
                        size="sm"
                        class="mt-4 gap-1.5"
                        onclick={openAddModal}
                    >
                        <Plus class="size-3.5" />
                        إضافة قسط جديد
                    </Button>
                {/if}
            </CardContent>
        </Card>
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each filteredInstallments as item}
                {@const remaining = remainingMonths(item)}
                {@const remAmount = remainingAmount(item)}
                <Card class="overflow-hidden">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <CategoryIcon
                                    icon={item.icon}
                                    color="#4a3aa7"
                                    size="md"
                                />
                                <div>
                                    <CardTitle class="text-base"
                                        >{item.name}</CardTitle
                                    >
                                    <CardDescription
                                        >{item.reason}</CardDescription
                                    >
                                </div>
                            </div>
                            {#if !item.is_completed}
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    <Clock class="size-2.5" /> نشط
                                </span>
                            {:else}
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-400"
                                >
                                    <CheckCircle2 class="size-2.5" /> منتهي
                                </span>
                            {/if}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground"
                                    >القسط الشهري</span
                                >
                                <span class="text-sm font-bold tabular-nums"
                                    >{formatCurrency(item.monthly_amount)}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground"
                                    >المتبقي</span
                                >
                                <span class="text-sm tabular-nums">
                                    {#if remaining > 0}
                                        {remaining} أشهر
                                    {:else}
                                        <span
                                            class="text-green-600 dark:text-green-400"
                                            >مكتمل</span
                                        >
                                    {/if}
                                </span>
                            </div>
                            {#if !item.is_completed}
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >المبلغ المتبقي</span
                                    >
                                    <span
                                        class="text-sm font-bold tabular-nums text-destructive"
                                        >{formatCurrency(remAmount)}</span
                                    >
                                </div>
                                <div
                                    class="relative h-2 w-full overflow-hidden rounded-full bg-secondary"
                                >
                                    <div
                                        class="absolute inset-y-0 h-full rounded-full bg-primary transition-all"
                                        style="inset-inline-start: 0; width: {Math.round(
                                            (item.paid_months /
                                                item.total_months) *
                                                100,
                                        )}%"
                                    ></div>
                                </div>
                                <div
                                    class="flex justify-between text-xs text-muted-foreground"
                                >
                                    <span
                                        >المدفوع: {formatCurrency(
                                            paidAmount(item),
                                        )}</span
                                    >
                                    <span
                                        >{Math.round(
                                            (item.paid_months /
                                                item.total_months) *
                                                100,
                                        )}%</span
                                    >
                                </div>
                            {/if}
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="flex-1 text-xs gap-1"
                                onclick={() => openDetailsModal(item)}
                            >
                                تفاصيل
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="حذف"
                                class="text-destructive hover:text-destructive"
                                onclick={() => confirmDelete(item.id)}
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<!-- لوح تفاصيل القسط -->
<SheetShell
    bind:open={showDetailsModal}
    title={selectedInstallment?.name ?? 'تفاصيل القسط'}
    subtitle={selectedInstallment?.reason ?? ''}
    onClose={closeDetailsModal}
>
    {#if selectedInstallment}
        {@const item = selectedInstallment}
        {@const pct = Math.round((item.paid_months / item.total_months) * 100)}
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3 rounded-2xl border border-border bg-card p-3">
                <CategoryIcon icon={item.icon} color="#4a3aa7" size="md" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[14px] font-semibold">{item.name}</p>
                    <p class="truncate text-[11.5px] text-muted-foreground">
                        {item.is_completed ? 'منتهي' : 'نشط'} · {formatDate(item.start_date)}
                    </p>
                </div>
            </div>

            {#if !item.is_completed}
                <div>
                    <div class="relative h-2 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="absolute inset-y-0 h-full rounded-full bg-primary transition-all"
                            style="inset-inline-start: 0; width: {pct}%"
                        ></div>
                    </div>
                    <div class="mt-1.5 flex justify-between text-[11.5px] text-muted-foreground tabular-nums">
                        <span>{pct}% مكتمل</span>
                        <span>{remainingMonths(item)} أشهر متبقية</span>
                    </div>
                </div>
            {/if}

            <div class="grid grid-cols-2 gap-2">
                {#each [{ l: 'القسط الشهري', v: formatCurrency(item.monthly_amount) }, { l: 'المبلغ الإجمالي', v: formatCurrency(item.total_amount) }, { l: 'المدفوع', v: `${item.paid_months} / ${item.total_months} شهر` }, { l: 'المبلغ المتبقي', v: formatCurrency(remainingAmount(item)) }, { l: 'المدفوع حتى الآن', v: formatCurrency(paidAmount(item)) }, { l: 'تاريخ البداية', v: formatDate(item.start_date) }] as cell (cell.l)}
                    <div class="rounded-2xl border border-border bg-card p-3">
                        <p class="text-[11.5px] text-muted-foreground">{cell.l}</p>
                        <p class="mt-0.5 text-[14px] font-semibold tabular-nums">{cell.v}</p>
                    </div>
                {/each}
            </div>
        </div>
    {/if}

    {#snippet footer()}
        <button
            type="button"
            onclick={closeDetailsModal}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85"
        >
            إغلاق
        </button>
        {#if selectedInstallment && !selectedInstallment.is_completed}
            <button
                type="button"
                onclick={payInstallment}
                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99]"
            >
                <CheckCircle2 class="size-[18px]" />
                سداد قسط
            </button>
        {/if}
    {/snippet}
</SheetShell>

<!-- لوح إضافة قسط -->
<SheetShell bind:open={showFormModal} title="إضافة قسط جديد" subtitle="التزام شهري ثابت" onClose={closeFormModal}>
    <div class="flex flex-col gap-3">
        {#if generalError(formErrors) || generalError(serverErrors)}
            <p class="flex items-start gap-2 rounded-2xl bg-destructive/10 px-3 py-2 text-[12px] text-destructive" role="alert">
                <CircleAlert class="mt-px size-4 shrink-0" />
                {generalError(formErrors) || generalError(serverErrors)}
            </p>
        {/if}

        <SheetField
            label="القسط الشهري"
            icon={Wallet}
            value={formMonthly > 0 ? `${formatAmount(formMonthly)} ر.س` : ''}
            placeholder="اضغط لإدخال المبلغ"
            error={formErrors.monthly_amount || errorText(serverErrors, 'monthly_amount')}
            onclick={() => (monthlySheetOpen = true)}
        />

        <SheetField
            label="شهر البداية"
            icon={CalendarClock}
            value={formStartDate ? formStartDate.slice(0, 7) : ''}
            placeholder="اختر الشهر"
            error={formErrors.start_date || errorText(serverErrors, 'start_date')}
            onclick={() => (startDateSheetOpen = true)}
        />

        <div class="flex flex-col gap-1.5">
            <label for="inst-months" class="text-[11.5px] text-muted-foreground">عدد الأشهر</label>
            <input
                id="inst-months"
                type="number"
                inputmode="numeric"
                min="1"
                placeholder="12"
                bind:value={formTotalMonths}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] font-semibold tabular-nums focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if formErrors.total_months || errorText(serverErrors, 'total_months')}
                <p class="text-[11.5px] text-destructive">
                    {formErrors.total_months || errorText(serverErrors, 'total_months')}
                </p>
            {/if}
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="inst-name" class="text-[11.5px] text-muted-foreground">اسم القسط</label>
            <input
                id="inst-name"
                type="text"
                placeholder="مثال: تقسيط سيارة"
                bind:value={formName}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if formErrors.name || errorText(serverErrors, 'name')}
                <p class="text-[11.5px] text-destructive">{formErrors.name || errorText(serverErrors, 'name')}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="inst-reason" class="text-[11.5px] text-muted-foreground">السبب / الوصف</label>
            <input
                id="inst-reason"
                type="text"
                placeholder="مثال: سيارة تويوتا 2025"
                bind:value={formReason}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
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

<AmountSheet bind:open={monthlySheetOpen} bind:value={formMonthly} title="القسط الشهري" quickAdd={[100, 500, 1000]} />

<DateSheet bind:open={startDateSheetOpen} bind:value={formStartDate} title="شهر بداية القسط" />

<ConfirmSheet
    bind:open={deleteOpen}
    message="سيُحذف هذا القسط نهائياً ولا يمكن التراجع."
    onConfirm={executeDelete}
/>
