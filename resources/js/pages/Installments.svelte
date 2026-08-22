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
    import X from 'lucide-svelte/icons/x';
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
    import { formatCurrency, formatDate } from '@/lib/format';
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

    function handleKeydown(event: KeyboardEvent): void {
        if (event.key !== 'Escape') {
            return;
        }

        if (showDetailsModal) {
            closeDetailsModal();
        } else if (showFormModal) {
            closeFormModal();
        } else if (deleteId !== null) {
            cancelDelete();
        }
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
    let formMonthly = $state('');
    let formTotalMonths = $state('');
    let formStartDate = $state('');
    let formErrors = $state<Record<string, string>>({});
    let submitting = $state(false);

    function openAddModal() {
        submitting = false;
        formName = '';
        formReason = '';
        formIcon = 'ellipsis';
        formMonthly = '';
        formTotalMonths = '';
        formStartDate = new Date().toISOString().slice(0, 7);
        formErrors = {};
        showFormModal = true;
    }

    function closeFormModal() {
        showFormModal = false;
        formErrors = {};
    }

    function submitForm() {
        formErrors = {};
        const monthlySar = parseFloat(formMonthly);
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
                start_date: formStartDate,
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

    function confirmDelete(id: number) {
        deleteId = id;
    }

    function cancelDelete() {
        deleteId = null;
    }

    function executeDelete() {
        if (!deleteId) return;

        const id = deleteId;

        router.delete(destroyInstallment(id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteId = null;
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

<!-- Details Modal -->
{#if showDetailsModal && selectedInstallment}
    {@const item = selectedInstallment}
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <button type="button" class="fixed inset-0 bg-black/50" aria-label="إغلاق" onclick={closeDetailsModal}></button>
        <div
            class="relative z-10 mx-4 w-full max-w-lg rounded-xl border bg-card p-0 shadow-lg"
        >
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <CategoryIcon icon={item.icon} color="#4a3aa7" size="md" />
                    {item.name}
                </h2>
                <button
                    class="text-muted-foreground hover:text-foreground cursor-pointer"
                    onclick={closeDetailsModal}
                >
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div class="rounded-lg bg-muted/50 p-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">السبب</span>
                            <p class="font-medium">{item.reason}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">الحالة</span>
                            <p class="font-medium">
                                {#if !item.is_completed}
                                    <span
                                        class="text-green-600 dark:text-green-400"
                                        >نشط</span
                                    >
                                {:else}
                                    <span class="text-muted-foreground"
                                        >منتهي</span
                                    >
                                {/if}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >القسط الشهري</span
                            >
                            <p class="font-bold tabular-nums">
                                {formatCurrency(item.monthly_amount)}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >المبلغ الإجمالي</span
                            >
                            <p class="font-bold tabular-nums">
                                {formatCurrency(item.total_amount)}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">المدفوع</span>
                            <p class="font-medium tabular-nums">
                                {item.paid_months} / {item.total_months} شهر
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">المتبقي</span>
                            <p class="font-medium tabular-nums">
                                {#if remainingMonths(item) > 0}
                                    {remainingMonths(item)} أشهر · {formatCurrency(
                                        remainingAmount(item),
                                    )}
                                {:else}
                                    <span
                                        class="text-green-600 dark:text-green-400"
                                        >مكتمل</span
                                    >
                                {/if}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >تاريخ البداية</span
                            >
                            <p class="font-medium tabular-nums">
                                {formatDate(item.start_date)}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >المدفوع حتى الآن</span
                            >
                            <p
                                class="font-bold tabular-nums text-green-600 dark:text-green-400"
                            >
                                {formatCurrency(paidAmount(item))}
                            </p>
                        </div>
                    </div>
                </div>

                {#if !item.is_completed}
                    <div
                        class="relative h-3 w-full overflow-hidden rounded-full bg-secondary"
                    >
                        <div
                            class="absolute inset-y-0 h-full rounded-full bg-primary transition-all"
                            style="inset-inline-start: 0; width: {Math.round(
                                (item.paid_months / item.total_months) * 100,
                            )}%"
                        ></div>
                    </div>
                    <div
                        class="flex justify-between text-sm text-muted-foreground"
                    >
                        <span
                            >{Math.round(
                                (item.paid_months / item.total_months) * 100,
                            )}% مكتمل</span
                        >
                        <span>{remainingMonths(item)} أشهر متبقية</span>
                    </div>
                {/if}
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                {#if !item.is_completed}
                    <Button onclick={payInstallment} class="gap-1.5">
                        <CheckCircle2 class="size-4" />
                        سداد قسط
                    </Button>
                {:else}
                    <Button variant="outline" onclick={closeDetailsModal}
                        >إغلاق</Button
                    >
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- Add Modal -->
{#if showFormModal}
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pt-[10vh]">
        <button type="button" class="fixed inset-0 bg-black/50" aria-label="إغلاق" onclick={closeFormModal}></button>
        <div
            class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg"
        >
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">إضافة قسط جديد</h2>
                <button
                    class="text-muted-foreground hover:text-foreground cursor-pointer"
                    onclick={closeFormModal}
                >
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                {#if generalError(formErrors) || generalError(serverErrors)}
                    <p class="flex items-center gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                        <CircleAlert class="size-4 shrink-0" />
                        {generalError(formErrors) || generalError(serverErrors)}
                    </p>
                {/if}
                <div>
                    <label
                        for="inst-name"
                        class="mb-1.5 block text-sm font-medium"
                        >اسم القسط</label
                    >
                    <input
                        id="inst-name"
                        type="text"
                        placeholder="مثال: تقسيط سيارة"
                        bind:value={formName}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.name || errorText(serverErrors, 'name')}
                        <p class="mt-1 text-xs text-destructive">
                            {formErrors.name || errorText(serverErrors, 'name')}
                        </p>
                    {/if}
                </div>
                <div>
                    <label
                        for="inst-reason"
                        class="mb-1.5 block text-sm font-medium"
                        >السبب / الوصف</label
                    >
                    <input
                        id="inst-reason"
                        type="text"
                        placeholder="مثال: سيارة تويوتا 2025"
                        bind:value={formReason}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div>
                    <label
                        for="inst-icon"
                        class="mb-1.5 block text-sm font-medium">الأيقونة</label
                    >
                    <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto">
                        {#each ICON_PICKER as key}
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-lg border transition-all {formIcon ===
                                key
                                    ? 'border-primary ring-2 ring-primary/20'
                                    : 'border-border hover:border-primary/50'}"
                                aria-label={ICON_LABELS[key]}
                                aria-pressed={formIcon === key}
                                onclick={() => (formIcon = key)}
                            >
                                <CategoryIcon icon={key} size="sm" />
                            </button>
                        {/each}
                    </div>
                </div>
                <div>
                    <label
                        for="inst-monthly"
                        class="mb-1.5 block text-sm font-medium"
                        >القسط الشهري (ر.س)</label
                    >
                    <input
                        id="inst-monthly"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={formMonthly}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-end text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.monthly_amount || errorText(serverErrors, 'monthly_amount')}
                        <p class="mt-1 text-xs text-destructive">
                            {formErrors.monthly_amount || errorText(serverErrors, 'monthly_amount')}
                        </p>
                    {/if}
                </div>
                <div>
                    <label
                        for="inst-months"
                        class="mb-1.5 block text-sm font-medium"
                        >عدد الأشهر</label
                    >
                    <input
                        id="inst-months"
                        type="number"
                        min="1"
                        placeholder="12"
                        bind:value={formTotalMonths}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.total_months || errorText(serverErrors, 'total_months')}
                        <p class="mt-1 text-xs text-destructive">
                            {formErrors.total_months || errorText(serverErrors, 'total_months')}
                        </p>
                    {/if}
                </div>
                <div>
                    <label
                        for="inst-start"
                        class="mb-1.5 block text-sm font-medium"
                        >تاريخ البداية</label
                    >
                    <input
                        id="inst-start"
                        type="month"
                        bind:value={formStartDate}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.start_date || errorText(serverErrors, 'start_date')}
                        <p class="mt-1 text-xs text-destructive">
                            {formErrors.start_date || errorText(serverErrors, 'start_date')}
                        </p>
                    {/if}
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeFormModal}>إلغاء</Button
                >
                <Button onclick={submitForm} disabled={submitting}>
                    {submitting ? 'جاري الإضافة...' : 'إضافة'}
                </Button>
            </div>
        </div>
    </div>
{/if}

<!-- Delete confirmation -->
{#if deleteId !== null}
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <button type="button" class="fixed inset-0 bg-black/50" aria-label="إغلاق" onclick={cancelDelete}></button>
        <div
            class="relative z-10 mx-4 w-full max-w-sm rounded-xl border bg-card p-6 shadow-lg"
        >
            <h2 class="text-lg font-semibold">تأكيد الحذف</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                هل أنت متأكد من حذف هذا القسط؟ لا يمكن التراجع عن هذا الإجراء.
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" onclick={cancelDelete}>إلغاء</Button>
                <Button variant="destructive" onclick={executeDelete}
                    >حذف</Button
                >
            </div>
        </div>
    </div>
{/if}

<svelte:window onkeydown={handleKeydown} />
