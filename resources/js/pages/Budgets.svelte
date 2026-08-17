<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الميزانيات', href: '/budgets' },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import ChevronUp from 'lucide-svelte/icons/chevron-up';
    import Plus from 'lucide-svelte/icons/plus';
    import Wallet from 'lucide-svelte/icons/wallet';
    import X from 'lucide-svelte/icons/x';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';

    interface BudgetRecord {
        id: number | null;
        name: string;
        icon: string;
        color: string;
        budget: number;
        spent: number;
        rollover: number;
        alert_percentage: number;
    }

    interface BudgetStats {
        totalBudget: number;
        totalSpent: number;
        remaining: number;
        rollover: number;
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
        return (halalas / 100).toLocaleString('ar-SA', { maximumFractionDigits: 2 }) + ' ر.س';
    }

    const usagePct = $derived(
        stats.totalBudget > 0 ? Math.round((stats.totalSpent / stats.totalBudget) * 100) : 0
    );

    function getProgressColor(pct: number): string {
        if (pct > 90) return 'bg-destructive';
        if (pct >= 70) return 'bg-amber-500';
        return 'bg-emerald-500';
    }

    function getTextColor(pct: number): string {
        if (pct > 90) return 'text-destructive';
        if (pct >= 70) return 'text-amber-600 dark:text-amber-400';
        return 'text-emerald-600 dark:text-emerald-400';
    }

    function getBadgeColor(pct: number): string {
        if (pct > 90) return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        if (pct >= 70) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
    }

    function getBadgeText(pct: number): string {
        if (pct > 90) return 'تجاوز';
        if (pct >= 70) return 'اقترب';
        return 'آمن';
    }

    function getBadgeIcon(pct: number) {
        if (pct > 90) return AlertTriangle;
        return CircleCheck;
    }

    // Sort state
    let sortBy = $state<'name' | 'budget' | 'spent' | 'percentage'>('percentage');
    let sortDir = $state<'asc' | 'desc'>('desc');

    const sortedBudgets = $derived.by(() => {
        const items = [...budgets];
        const dir = sortDir === 'asc' ? 1 : -1;
        items.sort((a, b) => {
            const pctA = a.budget > 0 ? a.spent / a.budget : 0;
            const pctB = b.budget > 0 ? b.spent / b.budget : 0;
            switch (sortBy) {
                case 'name': return dir * a.name.localeCompare(b.name);
                case 'budget': return dir * (a.budget - b.budget);
                case 'spent': return dir * (a.spent - b.spent);
                default: return dir * (pctA - pctB);
            }
        });
        return items;
    });

    function toggleSort(field: typeof sortBy) {
        if (sortBy === field) { sortDir = sortDir === 'asc' ? 'desc' : 'asc'; }
        else { sortBy = field; sortDir = 'desc'; }
    }

    // Edit budget modal
    let editingBudget = $state<BudgetRecord | null>(null);
    let showBudgetModal = $state(false);
    let budgetAmount = $state('');
    let budgetErrors = $state<Record<string, string>>({});
    let budgetSubmitting = $state(false);

    function openBudgetModal(b: BudgetRecord) {
        editingBudget = b;
        budgetAmount = b.budget > 0 ? (b.budget / 100).toFixed(2) : '';
        budgetErrors = {};
        showBudgetModal = true;
    }

    function closeBudgetModal() {
        showBudgetModal = false;
        editingBudget = null;
        budgetAmount = '';
        budgetErrors = {};
    }

    function handleBudgetSave() {
        budgetErrors = {};
        budgetSubmitting = true;
        const amt = parseFloat(budgetAmount);
        if (!budgetAmount || amt < 0) {
            budgetErrors.amount = 'المبلغ مطلوب';
            budgetSubmitting = false;
            return;
        }
        const month = new Date().toISOString().slice(0, 7);

        router.post('/budgets', {
            amount: amt,
            category_id: editingBudget ? categories.find(c => c.name === editingBudget.name)?.id : null,
            month: month,
        } as any, {
            onFinish: () => { budgetSubmitting = false; closeBudgetModal(); },
        });
    }

    // Add category modal
    let showCatModal = $state(false);
    let catName = $state('');
    let catIcon = $state('📦');
    let catColor = $state('#6b7280');
    let catErrors = $state<Record<string, string>>({});
    let catSubmitting = $state(false);

    const colors = ['#ef4444','#f97316','#eab308','#22c55e','#14b8a6','#3b82f6','#6366f1','#a855f7','#ec4899','#6b7280'];
    const icons = ['🍔','🚗','🎮','⚡','💊','📚','📦','🏠','👕','💻','🎓','✈️','🎁','🐱'];

    function openCatModal() {
        catName = ''; catIcon = icons[0]; catColor = colors[0]; catErrors = {}; showCatModal = true;
    }
    function closeCatModal() { showCatModal = false; catName = ''; catErrors = {}; }

    function handleCatSave() {
        catErrors = {};
        if (!catName.trim()) { catErrors.name = 'الاسم مطلوب'; return; }
        catSubmitting = true;
        router.post('/categories', { name: catName.trim(), icon: catIcon, color: catColor } as any, {
            onFinish: () => { catSubmitting = false; closeCatModal(); },
        });
    }
