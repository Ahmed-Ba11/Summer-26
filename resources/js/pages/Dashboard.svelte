<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'لوحة التحكم', href: '/dashboard' },
        ],
    };
</script>

<script lang="ts">
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowDownRight from 'lucide-svelte/icons/arrow-down-right';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import ChartNoAxesColumn from 'lucide-svelte/icons/chart-no-axes-column';
    import CheckCircle from 'lucide-svelte/icons/check-circle';
    import Info from 'lucide-svelte/icons/info';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Wallet from 'lucide-svelte/icons/wallet';
    import AppHead from '@/components/AppHead.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
        recentTransactions = [],
        totalSavings = 0,
        totalInstallmentsMonthly = 0,
        totalBillsDue = 0,
        activeInstallments = 0,
        upcomingBills = 0,
    }: {
        stats?: Stat;
        categories?: Category[];
        monthlyExpenses?: MonthlyItem[];
        recentTransactions?: Transaction[];
        totalSavings?: number;
        totalInstallmentsMonthly?: number;
        totalBillsDue?: number;
        activeInstallments?: number;
        upcomingBills?: number;
    } = $props();

    const allCommitments = $derived(
        stats.totalExpenses + totalInstallmentsMonthly + totalBillsDue + totalSavings,
    );

    const netMoney = $derived(stats.totalIncome - allCommitments);

    const budgetRemaining = $derived(stats.budgetTotal - stats.totalExpenses);

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

    const overspendingCategories = $derived(
        categories
            .filter((c) => c.budget > 0 && c.amount / c.budget >= 0.8)
            .sort((a, b) => b.amount / b.budget - a.amount / a.budget)
            .slice(0, 3),
    );

    const hasOverBudget = $derived(
        categories.some((c) => c.budget > 0 && c.amount > c.budget),
    );
    const hasNearBudget = $derived(
        categories.some(
            (c) => c.budget > 0 && c.amount / c.budget >= 0.8 && c.amount <= c.budget,
        ),
    );

    const motivationalStatus = $derived(
        hasOverBudget ? 'over' : hasNearBudget ? 'near' : 'under',
    );

    const months = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    let selectedMonth = $state(6);

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

    function overspendPct(cat: Category): number {
        return cat.budget > 0 ? Math.round((cat.amount / cat.budget) * 100) : 0;
    }

    let showMonthDropdown = $state(false);
</script>

