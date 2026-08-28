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
    import MobileHeader from '@/components/MobileHeader.svelte';
    import CategoryDonut from '@/components/CategoryDonut.svelte';
    import DateSheet from '@/components/ui/DateSheet.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import MonthlyBars from '@/components/MonthlyBars.svelte';
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
        salaryMonth?: { key: string; label: string; range: string } | null;
    }

    let {
        month = '',
        salaryMonth = null,
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
    const exportPdfHref = $derived(
        month
            ? `/reports/export-pdf?month=${encodeURIComponent(month)}`
            : '/reports/export-pdf',
    );
    const donutCategories = $derived(
        categories
            .filter((category) => category.amount > 0)
            .map((category) => ({
                id: category.id ?? category.name,
                name: category.name,
                icon: category.icon ?? 'ellipsis',
                color: category.color ?? 'var(--chart-7)',
                amount: category.amount,
            })),
    );

    let monthSheetOpen = $state(false);
    let monthIso = $state('');

    $effect(() => {
        monthIso = month ? `${month}-01` : '';
    });

    function selectMonth(iso: string): void {
        const value = iso.slice(0, 7);

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
<MobileHeader
    title="التقارير"
    subtitle={salaryMonth ? `${salaryMonth.label} · ${salaryMonth.range}` : 'اتجاهات دخلك وإنفاقك من بياناتك المسجلة'}
/>

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="hidden flex-col gap-4 md:flex md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">التقارير</h1>
            <p class="text-sm text-muted-foreground">اقرأ اتجاهات دخلك وإنفاقك من بياناتك المسجلة.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                onclick={() => (monthSheetOpen = true)}
                class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm tabular-nums transition-transform active:scale-[.99]"
            >
                <CalendarRange class="size-[18px] text-muted-foreground" />
                <span class="sr-only">شهر التقرير</span>
                {salaryMonth?.label ?? month}
            </button>
            <a href={exportHref} download class="inline-flex items-center gap-2 rounded-lg border border-input bg-card px-3 py-2 text-sm font-medium hover:bg-secondary">
                <Download class="size-4" /> تصدير CSV
            </a>
            <a href={exportPdfHref} class="inline-flex items-center gap-2 rounded-lg border border-input bg-card px-3 py-2 text-sm font-medium hover:bg-secondary">
                <Download class="size-4" /> تصدير PDF
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
                    <MonthlyBars data={monthly} />
                </CardContent>
            </Card>
        {/if}

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>وين راحت مصاريفك؟</CardTitle></CardHeader>
                <CardContent>
                    {#if donutCategories.length === 0}
                        <p class="py-8 text-center text-sm text-muted-foreground">لا توجد فئات في الفترة المحددة.</p>
                    {:else}
                        <CategoryDonut categories={donutCategories} />
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

<DateSheet bind:open={monthSheetOpen} bind:value={monthIso} title="شهر التقرير" saveLabel="عرض" onSave={selectMonth} />
