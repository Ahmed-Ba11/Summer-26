<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الميزانيات', href: '/budgets' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import Plus from 'lucide-svelte/icons/plus';
    import BudgetRow from '@/components/BudgetRow.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { ICON_LABELS, ICON_PICKER } from '@/lib/category-icons';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import Check from 'lucide-svelte/icons/check';
    import { formatCurrency } from '@/lib/format';
    import {
        store as storeBudget,
        update as updateBudget,
    } from '@/routes/budgets';
    import { store as storeCategory } from '@/routes/categories';
    import type { ValidationErrors } from '@/types';

    interface BudgetRecord {
        id: number | null;
        category_id: number;
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

    let {
        budgets = [] as BudgetRecord[],
        stats = {
            totalBudget: 0,
            totalSpent: 0,
            remaining: 0,
            rollover: 0,
        } as BudgetStats,
    }: {
        budgets?: BudgetRecord[];
        stats?: BudgetStats;
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

    const usagePct = $derived(
        stats.totalBudget > 0
            ? Math.round((stats.totalSpent / stats.totalBudget) * 100)
            : 0,
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

    // Sort state
    let sortBy = $state<'name' | 'budget' | 'spent' | 'percentage'>(
        'percentage',
    );
    let sortDir = $state<'asc' | 'desc'>('desc');

    const sortedBudgets = $derived.by(() => {
        const items = [...budgets];
        const dir = sortDir === 'asc' ? 1 : -1;
        items.sort((a, b) => {
            const pctA = a.budget > 0 ? a.spent / a.budget : 0;
            const pctB = b.budget > 0 ? b.spent / b.budget : 0;
            switch (sortBy) {
                case 'name':
                    return dir * a.name.localeCompare(b.name);
                case 'budget':
                    return dir * (a.budget - b.budget);
                case 'spent':
                    return dir * (a.spent - b.spent);
                default:
                    return dir * (pctA - pctB);
            }
        });
        return items;
    });

    function toggleSort(field: typeof sortBy) {
        if (sortBy === field) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortBy = field;
            sortDir = 'desc';
        }
    }

    // Edit budget modal
    let editingBudget = $state<BudgetRecord | null>(null);
    let showBudgetModal = $state(false);
    /** الميزانية بالهللات */
    let budgetAmount = $state(0);
    let budgetErrors = $state<Record<string, string>>({});
    let budgetSubmitting = $state(false);

    function openBudgetModal(b: BudgetRecord) {
        editingBudget = b;
        budgetAmount = b.budget;
        budgetErrors = {};
        showBudgetModal = true;
    }

    function closeBudgetModal() {
        showBudgetModal = false;
        editingBudget = null;
        budgetAmount = 0;
        budgetErrors = {};
    }

    function handleBudgetSave(halalas: number) {
        budgetErrors = {};
        budgetSubmitting = true;
        const amt = halalas / 100;
        if (amt <= 0) {
            budgetErrors.amount = 'المبلغ مطلوب';
            budgetSubmitting = false;
            return;
        }
        const month = new Date().toISOString().slice(0, 7);

        const request = editingBudget?.id
            ? updateBudget(editingBudget.id)
            : storeBudget();

        router.visit(request, {
            method: editingBudget?.id ? 'put' : 'post',
            data: {
                amount: amt,
                category_id: editingBudget?.category_id ?? null,
                month: month,
            },
            preserveScroll: true,
            onSuccess: () => closeBudgetModal(),
            onError: (errors) => {
                budgetErrors = errors as Record<string, string>;
            },
            onFinish: () => {
                budgetSubmitting = false;
            },
        });
    }

    // Add category modal
    let showCatModal = $state(false);
    let catName = $state('');
    let catIcon = $state('ellipsis');
    let catColor = $state('#6b7280');
    let catErrors = $state<Record<string, string>>({});
    let catSubmitting = $state(false);

    const colors = [
        '#ef4444',
        '#f97316',
        '#eab308',
        '#22c55e',
        '#14b8a6',
        '#3b82f6',
        '#6366f1',
        '#a855f7',
        '#ec4899',
        '#6b7280',
    ];

    function openCatModal() {
        catName = '';
        catIcon = 'ellipsis';
        catColor = colors[0];
        catErrors = {};
        showCatModal = true;
    }
    function closeCatModal() {
        showCatModal = false;
        catName = '';
        catErrors = {};
    }

    function handleCatSave() {
        catErrors = {};
        if (!catName.trim()) {
            catErrors.name = 'الاسم مطلوب';
            return;
        }
        catSubmitting = true;
        router.post(
            storeCategory(),
            { name: catName.trim(), icon: catIcon, color: catColor },
            {
                preserveScroll: true,
                onSuccess: () => closeCatModal(),
                onError: (errors) => {
                    catErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    catSubmitting = false;
                },
            },
        );
    }
</script>

<AppHead title="الميزانيات" />
<MobileHeader title="الميزانية العامة" subtitle="وزّع دخلك على الفئات وراقب إنفاقك" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="hidden items-center justify-between md:flex">
        <div>
            <h1 class="text-2xl font-bold">الميزانية العامة</h1>
            <p class="text-muted-foreground">
                وزّع دخلك على الفئات وراقب إنفاقك
            </p>
        </div>
        <Button size="sm" class="gap-1.5" onclick={openCatModal}>
            <Plus class="size-3.5" />
            إضافة فئة
        </Button>
    </div>

    <!-- بطاقة ملخص الميزانية العامة -->
    <Card class="border-emerald-500/30">
        <CardContent class="p-6">
            <div
                class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm text-muted-foreground">
                        إجمالي الميزانية
                    </p>
                    <p class="text-3xl font-bold">
                        {formatCurrency(stats.totalBudget)}
                    </p>
                </div>
                <div class="flex flex-wrap gap-6">
                    <div>
                        <p class="text-xs text-muted-foreground">المنفق</p>
                        <p class="text-lg font-bold text-destructive">
                            {formatCurrency(stats.totalSpent)}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">المتبقي</p>
                        <p
                            class="text-lg font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            {formatCurrency(stats.remaining)}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            نسبة الاستخدام
                        </p>
                        <p class="text-lg font-bold {getTextColor(usagePct)}">
                            {usagePct}%
                        </p>
                    </div>
                </div>
            </div>
            <div
                class="mt-4 h-2 w-full overflow-hidden rounded-full bg-secondary"
            >
                <div
                    class="h-full rounded-full {getProgressColor(usagePct)}"
                    style="width: {Math.min(usagePct, 100)}%"
                ></div>
            </div>
        </CardContent>
    </Card>

    <!-- ترتيب -->
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-muted-foreground">ترتيب:</span>
        {#each [{ v: 'percentage' as const, l: 'نسبة الإنفاق' }, { v: 'name' as const, l: 'الاسم' }, { v: 'budget' as const, l: 'الميزانية' }, { v: 'spent' as const, l: 'المنفق' }] as opt}
            <button
                class="rounded-md px-2.5 py-1 text-xs transition-colors {sortBy ===
                opt.v
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted hover:bg-muted/80'}"
                onclick={() => toggleSort(opt.v)}
            >
                {opt.l}
                {sortBy === opt.v ? (sortDir === 'asc' ? '↑' : '↓') : ''}
            </button>
        {/each}
    </div>

    <!-- فئات الميزانية -->
    <div class="grid gap-3 sm:grid-cols-2">
        {#each sortedBudgets as b, i (b.category_id ?? b.id ?? `cat-${i}`)}
            <BudgetRow
                name={b.name}
                icon={b.icon}
                color={b.color}
                spent={b.spent}
                budget={b.budget}
                rollover={b.rollover}
                onclick={() => openBudgetModal(b)}
            />
        {/each}
    </div>

    <!-- إضافة فئة جديدة -->
    <Card
        class="cursor-pointer border-dashed hover:border-primary/50 transition-colors"
        onclick={openCatModal}
    >
        <CardContent class="flex items-center justify-center py-8">
            <div class="text-center">
                <div
                    class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-muted"
                >
                    <Plus class="size-6 text-muted-foreground" />
                </div>
                <p class="text-sm font-medium">إضافة فئة مخصصة</p>
                <p class="text-xs text-muted-foreground">
                    أنشئ فئة جديدة مع أيقونة ولون
                </p>
            </div>
        </CardContent>
    </Card>

</div>

<!-- لوح إضافة فئة -->
<SheetShell bind:open={showCatModal} title="إضافة فئة جديدة" subtitle="اسم وأيقونة ولون" onClose={closeCatModal}>
    <div class="flex flex-col gap-3">
        {#if generalError(catErrors) || generalError(serverErrors)}
            <p class="flex items-start gap-2 rounded-2xl bg-destructive/10 px-3 py-2 text-[12px] text-destructive" role="alert">
                <CircleAlert class="mt-px size-4 shrink-0" />
                {generalError(catErrors) || generalError(serverErrors)}
            </p>
        {/if}

        <div class="flex flex-col gap-1.5">
            <label for="category-name" class="text-[11.5px] text-muted-foreground">اسم الفئة</label>
            <input
                id="category-name"
                type="text"
                bind:value={catName}
                placeholder="مثال: سكن، ملابس…"
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if catErrors.name || errorText(serverErrors, 'name')}
                <p class="text-[11.5px] text-destructive">{catErrors.name || errorText(serverErrors, 'name')}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-1.5">
            <span class="text-[11.5px] text-muted-foreground">الأيقونة</span>
            <div class="flex flex-wrap gap-2">
                {#each ICON_PICKER as key (key)}
                    <button
                        type="button"
                        class="grid size-11 place-items-center rounded-xl border transition-colors {catIcon === key
                            ? 'border-primary bg-primary/8'
                            : 'border-border'}"
                        aria-label={ICON_LABELS[key]}
                        aria-pressed={catIcon === key}
                        onclick={() => (catIcon = key)}
                    >
                        <CategoryIcon icon={key} size="sm" color={catColor} />
                    </button>
                {/each}
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <span class="text-[11.5px] text-muted-foreground">اللون</span>
            <div class="flex flex-wrap gap-2">
                {#each colors as clr (clr)}
                    <button
                        type="button"
                        class="grid size-11 place-items-center rounded-xl border transition-colors {catColor === clr
                            ? 'border-primary'
                            : 'border-border'}"
                        aria-label={`اختيار اللون ${clr}`}
                        aria-pressed={catColor === clr}
                        onclick={() => (catColor = clr)}
                    >
                        <span class="block size-6 rounded-full" style="background: {clr}"></span>
                    </button>
                {/each}
            </div>
        </div>
    </div>

    {#snippet footer()}
        <button
            type="button"
            onclick={closeCatModal}
            disabled={catSubmitting}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
        >
            إلغاء
        </button>
        <button
            type="button"
            onclick={handleCatSave}
            disabled={catSubmitting}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            <Check class="size-[18px]" />
            {catSubmitting ? 'جارٍ الحفظ…' : 'حفظ'}
        </button>
    {/snippet}
</SheetShell>

<!-- لوح تعديل ميزانية الفئة -->
<AmountSheet
    bind:open={showBudgetModal}
    bind:value={budgetAmount}
    title={editingBudget ? `ميزانية ${editingBudget.name}` : 'الميزانية الشهرية'}
    subtitle={editingBudget ? `المصروف هذا الشهر ${formatCurrency(editingBudget.spent)}` : ''}
    hint={budgetErrors.amount ||
        errorText(serverErrors, 'amount') ||
        generalError(budgetErrors) ||
        generalError(serverErrors)}
    quickAdd={[100, 500, 1000]}
    onSave={handleBudgetSave}
/>