<AppHead title="لوحة التحكم" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">

    <!-- Section 1: Top Banner -->
    <Card class="bg-gradient-to-l from-primary/10 to-primary/5 border-primary/20">
        <CardContent class="p-6 sm:p-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/20">
                        <Wallet class="size-6 text-primary" />
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">صافي المال</p>
                        <p class="text-2xl font-bold tabular-nums sm:text-3xl {netMoney >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}">
                            {formatCurrency(netMoney)}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            الدخل ({formatCurrency(stats.totalIncome)}) - الالتزامات ({formatCurrency(allCommitments)})
                        </p>
                    </div>
                </div>

                <div class="hidden sm:block h-16 w-px bg-border"></div>

                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20">
                        <TrendingUp class="size-6 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">المتبقي من الميزانية</p>
                        <p class="text-2xl font-bold tabular-nums sm:text-3xl {budgetRemaining >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}">
                            {formatCurrency(budgetRemaining)}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            من إجمالي {formatCurrency(stats.budgetTotal)}
                        </p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Section 2: Summary Cards Row -->
    <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">إجمالي الدخل</p>
                    <ArrowUpRight class="size-5 text-green-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums">{formatCurrency(stats.totalIncome)}</p>
                <p class="mt-1 text-xs text-muted-foreground">دخل الشهر الحالي</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">إجمالي المصروفات</p>
                    <ArrowDownRight class="size-5 text-red-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums">{formatCurrency(stats.totalExpenses)}</p>
                <p class="mt-1 text-xs text-muted-foreground">مصاريف الشهر الحالي</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الادخار</p>
                    <PiggyBank class="size-5 text-emerald-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums">{formatCurrency(totalSavings)}</p>
                <p class="mt-1 text-xs text-muted-foreground">نسبة الادخار {stats.savingsRate}%</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الأقساط الشهرية</p>
                    <CreditCard class="size-5 text-orange-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums">{formatCurrency(totalInstallmentsMonthly)}</p>
                <p class="mt-1 text-xs text-muted-foreground">{activeInstallments} أقساط نشطة</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">الفواتير المستحقة</p>
                    <ReceiptText class="size-5 text-purple-500 shrink-0" />
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums">{formatCurrency(totalBillsDue)}</p>
                <p class="mt-1 text-xs text-muted-foreground">{upcomingBills} فواتير معلقة</p>
            </CardContent>
        </Card>
    </div>

    <!-- Section 3: Charts -->
    <div class="grid gap-4 lg:grid-cols-2">
        <Card>
            <CardHeader class="pb-2">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-base">توزيع المصاريف حسب الفئة</CardTitle>
                    <div class="relative">
                        <button
                            class="flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                            onclick={() => (showMonthDropdown = !showMonthDropdown)}
                        >
                            {months[selectedMonth]}
                            <ChevronDown class="size-3 text-muted-foreground" />
                        </button>
                        {#if showMonthDropdown}
                            <div class="absolute left-0 z-50 mt-1 w-32 rounded-lg border border-border bg-background shadow-lg">
                                {#each months as month, i}
                                    <button
                                        class="w-full px-3 py-1.5 text-right text-xs hover:bg-muted first:rounded-t-lg last:rounded-b-lg {i === selectedMonth ? 'bg-muted font-medium' : ''}"
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
                </div>
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
                                <div class="flex items-center gap-1.5 min-w-0">
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
                <CardTitle class="text-base">المصاريف الشهرية</CardTitle>
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

    <!-- Section 4: Top 3 Overspending Categories -->
    {#if overspendingCategories.length > 0}
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-base">الفئات القريبة أو المتجاوزة للميزانية</CardTitle>
                <Button variant="ghost" size="sm" class="gap-1.5 text-xs" href="/budgets">
                    عرض كل الميزانيات
                    <ArrowLeft class="size-3.5" />
                </Button>
            </CardHeader>
            <CardContent>
                <div class="grid gap-5 sm:grid-cols-3">
                    {#each overspendingCategories as cat}
                        {@const pct = overspendPct(cat)}
                        {@const isOver = cat.amount > cat.budget}
                        <div class="rounded-lg border p-4 {isOver ? 'border-destructive/30 bg-destructive/5' : 'border-orange-500/30 bg-orange-500/5'}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full"
                                        style="background:{cat.color}"
                                    ></span>
                                    <span class="text-sm font-medium">{cat.name}</span>
                                </div>
                                <span class="text-xs tabular-nums {isOver ? 'font-bold text-destructive' : 'font-medium text-orange-600 dark:text-orange-400'}">
                                    {pct}%
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                <div
                                    class="h-full rounded-full transition-all {isOver ? 'bg-destructive' : 'bg-orange-500'}"
                                    style="width: {Math.min(pct, 100)}%"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5">
                                <span class="text-xs tabular-nums text-muted-foreground">
                                    {formatCurrency(cat.amount)}
                                </span>
                                <span class="text-xs tabular-nums text-muted-foreground">
                                    من {formatCurrency(cat.budget)}
                                </span>
                            </div>
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}

    <!-- Section 5: Motivational Message -->
    <Card class="{motivationalStatus === 'under' ? 'border-emerald-500/30 bg-emerald-500/5' : motivationalStatus === 'near' ? 'border-yellow-500/30 bg-yellow-500/5' : 'border-destructive/30 bg-destructive/5'}">
        <CardContent class="flex items-center gap-4 p-6">
            {#if motivationalStatus === 'under'}
                <CheckCircle class="size-8 text-emerald-500 shrink-0" />
                <div>
                    <p class="text-lg font-semibold text-emerald-700 dark:text-emerald-300">
                        أحسنت! أنت على الطريق الصحيح 💚
                    </p>
                    <p class="text-sm text-muted-foreground">كل فئاتك ضمن الميزانية المحددة، استمر على هذا النهج</p>
                </div>
            {:else if motivationalStatus === 'near'}
                <Info class="size-8 text-yellow-500 shrink-0" />
                <div>
                    <p class="text-lg font-semibold text-yellow-700 dark:text-yellow-300">
                        انتبه! بعض الفئات قاربت حد الميزانية 🟡
                    </p>
                    <p class="text-sm text-muted-foreground">راقب إنفاقك في الفئات القريبة من الحد لتجنب التجاوز</p>
                </div>
            {:else}
                <AlertTriangle class="size-8 text-destructive shrink-0" />
                <div>
                    <p class="text-lg font-semibold text-destructive">
                        بعض الفئات تجاوزت الميزانية، راجع إنفاقك 🔴
                    </p>
                    <p class="text-sm text-muted-foreground">حاول ضبط مصاريفك في الفئات المتجاوزة أو تعديل الميزانية</p>
                </div>
            {/if}
        </CardContent>
    </Card>

    <!-- Section 6: Recent Transactions -->
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
