<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الميزانيات', href: '/budgets' },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import Button from '@/components/ui/button/Button.svelte';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import Wallet from 'lucide-svelte/icons/wallet';
    import Pencil from 'lucide-svelte/icons/pencil';
    import LoaderCircle from 'lucide-svelte/icons/loader-circle';
    import Tag from 'lucide-svelte/icons/tag';

    interface BudgetRecord {
        id: number;
        name: string;
        icon: string;
        color: string;
        budget: number; // halalas
        spent: number; // halalas
        rollover: number; // halalas
        alert_percentage: number;
    }

    interface BudgetStats {
        totalBudget: number; // halalas
        totalSpent: number; // halalas
        remaining: number; // halalas
        rollover: number; // halalas
    }

    interface CategoryOption {
        id: number;
        name: string;
    }

    let {
        budgets = [] as BudgetRecord[],
        stats = { totalBudget: 0, totalSpent: 0, remaining: 0, rollover: 0 } as BudgetStats,
        categories = [] as CategoryOption[],
    }: {
        budgets?: BudgetRecord[];
        stats?: BudgetStats;
        categories?: CategoryOption[];
    } = $props();

    function displayAmount(halalas: number): string {
        return (halalas / 100).toLocaleString('ar-SA') + ' ر.س';
    }

    function getProgressColor(b: BudgetRecord): string {
        if (b.spent > b.budget + b.rollover) return 'bg-destructive';
        if ((b.budget + b.rollover) > 0 && (b.spent / (b.budget + b.rollover)) * 100 >= b.alert_percentage) return 'bg-yellow-500';
        return '';
    }

    function getStatus(b: BudgetRecord): 'over' | 'warning' | 'safe' {
        const effective = b.budget + b.rollover;
        if (effective <= 0) return 'safe';
        const pct = (b.spent / effective) * 100;
        if (pct >= 100) return 'over';
        if (pct >= b.alert_percentage) return 'warning';
        return 'safe';
    }

    // Budget form modal
    let showBudgetModal = $state(false);
    let editingBudgetId = $state<number | null>(null);
    let budgetFormCategoryId = $state('');
    let budgetFormAmount = $state('');
    let budgetFormAlertPct = $state('80');
    let budgetFormErrors = $state<Record<string, string>>({});
    let budgetFormSubmitting = $state(false);

    function openAddBudget() {
        editingBudgetId = null;
        budgetFormCategoryId = '';
        budgetFormAmount = '';
        budgetFormAlertPct = '80';
        budgetFormErrors = {};
        showBudgetModal = true;
    }

    function openEditBudget(b: BudgetRecord) {
        editingBudgetId = b.id;
        budgetFormCategoryId = '';
        budgetFormAmount = String(b.budget / 100);
        budgetFormAlertPct = String(b.alert_percentage);
        budgetFormErrors = {};
        showBudgetModal = true;
    }

    function closeBudgetModal() {
        showBudgetModal = false;
        editingBudgetId = null;
        budgetFormErrors = {};
    }

    function submitBudget() {
        budgetFormErrors = {};
        const amountSar = parseFloat(budgetFormAmount);
        if (!editingBudgetId && !budgetFormCategoryId) {
            budgetFormErrors.category_id = 'اختر فئة';
            return;
        }
        if (!amountSar || amountSar <= 0) {
            budgetFormErrors.amount = 'المبلغ مطلوب';
            return;
        }

        budgetFormSubmitting = true;
        const data: Record<string, string | number> = {
            amount: amountSar,
            alert_percentage: parseInt(budgetFormAlertPct) || 80,
        };
        if (!editingBudgetId) {
            data.category_id = parseInt(budgetFormCategoryId);
        }

        if (editingBudgetId) {
            router.put(`/budgets/${editingBudgetId}`, data, {
                onSuccess: () => {
                    closeBudgetModal();
                    router.reload({ only: ['budgets', 'stats'] });
                },
                onError: (err) => {
                    budgetFormErrors = err as Record<string, string>;
                },
                onFinish: () => {
                    budgetFormSubmitting = false;
                },
            });
        } else {
            router.post('/budgets', data, {
                onSuccess: () => {
                    closeBudgetModal();
                    router.reload({ only: ['budgets', 'stats'] });
                },
                onError: (err) => {
                    budgetFormErrors = err as Record<string, string>;
                },
                onFinish: () => {
                    budgetFormSubmitting = false;
                },
            });
        }
    }

    // Category form modal
    let showCategoryModal = $state(false);
    let catFormName = $state('');
    let catFormIcon = $state('');
    let catFormColor = $state('#3b82f6');
    let catFormErrors = $state<Record<string, string>>({});
    let catFormSubmitting = $state(false);

    const colorOptions = [
        '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
        '#3b82f6', '#6366f1', '#a855f7', '#ec4899', '#6b7280',
    ];

    const iconOptions = ['🍔', '🚗', '🎮', '⚡', '💊', '📚', '📦', '🏠', '👕', '💻', '🎓', '✈️'];

    function openAddCategory() {
        catFormName = '';
        catFormIcon = iconOptions[0];
        catFormColor = colorOptions[0];
        catFormErrors = {};
        showCategoryModal = true;
    }

    function closeCategoryModal() {
        showCategoryModal = false;
        catFormErrors = {};
    }

    function submitCategory() {
        catFormErrors = {};
        if (!catFormName.trim()) {
            catFormErrors.name = 'اسم الفئة مطلوب';
            return;
        }

        catFormSubmitting = true;
        router.post('/categories', {
            name: catFormName.trim(),
            icon: catFormIcon,
            color: catFormColor,
        }, {
            onSuccess: () => {
                closeCategoryModal();
                router.reload({ only: ['budgets', 'categories'] });
            },
            onError: (err) => {
                catFormErrors = err as Record<string, string>;
            },
            onFinish: () => {
                catFormSubmitting = false;
            },
        });
    }

    // Delete budget
    let deleteId = $state<number | null>(null);
    let deleteSubmitting = $state(false);

    function confirmDelete(id: number) {
        deleteId = id;
    }

    function cancelDelete() {
        deleteId = null;
    }

    function executeDelete() {
        if (!deleteId) return;
        deleteSubmitting = true;
        router.delete(`/budgets/${deleteId}`, {
            onSuccess: () => {
                deleteId = null;
                router.reload({ only: ['budgets', 'stats'] });
            },
            onFinish: () => {
                deleteSubmitting = false;
            },
        });
    }

    // Search
    let search = $state('');

    const filteredBudgets = $derived.by(() => {
        if (!search) return budgets;
        const q = search.toLowerCase();
        return budgets.filter((b) => b.name.toLowerCase().includes(q));
    });
