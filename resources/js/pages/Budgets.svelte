<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الميزانيات', href: '/budgets' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Vault from 'lucide-svelte/icons/vault';
    import BudgetRow from '@/components/BudgetRow.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { ICON_LABELS, ICON_PICKER } from '@/lib/category-icons';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import Check from 'lucide-svelte/icons/check';
    import { toast } from 'svelte-sonner';
    import { formatAmount, formatCurrency, formatPercent } from '@/lib/format';
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
        unallocated: number;
        monthlyIncome: number;
    }

    let {
        budgets = [] as BudgetRecord[],
        stats = {
            totalBudget: 0,
            totalSpent: 0,
            remaining: 0,
            rollover: 0,
            unallocated: 0,
            monthlyIncome: 0,
        } as BudgetStats,
        salaryMonth = null,
    }: {
        budgets?: BudgetRecord[];
        stats?: BudgetStats;
        salaryMonth?: { key: string; label: string; range: string; daysLeft: number } | null;
    } = $props();

    /** الميزانية تتبع شهر الراتب — يُذكر مداه صراحةً حتى لا يُقرأ كشهر تقويمي. */
    const periodLine = $derived(
        salaryMonth
            ? `${salaryMonth.label} · ${salaryMonth.range}`
            : 'وزّع دخلك على الفئات وراقب إنفاقك',
    );

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

    /**
     * الترتيب ثابت: الأعلى استهلاكاً أولاً.
     *
     * شريط الترتيب حُذف عمداً — أربعة أزرار تُبدّل صفوفاً تحتها بلا حاجة
     * حقيقية، والسؤال الوحيد الذي يهمّ («وين تروح فلوسي؟») يجيب عليه
     * ترتيب واحد.
     */
    const sortedBudgets = $derived(
        [...budgets].sort((a, b) => b.spent - a.spent || b.budget - a.budget),
    );

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
        // شهر الراتب لا الشهر التقويمي — الميزانية تُقرأ بمفتاح الفترة.
        const month = salaryMonth?.key ?? new Date().toISOString().slice(0, 7);
        const name = editingBudget?.name ?? '';

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
            onSuccess: () => {
                toast.success(
                    `تم تحديد ميزانية ${name} ${formatAmount(halalas)} ر.س`,
                );
                closeBudgetModal();
            },
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
        const name = catName.trim();
        router.post(
            storeCategory(),
            { name, icon: catIcon, color: catColor },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`تمت إضافة فئة ${name}`);
                    closeCatModal();
                },
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
<MobileHeader title="الميزانية العامة" subtitle={periodLine} />

<div class="flex flex-1 flex-col gap-3 p-3 md:gap-5 md:p-6">
    <div class="hidden items-center justify-between md:flex">
        <div>
            <h1 class="text-[22px] font-semibold tracking-tight">الميزانية العامة</h1>
            <p class="text-[13px] text-muted-foreground">{periodLine}</p>
        </div>
        <Button size="sm" class="gap-1.5" onclick={openCatModal}>
            <Plus class="size-[17px]" stroke-width="1.9" />
            إضافة فئة
        </Button>
    </div>

    <!-- «غير مخصّص» — أبرز رقم في الصفحة، ويختفي تماماً إذا كان صفراً -->
    {#if stats.unallocated > 0}
        <section class="rounded-2xl border border-primary/30 bg-card p-3 shadow-xs md:p-6">
            <div class="flex items-start gap-3">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl"
                    style="background-color: color-mix(in srgb, var(--chart-3) 12%, transparent); color: var(--chart-3)"
                >
                    <Vault class="size-[19px]" stroke-width="1.9" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11.5px] text-muted-foreground">غير مخصّص</p>
                    <p class="mt-0.5 text-[30px] leading-none font-semibold tracking-tighter tabular-nums md:text-[36px]">
                        {formatAmount(stats.unallocated)}<span
                            class="ms-1 text-[13px] font-medium text-foreground/80 md:text-[16px]">ر.س</span
                        >
                    </p>
                    <p class="mt-1.5 text-[12.5px] text-foreground/80">
                        فلوس ما لها وجهة بعد — تقدر تضمّه لهدف ادخار.
                    </p>
                </div>
            </div>

            <a
                href="/savings"
                class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-2xl bg-primary px-4 text-[14px] font-semibold text-primary-foreground no-underline transition-transform active:scale-[.98]"
            >
                ضمّه لهدف ادخار
                <ArrowLeft class="size-[18px]" stroke-width="1.9" />
            </a>
        </section>
    {/if}

    <!-- ملخّص الميزانية العامة -->
    <section class="rounded-2xl border border-border bg-card p-3 shadow-xs md:p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between md:gap-5">
            <div>
                <p class="text-[11.5px] text-muted-foreground">إجمالي الميزانية</p>
                <p class="mt-0.5 text-[24px] font-semibold tracking-tight tabular-nums md:text-[28px]">
                    {formatCurrency(stats.totalBudget)}
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3 border-t border-border pt-2.5 md:flex md:gap-6 md:border-0 md:pt-0">
                <div>
                    <p class="text-[11px] text-muted-foreground">المنفق</p>
                    <p class="mt-0.5 text-[15px] font-semibold text-destructive tabular-nums md:text-[18px]">
                        {formatAmount(stats.totalSpent)}
                    </p>
                </div>
                <div>
                    <p class="text-[11px] text-muted-foreground">المتبقي</p>
                    <p class="mt-0.5 text-[15px] font-semibold text-success-text tabular-nums md:text-[18px]">
                        {formatAmount(stats.remaining)}
                    </p>
                </div>
                <div>
                    <p class="text-[11px] text-muted-foreground">نسبة الاستخدام</p>
                    <p class="mt-0.5 text-[15px] font-semibold tabular-nums md:text-[18px] {getTextColor(usagePct)}">
                        {formatPercent(usagePct)}
                    </p>
                </div>
            </div>
        </div>
        <div class="mt-3 h-2 w-full overflow-hidden rounded-full border border-border bg-secondary">
            <div
                class="h-full rounded-full {getProgressColor(usagePct)}"
                style="width: {Math.min(usagePct, 100)}%"
            ></div>
        </div>
    </section>

    <!-- فئات الميزانية — الأعلى استهلاكاً أولاً -->
    <div class="grid gap-3 md:grid-cols-2">
        {#each sortedBudgets as b, i (b.category_id ?? b.id ?? `cat-${i}`)}
            <BudgetRow
                name={b.name}
                icon={b.icon}
                color={b.color}
                spent={b.spent}
                budget={b.budget}
                rollover={b.rollover}
                onEdit={() => openBudgetModal(b)}
            />
        {/each}
    </div>

    <!-- إضافة فئة جديدة -->
    <button
        type="button"
        onclick={openCatModal}
        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-input bg-card px-4 py-4 text-[13px] font-medium text-foreground/85 transition-transform active:scale-[.99]"
    >
        <Plus class="size-[18px]" stroke-width="1.9" />
        إضافة فئة مخصصة
    </button>
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
                        aria-label="اختيار اللون {clr}"
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
    saveLabel={budgetSubmitting ? 'جارٍ الحفظ…' : 'حفظ'}
    onSave={handleBudgetSave}
/>
