<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الادخار', href: '/savings' },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Wallet from 'lucide-svelte/icons/wallet';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

    interface GoalItem {
        id: number;
        name: string;
        icon: string;
        target_amount: number;
        current_amount: number;
        target_date: string | null;
        is_completed: boolean;
    }

    interface SavingsStats {
        total_saved: number;
        monthly_income: number;
        savings_rate: number;
    }

    let {
        goals = [],
        stats = { total_saved: 0, monthly_income: 0, savings_rate: 0 },
    }: {
        goals?: GoalItem[];
        stats?: SavingsStats;
    } = $props();

    const totalSavings = $derived(stats.total_saved);
    const savingsRate = $derived(stats.savings_rate);
    const totalTarget = $derived(goals.reduce((sum, g) => sum + g.target_amount, 0));
    const overallPct = $derived(
        totalTarget > 0 ? Math.min(100, Math.round((totalSavings / totalTarget) * 100)) : 0
    );

    function displayAmount(halalas: number): string {
        return (halalas / 100).toLocaleString('ar-SA') + ' ر.س';
    }

    function displayAmountShort(halalas: number): string {
        return (halalas / 100).toLocaleString('ar-SA');
    }

    function formatDate(dateStr: string | null): string {
        if (!dateStr) return '—';
        const dt = new Date(dateStr);
        if (isNaN(dt.getTime())) return '—';
        return dt.toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function getProgressColorClass(pct: number): string {
        if (pct > 90) return 'bg-destructive';
        if (pct >= 70) return 'bg-amber-500';
        return 'bg-emerald-500';
    }

    function getProgressTextClass(pct: number): string {
        if (pct > 90) return 'text-destructive';
        if (pct >= 70) return 'text-amber-600 dark:text-amber-400';
        return 'text-emerald-600 dark:text-emerald-400';
    }

    // Add goal modal
    let showFormModal = $state(false);
    let formName = $state('');
    let formIcon = $state('💰');
    let formTargetAmount = $state('');
    let formTargetDate = $state('');
    let formErrors = $state<Record<string, string>>({});
    let submitting = $state(false);

    function openAddModal() {
        formName = '';
        formIcon = '💰';
        formTargetAmount = '';
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
        const targetSar = parseFloat(formTargetAmount);

        if (!formName.trim()) {
            formErrors.name = 'اسم الهدف مطلوب';
            return;
        }
        if (!targetSar || targetSar <= 0) {
            formErrors.targetAmount = 'المبلغ المستهدف مطلوب';
            return;
        }

        submitting = true;

        router.post('/savings', {
            name: formName.trim(),
            icon: formIcon,
            target_amount: targetSar,
            target_date: formTargetDate || null,
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

    // Add amount modal
    let showAddAmountModal = $state(false);
    let selectedGoalId = $state<number | null>(null);
    let selectedGoalName = $state('');
    let addAmountValue = $state('');
    let addAmountErrors = $state<Record<string, string>>({});

    function openAddAmountModal(goal: GoalItem) {
        selectedGoalId = goal.id;
        selectedGoalName = goal.name;
        addAmountValue = '';
        addAmountErrors = {};
        showAddAmountModal = true;
    }

    function closeAddAmountModal() {
        showAddAmountModal = false;
        selectedGoalId = null;
        selectedGoalName = '';
        addAmountErrors = {};
    }

    function submitAddAmount() {
        addAmountErrors = {};
        const amountSar = parseFloat(addAmountValue);

        if (!amountSar || amountSar <= 0) {
            addAmountErrors.amount = 'المبلغ مطلوب';
            return;
        }

        submitting = true;

        router.put(`/savings/${selectedGoalId}`, {
            amount: amountSar,
        }, {
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

        router.delete(`/savings/${deleteId}`, {
            preserveScroll: true,
            onFinish: () => {
                deleteId = null;
            },
        });
    }

    // Complete
    function completeGoal(id: number) {
        router.put(`/savings/${id}/complete`, {}, {
            preserveScroll: true,
        });
    }
</script>

<AppHead title="الادخار" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">الادخار</h1>
            <p class="text-muted-foreground">أهدافك الادخارية</p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة هدف جديد
        </Button>
    </div>

    <!-- Stats bar -->
    {#if goals.length > 0}
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">إجمالي المدخرات</p>
                        <Wallet class="size-4 text-emerald-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold text-emerald-600 dark:text-emerald-400">{displayAmount(totalSavings)}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">معدل الادخار</p>
                        <TrendingUp class="size-4 text-blue-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{savingsRate}%</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">الأهداف</p>
                        <CheckCircle2 class="size-4 text-amber-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{goals.filter((g) => g.is_completed).length} / {goals.length}</p>
                </CardContent>
            </Card>
        </div>
    {/if}

    <!-- Big green banner -->
    {#if goals.length > 0}
        <Card class="border-emerald-500/30 bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-950/40 dark:to-emerald-900/20">
            <CardContent class="p-6 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">إجمالي المدخرات</p>
                        <p class="mt-1 text-4xl font-bold text-emerald-900 dark:text-emerald-100">{displayAmount(totalSavings)}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-200 dark:bg-emerald-800 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">
                                <TrendingUp class="size-3" />
                                {savingsRate}% من الدخل
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <div class="relative flex h-24 w-24 items-center justify-center">
                            <svg viewBox="0 0 100 100" class="h-24 w-24 -rotate-90">
                                <circle
                                    cx="50" cy="50" r="40"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="8"
                                    class="text-emerald-200 dark:text-emerald-800"
                                />
                                <circle
                                    cx="50" cy="50" r="40"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="8"
                                    stroke-linecap="round"
                                    class="text-emerald-600 dark:text-emerald-400 transition-all duration-1000"
                                    stroke-dasharray={`${overallPct * 2.51} 251`}
                                />
                            </svg>
                            <span class="absolute text-lg font-bold text-emerald-800 dark:text-emerald-200">{overallPct}%</span>
                        </div>
                        <span class="text-xs text-emerald-600 dark:text-emerald-400">من إجمالي الأهداف</span>
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
                {@const pct = goal.target_amount > 0 ? Math.round((goal.current_amount / goal.target_amount) * 100) : 0}
                {@const remaining = goal.target_amount - goal.current_amount}
                <Card class="overflow-hidden transition-all hover:shadow-md {goal.is_completed ? 'opacity-70' : ''}">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{goal.icon}</span>
                                <div>
                                    <CardTitle class="text-base">{goal.name}</CardTitle>
                                    {#if goal.target_date}
                                        <p class="text-xs text-muted-foreground">{formatDate(goal.target_date)}</p>
                                    {/if}
                                </div>
                            </div>
                            {#if goal.is_completed}
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 shrink-0">
                                    <CheckCircle2 class="size-2.5" />
                                    مكتمل
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 shrink-0">
                                    قيد الادخار
                                </span>
                            {/if}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div>
                                <div class="relative h-3 w-full overflow-hidden rounded-full bg-secondary">
                                    <div
                                        class="absolute inset-y-0 h-full rounded-full transition-all duration-500 {getProgressColorClass(pct)}"
                                        style="left: 0; width: {Math.min(pct, 100)}%"
                                    ></div>
                                </div>
                                <div class="mt-1.5 flex items-center justify-between text-sm">
                                    <span class="{getProgressTextClass(pct)} font-medium tabular-nums">{pct}%</span>
                                    <span class="text-muted-foreground tabular-nums">
                                        {#if remaining > 0}
                                            متبقي: {displayAmountShort(remaining)} ر.س
                                        {:else}
                                            مكتمل
                                        {/if}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-muted/50 p-3 text-sm">
                                <div class="flex flex-col">
                                    <span class="text-xs text-muted-foreground">المبلغ المستهدف</span>
                                    <span class="font-bold tabular-nums">{displayAmountShort(goal.target_amount)} ر.س</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-muted-foreground">المدخر حالياً</span>
                                    <span class="font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{displayAmountShort(goal.current_amount)} ر.س</span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                {#if !goal.is_completed}
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
                                    class="text-destructive hover:text-destructive {goal.is_completed ? 'ml-auto' : ''}"
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
            <Card class="border-2 border-dashed border-border hover:border-primary/50 hover:bg-muted/30 transition-colors cursor-pointer" onclick={openAddModal}>
                <CardContent class="flex items-center justify-center py-10">
                    <div class="text-center">
                        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-muted">
                            <Plus class="size-6 text-muted-foreground" />
                        </div>
                        <p class="text-sm font-medium">إضافة هدف جديد</p>
                        <p class="mt-1 text-xs text-muted-foreground">حدد هدفاً ادخارياً جديداً</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    {#if goals.length === 0}
        <Card>
            <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                <Wallet class="size-12 text-muted-foreground" />
                <p class="mt-3 font-medium">لا توجد أهداف ادخارية</p>
                <p class="text-sm text-muted-foreground">أضف هدفاً ادخارياً للبدء</p>
                <Button size="sm" class="mt-4 gap-1.5" onclick={openAddModal}>
                    <Plus class="size-3.5" />
                    إضافة هدف جديد
                </Button>
            </CardContent>
        </Card>
    {/if}
</div>

<!-- Add Goal Modal -->
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
                <h2 class="text-lg font-semibold">إضافة هدف ادخاري جديد</h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeFormModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label for="goal-name" class="mb-1.5 block text-sm font-medium">الاسم</label>
                    <input
                        id="goal-name"
                        type="text"
                        placeholder="مثال: سيارة جديدة"
                        bind:value={formName}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.name}
                        <p class="mt-1 text-xs text-destructive">{formErrors.name}</p>
                    {/if}
                </div>
                <div>
                    <label for="goal-icon" class="mb-1.5 block text-sm font-medium">الرمز</label>
                    <input
                        id="goal-icon"
                        type="text"
                        placeholder="💰"
                        bind:value={formIcon}
                        maxlength="2"
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div>
                    <label for="goal-amount" class="mb-1.5 block text-sm font-medium">المبلغ المستهدف (ر.س)</label>
                    <input
                        id="goal-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={formTargetAmount}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring text-left direction-ltr"
                    />
                    {#if formErrors.targetAmount}
                        <p class="mt-1 text-xs text-destructive">{formErrors.targetAmount}</p>
                    {/if}
                </div>
                <div>
                    <label for="goal-date" class="mb-1.5 block text-sm font-medium">التاريخ المستهدف (اختياري)</label>
                    <input
                        id="goal-date"
                        type="date"
                        bind:value={formTargetDate}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
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

<!-- Add Amount Modal -->
{#if showAddAmountModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center"
        onclick={(e) => { if (e.target === e.currentTarget) closeAddAmountModal(); }}
        onkeydown={(e) => { if (e.key === 'Escape') closeAddAmountModal(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeAddAmountModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-sm rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">إضافة مبلغ - {selectedGoalName}</h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeAddAmountModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label for="add-amount" class="mb-1.5 block text-sm font-medium">المبلغ المضاف (ر.س)</label>
                    <input
                        id="add-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={addAmountValue}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring text-left direction-ltr"
                    />
                    {#if addAmountErrors.amount}
                        <p class="mt-1 text-xs text-destructive">{addAmountErrors.amount}</p>
                    {/if}
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeAddAmountModal}>إلغاء</Button>
                <Button onclick={submitAddAmount} disabled={submitting}>
                    {submitting ? 'جاري...' : 'إضافة'}
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
            <p class="mt-2 text-sm text-muted-foreground">هل أنت متأكد من حذف هذا الهدف؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" onclick={cancelDelete}>إلغاء</Button>
                <Button variant="destructive" onclick={executeDelete}>حذف</Button>
            </div>
        </div>
    </div>
{/if}