</script>

<AppHead title="الميزانيات" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">الميزانيات</h1>
            <p class="text-muted-foreground">حدد ميزانية لكل فئة وراقب إنفاقك</p>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" size="sm" class="gap-1.5" onclick={openAddCategory}>
                <Tag class="size-3.5" />
                إضافة فئة
            </Button>
            <Button size="sm" class="gap-1.5" onclick={openAddBudget}>
                <Plus class="size-3.5" />
                إضافة ميزانية
            </Button>
        </div>
    </div>

    <!-- Summary -->
    <div class="grid gap-4 sm:grid-cols-4">
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">إجمالي الميزانية</p>
                <p class="text-xl font-bold">{displayAmount(stats.totalBudget)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">المنفق</p>
                <p class="text-xl font-bold text-destructive">{displayAmount(stats.totalSpent)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">المتبقي</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400">{displayAmount(stats.remaining)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">فائض مرّحل</p>
                <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{displayAmount(stats.rollover)}</p>
            </CardContent>
        </Card>
    </div>

    <!-- Search -->
    <Card>
        <CardContent class="pt-6">
            <div class="relative">
                <input
                    type="text"
                    placeholder="ابحث عن فئة..."
                    bind:value={search}
                    class="w-full rounded-lg border border-border bg-background pe-9 ps-9 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                {#if search}
                    <button class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer" onclick={() => (search = '')}>
                        <X class="size-3.5" />
                    </button>
                {/if}
            </div>
        </CardContent>
    </Card>

    <!-- Budget grid -->
    {#if filteredBudgets.length === 0}
        <Card>
            <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                <Wallet class="size-12 text-muted-foreground" />
                <p class="mt-3 font-medium">لا توجد ميزانيات</p>
                <p class="text-sm text-muted-foreground">{search ? 'لا توجد نتائج مطابقة للبحث' : 'أضف ميزانية للبدء'}</p>
                {#if !search}
                    <Button size="sm" class="mt-4 gap-1.5" onclick={openAddBudget}>
                        <Plus class="size-3.5" />
                        إضافة ميزانية
                    </Button>
                {/if}
            </CardContent>
        </Card>
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each filteredBudgets as b}
                {@const status = getStatus(b)}
                {@const effectiveTotal = b.budget + b.rollover}
                {@const pct = effectiveTotal > 0 ? Math.round((b.spent / effectiveTotal) * 100) : 0}
                <Card class="overflow-hidden transition-all {status === 'over' ? 'border-destructive/50 shadow-destructive/10 shadow-sm' : ''} {status === 'warning' ? 'border-yellow-500/50' : ''}">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">{b.icon}</span>
                                <CardTitle class="text-base">{b.name}</CardTitle>
                            </div>
                            <div class="flex items-center gap-2">
                                {#if status === 'over'}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        <AlertTriangle class="size-2.5" /> تجاوز
                                    </span>
                                {:else if status === 'warning'}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-[10px] font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        <AlertTriangle class="size-2.5" /> اقترب
                                    </span>
                                {:else}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        <CircleCheck class="size-2.5" /> آمن
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-3 flex items-baseline justify-between">
                            <p class="text-2xl font-bold tabular-nums">{displayAmount(b.spent)}</p>
                            <div class="text-right">
                                <p class="text-xs text-muted-foreground">الميزانية: {displayAmount(b.budget)}</p>
                                {#if b.rollover > 0}
                                    <p class="text-xs text-blue-600 dark:text-blue-400">
                                        +{displayAmount(b.rollover)} فائض مرّحل
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <!-- Progress bar -->
                        <div class="relative h-3 w-full overflow-hidden rounded-full bg-secondary">
                            <div
                                class="absolute inset-y-0 h-full rounded-full transition-all {getProgressColor(b)}"
                                style="left: 0; width: {Math.min(pct, 100)}%; {status === 'safe' ? 'background:' + b.color : ''}"
                            ></div>
                            <div class="absolute inset-y-0 w-0.5 bg-background/50" style="left: {b.alert_percentage}%"></div>
                            {#if b.rollover > 0}
                                <div class="absolute inset-y-0 w-0.5 bg-blue-400" style="left: {Math.round((b.budget / effectiveTotal) * 100)}%"></div>
                            {/if}
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="{status === 'over' ? 'text-destructive font-medium' : 'text-muted-foreground'} tabular-nums">
                                {pct}% مستخدم
                            </span>
                            <span class="text-muted-foreground tabular-nums">
                                {#if status === 'over'}
                                    تجاوز بـ {displayAmount(b.spent - effectiveTotal)}
                                {:else}
                                    متبقي {displayAmount(effectiveTotal - b.spent)}
                                {/if}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="mt-3 flex gap-2 border-t pt-3">
                            <Button variant="outline" size="sm" class="flex-1 text-xs gap-1" onclick={() => openEditBudget(b)}>
                                <Pencil class="size-3" />
                                تعديل
                            </Button>
                            <Button variant="ghost" size="sm" class="flex-1 text-xs gap-1 text-destructive hover:text-destructive" onclick={() => confirmDelete(b.id)}>
                                حذف
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}

    <!-- Add category card -->
    <Card class="border-dashed cursor-pointer hover:border-primary/50 hover:bg-muted/50 transition-colors" onclick={openAddCategory}>
        <CardContent class="flex items-center justify-center py-8">
            <div class="text-center">
                <div class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-muted">
                    <Plus class="size-6 text-muted-foreground" />
                </div>
                <p class="text-sm font-medium">إضافة فئة مخصصة</p>
                <p class="text-xs text-muted-foreground">أنشئ فئة جديدة مع أيقونة ولون خاصين بك</p>
            </div>
        </CardContent>
    </Card>
</div>

<!-- Budget Add/Edit Modal -->
{#if showBudgetModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pt-[10vh]"
        onclick={(e) => { if (e.target === e.currentTarget) closeBudgetModal(); }}
        onkeydown={(e) => { if (e.key === 'Escape') closeBudgetModal(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeBudgetModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">
                    {editingBudgetId ? 'تعديل الميزانية' : 'إضافة ميزانية جديدة'}
                </h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeBudgetModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                {#if !editingBudgetId}
                    <div>
                        <label for="budget-category" class="mb-1.5 block text-sm font-medium">الفئة</label>
                        <select
                            id="budget-category"
                            bind:value={budgetFormCategoryId}
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="">اختر فئة...</option>
                            {#each categories as cat}
                                <option value={cat.id}>{cat.name}</option>
                            {/each}
                        </select>
                        {#if budgetFormErrors.category_id}
                            <p class="mt-1 text-xs text-destructive">{budgetFormErrors.category_id}</p>
                        {/if}
                    </div>
                {/if}
                <div>
                    <label for="budget-amount" class="mb-1.5 block text-sm font-medium">المبلغ (ر.س)</label>
                    <input
                        id="budget-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={budgetFormAmount}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if budgetFormErrors.amount}
                        <p class="mt-1 text-xs text-destructive">{budgetFormErrors.amount}</p>
                    {/if}
                </div>
                <div>
                    <label for="budget-alert" class="mb-1.5 block text-sm font-medium">نسبة التنبيه (%)</label>
                    <input
                        id="budget-alert"
                        type="number"
                        min="1"
                        max="100"
                        bind:value={budgetFormAlertPct}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">يتم التنبيه عند تجاوز هذه النسبة من الميزانية</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeBudgetModal} disabled={budgetFormSubmitting}>إلغاء</Button>
                <Button onclick={submitBudget} disabled={budgetFormSubmitting}>
                    {#if budgetFormSubmitting}
                        <LoaderCircle class="size-4 animate-spin" />
                    {/if}
                    {editingBudgetId ? 'حفظ التعديلات' : 'إضافة'}
                </Button>
            </div>
        </div>
    </div>
{/if}

<!-- Category Add Modal -->
{#if showCategoryModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pt-[10vh]"
        onclick={(e) => { if (e.target === e.currentTarget) closeCategoryModal(); }}
        onkeydown={(e) => { if (e.key === 'Escape') closeCategoryModal(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeCategoryModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">إضافة فئة جديدة</h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeCategoryModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label for="cat-name" class="mb-1.5 block text-sm font-medium">اسم الفئة</label>
                    <input
                        id="cat-name"
                        type="text"
                        placeholder="مثال: طعام"
                        bind:value={catFormName}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if catFormErrors.name}
                        <p class="mt-1 text-xs text-destructive">{catFormErrors.name}</p>
                    {/if}
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">الأيقونة</label>
                    <div class="flex flex-wrap gap-2">
                        {#each iconOptions as icon}
                            <button
                                class="flex size-9 items-center justify-center rounded-lg border text-lg {catFormIcon === icon ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted'} cursor-pointer"
                                onclick={() => (catFormIcon = icon)}
                            >
                                {icon}
                            </button>
                        {/each}
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">اللون</label>
                    <div class="flex flex-wrap gap-2">
                        {#each colorOptions as color}
                            <button
                                class="size-8 rounded-full border-2 cursor-pointer transition-all {catFormColor === color ? 'border-foreground scale-110' : 'border-transparent'}"
                                style="background-color: {color}"
                                onclick={() => (catFormColor = color)}
                                title={color}
                            ></button>
                        {/each}
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeCategoryModal} disabled={catFormSubmitting}>إلغاء</Button>
                <Button onclick={submitCategory} disabled={catFormSubmitting}>
                    {#if catFormSubmitting}
                        <LoaderCircle class="size-4 animate-spin" />
                    {/if}
                    إضافة
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
            <p class="mt-2 text-sm text-muted-foreground">هل أنت متأكد من حذف هذه الميزانية؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" onclick={cancelDelete} disabled={deleteSubmitting}>إلغاء</Button>
                <Button variant="destructive" onclick={executeDelete} disabled={deleteSubmitting}>
                    {#if deleteSubmitting}
                        <LoaderCircle class="size-4 animate-spin" />
                    {/if}
                    حذف
                </Button>
            </div>
        </div>
    </div>
{/if}