</script>

<AppHead title="الميزانيات" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">الميزانية العامة</h1>
            <p class="text-muted-foreground">وزّع دخلك على الفئات وراقب إنفاقك</p>
        </div>
        <Button size="sm" class="gap-1.5" onclick={openCatModal}>
            <Plus class="size-3.5" />
            إضافة فئة
        </Button>
    </div>

    <!-- بطاقة ملخص الميزانية العامة -->
    <Card class="border-emerald-500/30 bg-gradient-to-l from-emerald-500/5 to-transparent">
        <CardContent class="p-6">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-muted-foreground">إجمالي الميزانية</p>
                    <p class="text-3xl font-bold">{displayAmount(stats.totalBudget)}</p>
                </div>
                <div class="flex flex-wrap gap-6">
                    <div>
                        <p class="text-xs text-muted-foreground">المنفق</p>
                        <p class="text-lg font-bold text-destructive">{displayAmount(stats.totalSpent)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">المتبقي</p>
                        <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{displayAmount(stats.remaining)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">نسبة الاستخدام</p>
                        <p class="text-lg font-bold {getTextColor(usagePct)}">{usagePct}%</p>
                    </div>
                </div>
            </div>
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-secondary">
                <div class="h-full rounded-full {getProgressColor(usagePct)}" style="width: {Math.min(usagePct, 100)}%"></div>
            </div>
        </CardContent>
    </Card>

    <!-- ترتيب -->
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-muted-foreground">ترتيب:</span>
        {#each [{ v: 'percentage' as const, l: 'نسبة الإنفاق' }, { v: 'name' as const, l: 'الاسم' }, { v: 'budget' as const, l: 'الميزانية' }, { v: 'spent' as const, l: 'المنفق' }] as opt}
            <button
                class="rounded-md px-2.5 py-1 text-xs transition-colors {sortBy === opt.v ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/80'}"
                onclick={() => toggleSort(opt.v)}
            >
                {opt.l} {sortBy === opt.v ? (sortDir === 'asc' ? '↑' : '↓') : ''}
            </button>
        {/each}
    </div>

    <!-- فئات الميزانية -->
    <div class="grid gap-4 sm:grid-cols-2">
        {#each sortedBudgets as b, i (b.id ?? `cat-${i}`)}
            {@const effective = b.budget + b.rollover}
            {@const pct = effective > 0 ? Math.round((b.spent / effective) * 100) : 0}
            {@const BadgeIcon = getBadgeIcon(pct)}
            <Card class={pct > 90 ? 'border-destructive/50' : pct >= 70 ? 'border-amber-500/50' : ''}>
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-full text-lg" style="background: {b.color}20; color: {b.color}">
                                {b.icon}
                            </span>
                            <div>
                                <CardTitle class="text-base">{b.name}</CardTitle>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium {getBadgeColor(pct)}">
                            <BadgeIcon class="size-2.5" />
                            {getBadgeText(pct)}
                        </span>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="mb-3 flex items-baseline justify-between">
                        <p class="text-xl font-bold">{displayAmount(b.spent)}</p>
                        <p class="text-sm text-muted-foreground">الميزانية: {displayAmount(b.budget)}</p>
                    </div>

                    <div class="relative h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full rounded-full transition-all"
                            style="width: {Math.min(pct, 100)}%; {pct > 90 ? '' : 'background: ' + b.color}"
                            class:bg-destructive={pct > 90}
                            class:bg-amber-500={pct >= 70 && pct <= 90}
                        ></div>
                    </div>

                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="tabular-nums {getTextColor(pct)}">{pct}% مستخدم</span>
                        <span class="tabular-nums text-muted-foreground">
                            متبقي {displayAmount(effective - b.spent)}
                        </span>
                    </div>

                    <div class="mt-3 flex gap-2 border-t pt-3">
                        <Button variant="outline" size="sm" class="flex-1 text-xs gap-1" onclick={() => openBudgetModal(b)}>
                            <Wallet class="size-3" />
                            تعديل الميزانية
                        </Button>
                        <a href="/expenses" class="inline-flex flex-1 items-center justify-center gap-1 rounded-md border border-border px-3 py-1.5 text-xs hover:bg-muted transition-colors cursor-pointer no-underline text-foreground">
                            <ArrowRightLeft class="size-3" />
                            عرض المصاريف
                        </a>
                    </div>
                </CardContent>
            </Card>
        {/each}
    </div>

    <!-- إضافة فئة جديدة -->
    <Card class="cursor-pointer border-dashed hover:border-primary/50 transition-colors" onclick={openCatModal}>
        <CardContent class="flex items-center justify-center py-8">
            <div class="text-center">
                <div class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-muted">
                    <Plus class="size-6 text-muted-foreground" />
                </div>
                <p class="text-sm font-medium">إضافة فئة مخصصة</p>
                <p class="text-xs text-muted-foreground">أنشئ فئة جديدة مع أيقونة ولون</p>
            </div>
        </CardContent>
    </Card>

    <!-- مودال إضافة فئة -->
    {#if showCatModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <button type="button" class="fixed inset-0 bg-black/50 cursor-default" onclick={closeCatModal} aria-label="إغلاق"></button>
            <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h2 class="text-lg font-semibold">إضافة فئة جديدة</h2>
                    <button class="cursor-pointer text-muted-foreground hover:text-foreground" onclick={closeCatModal} aria-label="إغلاق"><X class="size-5" /></button>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">اسم الفئة</label>
                        <input type="text" bind:value={catName} class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" placeholder="مثال: سكن، ملابس..." />
                        {#if catErrors.name}<p class="mt-1 text-xs text-destructive">{catErrors.name}</p>{/if}
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">الأيقونة</label>
                        <div class="flex flex-wrap gap-2">
                            {#each icons as ico}
                                <button
                                    class="flex size-9 items-center justify-center rounded-lg border text-lg transition-all {catIcon === ico ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:border-primary/50'}"
                                    onclick={() => (catIcon = ico)}
                                >{ico}</button>
                            {/each}
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">اللون</label>
                        <div class="flex flex-wrap gap-2">
                            {#each colors as clr}
                                <button
                                    class="size-8 rounded-full border-2 transition-all {catColor === clr ? 'border-primary ring-2 ring-primary/20 scale-110' : 'border-transparent'}"
                                    style="background: {clr}"
                                    onclick={() => (catColor = clr)}
                                ></button>
                            {/each}
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t px-6 py-4">
                    <Button variant="outline" size="sm" onclick={closeCatModal}>إلغاء</Button>
                    <Button size="sm" disabled={catSubmitting} onclick={handleCatSave}>
                        {catSubmitting ? 'جاري الحفظ...' : 'حفظ'}
                    </Button>
                </div>
            </div>
        </div>
    {/if}

    <!-- مودال تعديل الميزانية -->
    {#if showBudgetModal && editingBudget}
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <button type="button" class="fixed inset-0 bg-black/50 cursor-default" onclick={closeBudgetModal} aria-label="إغلاق"></button>
            <div class="relative z-10 mx-4 w-full max-w-sm rounded-xl border bg-card p-0 shadow-lg">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">{editingBudget.icon}</span>
                        <h2 class="text-lg font-semibold">{editingBudget.name}</h2>
                    </div>
                    <button class="cursor-pointer text-muted-foreground hover:text-foreground" onclick={closeBudgetModal} aria-label="إغلاق"><X class="size-5" /></button>
                </div>
                <div class="space-y-3 px-6 py-4">
                    <p class="text-sm text-muted-foreground">المصروف هذا الشهر: <span class="font-bold text-foreground">{displayAmount(editingBudget.spent)}</span></p>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">الميزانية الشهرية (ر.س)</label>
                        <input type="number" step="0.01" bind:value={budgetAmount} class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" placeholder="0.00" />
                        {#if budgetErrors.amount}<p class="mt-1 text-xs text-destructive">{budgetErrors.amount}</p>{/if}
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t px-6 py-4">
                    <Button variant="outline" size="sm" onclick={closeBudgetModal}>إلغاء</Button>
                    <Button size="sm" disabled={budgetSubmitting} onclick={handleBudgetSave}>
                        {budgetSubmitting ? 'جاري الحفظ...' : 'حفظ'}
                    </Button>
                </div>
            </div>
        </div>
    {/if}
</div>
