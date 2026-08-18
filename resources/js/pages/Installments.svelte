<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الأقساط', href: '/installments' },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Clock from 'lucide-svelte/icons/clock';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
    import { formatCurrency, formatDate } from '@/lib/format';

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
            activeTab === 'active' ? !i.is_completed : i.is_completed
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

        router.put(`/installments/${selectedInstallment.id}/pay`, {}, {
            preserveScroll: true,
            onFinish: () => {},
        });
    }

    // Add modal
    let showFormModal = $state(false);
    let formName = $state('');
    let formReason = $state('');
    let formIcon = $state('📦');
    let formMonthly = $state('');
    let formTotalMonths = $state('');
    let formStartDate = $state('');
    let formErrors = $state<Record<string, string>>({});
    let submitting = $state(false);

    function openAddModal() {
        formName = '';
        formReason = '';
        formIcon = '📦';
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
            formErrors.monthly = 'القسط الشهري مطلوب';
            return;
        }
        if (!totalMonthsNum || totalMonthsNum < 1) {
            formErrors.totalMonths = 'عدد الأشهر مطلوب';
            return;
        }
        if (!formStartDate) {
            formErrors.startDate = 'تاريخ البداية مطلوب';
            return;
        }

        submitting = true;

        router.post('/installments', {
            name: formName.trim(),
            reason: formReason.trim(),
            icon: formIcon,
            monthly_amount: monthlySar,
            total_amount: monthlySar * totalMonthsNum,
            total_months: totalMonthsNum,
            start_date: formStartDate,
        }, {
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
        });
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

        router.delete(`/installments/${deleteId}`, {
            preserveScroll: true,
            onFinish: () => {
                deleteId = null;
                if (showDetailsModal && selectedInstallment?.id === deleteId) {
                    closeDetailsModal();
                }
            },
        });
    }

    const tabs = [
        { key: 'active' as const, label: 'نشطة', count: activeCount },
        { key: 'completed' as const, label: 'منتهية', count: completedCount },
        { key: 'all' as const, label: 'الكل', count: installments.length },
    ];
</script>

