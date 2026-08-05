<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'المصاريف', href: '/expenses' },
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
    import Filter from 'lucide-svelte/icons/filter';
    import Repeat from 'lucide-svelte/icons/repeat';
    import X from 'lucide-svelte/icons/x';
    import Image from 'lucide-svelte/icons/image';

    interface Expense {
        id: number;
        desc: string;
        cat: string;
        amount: number;
        date: string;
        recurring?: { frequency: string; nextDate: string };
    }

    const allExpenses: Expense[] = [
        { id: 1, desc: 'مطعم', cat: 'طعام', amount: 150, date: '2026-07-14' },
        { id: 2, desc: 'تاكسي', cat: 'مواصلات', amount: 45, date: '2026-07-13' },
        { id: 3, desc: 'فاتورة كهرباء', cat: 'فواتير', amount: 320, date: '2026-07-12', recurring: { frequency: 'شهري', nextDate: '2026-08-05' } },
        { id: 4, desc: 'سينما', cat: 'ترفيه', amount: 120, date: '2026-07-10' },
        { id: 5, desc: 'دواء', cat: 'صحة', amount: 85, date: '2026-07-09' },
        { id: 6, desc: 'اشتراك نت', cat: 'فواتير', amount: 199, date: '2026-07-05', recurring: { frequency: 'شهري', nextDate: '2026-08-05' } },
        { id: 7, desc: 'بقالة', cat: 'طعام', amount: 340, date: '2026-07-04' },
        { id: 8, desc: 'بنزين', cat: 'مواصلات', amount: 200, date: '2026-07-03' },
        { id: 9, desc: 'إيجار', cat: 'فواتير', amount: 2000, date: '2026-07-01', recurring: { frequency: 'شهري', nextDate: '2026-08-01' } },
        { id: 10, desc: 'مطعم', cat: 'طعام', amount: 85, date: '2026-06-28' },
        { id: 11, desc: 'مقهى', cat: 'ترفيه', amount: 55, date: '2026-06-25' },
        { id: 12, desc: 'دواء', cat: 'صحة', amount: 120, date: '2026-06-20' },
    ];

    const categories = ['الكل', 'طعام', 'مواصلات', 'ترفيه', 'فواتير', 'صحة', 'تعليم', 'أخرى'];
    let search = $state('');
    let selectedCategory = $state('الكل');
    let sortField = $state<'date' | 'amount'>('date');
    let sortDir = $state<'asc' | 'desc'>('desc');
    let showRecurringOnly = $state(false);

    const filteredExpenses = $derived.by(() => {
        let list = [...allExpenses];

        if (search) {
            const q = search.toLowerCase();
            list = list.filter((e) => e.desc.toLowerCase().includes(q) || e.cat.toLowerCase().includes(q));
        }

        if (selectedCategory !== 'الكل') {
            list = list.filter((e) => e.cat === selectedCategory);
        }

        if (showRecurringOnly) {
            list = list.filter((e) => e.recurring);
        }

        list.sort((a, b) => {
            const valA = sortField === 'date' ? a.date : a.amount;
            const valB = sortField === 'date' ? b.date : b.amount;
            if (sortField === 'date') return sortDir === 'desc' ? valB.localeCompare(valA) : valA.localeCompare(valB);
            return sortDir === 'desc' ? valB - valA : valA - valB;
        });

        return list;
    });

    const totalFiltered = $derived(filteredExpenses.reduce((s, e) => s + e.amount, 0));

    let currentPage = $state(1);
    const perPage = 8;
    const totalPages = $derived(Math.ceil(filteredExpenses.length / perPage));
    const pagedExpenses = $derived(filteredExpenses.slice((currentPage - 1) * perPage, currentPage * perPage));

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
        selectedCategory = 'الكل';
        showRecurringOnly = false;
        currentPage = 1;
    }

    const hasFilters = $derived(search !== '' || selectedCategory !== 'الكل' || showRecurringOnly);
</script>

