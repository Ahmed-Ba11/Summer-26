<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الدخل', href: '/income' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import ArrowDown from 'lucide-svelte/icons/arrow-down';
    import ArrowUp from 'lucide-svelte/icons/arrow-up';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import LoaderCircle from 'lucide-svelte/icons/loader-circle';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Repeat from 'lucide-svelte/icons/repeat';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Wallet from 'lucide-svelte/icons/wallet';
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
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import SheetField from '@/components/ui/SheetField.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DateSheet from '@/components/ui/DateSheet.svelte';
    import ConfirmSheet from '@/components/ui/ConfirmSheet.svelte';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import Check from 'lucide-svelte/icons/check';
    import { formatAmount, formatCurrency, formatDate, formatFullDate } from '@/lib/format';
    import type { ValidationErrors } from '@/types';
    import {
        destroy as destroyIncome,
        store as storeIncome,
        update as updateIncome,
    } from '@/routes/income';
    import type { ListFilters, PaginationMeta } from '@/types';

    interface IncomeRecord {
        id: number;
        description: string;
        source: string;
        amount: number; // halalas
        date: string;
        is_recurring: boolean;
    }

    interface Paginator {
        data: IncomeRecord[];
        current_page: number;
        last_page: number;
        total: number;
    }

    let {
        incomes = {
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        } as Paginator,
        recurringCount = 0,
        pagination = { current_page: 1, last_page: 1, total: 0 },
        filters = {},
        sources: serverSources = [],
        recurringIncomes: recurringIncomeItems,
    }: {
        incomes?: Paginator;
        recurringCount?: number;
        pagination?: PaginationMeta;
        filters?: ListFilters;
        sources?: string[];
        recurringIncomes?: IncomeRecord[];
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

    // Filters
    let search = $state('');
    let selectedSource = $state('الكل');
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
        selectedSource =
            (filters.source ?? queryValue('source')) || 'الكل';
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

    const sources = $derived.by(() => {
        const set = new Set([
            ...serverSources,
            ...incomes.data.map((i) => i.source),
        ]);

        if (selectedSource !== 'الكل') {
            set.add(selectedSource);
        }

        return ['الكل', ...Array.from(set)];
    });

    const totalFiltered = $derived(
        incomes.data.reduce((s, i) => s + i.amount, 0),
    );

    const currentPage = $derived(Math.max(1, pagination.current_page));
    const totalPages = $derived(Math.max(1, pagination.last_page));
    const pagedIncomes = $derived(incomes.data);

    function visitIncomeIndex(page: number = 1): void {
        router.get(
            '/income',
            {
                search: search.trim() || undefined,
                source: selectedSource !== 'الكل' ? selectedSource : undefined,
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

    function toggleSort(field: 'date' | 'amount'): void {
        if (sortField === field) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortField = field;
            sortDir = 'desc';
        }

        visitIncomeIndex(currentPage);
    }

    function clearFilters(): void {
        search = '';
        selectedSource = 'الكل';
        showRecurringOnly = false;
        visitIncomeIndex(1);
    }

    function toggleRecurring(): void {
        showRecurringOnly = !showRecurringOnly;
        visitIncomeIndex(1);
    }

    function handleSearchKeydown(event: KeyboardEvent): void {
        if (event.key === 'Enter') {
            visitIncomeIndex(1);
        }
    }

    const hasFilters = $derived(
        search !== '' || selectedSource !== 'الكل' || showRecurringOnly,
    );

    // Modal state
    let showModal = $state(false);
    let editingId = $state<number | null>(null);

    // Form state — المبلغ بالهللات
    let formAmount = $state(0);
    let amountSheetOpen = $state(false);
    let dateSheetOpen = $state(false);
    let formSource = $state('');
    let formDescription = $state('');
    let formDate = $state('');
    let formIsRecurring = $state(false);
    let formErrors = $state<Record<string, string>>({});
    let formSubmitting = $state(false);

    function openAddModal() {
        formSubmitting = false;
        editingId = null;
        formAmount = 0;
        formSource = '';
        formDescription = '';
        formDate = new Date().toISOString().split('T')[0];
        formIsRecurring = false;
        formErrors = {};
        showModal = true;
    }

    function openEditModal(inc: IncomeRecord) {
        editingId = inc.id;
        formAmount = inc.amount;
        formSource = inc.source;
        formDescription = inc.description;
        formDate = inc.date.slice(0, 10);
        formIsRecurring = inc.is_recurring;
        formErrors = {};
        showModal = true;
    }

    function closeModal() {
        showModal = false;
        editingId = null;
        formErrors = {};
    }

    function submitForm() {
        formErrors = {};
        const amountSar = formAmount / 100;

        if (!amountSar || amountSar <= 0) {
            formErrors.amount = 'المبلغ مطلوب';

            return;
        }

        if (!formSource.trim()) {
            formErrors.source = 'المصدر مطلوب';

            return;
        }

        if (!formDate) {
            formErrors.income_date = 'التاريخ مطلوب';

            return;
        }

        formSubmitting = true;

        const data = {
            amount: amountSar,
            source: formSource.trim(),
            description: formDescription.trim() || null,
            income_date: formDate,
            is_recurring: formIsRecurring,
        };

        if (editingId) {
            router.put(updateIncome(editingId), data, {
                onSuccess: () => {
                    closeModal();
                },
                onError: (err) => {
                    formErrors = err as Record<string, string>;
                },
                onFinish: () => {
                    formSubmitting = false;
                },
            });
        } else {
            router.post(storeIncome(), data, {
                onSuccess: () => {
                    closeModal();
                },
                onError: (err) => {
                    formErrors = err as Record<string, string>;
                },
                onFinish: () => {
                    formSubmitting = false;
                },
            });
        }
    }

    // Delete
    let deleteId = $state<number | null>(null);
    let deleteOpen = $state(false);
    let deleteSubmitting = $state(false);

    function confirmDelete(id: number) {
        deleteId = id;
        deleteOpen = true;
    }

    function cancelDelete() {
        deleteId = null;
        deleteOpen = false;
    }

    function executeDelete() {
        if (!deleteId) {
            return;
        }

        deleteSubmitting = true;
        router.delete(destroyIncome(deleteId), {
            onSuccess: () => {
                deleteId = null;
                deleteOpen = false;
            },
            onFinish: () => {
                deleteSubmitting = false;
            },
        });
    }

    const recurringIncomes = $derived(
        recurringIncomeItems ?? incomes.data.filter((i) => i.is_recurring),
    );

    onMount(() => {
        if (new URLSearchParams(window.location.search).get('new') === '1') {
            openAddModal();
            const url = new URL(window.location.href);
            url.searchParams.delete('new');
            window.history.replaceState({}, '', url);
        }
    });
</script>

<AppHead title="الدخل" />
<MobileHeader title="الدخل" subtitle={`${pagination.total} دخل مسجل`} />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div
        class="hidden flex-col gap-4 md:flex md:flex-row md:items-center md:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">الدخل</h1>
            <p class="text-muted-foreground">{pagination.total} دخل مسجل</p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة دخل
        </Button>
    </div>

    <!-- Summary cards -->
    <div class="grid gap-4 sm:grid-cols-3">
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الدخل في الصفحة</p>
                    <TrendingUp class="size-4 text-green-500" />
                </div>
                <p
                    class="mt-2 text-xl font-bold text-green-600 dark:text-green-400"
                >
                    {formatCurrency(totalFiltered)}
                </p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">عدد المعاملات</p>
                    <Wallet class="size-4 text-blue-500" />
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums">{pagination.total}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الدخل المتكرر</p>
                    <Repeat class="size-4 text-purple-500" />
                </div>
                <p class="mt-2 text-xl font-bold">{recurringCount}</p>
            </CardContent>
        </Card>
    </div>

    <!-- Search and filters -->
    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <input
                        type="text"
                        placeholder="ابحث عن وصف أو مصدر..."
                        bind:value={search}
                        onkeydown={handleSearchKeydown}
                        onchange={() => visitIncomeIndex(1)}
                        class="w-full rounded-lg border border-border bg-background pe-9 ps-9 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <Search
                        class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none"
                    />
                </div>

                <select
                    bind:value={selectedSource}
                    onchange={() => visitIncomeIndex(1)}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    {#each sources as src}
                        <option value={src}
                            >{src === 'الكل' ? 'كل المصادر' : src}</option
                        >
                    {/each}
                </select>

                <Button
                    variant={showRecurringOnly ? 'default' : 'outline'}
                    size="sm"
                    class="gap-1.5 shrink-0"
                    onclick={toggleRecurring}
                >
                    <Repeat class="size-3.5" />
                    المتكرر فقط
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

    <!-- Income table -->
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
                                >المصدر</th
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
                        {#if pagedIncomes.length === 0}
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-6 py-8 text-center text-muted-foreground"
                                >
                                    لا يوجد دخل مطابق للبحث
                                </td>
                            </tr>
                        {:else}
                            {#each pagedIncomes as inc}
                                <tr
                                    class="border-b last:border-0 hover:bg-muted/50 transition-colors"
                                >
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            {inc.description || inc.source}
                                            {#if inc.is_recurring}
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
                                                >
                                                    <Repeat class="size-2.5" /> متكرر
                                                </span>
                                            {/if}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground"
                                        >{inc.source}</td
                                    >
                                    <td
                                        class="px-6 py-3 text-muted-foreground tabular-nums"
                                        >{formatDate(inc.date)}</td
                                    >
                                    <td
                                        class="px-6 py-3 font-medium tabular-nums text-green-600 dark:text-green-400"
                                        >{formatCurrency(inc.amount)}</td
                                    >
                                    <td class="px-6 py-3">
                                        <div class="flex gap-2">
                                            <button
                                                class="cursor-pointer inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                                onclick={() =>
                                                    openEditModal(inc)}
                                            >
                                                <Pencil class="size-3" /> تعديل
                                            </button>
                                            <button
                                                class="cursor-pointer inline-flex items-center gap-1 text-xs text-destructive hover:text-destructive/80"
                                                onclick={() =>
                                                    confirmDelete(inc.id)}
                                            >
                                                <Trash2 class="size-3" /> حذف
                                            </button>
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
                {#if pagedIncomes.length === 0}
                    <li class="px-4 py-8 text-center text-muted-foreground">
                        لا يوجد دخل مطابق للبحث
                    </li>
                {:else}
                    {#each pagedIncomes as inc}
                        <li class="flex items-center gap-3 px-4 py-3">
                            <CategoryIcon
                                icon="banknote"
                                color="var(--chart-3)"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px]">
                                    {inc.description || inc.source}
                                    {#if inc.is_recurring}
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
                                        >
                                            <Repeat class="size-2.5" /> متكرر
                                        </span>
                                    {/if}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {inc.source} · {formatDate(inc.date)}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs text-muted-foreground hover:text-foreground"
                                    onclick={() => openEditModal(inc)}
                                >
                                    <Pencil class="size-3.5" /> تعديل
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs text-destructive hover:text-destructive/80"
                                    onclick={() => confirmDelete(inc.id)}
                                >
                                    <Trash2 class="size-3.5" /> حذف
                                </button>
                            </div>
                            <span
                                class="shrink-0 text-[13px] font-semibold tabular-nums text-green-600 dark:text-green-400"
                            >
                                {formatCurrency(inc.amount)}
                            </span>
                        </li>
                    {/each}
                {/if}
            </ul>

            {#if totalPages > 1}
                <div
                    class="flex items-center justify-between border-t px-6 py-3"
                >
                    <span class="text-xs text-muted-foreground"
                        >صفحة {currentPage} من {totalPages}</span
                    >
                    <div class="flex gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === 1}
                            onclick={() => visitIncomeIndex(currentPage - 1)}
                            >السابق</Button
                        >
                        {#each Array(totalPages) as _, i}
                            <Button
                                variant={currentPage === i + 1
                                    ? 'default'
                                    : 'outline'}
                                size="sm"
                                class="min-w-[36px]"
                                onclick={() => visitIncomeIndex(i + 1)}
                            >
                                {i + 1}
                            </Button>
                        {/each}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === totalPages}
                            onclick={() => visitIncomeIndex(currentPage + 1)}>التالي</Button
                        >
                    </div>
                </div>
            {/if}
        </CardContent>
    </Card>

    <!-- Recurring income -->
    {#if recurringIncomes.length > 0}
        <Card>
            <CardHeader>
                <CardTitle class="text-base">الدخل المتكرر</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    {#each recurringIncomes as inc}
                        <div
                            class="flex items-center justify-between rounded-lg border border-green-200 p-3 dark:border-green-800"
                        >
                            <div class="flex items-center gap-3">
                                <Repeat class="size-4 text-green-500" />
                                <div>
                                    <p class="text-sm font-medium">
                                        {inc.description}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {inc.source}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="text-sm font-bold tabular-nums text-green-600 dark:text-green-400"
                                >{formatCurrency(inc.amount)}</span
                            >
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}
</div>

<!-- لوح إضافة/تعديل دخل -->
<SheetShell
    bind:open={showModal}
    title={editingId ? 'تعديل الدخل' : 'إضافة دخل جديد'}
    subtitle={editingId ? 'عدّل بيانات الدخل' : 'أدخل بيانات الدخل الجديد'}
    onClose={closeModal}
>
    <div class="flex flex-col gap-3">
        {#if generalError(formErrors) || generalError(serverErrors)}
            <p class="flex items-start gap-2 rounded-2xl bg-destructive/10 px-3 py-2 text-[12px] text-destructive" role="alert">
                <CircleAlert class="mt-px size-4 shrink-0" />
                {generalError(formErrors) || generalError(serverErrors)}
            </p>
        {/if}

        <SheetField
            label="المبلغ"
            icon={Wallet}
            value={formAmount > 0 ? `${formatAmount(formAmount)} ر.س` : ''}
            placeholder="اضغط لإدخال المبلغ"
            error={formErrors.amount || errorText(serverErrors, 'amount')}
            onclick={() => (amountSheetOpen = true)}
        />

        <SheetField
            label="التاريخ"
            icon={CalendarDays}
            value={formDate ? formatFullDate(formDate) : ''}
            placeholder="اختر التاريخ"
            error={formErrors.income_date || errorText(serverErrors, 'income_date')}
            onclick={() => (dateSheetOpen = true)}
        />

        <div class="flex flex-col gap-1.5">
            <label for="income-source" class="text-[11.5px] text-muted-foreground">المصدر</label>
            <input
                id="income-source"
                type="text"
                placeholder="مثال: وظيفة، عمل حر"
                bind:value={formSource}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if formErrors.source || errorText(serverErrors, 'source')}
                <p class="text-[11.5px] text-destructive">
                    {formErrors.source || errorText(serverErrors, 'source')}
                </p>
            {/if}
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="income-desc" class="text-[11.5px] text-muted-foreground">الوصف</label>
            <input
                id="income-desc"
                type="text"
                placeholder="مثال: راتب شهري"
                bind:value={formDescription}
                class="min-h-11 rounded-2xl border border-input bg-background px-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-ring"
            />
            {#if formErrors.description || errorText(serverErrors, 'description')}
                <p class="text-[11.5px] text-destructive">
                    {formErrors.description || errorText(serverErrors, 'description')}
                </p>
            {/if}
        </div>

        <button
            type="button"
            onclick={() => (formIsRecurring = !formIsRecurring)}
            aria-pressed={formIsRecurring}
            class="inline-flex min-h-11 items-center gap-2.5 rounded-2xl border px-3 text-start transition-transform active:scale-[.99] {formIsRecurring
                ? 'border-primary bg-primary/8'
                : 'border-input'}"
        >
            <span
                class="grid size-5 shrink-0 place-items-center rounded-md border {formIsRecurring
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-input'}"
            >
                {#if formIsRecurring}<Check class="size-3.5" />{/if}
            </span>
            <span class="text-[13px] {formIsRecurring ? 'font-semibold text-primary' : ''}">دخل متكرر</span>
        </button>
    </div>

    {#snippet footer()}
        <button
            type="button"
            onclick={closeModal}
            disabled={formSubmitting}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
        >
            إلغاء
        </button>
        <button
            type="button"
            onclick={submitForm}
            disabled={formSubmitting}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            {#if formSubmitting}
                <LoaderCircle class="size-[18px] animate-spin" />
            {:else}
                <Check class="size-[18px]" />
            {/if}
            {editingId ? 'حفظ التعديلات' : 'إضافة'}
        </button>
    {/snippet}
</SheetShell>

<AmountSheet bind:open={amountSheetOpen} bind:value={formAmount} title="مبلغ الدخل" quickAdd={[100, 500, 1000]} />

<DateSheet bind:open={dateSheetOpen} bind:value={formDate} title="تاريخ الدخل" />

<ConfirmSheet
    bind:open={deleteOpen}
    message="سيُحذف هذا الدخل نهائياً ولا يمكن التراجع."
    loading={deleteSubmitting}
    onConfirm={executeDelete}
/>
