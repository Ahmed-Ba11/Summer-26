<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'التقارير', href: '/reports' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import CalendarRange from 'lucide-svelte/icons/calendar-range';
    import Download from 'lucide-svelte/icons/download';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import WalletCards from 'lucide-svelte/icons/wallet-cards';
    import AppHead from '@/components/AppHead.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { formatCurrency, formatDate, formatPercent } from '@/lib/format';
    import type {
        ReportCategory,
        ReportMonthlyPoint,
        ReportSummary,
        ReportTopExpense,
    } from '@/types';

    interface PageProps {
        month?: string;
        summary?: ReportSummary;
        monthly?: ReportMonthlyPoint[];
        categories?: ReportCategory[];
        topExpenses?: ReportTopExpense[];
        hasData?: boolean;
        error?: string | null;
    }

    let {
        month = '',
        summary = {},
        monthly = [],
        categories = [],
        topExpenses = [],
        hasData = false,
        error = null,
    }: PageProps = $props();

    const netSavings = $derived(summary.net_savings ?? summary.net ?? 0);
    const exportHref = $derived(
        month
            ? `/reports/export?month=${encodeURIComponent(month)}`
            : '/reports/export',
    );
    const maxMonthlyAmount = $derived(
        Math.max(0, ...monthly.flatMap((point) => [point.income, point.expenses])),
    );

    function barHeight(value: number): number {
        return maxMonthlyAmount > 0
            ? Math.max(4, Math.round((value / maxMonthlyAmount) * 100))
            : 0;
    }

    function selectMonth(event: Event): void {
        const value = (event.currentTarget as HTMLInputElement).value;

        if (!value || value === month) {
            return;
        }

        router.get('/reports', { month: value }, {
            preserveScroll: true,
            replace: true,
        });
    }
</script>

<AppHead title="التقارير" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">التقارير</h1>
            <p class="text-sm text-muted-foreground">اقرأ اتجاهات دخلك وإنفاقك من بياناتك المسجلة.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm">
                <CalendarRange class="size-4 text-muted-foreground" />
                <span class="sr-only">شهر التقرير</span>
                <input type="month" value={month} onchange={selectMonth} class="bg-transparent text-sm outline-none" />
            </label>
            <a href={exportHref} download class="inline-flex items-center gap-2 rounded-lg border border-input bg-card px-3 py-2 text-sm font-medium hover:bg-secondary">
                <Download class="size-4" /> تصدير CSV
            </a>
        </div>
    </div>

    {#if error}
        <Card class="border-destructive/40">
            <CardContent class="flex items-center gap-3 py-5 text-destructive">
                <BarChart3 class="size-5" />
                <p>{error}</p>
            </CardContent>
        </Card>
    {:else if !hasData}
        <Card>
            <EmptyState icon={BarChart3} title="لا توجد بيانات للتقرير" description="سجّل دخلاً أو مصروفاً لتظهر المقارنات والاتجاهات هنا." />
        </Card>
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between text-muted-foreground"><p class="text-sm">إجمالي الدخل</p><TrendingUp class="size-4" /></div>
                    <p class="mt-2 text-xl font-semibold tabular-nums">{formatCurrency(summary.total_income ?? 0)}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between text-muted-foreground"><p class="text-sm">إجمالي المصاريف</p><TrendingDown class="size-4" /></div>
                    <p class="mt-2 text-xl font-semibold tabular-nums text-destructive">{formatCurrency(summary.total_expenses ?? 0)}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between text-muted-foreground"><p class="text-sm">صافي الادخار</p><WalletCards class="size-4" /></div>
                    <p class="mt-2 text-xl font-semibold tabular-nums">{formatCurrency(netSavings)}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between text-muted-foreground"><p class="text-sm">نسبة الادخار</p><BarChart3 class="size-4" /></div>
                    <p class="mt-2 text-xl font-semibold tabular-nums">{formatPercent(summary.savings_rate ?? 0)}</p>
                </CardContent>
            </Card>
        </div>

        {#if monthly.some((point) => point.income > 0 || point.expenses > 0)}
            <Card>
                <CardHeader><CardTitle>الدخل مقابل المصاريف</CardTitle></CardHeader>
                <CardContent>
                    <div class="flex h-56 items-end gap-2 overflow-x-auto border-b border-border pb-8 sm:gap-4">
                        {#each monthly as point (point.month)}
                            <div class="flex min-w-12 flex-1 flex-col items-center justify-end gap-2 self-stretch">
                                <div class="flex h-full items-end gap-1">
                                    <span class="w-3 rounded-t bg-[var(--chart-3)]" style="height: {barHeight(point.income)}%" title={`الدخل: ${formatCurrency(point.income)}`}></span>
                                    <span class="w-3 rounded-t bg-[var(--chart-1)]" style="height: {barHeight(point.expenses)}%" title={`المصاريف: ${formatCurrency(point.expenses)}`}></span>
                                </div>
                                <span class="text-xs text-muted-foreground">{formatDate(`${point.month}-01`)}</span>
                            </div>
                        {/each}
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-2"><i class="size-2.5 rounded-sm bg-[var(--chart-3)]"></i>الدخل</span>
                        <span class="inline-flex items-center gap-2"><i class="size-2.5 rounded-sm bg-[var(--chart-1)]"></i>المصاريف</span>
                    </div>
                </CardContent>
            </Card>
        {/if}

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>المصاريف حسب الفئة</CardTitle></CardHeader>
                <CardContent>
                    {#if categories.filter((category) => category.amount > 0).length === 0}
                        <p class="py-8 text-center text-sm text-muted-foreground">لا توجد فئات في الفترة المحددة.</p>
                    {:else}
                        <div class="flex flex-col gap-3">
                            {#each categories.filter((category) => category.amount > 0) as category (category.id ?? category.name)}
                                <div class="flex items-center gap-3">
                                    <CategoryIcon icon={category.icon ?? 'ellipsis'} color={category.color ?? 'var(--chart-7)'} size="sm" />
                                    <span class="min-w-0 flex-1 truncate text-sm">{category.name}</span>
                                    <span class="text-sm font-semibold tabular-nums">{formatCurrency(category.amount)}</span>
                                    {#if category.budget > 0}
                                        <span class="w-28 text-end text-xs text-muted-foreground tabular-nums">{formatPercent(category.percentage)} من الميزانية</span>
                                    {:else}
                                        <span class="w-28 text-end text-xs text-muted-foreground">لا توجد ميزانية</span>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {/if}
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>أعلى المصاريف</CardTitle></CardHeader>
                <CardContent>
                    {#if topExpenses.length === 0}
                        <p class="py-8 text-center text-sm text-muted-foreground">لا توجد مصاريف في الفترة المحددة.</p>
                    {:else}
                        <div class="flex flex-col gap-3">
                            {#each topExpenses as expense (expense.id)}
                                <div class="flex items-center gap-3 rounded-lg border border-border p-3">
                                    <CategoryIcon icon={expense.icon ?? 'ellipsis'} color="var(--chart-2)" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{expense.description || expense.category || 'مصروف'}</p>
                                        <p class="text-xs text-muted-foreground tabular-nums">{formatDate(expense.date)}</p>
                                    </div>
                                    <span class="text-sm font-semibold tabular-nums text-destructive">{formatCurrency(expense.amount)}</span>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </CardContent>
            </Card>
        </div>
    {/if}
</div>
