<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الميزانيات', href: '/budgets' },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import Button from '@/components/ui/button/Button.svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';
    import Settings from 'lucide-svelte/icons/settings';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Wallet from 'lucide-svelte/icons/wallet';

    interface Budget {
        name: string;
        icon: string;
        budget: number;
        spent: number;
        color: string;
        rollover: number;
        isOverBudget: boolean;
        isWarning: boolean;
    }

    const budgets: Budget[] = $state([
        { name: 'طعام', icon: '🍔', budget: 1500, spent: 1200, color: '#ef4444', rollover: 0, isOverBudget: false, isWarning: false },
        { name: 'مواصلات', icon: '🚗', budget: 1000, spent: 800, color: '#3b82f6', rollover: 50, isOverBudget: false, isWarning: false },
        { name: 'ترفيه', icon: '🎮', budget: 500, spent: 650, color: '#eab308', rollover: 0, isOverBudget: true, isWarning: true },
        { name: 'فواتير', icon: '⚡', budget: 1000, spent: 900, color: '#a855f7', rollover: 0, isOverBudget: false, isWarning: true },
        { name: 'صحة', icon: '💊', budget: 500, spent: 400, color: '#22c55e', rollover: 20, isOverBudget: false, isWarning: false },
        { name: 'تعليم', icon: '📚', budget: 300, spent: 300, color: '#6366f1', rollover: 0, isOverBudget: false, isWarning: false },
        { name: 'أخرى', icon: '📦', budget: 200, spent: 270, color: '#6b7280', rollover: 0, isOverBudget: true, isWarning: true },
    ]);

    const totalBudget = $derived(budgets.reduce((s, b) => s + b.budget, 0));
    const totalSpent = $derived(budgets.reduce((s, b) => s + b.spent, 0));
    const totalRollover = $derived(budgets.reduce((s, b) => s + b.rollover, 0));
    const effectiveBudget = $derived(totalBudget + totalRollover);

    const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    let currentMonth = $state('يوليو 2026');

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }

    function getProgressColor(budget: Budget): string {
        if (budget.isOverBudget) return 'bg-destructive';
        if (budget.isWarning) return 'bg-yellow-500';
        return '';
    }
</script>

<AppHead title="الميزانيات" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">الميزانيات</h1>
            <p class="text-muted-foreground">حدد ميزانية لكل فئة وراقب إنفاقك — {currentMonth}</p>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" size="sm" class="gap-1.5">
                <RefreshCw class="size-3.5" />
                تجديد تلقائي
            </Button>
            <Button size="sm" class="gap-1.5">
                <Plus class="size-3.5" />
                إضافة فئة
            </Button>
        </div>
    </div>

    <!-- ملخص الميزانية -->
    <div class="grid gap-4 sm:grid-cols-4">
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">إجمالي الميزانية</p>
                <p class="text-xl font-bold">{formatCurrency(totalBudget)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">المنفق</p>
                <p class="text-xl font-bold text-destructive">{formatCurrency(totalSpent)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">المتبقي</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400">{formatCurrency(effectiveBudget - totalSpent)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">فائض مرّحل</p>
                <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{formatCurrency(totalRollover)}</p>
            </CardContent>
        </Card>
    </div>

    <!-- شبكة الميزانيات -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {#each budgets as b}
            {@const pct = Math.round((b.spent / (b.budget + b.rollover)) * 100)}
            {@const effectiveTotal = b.budget + b.rollover}
            <Card class="overflow-hidden transition-all {b.isOverBudget ? 'border-destructive/50 shadow-destructive/10 shadow-sm' : ''} {b.isWarning && !b.isOverBudget ? 'border-yellow-500/50' : ''}">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">{b.icon}</span>
                            <CardTitle class="text-base">{b.name}</CardTitle>
                        </div>
                        <div class="flex items-center gap-2">
                            {#if b.isOverBudget}
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    <AlertTriangle class="size-2.5" /> تجاوز
                                </span>
                            {:else if b.isWarning}
                                <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-[10px] font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                    <AlertTriangle class="size-2.5" /> اقترب
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    <CircleCheck class="size-2.5" /> آمن
                                </span>
                            {/if}
                            <button class="text-muted-foreground hover:text-foreground cursor-pointer">
                                <Settings class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="mb-3 flex items-baseline justify-between">
                        <p class="text-2xl font-bold tabular-nums">{formatCurrency(b.spent)}</p>
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">الميزانية: {formatCurrency(b.budget)}</p>
                            {#if b.rollover > 0}
                                <p class="text-xs text-blue-600 dark:text-blue-400">
                                    +{formatCurrency(b.rollover)} فائض مرّحل
                                </p>
                            {/if}
                        </div>
                    </div>

                    <!-- شريط التقدم -->
                    <div class="relative h-3 w-full overflow-hidden rounded-full bg-secondary">
                        <div
                            class="absolute inset-y-0 h-full rounded-full transition-all {getProgressColor(b)}"
                            style="left: 0; width: {Math.min(pct, 100)}%; {!b.isOverBudget && !b.isWarning ? 'background:' + b.color : ''}"
                        ></div>
                        <!-- علامة 80% -->
                        <div class="absolute inset-y-0 w-0.5 bg-background/50" style="left: 80%"></div>
                        <!-- خط الفائض -->
                        {#if b.rollover > 0}
                            <div class="absolute inset-y-0 w-0.5 bg-blue-400" style="left: {Math.round((b.budget / effectiveTotal) * 100)}%"></div>
                        {/if}
                    </div>

                    <!-- معلومات إضافية -->
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="{b.isOverBudget ? 'text-destructive font-medium' : 'text-muted-foreground'} tabular-nums">
                            {pct}% مستخدم
                        </span>
                        <span class="text-muted-foreground tabular-nums">
                            {#if b.isOverBudget}
                                تجاوز بـ {formatCurrency(b.spent - effectiveTotal)}
                            {:else}
                                متبقي {formatCurrency(effectiveTotal - b.spent)}
                            {/if}
                        </span>
                    </div>

                    <!-- أزرار سريعة -->
                    <div class="mt-3 flex gap-2 border-t pt-3">
                        <Button variant="outline" size="sm" class="flex-1 text-xs gap-1">
                            <ArrowRightLeft class="size-3" />
                            عرض المصاريف
                        </Button>
                    </div>
                </CardContent>
            </Card>
        {/each}
    </div>

    <!-- فئات مخصصة - إضافة فئة جديدة -->
    <Card class="border-dashed">
        <CardContent class="flex items-center justify-center py-8">
            <div class="text-center">
                <div class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-muted">
                    <Plus class="size-6 text-muted-foreground" />
                </div>
                <p class="text-sm font-medium">إضافة فئة مخصصة</p>
                <p class="text-xs text-muted-foreground">أنشئ فئة جديدة مع أيقونة ولون خاصين بك</p>
            </div>
        </CardContent>
    </Card>
</div>