<AppHead title="المصاريف" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">المصاريف</h1>
            <p class="text-muted-foreground">{allExpenses.length} مصروف مسجل</p>
        </div>
        <Button class="gap-1.5">
            <Plus class="size-4" />
            إضافة مصروف
        </Button>
    </div>

    <!-- شريط البحث والفلاتر -->
    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <!-- بحث -->
                <div class="relative flex-1">
                    <input
                        type="text"
                        placeholder="ابحث عن وصف أو فئة..."
                        bind:value={search}
                        class="w-full rounded-lg border border-border bg-background pe-9 ps-9 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <Search class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                </div>

                <!-- فلتر الفئة -->
                <select
                    bind:value={selectedCategory}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    {#each categories as cat}
                        <option value={cat}>{cat === 'الكل' ? 'كل الفئات' : cat}</option>
                    {/each}
                </select>

                <!-- فلتر المتكررة -->
                <Button
                    variant={showRecurringOnly ? 'default' : 'outline'}
                    size="sm"
                    class="gap-1.5 shrink-0"
                    onclick={() => (showRecurringOnly = !showRecurringOnly)}
                >
                    <Repeat class="size-3.5" />
                    المتكررة فقط
                </Button>

                <!-- مسح الفلاتر -->
                {#if hasFilters}
                    <Button variant="ghost" size="sm" class="shrink-0 gap-1 text-muted-foreground" onclick={clearFilters}>
                        <X class="size-3.5" />
                        مسح
                    </Button>
                {/if}
            </div>
        </CardContent>
    </Card>

    <!-- ملخص سريع -->
    <div class="flex items-center gap-4 text-sm">
        <span class="text-muted-foreground">
            {filteredExpenses.length} نتيجة
            {#if hasFilters}
                <span class="text-foreground">(مُصفّى)</span>
            {/if}
        </span>
        <span class="font-bold text-destructive">المجموع: {formatCurrency(totalFiltered)}</span>
    </div>

    <!-- جدول المصاريف -->
    <Card>
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-6 py-3 text-right font-medium">الوصف</th>
                            <th class="px-6 py-3 text-right font-medium">الفئة</th>
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
                        {#each pagedExpenses as e}
                            <tr class="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        {e.desc}
                                        {#if e.recurring}
                                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[10px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400" title="متكرر: {e.recurring.frequency}">
                                                <Repeat class="size-2.5" /> متكرر
                                            </span>
                                        {/if}
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">{e.cat}</td>
                                <td class="px-6 py-3 text-muted-foreground tabular-nums">{e.date}</td>
                                <td class="px-6 py-3 font-medium tabular-nums text-destructive">{formatCurrency(e.amount)}</td>
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

            <!-- ترقيم الصفحات -->
            {#if totalPages > 1}
                <div class="flex items-center justify-between border-t px-6 py-3">
                    <span class="text-xs text-muted-foreground">
                        صفحة {currentPage} من {totalPages}
                    </span>
                    <div class="flex gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === 1}
                            onclick={() => (currentPage = Math.max(1, currentPage - 1))}
                        >
                            السابق
                        </Button>
                        {#each Array(totalPages) as _, i}
                            <Button
                                variant={currentPage === i + 1 ? 'default' : 'outline'}
                                size="sm"
                                class="min-w-[36px]"
                                onclick={() => (currentPage = i + 1)}
                            >
                                {i + 1}
                            </Button>
                        {/each}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === totalPages}
                            onclick={() => (currentPage = Math.min(totalPages, currentPage + 1))}
                        >
                            التالي
                        </Button>
                    </div>
                </div>
            {/if}
        </CardContent>
    </Card>

    <!-- المصاريف المتكررة -->
    <Card>
        <CardHeader>
            <CardTitle class="text-base">المصاريف المتكررة</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                {#each allExpenses.filter((e) => e.recurring) as e}
                    <div class="flex items-center justify-between rounded-lg border p-3">
                        <div class="flex items-center gap-3">
                            <Repeat class="size-4 text-purple-500" />
                            <div>
                                <p class="text-sm font-medium">{e.desc}</p>
                                <p class="text-xs text-muted-foreground">
                                    {e.recurring?.frequency} · التالي: {e.recurring?.nextDate}
                                </p>
                            </div>
                        </div>
                        <span class="text-sm font-bold tabular-nums">{formatCurrency(e.amount)}</span>
                    </div>
                {/each}
            </div>
        </CardContent>
    </Card>
</div>
