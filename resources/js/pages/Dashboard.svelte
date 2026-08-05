<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'لوحة التحكم', href: '/dashboard' },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import Button from '@/components/ui/button/Button.svelte';
    import ArrowDownRight from 'lucide-svelte/icons/arrow-down-right';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Wallet from 'lucide-svelte/icons/wallet';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Plus from 'lucide-svelte/icons/plus';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';

    interface Transaction {
        id: number;
        type: 'expense' | 'income';
        desc: string;
        cat: string;
        amount: number;
        date: string;
    }

    interface Category {
        name: string;
        amount: number;
        budget: number;
        color: string;
        prevAmount: number;
    }

    const months = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];
    const years = ['2025', '2026'];
    let selectedMonth = $state(6); // يوليو
    let selectedYear = $state('2026');

    const allMonthlyData: Record<string, { month: string; expenses: number; income: number }[]> = {
        '2025': [
            { month: 'يناير', expenses: 3200, income: 7500 },
            { month: 'فبراير', expenses: 3500, income: 7500 },
            { month: 'مارس', expenses: 2800, income: 7500 },
            { month: 'أبريل', expenses: 4000, income: 7500 },
            { month: 'مايو', expenses: 3600, income: 7800 },
            { month: 'يونيو', expenses: 3900, income: 7800 },
            { month: 'يوليو', expenses: 4100, income: 7800 },
            { month: 'أغسطس', expenses: 3800, income: 7800 },
            { month: 'سبتمبر', expenses: 3500, income: 8000 },
            { month: 'أكتوبر', expenses: 4200, income: 8000 },
            { month: 'نوفمبر', expenses: 3700, income: 8000 },
            { month: 'ديسمبر', expenses: 4500, income: 8500 },
        ],
        '2026': [
            { month: 'يناير', expenses: 3800, income: 8000 },
            { month: 'فبراير', expenses: 4100, income: 8000 },
            { month: 'مارس', expenses: 3600, income: 8000 },
            { month: 'أبريل', expenses: 4300, income: 8000 },
            { month: 'مايو', expenses: 3900, income: 8000 },
            { month: 'يونيو', expenses: 4200, income: 8000 },
            { month: 'يوليو', expenses: 4520, income: 8000 },
            { month: 'أغسطس', expenses: 4100, income: 8000 },
            { month: 'سبتمبر', expenses: 3900, income: 8000 },
            { month: 'أكتوبر', expenses: 4600, income: 8500 },
            { month: 'نوفمبر', expenses: 4300, income: 8500 },
            { month: 'ديسمبر', expenses: 5200, income: 9000 },
        ],
    };

    const monthlyData = $derived(allMonthlyData[selectedYear] || []);
    const currentMonthData = $derived(monthlyData[selectedMonth] || monthlyData[0]);
    const prevMonthData = $derived(monthlyData[selectedMonth > 0 ? selectedMonth - 1 : 0]);
    const totalExpenses = $derived(currentMonthData.expenses);
    const totalIncome = $derived(currentMonthData.income);
    const balance = $derived(totalIncome - totalExpenses);
    const savingsRate = $derived(Math.round((balance / totalIncome) * 100));
    const budgetTotal = 5000;
    const prevExpenses = $derived(prevMonthData.expenses);
    const expenseChange = $derived(Math.round(((totalExpenses - prevExpenses) / prevExpenses) * 100));

    const categories: Category[] = $derived([
        { name: 'طعام', amount: 1200, budget: 1500, prevAmount: 1050, color: '#ef4444' },
        { name: 'مواصلات', amount: 800, budget: 1000, prevAmount: 750, color: '#3b82f6' },
        { name: 'ترفيه', amount: 650, budget: 500, prevAmount: 580, color: '#eab308' },
        { name: 'فواتير', amount: 900, budget: 1000, prevAmount: 880, color: '#a855f7' },
        { name: 'صحة', amount: 400, budget: 500, prevAmount: 350, color: '#22c55e' },
        { name: 'تعليم', amount: 300, budget: 300, prevAmount: 320, color: '#6366f1' },
        { name: 'أخرى', amount: 270, budget: 200, prevAmount: 260, color: '#6b7280' },
    ]);

    const totalCatAmount = $derived(categories.reduce((s, c) => s + c.amount, 0));

    const maxExpense = $derived(Math.max(...monthlyData.map((d) => d.expenses), 1));

    // SVG donut chart calculations
    const donutSegments = $derived.by(() => {
        const total = categories.reduce((s, c) => s + c.amount, 0);
        let cumulative = 0;
        return categories.map((cat) => {
            const pct = total > 0 ? (cat.amount / total) * 100 : 0;
            const start = cumulative;
            cumulative += pct;
            return { ...cat, pct, start, end: cumulative };
        });
    });

    function donutPath(startPct: number, endPct: number): string {
        const r = 40;
        const cx = 50; const cy = 50;
        const startAngle = (startPct / 50) * Math.PI - Math.PI / 2;
        const endAngle = (endPct / 50) * Math.PI - Math.PI / 2;
        const x1 = cx + r * Math.cos(startAngle);
        const y1 = cy + r * Math.sin(startAngle);
        const x2 = cx + r * Math.cos(endAngle);
        const y2 = cy + r * Math.sin(endAngle);
        const large = endPct - startPct > 50 ? 1 : 0;
        return `M ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2}`;
    }

    const recentTransactions: Transaction[] = $derived([
        { id: 1, type: 'expense', desc: 'مطعم', cat: 'طعام', amount: 150, date: '2026-07-14' },
        { id: 2, type: 'expense', desc: 'تاكسي', cat: 'مواصلات', amount: 45, date: '2026-07-13' },
        { id: 3, type: 'income', desc: 'راتب شهري', cat: 'وظيفة', amount: 8000, date: '2026-07-01' },
        { id: 4, type: 'expense', desc: 'فاتورة كهرباء', cat: 'فواتير', amount: 320, date: '2026-07-12' },
        { id: 5, type: 'expense', desc: 'سينما', cat: 'ترفيه', amount: 120, date: '2026-07-10' },
        { id: 6, type: 'income', desc: 'عمل حر', cat: 'مستقل', amount: 500, date: '2026-07-08' },
        { id: 7, type: 'expense', desc: 'دواء', cat: 'صحة', amount: 85, date: '2026-07-09' },
        { id: 8, type: 'expense', desc: 'اشتراك نت', cat: 'فواتير', amount: 199, date: '2026-07-05' },
    ]);

    let showQuickAdd = $state(false);

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }
</script>

