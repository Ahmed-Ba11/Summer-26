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
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ArrowUp from 'lucide-svelte/icons/arrow-up';
    import ArrowDown from 'lucide-svelte/icons/arrow-down';
    import Repeat from 'lucide-svelte/icons/repeat';
    import X from 'lucide-svelte/icons/x';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Wallet from 'lucide-svelte/icons/wallet';

    interface Income {
        id: number;
        desc: string;
        source: string;
        amount: number;
        date: string;
        recurring?: { frequency: string; nextDate: string };
    }

    const allIncomes: Income[] = [
        { id: 1, desc: 'راتب شهري', source: 'وظيفة', amount: 8000, date: '2026-07-01', recurring: { frequency: 'شهري', nextDate: '2026-08-01' } },
        { id: 2, desc: 'عمل حر', source: 'مستقل', amount: 500, date: '2026-07-08' },
        { id: 3, desc: 'بيع أغراض', source: 'مبيعات', amount: 200, date: '2026-06-15' },
        { id: 4, desc: 'راتب شهري', source: 'وظيفة', amount: 8000, date: '2026-06-01', recurring: { frequency: 'شهري', nextDate: '2026-07-01' } },
        { id: 5, desc: 'تدريب', source: 'مستقل', amount: 1200, date: '2026-05-20' },
        { id: 6, desc: 'أرباح أسهم', source: 'استثمار', amount: 350, date: '2026-05-10' },
        { id: 7, desc: 'راتب شهري', source: 'وظيفة', amount: 8000, date: '2026-05-01', recurring: { frequency: 'شهري', nextDate: '2026-06-01' } },
    ];

    const sources = ['الكل', 'وظيفة', 'مستقل', 'مبيعات', 'استثمار'];
    let search = $state('');
    let selectedSource = $state('الكل');
    let sortField = $state<'date' | 'amount'>('date');
    let sortDir = $state<'asc' | 'desc'>('desc');
    let showRecurringOnly = $state(false);

    const filteredIncomes = $derived.by(() => {
        let list = [...allIncomes];

        if (search) {
            const q = search.toLowerCase();
            list = list.filter((e) => e.desc.toLowerCase().includes(q) || e.source.toLowerCase().includes(q));
        }

        if (selectedSource !== 'الكل') {
            list = list.filter((e) => e.source === selectedSource);
        }

        if (showRecurringOnly) {
            list = list.filter((e) => e.recurring);
        }

        list.sort((a, b) => {
            if (sortField === 'date') return sortDir === 'desc' ? b.date.localeCompare(a.date) : a.date.localeCompare(b.date);
            return sortDir === 'desc' ? b.amount - a.amount : a.amount - b.amount;
        });

        return list;
    });

    const totalFiltered = $derived(filteredIncomes.reduce((s, e) => s + e.amount, 0));
    const avgMonthly = $derived(Math.round(totalFiltered / Math.max(filteredIncomes.length || 1, 1)));

    let currentPage = $state(1);
    const perPage = 6;
    const totalPages = $derived(Math.ceil(filteredIncomes.length / perPage));
    const pagedIncomes = $derived(filteredIncomes.slice((currentPage - 1) * perPage, currentPage * perPage));

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }

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
</script>

<AppHead title="الدخل" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">الدخل</h1>
            <p class="text-muted-foreground">{allIncomes.length} دخل مسجل</p>
        </div>
        <Button class="gap-1.5">
            <Plus class="size-4" />
            إضافة دخل
        </Button>
    </div>

    <!-- بطاقات الملخص -->
    <div class="grid gap-4 sm:grid-cols-3">
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">إجمالي الدخل</p>
                    <TrendingUp class="size-4 text-green-500" />
                </div>
                <p class="mt-2 text-xl font-bold text-green-600 dark:text-green-400">{formatCurrency(totalFiltered)}</p>
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
                    <p class="text-sm text-muted-foreground">متوسط الدخل</p>
                    <TrendingUp class="size-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(avgMonthly)}</p>
            </CardContent>
        </Card>
    </div>

    <!-- شريط البحث والفلاتر -->
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

    <!-- جدول الدخل -->
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
                        {#each pagedIncomes as inc}
                            <tr class="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        {inc.desc}
                                        {#if inc.recurring}
                                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[10px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                <Repeat class="size-2.5" /> متكرر
                                            </span>
                                        {/if}
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">{inc.source}</td>
                                <td class="px-6 py-3 text-muted-foreground tabular-nums">{inc.date}</td>
                                <td class="px-6 py-3 font-medium tabular-nums text-green-600 dark:text-green-400">{formatCurrency(inc.amount)}</td>
                                <td class="px-6 py-3">
                                    <div class="flex gap-2">
                                        <button class="cursor-pointer text-xs text-muted-foreground hover:text-foreground">تعديل</button>
                                        <button class="cursor-pointer text-xs text-destructive hover:text-destructive/80">حذف</button>
                                    </div>
                                </td>
                            </tr>
                        {/each}
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

    <!-- الدخل المتكرر -->
    <Card>
        <CardHeader>
            <CardTitle class="text-base">الدخل المتكرر</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                {#each allIncomes.filter((e) => e.recurring) as e}
                    <div class="flex items-center justify-between rounded-lg border border-green-200 p-3 dark:border-green-800">
                        <div class="flex items-center gap-3">
                            <Repeat class="size-4 text-green-500" />
                            <div>
                                <p class="text-sm font-medium">{e.desc}</p>
                                <p class="text-xs text-muted-foreground">
                                    {e.recurring?.frequency} · التالي: {e.recurring?.nextDate}
                                </p>
                            </div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-green-600 dark:text-green-400">{formatCurrency(e.amount)}</span>
                    </div>
                {/each}
            </div>
        </CardContent>
    </Card>
</div>
