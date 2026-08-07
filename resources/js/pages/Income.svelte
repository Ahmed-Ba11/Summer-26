<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الدخل', href: '/income' },
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
    import ArrowUp from 'lucide-svelte/icons/arrow-up';
    import ArrowDown from 'lucide-svelte/icons/arrow-down';
    import Repeat from 'lucide-svelte/icons/repeat';
    import X from 'lucide-svelte/icons/x';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Wallet from 'lucide-svelte/icons/wallet';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import LoaderCircle from 'lucide-svelte/icons/loader-circle';

    interface IncomeRecord {
        id: number;
        description: string;
        source: string;
        amount: number; // halalas
        date: string;
        is_recurring: boolean;
    }

    interface Pagination {
        current_page: number;
        last_page: number;
        total: number;
    }

    interface Paginator {
        data: IncomeRecord[];
        current_page: number;
        last_page: number;
        total: number;
    }

    let {
        incomes = { data: [], current_page: 1, last_page: 1, total: 0 } as Paginator,
        recurringCount = 0,
    }: {
        incomes?: Paginator;
        recurringCount?: number;
    } = $props();

    function displayAmount(halalas: number): string {
        return (halalas / 100).toLocaleString('ar-SA') + ' ر.س';
    }

    function toHalalas(sar: number): number {
        return Math.round(sar * 100);
    }

    function formatDate(date: string): string {
        return new Date(date).toLocaleDateString('ar-SA');
    }

    // Filters
    let search = $state('');
    let selectedSource = $state('الكل');
    let sortField = $state<'date' | 'amount'>('date');
    let sortDir = $state<'asc' | 'desc'>('desc');
    let showRecurringOnly = $state(false);

    const sources = $derived.by(() => {
        const set = new Set(incomes.data.map((i) => i.source));
        return ['الكل', ...Array.from(set)];
    });

    const filteredIncomes = $derived.by(() => {
        let list = [...incomes.data];

        if (search) {
            const q = search.toLowerCase();
            list = list.filter((i) => i.description.toLowerCase().includes(q) || i.source.toLowerCase().includes(q));
        }

        if (selectedSource !== 'الكل') {
            list = list.filter((i) => i.source === selectedSource);
        }

        if (showRecurringOnly) {
            list = list.filter((i) => i.is_recurring);
        }

        list.sort((a, b) => {
            if (sortField === 'date') {
                return sortDir === 'desc'
                    ? b.date.localeCompare(a.date)
                    : a.date.localeCompare(b.date);
            }
            return sortDir === 'desc' ? b.amount - a.amount : a.amount - b.amount;
        });

        return list;
    });

    const totalFiltered = $derived(filteredIncomes.reduce((s, i) => s + i.amount, 0));

    let currentPage = $state(1);
    const perPage = 8;
    const totalPages = $derived(Math.ceil(filteredIncomes.length / perPage));
    const pagedIncomes = $derived(filteredIncomes.slice((currentPage - 1) * perPage, currentPage * perPage));

    function toggleSort(field: 'date' | 'amount') {
        if (sortField === field) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortField = field;
            sortDir = 'desc';
        }
    }

    function clearFilters() {
        search = '';
        selectedSource = 'الكل';
        showRecurringOnly = false;
        currentPage = 1;
    }

    const hasFilters = $derived(search !== '' || selectedSource !== 'الكل' || showRecurringOnly);

    // Modal state
    let showModal = $state(false);
    let editingId = $state<number | null>(null);

    // Form state
    let formAmount = $state('');
    let formSource = $state('');
    let formDescription = $state('');
    let formDate = $state('');
    let formIsRecurring = $state(false);
    let formErrors = $state<Record<string, string>>({});
    let formSubmitting = $state(false);

    function openAddModal() {
        editingId = null;
        formAmount = '';
        formSource = '';
        formDescription = '';
        formDate = new Date().toISOString().split('T')[0];
        formIsRecurring = false;
        formErrors = {};
        showModal = true;
    }

    function openEditModal(inc: IncomeRecord) {
        editingId = inc.id;
        formAmount = String(inc.amount / 100);
        formSource = inc.source;
        formDescription = inc.description;
        formDate = inc.date;
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
        const amountSar = parseFloat(formAmount);
        if (!amountSar || amountSar <= 0) {
            formErrors.amount = 'المبلغ مطلوب';
            return;
        }
        if (!formSource.trim()) {
            formErrors.source = 'المصدر مطلوب';
            return;
        }
        if (!formDescription.trim()) {
            formErrors.description = 'الوصف مطلوب';
            return;
        }
        if (!formDate) {
            formErrors.date = 'التاريخ مطلوب';
            return;
        }

        formSubmitting = true;

        const data = {
            amount: amountSar,
            source: formSource.trim(),
            description: formDescription.trim(),
            income_date: formDate,
            is_recurring: formIsRecurring,
        };

        if (editingId) {
            router.put(`/income/${editingId}`, data, {
                onSuccess: () => {
                    closeModal();
                    router.reload({ only: ['incomes', 'recurringCount'] });
                },
                onError: (err) => {
                    formErrors = err as Record<string, string>;
                },
                onFinish: () => {
                    formSubmitting = false;
                },
            });
        } else {
            router.post('/income', data, {
                onSuccess: () => {
                    closeModal();
                    router.reload({ only: ['incomes', 'recurringCount'] });
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
        router.delete(`/income/${deleteId}`, {
            onSuccess: () => {
                deleteId = null;
                router.reload({ only: ['incomes', 'recurringCount'] });
            },
            onFinish: () => {
                deleteSubmitting = false;
            },
        });
    }

    const recurringIncomes = $derived(incomes.data.filter((i) => i.is_recurring));
</script>

<AppHead title="الدخل" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">الدخل</h1>
            <p class="text-muted-foreground">{incomes.total} دخل مسجل</p>
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
                    <p class="text-sm text-muted-foreground">إجمالي الدخل</p>
                    <TrendingUp class="size-4 text-green-500" />
                </div>
                <p class="mt-2 text-xl font-bold text-green-600 dark:text-green-400">{displayAmount(totalFiltered)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">عدد المعاملات</p>
                    <Wallet class="size-4 text-blue-500" />
                </div>
                <p class="mt-2 text-xl font-bold">{filteredIncomes.length}</p>
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
                        class="w-full rounded-lg border border-border bg-background pe-9 ps-9 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                </div>

                <select
                    bind:value={selectedSource}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    {#each sources as src}
                        <option value={src}>{src === 'الكل' ? 'كل المصادر' : src}</option>
                    {/each}
                </select>

                <Button
                    variant={showRecurringOnly ? 'default' : 'outline'}
                    size="sm"
                    class="gap-1.5 shrink-0"
                    onclick={() => (showRecurringOnly = !showRecurringOnly)}
                >
                    <Repeat class="size-3.5" />
                    المتكرر فقط
                </Button>

                {#if hasFilters}
                    <Button variant="ghost" size="sm" class="shrink-0 gap-1 text-muted-foreground" onclick={clearFilters}>
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
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-6 py-3 text-right font-medium">الوصف</th>
                            <th class="px-6 py-3 text-right font-medium">المصدر</th>
                            <th class="px-6 py-3 text-right font-medium cursor-pointer select-none hover:text-foreground" onclick={() => toggleSort('date')}>
                                <span class="inline-flex items-center gap-1">
                                    التاريخ
                                    {#if sortField === 'date'}
                                        {#if sortDir === 'desc'}<ArrowDown class="size-3" />{:else}<ArrowUp class="size-3" />{/if}
                                    {/if}
                                </span>
                            </th>
                            <th class="px-6 py-3 text-right font-medium cursor-pointer select-none hover:text-foreground" onclick={() => toggleSort('amount')}>
                                <span class="inline-flex items-center gap-1">
                                    المبلغ
                                    {#if sortField === 'amount'}
                                        {#if sortDir === 'desc'}<ArrowDown class="size-3" />{:else}<ArrowUp class="size-3" />{/if}
                                    {/if}
                                </span>
                            </th>
                            <th class="px-6 py-3 text-right font-medium">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#if pagedIncomes.length === 0}
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-muted-foreground">
                                    لا يوجد دخل مطابق للبحث
                                </td>
                            </tr>
                        {:else}
                            {#each pagedIncomes as inc}
                                <tr class="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            {inc.description}
                                            {#if inc.is_recurring}
                                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[10px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                    <Repeat class="size-2.5" /> متكرر
                                                </span>
                                            {/if}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground">{inc.source}</td>
                                    <td class="px-6 py-3 text-muted-foreground tabular-nums">{formatDate(inc.date)}</td>
                                    <td class="px-6 py-3 font-medium tabular-nums text-green-600 dark:text-green-400">{displayAmount(inc.amount)}</td>
                                    <td class="px-6 py-3">
                                        <div class="flex gap-2">
                                            <button class="cursor-pointer inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground" onclick={() => openEditModal(inc)}>
                                                <Pencil class="size-3" /> تعديل
                                            </button>
                                            <button class="cursor-pointer inline-flex items-center gap-1 text-xs text-destructive hover:text-destructive/80" onclick={() => confirmDelete(inc.id)}>
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

            {#if totalPages > 1}
                <div class="flex items-center justify-between border-t px-6 py-3">
                    <span class="text-xs text-muted-foreground">صفحة {currentPage} من {totalPages}</span>
                    <div class="flex gap-1">
                        <Button variant="outline" size="sm" disabled={currentPage === 1} onclick={() => (currentPage = Math.max(1, currentPage - 1))}>السابق</Button>
                        {#each Array(totalPages) as _, i}
                            <Button variant={currentPage === i + 1 ? 'default' : 'outline'} size="sm" class="min-w-[36px]" onclick={() => (currentPage = i + 1)}>
                                {i + 1}
                            </Button>
                        {/each}
                        <Button variant="outline" size="sm" disabled={currentPage === totalPages} onclick={() => (currentPage = Math.min(totalPages, currentPage + 1))}>التالي</Button>
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
                        <div class="flex items-center justify-between rounded-lg border border-green-200 p-3 dark:border-green-800">
                            <div class="flex items-center gap-3">
                                <Repeat class="size-4 text-green-500" />
                                <div>
                                    <p class="text-sm font-medium">{inc.description}</p>
                                    <p class="text-xs text-muted-foreground">{inc.source}</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold tabular-nums text-green-600 dark:text-green-400">{displayAmount(inc.amount)}</span>
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}
</div>

<!-- Add / Edit Modal -->
{#if showModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pt-[10vh]"
        onclick={(e) => { if (e.target === e.currentTarget) closeModal(); }}
        onkeydown={(e) => { if (e.key === 'Escape') closeModal(); }}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">
                    {editingId ? 'تعديل الدخل' : 'إضافة دخل جديد'}
                </h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label for="income-amount" class="mb-1.5 block text-sm font-medium">المبلغ (ر.س)</label>
                    <input
                        id="income-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={formAmount}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.amount}
                        <p class="mt-1 text-xs text-destructive">{formErrors.amount}</p>
                    {/if}
                </div>
                <div>
                    <label for="income-source" class="mb-1.5 block text-sm font-medium">المصدر</label>
                    <input
                        id="income-source"
                        type="text"
                        placeholder="مثال: وظيفة، عمل حر"
                        bind:value={formSource}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.source}
                        <p class="mt-1 text-xs text-destructive">{formErrors.source}</p>
                    {/if}
                </div>
                <div>
                    <label for="income-desc" class="mb-1.5 block text-sm font-medium">الوصف</label>
                    <input
                        id="income-desc"
                        type="text"
                        placeholder="مثال: راتب شهري"
                        bind:value={formDescription}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.description}
                        <p class="mt-1 text-xs text-destructive">{formErrors.description}</p>
                    {/if}
                </div>
                <div>
                    <label for="income-date" class="mb-1.5 block text-sm font-medium">التاريخ</label>
                    <input
                        id="income-date"
                        type="date"
                        bind:value={formDate}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.date}
                        <p class="mt-1 text-xs text-destructive">{formErrors.date}</p>
                    {/if}
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="income-recurring"
                        type="checkbox"
                        bind:checked={formIsRecurring}
                        class="size-4 rounded border-border accent-primary"
                    />
                    <label for="income-recurring" class="text-sm text-muted-foreground cursor-pointer">دخل متكرر</label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeModal} disabled={formSubmitting}>إلغاء</Button>
                <Button onclick={submitForm} disabled={formSubmitting}>
                    {#if formSubmitting}
                        <LoaderCircle class="size-4 animate-spin" />
                    {/if}
                    {editingId ? 'حفظ التعديلات' : 'إضافة'}
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
            <p class="mt-2 text-sm text-muted-foreground">هل أنت متأكد من حذف هذا الدخل؟ لا يمكن التراجع عن هذا الإجراء.</p>
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
