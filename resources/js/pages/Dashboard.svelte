<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'لوحة التحكم',
                href: '/dashboard',
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import ArrowDownRight from 'lucide-svelte/icons/arrow-down-right';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Wallet from 'lucide-svelte/icons/wallet';
    import Target from 'lucide-svelte/icons/target';

    const totalExpenses = 4520;
    const totalIncome = 8000;
    const balance = totalIncome - totalExpenses;
    const budgetTotal = 5000;

    const categories = [
        { name: 'طعام', amount: 1200, budget: 1500, color: 'bg-red-500 dark:bg-red-400' },
        { name: 'مواصلات', amount: 800, budget: 1000, color: 'bg-blue-500 dark:bg-blue-400' },
        { name: 'ترفيه', amount: 650, budget: 500, color: 'bg-yellow-500 dark:bg-yellow-400' },
        { name: 'فواتير', amount: 900, budget: 1000, color: 'bg-purple-500 dark:bg-purple-400' },
        { name: 'صحة', amount: 400, budget: 500, color: 'bg-green-500 dark:bg-green-400' },
        { name: 'تعليم', amount: 300, budget: 300, color: 'bg-indigo-500 dark:bg-indigo-400' },
        { name: 'أخرى', amount: 270, budget: 200, color: 'bg-gray-500 dark:bg-gray-400' },
    ];

    const monthlyData = [
        { month: 'يناير', expenses: 3800, income: 8000 },
        { month: 'فبراير', expenses: 4100, income: 8000 },
        { month: 'مارس', expenses: 3600, income: 8000 },
        { month: 'أبريل', expenses: 4300, income: 8000 },
        { month: 'مايو', expenses: 3900, income: 8000 },
        { month: 'يونيو', expenses: 4200, income: 8000 },
        { month: 'يوليو', expenses: 4520, income: 8000 },
    ];

    const maxExpense = Math.max(...monthlyData.map((d) => d.expenses));

    const recentTransactions = [
        { id: 1, type: 'expense', desc: 'مطعم', cat: 'طعام', amount: 150, date: '2026-07-14' },
        { id: 2, type: 'expense', desc: 'تاكسي', cat: 'مواصلات', amount: 45, date: '2026-07-13' },
        { id: 3, type: 'income', desc: 'راتب شهري', cat: 'وظيفة', amount: 8000, date: '2026-07-01' },
        { id: 4, type: 'expense', desc: 'فاتورة كهرباء', cat: 'فواتير', amount: 320, date: '2026-07-12' },
        { id: 5, type: 'expense', desc: 'سينما', cat: 'ترفيه', amount: 120, date: '2026-07-10' },
        { id: 6, type: 'income', desc: 'عمل حر', cat: 'مستقل', amount: 500, date: '2026-07-08' },
        { id: 7, type: 'expense', desc: 'دواء', cat: 'صحة', amount: 85, date: '2026-07-09' },
        { id: 8, type: 'expense', desc: 'اشتراك نت', cat: 'فواتير', amount: 199, date: '2026-07-05' },
    ];

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }
</script>

<AppHead title="لوحة التحكم" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div>
        <h1 class="text-2xl font-bold">لوحة التحكم</h1>
        <p class="text-muted-foreground">ملخص مصاريفك ودخلك لشهر يوليو 2026</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-sm font-medium">إجمالي المصاريف</CardTitle>
                <span class="flex size-9 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                    <ArrowDownRight class="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-bold">{formatCurrency(totalExpenses)}</p>
                <p class="text-xs text-muted-foreground">+8% عن الشهر الماضي</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-sm font-medium">إجمالي الدخل</CardTitle>
                <span class="flex size-9 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                    <ArrowUpRight class="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-bold">{formatCurrency(totalIncome)}</p>
                <p class="text-xs text-muted-foreground">ثابت منذ 7 أشهر</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-sm font-medium">المتبقي</CardTitle>
                <span class="flex size-9 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                    <Wallet class="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-bold">{formatCurrency(balance)}</p>
                <p class="text-xs text-muted-foreground">بعد خصم كل المصاريف</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-sm font-medium">الميزانية المتبقية</CardTitle>
                <span class="flex size-9 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                    <Target class="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-bold">{formatCurrency(budgetTotal - totalExpenses)}</p>
                <p class="text-xs text-muted-foreground">من أصل {formatCurrency(budgetTotal)}</p>
            </CardContent>
        </Card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">المصاريف حسب الفئة</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    {#each categories as cat}
                        {@const pct = Math.round((cat.amount / cat.budget) * 100)}
                        {@const isOver = cat.amount > cat.budget}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span>{cat.name}</span>
                                <span class="text-muted-foreground">
                                    {formatCurrency(cat.amount)}
                                    <span class="text-xs"> / {formatCurrency(cat.budget)}</span>
                                </span>
                            </div>
                            <div class="relative h-2 w-full overflow-hidden rounded-full bg-secondary">
                                <div
                                    class="h-full rounded-full transition-all {cat.color}"
                                    style="width: {Math.min(pct, 100)}%"
                                ></div>
                            </div>
                            <div class="text-xs {isOver ? 'text-destructive' : 'text-muted-foreground'}">
                                {isOver ? 'تجاوزت الميزانية بـ ' + formatCurrency(cat.amount - cat.budget) : pct + '% من الميزانية'}
                            </div>
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">المصاريف الشهرية</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-2 h-48">
                    {#each monthlyData as month}
                        {@const height = Math.round((month.expenses / maxExpense) * 100)}
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <span class="text-xs text-muted-foreground tabular-nums">{formatCurrency(month.expenses)}</span>
                            <div
                                class="w-full rounded-t-md bg-primary/80 hover:bg-primary transition-colors"
                                style="height: {height}%"
                            ></div>
                            <span class="text-xs text-muted-foreground">{month.month}</span>
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader>
            <CardTitle class="text-base">آخر المعاملات</CardTitle>
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
                                <td class="px-6 py-3 font-medium {txn.type === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}">
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