<AppHead title="لوحة التحكم" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- العنوان + الفلتر + أزرار سريعة -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">لوحة التحكم</h1>
            <p class="text-muted-foreground">
                ملخص مصاريفك ودخلك لشهر {months[selectedMonth]} {selectedYear}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- فلتر الشهر -->
            <select
                class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                bind:value={selectedMonth}
            >
                {#each months as month, i}
                    <option value={i}>{month}</option>
                {/each}
            </select>

            <!-- فلتر السنة -->
            <select
                class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                bind:value={selectedYear}
            >
                {#each years as year}
                    <option value={year}>{year}</option>
                {/each}
            </select>

            <!-- أزرار سريعة -->
            <div class="flex gap-2">
                <Button size="sm" class="gap-1.5">
                    <ArrowRightLeft class="size-3.5" />
                    مصروف
                </Button>
                <Button size="sm" variant="outline" class="gap-1.5">
                    <TrendingUp class="size-3.5" />
                    دخل
                </Button>
            </div>
        </div>
    </div>

    <!-- 5 بطاقات إحصائية -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">المصاريف</p>
                    <ArrowDownRight class="size-4 text-red-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(totalExpenses)}</p>
                <p class="mt-1 text-xs {expenseChange >= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}">
                    {expenseChange >= 0 ? '+' : ''}{expenseChange}% عن الشهر الماضي
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الدخل</p>
                    <ArrowUpRight class="size-4 text-green-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(totalIncome)}</p>
                <p class="mt-1 text-xs text-muted-foreground">ثابت منذ 7 أشهر</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">المتبقي</p>
                    <Wallet class="size-4 text-blue-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(balance)}</p>
                <p class="mt-1 text-xs text-muted-foreground">بعد خصم كل المصاريف</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">نسبة الادخار</p>
                    <PiggyBank class="size-4 text-emerald-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{savingsRate}%</p>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full rounded-full bg-emerald-500 transition-all"
                        style="width: {Math.min(savingsRate, 100)}%"
                    ></div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الميزانية</p>
                    <Wallet class="size-4 text-orange-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(budgetTotal - totalExpenses)}</p>
                <p class="mt-1 text-xs text-muted-foreground">متبقي من {formatCurrency(budgetTotal)}</p>
            </CardContent>
        </Card>
    </div>

    <!-- الصف الثاني: رسم دائري + رسم أعمدة -->
    <div class="grid gap-4 lg:grid-cols-2">
        <!-- رسم دائري Donut Chart -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">توزيع المصاريف حسب الفئة</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-center gap-6">
                    <svg viewBox="0 0 100 100" class="h-40 w-40 shrink-0">
                        {#each donutSegments as seg}
                            <path
                                d={donutPath(seg.start, seg.end)}
                                fill="none"
                                stroke={seg.color}
                                stroke-width="14"
                                class="hover:opacity-80 transition-opacity cursor-pointer"
                            />
                        {/each}
                        <circle cx="50" cy="50" r="28" fill="var(--color-card, #fff)" />
                        <text
                            x="50" y="47"
                            text-anchor="middle"
                            fill="currentColor"
                            class="text-sm font-bold"
                            style="direction: ltr;"
                        >{formatCurrency(totalCatAmount)}</text>
                        <text
                            x="50" y="61"
                            text-anchor="middle"
                            fill="var(--color-muted-foreground)"
                            class="text-[10px]"
                        >الإجمالي</text>
                    </svg>

                    <div class="flex-1 space-y-2 text-xs">
                        {#each donutSegments as seg}
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full shrink-0" style="background:{seg.color}"></span>
                                    <span class="truncate">{seg.name}</span>
                                </div>
                                <span class="tabular-nums text-muted-foreground shrink-0">
                                    {seg.pct.toFixed(1)}%
                                </span>
                            </div>
                        {/each}
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- رسم الأعمدة الشهري -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">المصاريف الشهرية — {selectedYear}</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-1.5 h-44">
                    {#each monthlyData as month, i}
                        {@const height = Math.round((month.expenses / maxExpense) * 100)}
                        {@const isSelected = i === selectedMonth}
                        <div class="flex flex-1 flex-col items-center gap-1 group">
                            <span class="text-[10px] text-muted-foreground tabular-nums opacity-0 group-hover:opacity-100 transition-opacity">
                                {formatCurrency(month.expenses)}
                            </span>
                            <div
                                class="w-full rounded-t-sm transition-all {isSelected ? 'bg-primary' : 'bg-primary/40 hover:bg-primary/60'}"
                                style="height: {height}%"
                                title="{month.month}: {formatCurrency(month.expenses)}"
                            ></div>
                            <span class="text-[10px] text-muted-foreground {isSelected ? 'font-bold text-foreground' : ''}">
                                {month.month}
                            </span>
                        </div>
                    {/each}
                </div>
                <div class="mt-3 flex items-center justify-center gap-4 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1">
                        <span class="size-2 rounded-sm bg-primary/40"></span> مصاريف
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-2 rounded-sm bg-primary"></span> الشهر الحالي
                    </span>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- المصاريف حسب الفئة + المقارنة -->
    <Card>
        <CardHeader>
            <CardTitle class="text-base">المصاريف حسب الفئة — مقارنة بالشهر الماضي</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                {#each categories as cat}
                    {@const pct = Math.round((cat.amount / cat.budget) * 100)}
                    {@const prevPct = Math.round((cat.prevAmount / cat.budget) * 100)}
                    {@const diff = cat.amount - cat.prevAmount}
                    {@const isOver = cat.amount > cat.budget}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" style="background:{cat.color}"></span>
                                <span>{cat.name}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="tabular-nums">{formatCurrency(cat.amount)}</span>
                                <span class="text-muted-foreground">/ {formatCurrency(cat.budget)}</span>
                                {#if diff !== 0}
                                    <span class="tabular-nums {diff > 0 ? 'text-red-500' : 'text-green-500'}">
                                        {diff > 0 ? '+' : ''}{formatCurrency(diff)}
                                    </span>
                                {/if}
                            </div>
                        </div>
                        <div class="relative h-2 w-full overflow-hidden rounded-full bg-secondary">
                            <div
                                class="absolute inset-y-0 h-full rounded-full {isOver ? 'bg-destructive' : ''}"
                                style="left: 0; width: {Math.min(pct, 100)}%; background: {isOver ? '' : cat.color}"
                            ></div>
                            <div
                                class="absolute inset-y-0 h-full border-e-2 border-background opacity-30"
                                style="left: {Math.min(prevPct, 100)}%; width: 2px"
                                title="الشهر الماضي: {prevPct}%"
                            ></div>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="{isOver ? 'text-destructive' : 'text-muted-foreground'}">
                                {isOver ? 'تجاوز بـ ' + formatCurrency(cat.amount - cat.budget) : pct + '% من الميزانية'}
                            </span>
                            <span class="text-muted-foreground">
                                الشهر الماضي: {formatCurrency(cat.prevAmount)}
                            </span>
                        </div>
                    </div>
                {/each}
            </div>
        </CardContent>
    </Card>

    <!-- آخر المعاملات -->
    <Card>
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle class="text-base">آخر المعاملات</CardTitle>
            <Button variant="ghost" size="sm" class="text-xs">عرض الكل</Button>
        </CardHeader>
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-6 py-3 text-right font-medium">الوصف</th>
                            <th class="px-6 py-3 text-right font-medium">الفئة</th>
                            <th class="px-6 py-3 text-right font-medium">التاريخ</th>
                            <th class="px-6 py-3 text-right font-medium">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each recentTransactions as txn}
                            <tr class="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="flex size-2 rounded-full {txn.type === 'expense' ? 'bg-red-500' : 'bg-green-500'}"></span>
                                        {txn.desc}
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">{txn.cat}</td>
                                <td class="px-6 py-3 text-muted-foreground">{txn.date}</td>
                                <td class="px-6 py-3 font-medium tabular-nums {txn.type === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}">
                                    {txn.type === 'expense' ? '-' : '+'}{formatCurrency(txn.amount)}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</div>