<AppHead title="الأقساط" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
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
                        <p class="text-sm text-muted-foreground">الأقساط النشطة</p>
                        <Clock class="size-4 text-blue-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{activeCount}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">الالتزام الشهري</p>
                        <CalendarClock class="size-4 text-orange-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold text-destructive">{formatCurrency(totalMonthlyCommitment)}</p>
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
                class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-all {activeTab === tab.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
                onclick={() => (activeTab = tab.key)}
            >
                {tab.label}
                <span class="ms-1 text-xs text-muted-foreground">({tab.count})</span>
            </button>
        {/each}
    </div>

    <!-- Cards -->
    {#if filteredInstallments.length === 0}
        <Card>
            <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                <CreditCard class="size-12 text-muted-foreground" />
                <p class="mt-3 font-medium">لا توجد أقساط</p>
                <p class="text-sm text-muted-foreground">
                    {activeTab === 'active' ? 'لا توجد أقساط نشطة حالياً' : activeTab === 'completed' ? 'لا توجد أقساط منتهية' : 'أضف قسطاً جديداً للبدء'}
                </p>
                {#if activeTab === 'all'}
                    <Button size="sm" class="mt-4 gap-1.5" onclick={openAddModal}>
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
                                <span class="flex size-10 items-center justify-center rounded-lg bg-muted text-xl">
                                    {item.icon}
                                </span>
                                <div>
                                    <CardTitle class="text-base">{item.name}</CardTitle>
                                    <CardDescription>{item.reason}</CardDescription>
                                </div>
                            </div>
                            {#if !item.is_completed}
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    <Clock class="size-2.5" /> نشط
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                                    <CheckCircle2 class="size-2.5" /> منتهي
                                </span>
                            {/if}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">القسط الشهري</span>
                                <span class="text-sm font-bold tabular-nums">{formatCurrency(item.monthly_amount)}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">المتبقي</span>
                                <span class="text-sm tabular-nums">
                                    {#if remaining > 0}
                                        {remaining} أشهر
                                    {:else}
                                        <span class="text-green-600 dark:text-green-400">مكتمل</span>
                                    {/if}
                                </span>
                            </div>
                            {#if !item.is_completed}
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">المبلغ المتبقي</span>
                                    <span class="text-sm font-bold tabular-nums text-destructive">{formatCurrency(remAmount)}</span>
                                </div>
                                <div class="relative h-2 w-full overflow-hidden rounded-full bg-secondary">
                                    <div
                                        class="absolute inset-y-0 h-full rounded-full bg-primary transition-all"
                                        style="inset-inline-start: 0; width: {Math.round((item.paid_months / item.total_months) * 100)}%"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-xs text-muted-foreground">
                                    <span>المدفوع: {formatCurrency(paidAmount(item))}</span>
                                    <span>{Math.round((item.paid_months / item.total_months) * 100)}%</span>
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
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center"
        onclick={(e) => { if (e.target === e.currentTarget) closeDetailsModal(); }}
        onkeydown={(e) => { if (e.key === 'Escape') closeDetailsModal(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeDetailsModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-lg rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <span class="text-xl">{item.icon}</span>
                    {item.name}
                </h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeDetailsModal}>
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
                                    <span class="text-green-600 dark:text-green-400">نشط</span>
                                {:else}
                                    <span class="text-muted-foreground">منتهي</span>
                                {/if}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">القسط الشهري</span>
                            <p class="font-bold tabular-nums">{formatCurrency(item.monthly_amount)}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">المبلغ الإجمالي</span>
                            <p class="font-bold tabular-nums">{formatCurrency(item.total_amount)}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">المدفوع</span>
                            <p class="font-medium tabular-nums">{item.paid_months} / {item.total_months} شهر</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">المتبقي</span>
                            <p class="font-medium tabular-nums">
                                {#if remainingMonths(item) > 0}
                                    {remainingMonths(item)} أشهر · {formatCurrency(remainingAmount(item))}
                                {:else}
                                    <span class="text-green-600 dark:text-green-400">مكتمل</span>
                                {/if}
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">تاريخ البداية</span>
                            <p class="font-medium tabular-nums">{formatDate(item.start_date)}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">المدفوع حتى الآن</span>
                            <p class="font-bold tabular-nums text-green-600 dark:text-green-400">{formatCurrency(paidAmount(item))}</p>
                        </div>
                    </div>
                </div>

                {#if !item.is_completed}
                    <div class="relative h-3 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="absolute inset-y-0 h-full rounded-full bg-primary transition-all"
                            style="inset-inline-start: 0; width: {Math.round((item.paid_months / item.total_months) * 100)}%"
                        ></div>
                    </div>
                    <div class="flex justify-between text-sm text-muted-foreground">
                        <span>{Math.round((item.paid_months / item.total_months) * 100)}% مكتمل</span>
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
                    <Button variant="outline" onclick={closeDetailsModal}>إغلاق</Button>
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- Add Modal -->
{#if showFormModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pt-[10vh]"
        onclick={(e) => { if (e.target === e.currentTarget) closeFormModal(); }}
        onkeydown={(e) => { if (e.key === 'Escape') closeFormModal(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeFormModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">إضافة قسط جديد</h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeFormModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label for="inst-name" class="mb-1.5 block text-sm font-medium">اسم القسط</label>
                    <input
                        id="inst-name"
                        type="text"
                        placeholder="مثال: تقسيط سيارة"
                        bind:value={formName}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.name}
                        <p class="mt-1 text-xs text-destructive">{formErrors.name}</p>
                    {/if}
                </div>
                <div>
                    <label for="inst-reason" class="mb-1.5 block text-sm font-medium">السبب / الوصف</label>
                    <input
                        id="inst-reason"
                        type="text"
                        placeholder="مثال: سيارة تويوتا 2025"
                        bind:value={formReason}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div>
                    <label for="inst-icon" class="mb-1.5 block text-sm font-medium">الرمز</label>
                    <input
                        id="inst-icon"
                        type="text"
                        placeholder="📦"
                        bind:value={formIcon}
                        maxlength="2"
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div>
                    <label for="inst-monthly" class="mb-1.5 block text-sm font-medium">القسط الشهري (ر.س)</label>
                    <input
                        id="inst-monthly"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={formMonthly}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring text-end direction-ltr"
                    />
                    {#if formErrors.monthly}
                        <p class="mt-1 text-xs text-destructive">{formErrors.monthly}</p>
                    {/if}
                </div>
                <div>
                    <label for="inst-months" class="mb-1.5 block text-sm font-medium">عدد الأشهر</label>
                    <input
                        id="inst-months"
                        type="number"
                        min="1"
                        placeholder="12"
                        bind:value={formTotalMonths}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.totalMonths}
                        <p class="mt-1 text-xs text-destructive">{formErrors.totalMonths}</p>
                    {/if}
                </div>
                <div>
                    <label for="inst-start" class="mb-1.5 block text-sm font-medium">تاريخ البداية</label>
                    <input
                        id="inst-start"
                        type="month"
                        bind:value={formStartDate}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.startDate}
                        <p class="mt-1 text-xs text-destructive">{formErrors.startDate}</p>
                    {/if}
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeFormModal}>إلغاء</Button>
                <Button onclick={submitForm} disabled={submitting}>
                    {submitting ? 'جاري الإضافة...' : 'إضافة'}
                </Button>
            </div>
        </div>
    </div>
{/if}

<!-- Delete confirmation -->
{#if deleteId !== null}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center"
        onclick={(e) => { if (e.target === e.currentTarget) cancelDelete(); }}
        onkeydown={(e) => { if (e.key === 'Escape') cancelDelete(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={cancelDelete}></div>
        <div class="relative z-10 mx-4 w-full max-w-sm rounded-xl border bg-card p-6 shadow-lg">
            <h2 class="text-lg font-semibold">تأكيد الحذف</h2>
            <p class="mt-2 text-sm text-muted-foreground">هل أنت متأكد من حذف هذا القسط؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" onclick={cancelDelete}>إلغاء</Button>
                <Button variant="destructive" onclick={executeDelete}>حذف</Button>
            </div>
        </div>
    </div>
{/if}
