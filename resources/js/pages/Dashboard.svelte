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
    import ChevronDown from 'lucide-svelte/icons/chevron-down';

    interface Stat {
        totalExpenses: number;
        prevExpenses: number;
        totalIncome: number;
        balance: number;
        savingsRate: number;
        budgetTotal: number;
    }

    interface Category {
        id: number;
        name: string;
        icon: string;
        color: string;
        amount: number;
        budget: number;
        prevAmount: number;
    }

    interface MonthlyItem {
        month: string;
        expenses: number;
        income: number;
    }

    interface Transaction {
        type: 'expense' | 'income';
        desc: string;
        cat: string;
        amount: number;
        date: string;
    }

    let {
        stats = {
            totalExpenses: 452000,
            prevExpenses: 420000,
            totalIncome: 800000,
            balance: 348000,
            savingsRate: 44,
            budgetTotal: 500000,
        } satisfies Stat,
        categories = [
            { id: 1, name: 'طعام', icon: 'utensils', color: '#ef4444', amount: 120000, budget: 150000, prevAmount: 105000 },
            { id: 2, name: 'مواصلات', icon: 'car', color: '#3b82f6', amount: 80000, budget: 100000, prevAmount: 75000 },
            { id: 3, name: 'ترفيه', icon: 'gamepad-2', color: '#eab308', amount: 65000, budget: 50000, prevAmount: 58000 },
            { id: 4, name: 'فواتير', icon: 'zap', color: '#a855f7', amount: 90000, budget: 100000, prevAmount: 88000 },
            { id: 5, name: 'صحة', icon: 'heart-pulse', color: '#22c55e', amount: 40000, budget: 50000, prevAmount: 35000 },
            { id: 6, name: 'تعليم', icon: 'graduation-cap', color: '#6366f1', amount: 30000, budget: 30000, prevAmount: 32000 },
            { id: 7, name: 'أخرى', icon: 'ellipsis', color: '#6b7280', amount: 27000, budget: 20000, prevAmount: 26000 },
        ] satisfies Category[],
        monthlyExpenses = [
            { month: 'يناير', expenses: 380000, income: 800000 },
            { month: 'فبراير', expenses: 410000, income: 800000 },
            { month: 'مارس', expenses: 360000, income: 800000 },
            { month: 'أبريل', expenses: 430000, income: 800000 },
            { month: 'مايو', expenses: 390000, income: 800000 },
            { month: 'يونيو', expenses: 420000, income: 800000 },
            { month: 'يوليو', expenses: 452000, income: 800000 },
            { month: 'أغسطس', expenses: 410000, income: 800000 },
            { month: 'سبتمبر', expenses: 390000, income: 800000 },
            { month: 'أكتوبر', expenses: 460000, income: 850000 },
            { month: 'نوفمبر', expenses: 430000, income: 850000 },
            { month: 'ديسمبر', expenses: 520000, income: 900000 },
        ] satisfies MonthlyItem[],
        recentTransactions = [
            { type: 'expense', desc: 'مطعم', cat: 'طعام', amount: 15000, date: '2026-07-14' },
            { type: 'expense', desc: 'تاكسي', cat: 'مواصلات', amount: 4500, date: '2026-07-13' },
            { type: 'income', desc: 'راتب شهري', cat: 'وظيفة', amount: 800000, date: '2026-07-01' },
            { type: 'expense', desc: 'فاتورة كهرباء', cat: 'فواتير', amount: 32000, date: '2026-07-12' },
            { type: 'expense', desc: 'سينما', cat: 'ترفيه', amount: 12000, date: '2026-07-10' },
            { type: 'income', desc: 'عمل حر', cat: 'مستقل', amount: 50000, date: '2026-07-08' },
            { type: 'expense', desc: 'دواء', cat: 'صحة', amount: 8500, date: '2026-07-09' },
            { type: 'expense', desc: 'اشتراك نت', cat: 'فواتير', amount: 19900, date: '2026-07-05' },
        ] satisfies Transaction[],
    }: {
        stats?: Stat;
        categories?: Category[];
        monthlyExpenses?: MonthlyItem[];
        recentTransactions?: Transaction[];
    } = $props();

    const months = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    let selectedMonth = $state(6);

    const expenseChange = $derived(
        stats.prevExpenses > 0
            ? Math.round(((stats.totalExpenses - stats.prevExpenses) / stats.prevExpenses) * 100)
            : 0,
    );

    const totalCatAmount = $derived(categories.reduce((s, c) => s + c.amount, 0));

    const maxExpense = $derived(Math.max(...monthlyExpenses.map((d) => d.expenses), 1));
    const maxIncome = $derived(Math.max(...monthlyExpenses.map((d) => d.income), 1));
    const barChartMax = $derived(Math.max(maxExpense, maxIncome));

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
        const cx = 50;
        const cy = 50;
        const startAngle = (startPct / 50) * Math.PI - Math.PI / 2;
        const endAngle = (endPct / 50) * Math.PI - Math.PI / 2;
        const x1 = cx + r * Math.cos(startAngle);
        const y1 = cy + r * Math.sin(startAngle);
        const x2 = cx + r * Math.cos(endAngle);
        const y2 = cy + r * Math.sin(endAngle);
        const large = endPct - startPct > 50 ? 1 : 0;
        return `M ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2}`;
    }

    function formatCurrency(amount: number): string {
        return (amount / 100).toLocaleString('ar-SA', { maximumFractionDigits: 2 }) + ' ر.س';
    }

    function formatDate(dateStr: string): string {
        return new Date(dateStr).toLocaleDateString('ar-SA');
    }

    let showMonthDropdown = $state(false);
