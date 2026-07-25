<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'الميزانيات',
                href: '/budgets',
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import Button from '@/components/ui/button/button.svelte';
    import { Plus, AlertTriangle } from 'lucide-svelte';

    const budgets = [
        { name: 'طعام', budget: 1500, spent: 1200, color: 'bg-red-500 dark:bg-red-400' },
        { name: 'مواصلات', budget: 1000, spent: 800, color: 'bg-blue-500 dark:bg-blue-400' },
        { name: 'ترفيه', budget: 500, spent: 650, color: 'bg-yellow-500 dark:bg-yellow-400' },
        { name: 'فواتير', budget: 1000, spent: 900, color: 'bg-purple-500 dark:bg-purple-400' },
        { name: 'صحة', budget: 500, spent: 400, color: 'bg-green-500 dark:bg-green-400' },
        { name: 'تعليم', budget: 300, spent: 300, color: 'bg-indigo-500 dark:bg-indigo-400' },
        { name: 'أخرى', budget: 200, spent: 270, color: 'bg-gray-500 dark:bg-gray-400' },
    ];

    const totalBudget = budgets.reduce((s, b) => s + b.budget, 0);
    const totalSpent = budgets.reduce((s, b) => s + b.spent, 0);

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }
</script>

<AppHead title="الميزانيات" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">الميزانيات</h1>
            <p class="text-muted-foreground">حدد ميزانية لكل فئة وراقب إنفاقك</p>
        </div>
        <Button>
            <Plus class="size-4 ms-0 me-2" />
            إضافة ميزانية
        </Button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">إجمالي الميزانية</p>
                <p class="text-2xl font-bold">{formatCurrency(totalBudget)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">إجمالي المنفق</p>
                <p class="text-2xl font-bold text-destructive">{formatCurrency(totalSpent)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">المتبقي</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{formatCurrency(totalBudget - totalSpent)}</p>
            </CardContent>
        </Card>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        {#each budgets as b}
            {@const pct = Math.round((b.spent / b.budget) * 100)}
            {@const isOver = b.spent > b.budget}
            <Card class={isOver ? 'border-destructive/50' : ''}>
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base">{b.name}</CardTitle>
                        {#if pct >= 80}
                            <span class="flex items-center gap-1 text-xs text-destructive">
                                <AlertTriangle class="size-3" />
                                {isOver ? 'تجاوز' : 'اقترب من الحد'}
                            </span>
                        {/if}
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{formatCurrency(b.spent)}</p>
                    <p class="text-sm text-muted-foreground">من {formatCurrency(b.budget)}</p>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full rounded-full transition-all {isOver ? 'bg-destructive' : b.color}"
                            style="width: {Math.min(pct, 100)}%"
                        ></div>
                    </div>
                    <p class="mt-2 text-xs {isOver ? 'text-destructive font-medium' : 'text-muted-foreground'}">
                        {pct}% مستخدم
                        {#if isOver}
                            - تجاوزت بـ {formatCurrency(b.spent - b.budget)}
                        {:else}
                            - متبقي {formatCurrency(b.budget - b.spent)}
                        {/if}
                    </p>
                </CardContent>
            </Card>
        {/each}
    </div>
</div>
