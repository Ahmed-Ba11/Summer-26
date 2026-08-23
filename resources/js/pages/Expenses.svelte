<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'المصاريف', href: '/expenses' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowDown from 'lucide-svelte/icons/arrow-down';
    import ArrowUp from 'lucide-svelte/icons/arrow-up';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Repeat from 'lucide-svelte/icons/repeat';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import DialogDescription from '@/components/ui/dialog/DialogDescription.svelte';
    import DialogFooter from '@/components/ui/dialog/DialogFooter.svelte';
    import DialogTitle from '@/components/ui/dialog/DialogTitle.svelte';
    import { formatCurrency, formatDate, toRiyals } from '@/lib/format';
    import type { ValidationErrors } from '@/types';
    import {
        destroy as destroyExpense,
        store as storeExpense,
        update as updateExpense,
    } from '@/routes/expenses';
    import type { ListFilters, PaginationMeta } from '@/types';

    interface ExpenseItem {
        id: number;
        description: string | null;
        category: string | null;
        category_icon: string;
        category_color: string;
        amount: number;
        date: string;
        is_recurring: boolean;
    }

    interface CategoryItem {
        id: number;
        name: string;
    }

    let {
        expenses = [],
        categories = [],
        recurringCount = 0,
        pagination = { current_page: 1, last_page: 1, total: 0 },
        filters = {},
        recurringExpenses: recurringExpenseItems,
    }: {
        expenses?: ExpenseItem[];
        categories?: CategoryItem[];
        recurringCount?: number;
        pagination?: PaginationMeta;
        filters?: ListFilters;
        recurringExpenses?: ExpenseItem[];
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

    function queryValue(key: string): string {
        if (typeof window === 'undefined') {
            return '';
        }

        return new URLSearchParams(window.location.search).get(key) ?? '';
    }

    function queryFlag(value: boolean | number | string | undefined): boolean {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    let search = $state('');
    let selectedCategoryName = $state('');
    let sortField = $state<'date' | 'amount'>('date');
    let sortDir = $state<'asc' | 'desc'>('desc');
    let showRecurringOnly = $state(false);
    let filtersInitialized = $state(false);

    $effect(() => {
        if (filtersInitialized) {
            return;
        }

        const initialRecurring = filters.recurring ?? queryValue('recurring');
        search = filters.search ?? queryValue('search');
        selectedCategoryName =
            (filters.category ?? queryValue('category')) || '';
        sortField =
            filters.sort === 'amount' || queryValue('sort') === 'amount'
                ? 'amount'
                : 'date';
        sortDir =
            filters.direction === 'asc' || queryValue('direction') === 'asc'
                ? 'asc'
                : 'desc';
        showRecurringOnly = queryFlag(initialRecurring);
        filtersInitialized = true;
    });
    let modalOpen = $state(false);
    let confirmDeleteOpen = $state(false);
    let editingExpense = $state<ExpenseItem | null>(null);
    let submitting = $state(false);
    let formErrors = $state<Record<string, string>>({});

    let form = $state({
        description: '',
        category_id: null as number | null,
        amount: '',
        expense_date: '',
        is_recurring: false,
    });

    const findCategoryIdByName = (name: string | null): number | null =>
        categories.find((c) => c.name === name)?.id ?? null;

    const totalFiltered = $derived(
        expenses.reduce((s, e) => s + e.amount, 0),
    );

    const currentPage = $derived(Math.max(1, pagination.current_page));
    const totalPages = $derived(Math.max(1, pagination.last_page));
    function visitExpenseIndex(page: number = 1): void {
        router.get(
            '/expenses',
            {
                search: search.trim() || undefined,
                category: selectedCategoryName || undefined,
                recurring: showRecurringOnly ? 1 : undefined,
                sort: sortField,
                direction: sortDir,
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    const hasFilters = $derived(
        search !== '' || selectedCategoryName !== '' || showRecurringOnly,
    );
    const recurringExpenses = $derived(
        recurringExpenseItems ?? expenses.filter((expense) => expense.is_recurring),
    );

    function toggleSort(field: 'date' | 'amount'): void {
        if (sortField === field) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortField = field;
            sortDir = 'desc';
        }

        visitExpenseIndex(currentPage);
    }

    function clearFilters(): void {
        search = '';
        selectedCategoryName = '';
        showRecurringOnly = false;
        visitExpenseIndex(1);
    }

    function toggleRecurring(): void {
        showRecurringOnly = !showRecurringOnly;
        visitExpenseIndex(1);
    }

    function handleSearchKeydown(event: KeyboardEvent): void {
        if (event.key === 'Enter') {
            visitExpenseIndex(1);
        }
    }

    function openAddModal() {
        editingExpense = null;
        formErrors = {};
        form = {
            description: '',
            category_id: categories.length > 0 ? categories[0].id : null,
            amount: '',
            expense_date: new Date().toISOString().slice(0, 10),
            is_recurring: false,
        };
        modalOpen = true;
    }

    function openEditModal(expense: ExpenseItem) {
        editingExpense = expense;
        formErrors = {};
        form = {
            description: expense.description ?? '',
            category_id: findCategoryIdByName(expense.category),
            amount: toRiyals(expense.amount).toFixed(2),
            expense_date: expense.date,
            is_recurring: expense.is_recurring ?? false,
        };
        modalOpen = true;
    }

    function closeModal() {
        modalOpen = false;
        editingExpense = null;
        formErrors = {};
    }

    function handleSubmit() {
        formErrors = {};
        submitting = true;

        if (!form.category_id) {
            formErrors.category_id = 'الفئة مطلوبة';
            submitting = false;

            return;
        }

        if (!form.amount || Number(form.amount) <= 0) {
            formErrors.amount = 'المبلغ مطلوب ويجب أن يكون أكبر من صفر';
            submitting = false;

            return;
        }

        if (!form.expense_date) {
            formErrors.expense_date = 'التاريخ مطلوب';
            submitting = false;

            return;
        }

        const payload = {
            description: form.description.trim(),
            category_id: form.category_id,
            amount: parseFloat(form.amount),
            expense_date: form.expense_date,
            is_recurring: form.is_recurring,
        };

        if (editingExpense) {
            router.put(updateExpense(editingExpense.id), payload, {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                },
                onError: (errors) => {
                    formErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    submitting = false;
                },
            });
        } else {
            router.post(storeExpense(), payload, {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                },
                onError: (errors) => {
                    formErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    submitting = false;
                },
            });
        }
    }

    function openDeleteConfirm(expense: ExpenseItem) {
        editingExpense = expense;
        confirmDeleteOpen = true;
    }

    function handleDelete() {
        if (!editingExpense) {
            return;
        }

        submitting = true;

        router.delete(destroyExpense(editingExpense.id), {
            preserveScroll: true,
            onSuccess: () => {
                confirmDeleteOpen = false;
                editingExpense = null;
            },
            onFinish: () => {
                submitting = false;
            },
        });
    }

    function goToPage(page: number): void {
        visitExpenseIndex(Math.max(1, Math.min(totalPages, page)));
    }

    onMount(() => {
        if (new URLSearchParams(window.location.search).get('new') === '1') {
            openAddModal();
            const url = new URL(window.location.href);
            url.searchParams.delete('new');
            window.history.replaceState({}, '', url);
        }
    });
</script>

<AppHead title="المصاريف" />
<MobileHeader title="المصاريف" subtitle={`${pagination.total} مصروف مسجل`} />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div
        class="hidden flex-col gap-4 md:flex md:flex-row md:items-center md:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">المصاريف</h1>
            <p class="text-muted-foreground">
                {pagination.total} مصروف مسجل
                {#if recurringCount > 0}
                    · {recurringCount} متكرر
                {/if}
            </p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة مصروف
        </Button>
    </div>

    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <input
                        type="text"
                        placeholder="ابحث عن وصف أو فئة..."
                        bind:value={search}
                        onkeydown={handleSearchKeydown}
                        onchange={() => visitExpenseIndex(1)}
                        class="w-full rounded-lg border border-border bg-background pe-9 ps-9 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <Search
                        class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none"
                    />
                </div>

                <select
                    bind:value={selectedCategoryName}
                    onchange={() => visitExpenseIndex(1)}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">كل الفئات</option>
                    {#each categories as cat}
                        <option value={cat.name}>{cat.name}</option>
                    {/each}
                </select>

                <Button
                    variant={showRecurringOnly ? 'default' : 'outline'}
                    size="sm"
                    class="gap-1.5 shrink-0"
                    onclick={toggleRecurring}
                >
                    <Repeat class="size-3.5" />
                    المتكررة فقط
                </Button>

                {#if hasFilters}
                    <Button
                        variant="ghost"
                        size="sm"
                        class="shrink-0 gap-1 text-muted-foreground"
                        onclick={clearFilters}
                    >
                        <X class="size-3.5" />
                        مسح
                    </Button>
                {/if}
            </div>
        </CardContent>
    </Card>

    <div class="flex items-center gap-4 text-sm">
        <span class="text-muted-foreground">
            {pagination.total} نتيجة
            {#if hasFilters}
                <span class="text-foreground">(مُصفّى)</span>
            {/if}
        </span>
            <span class="font-bold tabular-nums text-destructive"
                >مجموع الصفحة: {formatCurrency(totalFiltered)}</span
        >
    </div>

    <Card>
        <CardContent class="p-0">
            <div class="hidden md:block">
                <table class="hidden w-full text-sm md:table">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-6 py-3 text-start font-medium"
                                >الوصف</th
                            >
                            <th class="px-6 py-3 text-start font-medium"
                                >الفئة</th
                            >
                            <th
                                class="px-6 py-3 text-start font-medium cursor-pointer select-none hover:text-foreground"
                                onclick={() => toggleSort('date')}
                            >
                                <span class="inline-flex items-center gap-1">
                                    التاريخ
                                    {#if sortField === 'date'}
                                        {#if sortDir === 'desc'}<ArrowDown
                                                class="size-3"
                                            />{:else}<ArrowUp
                                                class="size-3"
                                            />{/if}
                                    {/if}
                                </span>
                            </th>
                            <th
                                class="px-6 py-3 text-start font-medium cursor-pointer select-none hover:text-foreground"
                                onclick={() => toggleSort('amount')}
                            >
                                <span class="inline-flex items-center gap-1">
                                    المبلغ
                                    {#if sortField === 'amount'}
                                        {#if sortDir === 'desc'}<ArrowDown
                                                class="size-3"
                                            />{:else}<ArrowUp
                                                class="size-3"
                                            />{/if}
                                    {/if}
                                </span>
                            </th>
                            <th class="px-6 py-3 text-start font-medium"
                                >إجراءات</th
                            >
                        </tr>
                    </thead>
                    <tbody>
                        {#if expenses.length === 0}
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-6 py-12 text-center text-muted-foreground"
                                >
                                    لا توجد مصاريف مطابقة للفلاتر
                                </td>
                            </tr>
                        {:else}
                            {#each expenses as expense (expense.id)}
                                <tr
                                    class="border-b last:border-0 hover:bg-muted/50 transition-colors"
                                >
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            {expense.description}
                                            {#if expense.is_recurring}
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
                                                    title="متكرر"
                                                >
                                                    <Repeat class="size-2.5" /> متكرر
                                                </span>
                                            {/if}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground"
                                        >{expense.category}</td
                                    >
                                    <td
                                        class="px-6 py-3 text-muted-foreground tabular-nums"
                                        >{formatDate(expense.date)}</td
                                    >
                                    <td
                                        class="px-6 py-3 font-medium tabular-nums text-destructive"
                                    >
                                        {formatCurrency(expense.amount)}
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="تعديل"
                                                onclick={() =>
                                                    openEditModal(expense)}
                                            >
                                                <Pencil class="size-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="حذف"
                                                class="text-destructive hover:text-destructive"
                                                onclick={() =>
                                                    openDeleteConfirm(expense)}
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>

            <!-- بطاقات مكدّسة على الجوال — صفر تمرير أفقي -->
            <ul class="divide-y divide-border md:hidden">
                {#if expenses.length === 0}
                    <li class="px-4 py-12 text-center text-muted-foreground">
                        لا توجد مصاريف مطابقة للفلاتر
                    </li>
                {:else}
                    {#each expenses as expense (expense.id)}
                        <li class="flex items-center gap-3 px-4 py-3">
                            <CategoryIcon
                                icon={expense.category_icon}
                                color={expense.category_color}
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px]">
                                    {expense.description || expense.category || 'مصروف'}
                                    {#if expense.is_recurring}
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
                                            title="متكرر"
                                        >
                                            <Repeat class="size-2.5" /> متكرر
                                        </span>
                                    {/if}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {expense.category} · {formatDate(expense.date)}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="grid size-9 place-items-center rounded-lg text-muted-foreground hover:text-foreground"
                                    aria-label="تعديل"
                                    onclick={() => openEditModal(expense)}
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    class="grid size-9 place-items-center rounded-lg text-destructive hover:text-destructive"
                                    aria-label="حذف"
                                    onclick={() => openDeleteConfirm(expense)}
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                            <span
                                class="shrink-0 text-[13px] font-semibold tabular-nums text-destructive"
                            >
                                {formatCurrency(expense.amount)}
                            </span>
                        </li>
                    {/each}
                {/if}
            </ul>

            {#if totalPages > 1}
                <div
                    class="flex items-center justify-between border-t px-6 py-3"
                >
                    <span class="text-xs text-muted-foreground">
                        صفحة {currentPage} من {totalPages}
                    </span>
                    <div class="flex gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === 1}
                            onclick={() => goToPage(currentPage - 1)}
                        >
                            السابق
                        </Button>
                        {#each Array(totalPages) as _, i}
                            {@const page = i + 1}
                            <Button
                                variant={currentPage === page
                                    ? 'default'
                                    : 'outline'}
                                size="sm"
                                class="min-w-[36px]"
                                onclick={() => goToPage(page)}
                            >
                                {page}
                            </Button>
                        {/each}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === totalPages}
                            onclick={() => goToPage(currentPage + 1)}
                        >
                            التالي
                        </Button>
                    </div>
                </div>
            {/if}
        </CardContent>
    </Card>

    {#if recurringExpenses.length > 0}
        <Card>
            <CardHeader>
                <CardTitle class="text-base">المصاريف المتكررة</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    {#each recurringExpenses as expense (expense.id)}
                        <div
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <div class="flex items-center gap-3">
                                <Repeat class="size-4 text-purple-500" />
                                <div>
                                    <p class="text-sm font-medium">
                                        {expense.description}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {expense.category}
                                    </p>
                                </div>
                            </div>
                            <span class="text-sm font-bold tabular-nums"
                                >{formatCurrency(expense.amount)}</span
                            >
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}
</div>

<!-- Add/Edit Modal -->
{#if modalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <button
            type="button"
            class="fixed inset-0 bg-black/50 cursor-default"
            aria-label="إغلاق"
            onclick={closeModal}
        ></button>
        <div
            class="relative z-10 w-full max-w-lg rounded-lg border bg-background p-6 shadow-lg max-h-[90vh] overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex flex-col gap-1.5">
                <DialogTitle
                    >{editingExpense
                        ? 'تعديل مصروف'
                        : 'إضافة مصروف'}</DialogTitle
                >
                <DialogDescription>
                    {editingExpense
                        ? 'عدّل بيانات المصروف أدناه'
                        : 'أدخل بيانات المصروف الجديد'}
                </DialogDescription>
            </div>

            <form
                class="mt-4 flex flex-col gap-4"
                onsubmit={(e) => {
                    e.preventDefault();
                    handleSubmit();
                }}
            >
                {#if generalError(formErrors) || generalError(serverErrors)}
                    <p class="flex items-center gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                        <AlertTriangle class="size-4 shrink-0" />
                        {generalError(formErrors) || generalError(serverErrors)}
                    </p>
                {/if}
                {#if formErrors.funding_source || errorText(serverErrors, 'funding_source')}
                    <p class="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                        {formErrors.funding_source || errorText(serverErrors, 'funding_source')}
                        <a href="/dashboard" class="ms-1 font-semibold underline underline-offset-2">استخدم الإضافة السريعة</a>
                    </p>
                {/if}
                <div class="flex flex-col gap-1.5">
                    <label for="expense-description" class="text-sm font-medium"
                        >الوصف</label
                    >
                    <input
                        id="expense-description"
                        type="text"
                        bind:value={form.description}
                        placeholder="مثال: مطعم، فاتورة كهرباء..."
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.description || errorText(serverErrors, 'description')}
                        <p class="text-xs text-destructive">
                            {formErrors.description || errorText(serverErrors, 'description')}
                        </p>
                    {/if}
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="expense-category" class="text-sm font-medium"
                        >الفئة</label
                    >
                    <select
                        id="expense-category"
                        bind:value={form.category_id}
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        <option value={null}>اختر الفئة</option>
                        {#each categories as cat}
                            <option value={cat.id}>{cat.name}</option>
                        {/each}
                    </select>
                    {#if formErrors.category_id || errorText(serverErrors, 'category_id')}
                        <p class="text-xs text-destructive">
                            {formErrors.category_id || errorText(serverErrors, 'category_id')}
                        </p>
                    {/if}
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="expense-amount" class="text-sm font-medium"
                        >المبلغ (ر.س)</label
                    >
                    <input
                        id="expense-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        bind:value={form.amount}
                        placeholder="0.00"
                        class="rounded-lg border border-border bg-background px-3 py-2 text-end text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.amount || errorText(serverErrors, 'amount')}
                        <p class="text-xs text-destructive">
                            {formErrors.amount || errorText(serverErrors, 'amount')}
                        </p>
                    {/if}
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="expense-date" class="text-sm font-medium"
                        >التاريخ</label
                    >
                    <input
                        id="expense-date"
                        type="date"
                        bind:value={form.expense_date}
                        class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.expense_date || errorText(serverErrors, 'expense_date')}
                        <p class="text-xs text-destructive">
                            {formErrors.expense_date || errorText(serverErrors, 'expense_date')}
                        </p>
                    {/if}
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        bind:checked={form.is_recurring}
                        class="size-4 rounded border-border accent-primary"
                    />
                    <span class="text-sm">مصروف متكرر</span>
                </label>

                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        onclick={closeModal}
                        disabled={submitting}
                    >
                        إلغاء
                    </Button>
                    <Button type="submit" disabled={submitting}>
                        {submitting
                            ? 'جاري الحفظ...'
                            : editingExpense
                              ? 'حفظ التعديلات'
                              : 'إضافة'}
                    </Button>
                </DialogFooter>
            </form>
        </div>
    </div>
{/if}

<!-- Delete Confirmation Modal -->
{#if confirmDeleteOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <button
            type="button"
            class="fixed inset-0 bg-black/50 cursor-default"
            aria-label="إغلاق"
            onclick={() => {
                confirmDeleteOpen = false;
                editingExpense = null;
            }}
        ></button>
        <div
            class="relative z-10 w-full max-w-md rounded-lg border bg-background p-6 shadow-lg"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex flex-col items-center gap-3 text-center">
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-destructive/10"
                >
                    <AlertTriangle class="size-6 text-destructive" />
                </div>
                <DialogTitle>تأكيد الحذف</DialogTitle>
                <DialogDescription>
                    هل أنت متأكد من حذف مصروف "{editingExpense?.description}"؟
                    لا يمكن التراجع عن هذا الإجراء.
                </DialogDescription>
            </div>

            <DialogFooter class="mt-6">
                <Button
                    variant="outline"
                    onclick={() => {
                        confirmDeleteOpen = false;
                        editingExpense = null;
                    }}
                    disabled={submitting}
                >
                    إلغاء
                </Button>
                <Button
                    variant="destructive"
                    onclick={handleDelete}
                    disabled={submitting}
                >
                    {submitting ? 'جاري الحذف...' : 'حذف'}
                </Button>
            </DialogFooter>
        </div>
    </div>
{/if}