</script>

<AppHead title="لوحة التحكم" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">لوحة التحكم</h1>
            <p class="text-muted-foreground">
                ملخص مصاريفك ودخلك لشهر {months[selectedMonth]}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <button
                    class="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    onclick={() => (showMonthDropdown = !showMonthDropdown)}
                >
                    {months[selectedMonth]}
                    <ChevronDown class="size-3.5 text-muted-foreground" />
                </button>
                {#if showMonthDropdown}
                    <div class="absolute left-0 z-50 mt-1 w-36 rounded-lg border border-border bg-background shadow-lg">
                        {#each months as month, i}
                            <button
                                class="w-full px-3 py-2 text-right text-sm hover:bg-muted first:rounded-t-lg last:rounded-b-lg {i === selectedMonth ? 'bg-muted font-medium' : ''}"
                                onclick={() => {
                                    selectedMonth = i;
                                    showMonthDropdown = false;
                                }}
                            >
                                {month}
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>

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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">المصاريف</p>
                    <ArrowDownRight class="size-4 text-red-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(stats.totalExpenses)}</p>
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
                <p class="mt-2 text-xl font-bold">{formatCurrency(stats.totalIncome)}</p>
                <p class="mt-1 text-xs text-muted-foreground">إجمالي دخل الشهر</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">المتبقي</p>
                    <Wallet class="size-4 text-blue-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{formatCurrency(stats.balance)}</p>
                <p class="mt-1 text-xs text-muted-foreground">بعد خصم كل المصاريف</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">نسبة الادخار</p>
                    <PiggyBank class="size-4 text-emerald-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold">{stats.savingsRate}%</p>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full rounded-full bg-emerald-500 transition-all"
                        style="width: {Math.min(stats.savingsRate, 100)}%"
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
                <p class="mt-2 text-xl font-bold">{formatCurrency(stats.budgetTotal - stats.totalExpenses)}</p>
                <p class="mt-1 text-xs text-muted-foreground">متبقي من {formatCurrency(stats.budgetTotal)}</p>
            </CardContent>
        </Card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
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
                            x="50"
                            y="47"
                            text-anchor="middle"
                            fill="currentColor"
                            class="text-[11px] font-bold"
                            style="direction: ltr;"
                        >
                            {formatCurrency(totalCatAmount)}
                        </text>
                        <text
                            x="50"
                            y="61"
                            text-anchor="middle"
                            fill="var(--color-muted-foreground)"
                            class="text-[10px]"
                        >
                            الإجمالي
                        </text>
                    </svg>

                    <div class="flex-1 space-y-2 text-xs">
                        {#each donutSegments as seg}
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="size-2 rounded-full shrink-0"
                                        style="background:{seg.color}"
                                    ></span>
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

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">المصاريف والدخل الشهري</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-1.5 h-44">
                    {#each monthlyExpenses as month, i}
                        {@const expHeight = Math.round((month.expenses / barChartMax) * 100)}
                        {@const incHeight = Math.round((month.income / barChartMax) * 100)}
                        {@const isSelected = i === selectedMonth}
                        <div class="flex flex-1 flex-col items-center gap-0.5 group">
                            <div class="flex items-end gap-0.5 h-36 w-full justify-center">
                                <div
                                    class="w-2.5 rounded-t-xs transition-all {isSelected ? 'bg-emerald-500' : 'bg-emerald-500/40 hover:bg-emerald-500/60'}"
                                    style="height: {incHeight}%"
                                    title="{month.month} — دخل: {formatCurrency(month.income)}"
                                ></div>
                                <div
                                    class="w-2.5 rounded-t-xs transition-all {isSelected ? 'bg-primary' : 'bg-primary/40 hover:bg-primary/60'}"
                                    style="height: {expHeight}%"
                                    title="{month.month} — مصاريف: {formatCurrency(month.expenses)}"
                                ></div>
                            </div>
                            <span class="text-[10px] text-muted-foreground {isSelected ? 'font-bold text-foreground' : ''}">
                                {month.month}
                            </span>
                        </div>
                    {/each}
                </div>
                <div class="mt-3 flex items-center justify-center gap-4 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1">
                        <span class="size-2 rounded-xs bg-emerald-500/40"></span> دخل
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-2 rounded-xs bg-primary/40"></span> مصاريف
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="size-2 rounded-xs bg-primary"></span> الشهر الحالي
                    </span>
                </div>
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader>
            <CardTitle class="text-base">المصاريف حسب الفئة — مقارنة بالشهر الماضي</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-5">
                {#each categories as cat}
                    {@const pct = Math.round((cat.amount / cat.budget) * 100)}
                    {@const prevPct = Math.round((cat.prevAmount / cat.budget) * 100)}
                    {@const diff = cat.amount - cat.prevAmount}
                    {@const isOver = cat.amount > cat.budget}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full"
                                    style="background:{cat.color}"
                                ></span>
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
                                {isOver
                                    ? 'تجاوز بـ ' + formatCurrency(cat.amount - cat.budget)
                                    : pct + '% من الميزانية'}
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
                                <td class="px-6 py-3 text-muted-foreground whitespace-nowrap">
                                    {formatDate(txn.date)}
                                </td>
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
