<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'التقارير', href: '/reports' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import BarChart3 from 'lucide-svelte/icons/bar-chart-3';
    import CalendarRange from 'lucide-svelte/icons/calendar-range';
    import Check from 'lucide-svelte/icons/check';
    import FileText from 'lucide-svelte/icons/file-text';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import WalletCards from 'lucide-svelte/icons/wallet-cards';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import CategoryDonut from '@/components/CategoryDonut.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import MonthlyBars from '@/components/MonthlyBars.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import { formatCurrency, formatDate, formatPercent } from '@/lib/format';
    import type {
        ReportCategory,
        ReportMonthlyPoint,
        ReportSummary,
        ReportTopExpense,
    } from '@/types';

    /** المدد الصريحة — لا «شهرياً» وحدها. */
    type ReportRange = '15d' | '30d' | '60d' | 'month';

    interface PageProps {
        month?: string | null;
        range?: ReportRange;
        periodLabel?: string;
        periodRange?: string;
        availableMonths?: { value: string; label: string }[];
        summary?: ReportSummary;
        monthly?: ReportMonthlyPoint[];
        categories?: ReportCategory[];
        topExpenses?: ReportTopExpense[];
        hasData?: boolean;
        error?: string | null;
    }

    let {
        month = null,
        range = 'month',
        periodLabel = '',
        periodRange = '',
        availableMonths = [],
        summary = {},
        monthly = [],
        categories = [],
        topExpenses = [],
        hasData = false,
        error = null,
    }: PageProps = $props();

    const RANGES: { value: ReportRange; label: string }[] = [
        { value: '15d', label: 'آخر 15 يوم' },
        { value: '30d', label: 'آخر 30 يوم' },
        { value: '60d', label: 'آخر 60 يوم' },
        { value: 'month', label: 'شهر محدّد' },
    ];

    let monthSheetOpen = $state(false);

    /** المدى المتدحرج ينتقل مباشرة، و«شهر محدّد» يفتح قائمة الأشهر. */
    function pickRange(value: ReportRange): void {
        if (value === 'month') {
            monthSheetOpen = true;

            return;
        }

        if (value === range) {
            return;
        }

        router.get(
            '/reports',
            { range: value },
            { preserveScroll: true, replace: true },
        );
    }

    function pickMonth(value: string): void {
        monthSheetOpen = false;

        router.get(
            '/reports',
            { range: 'month', month: value },
            { preserveScroll: true, replace: true },
        );
    }

    const netSavings = $derived(summary.net_savings ?? summary.net ?? 0);

    /** التصدير يتبع المدّة المعروضة — لا يصدّر شهراً والشاشة تعرض أسبوعين. */
    const exportPdfHref = $derived(
        range === 'month' && month
            ? `/reports/export-pdf?range=month&month=${encodeURIComponent(month)}`
            : `/reports/export-pdf?range=${range}`,
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

</script>

<AppHead title="التقارير" />
<MobileHeader
    title="التقارير"
    subtitle={periodLabel ? `${periodLabel} · ${periodRange}` : 'اتجاهات دخلك وإنفاقك من بياناتك المسجلة'}
/>

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="hidden flex-col gap-1 md:flex">
        <h1 class="text-2xl font-semibold">التقارير</h1>
        <p class="text-sm text-muted-foreground">اقرأ اتجاهات دخلك وإنفاقك من بياناتك المسجلة.</p>
    </div>

    <!--
        المدّة — خيارات صريحة معروضة كلّها، لا قائمة منسدلة تخفيها.
        المستخدم يرى المدى المتاح قبل أن يسأل عنه.
    -->
    <div class="flex flex-wrap items-center gap-2">
        <div
            class="flex min-w-0 flex-1 gap-1 overflow-x-auto rounded-xl border border-border bg-card p-1"
            role="group"
            aria-label="مدّة التقرير"
        >
            {#each RANGES as option (option.value)}
                {@const active = range === option.value}
                <button
                    type="button"
                    onclick={() => pickRange(option.value)}
                    aria-pressed={active}
                    class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg px-3 text-[12.5px] whitespace-nowrap transition-colors {active
                        ? 'bg-primary font-semibold text-primary-foreground'
                        : 'text-foreground/80 hover:bg-secondary'}"
                >
                    {#if option.value === 'month'}
                        <CalendarRange class="size-4" />
                    {/if}
                    {option.value === 'month' && range === 'month'
                        ? periodLabel
                        : option.label}
                </button>
            {/each}
        </div>

        <a
            href={exportPdfHref}
            class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border border-input bg-card px-3 text-[12.5px] font-medium no-underline hover:bg-secondary"
        >
            <FileText class="size-4" /> تصدير PDF
        </a>
    </div>

    {#if periodRange}
        <p class="-mt-3 text-[11.5px] text-muted-foreground">{periodRange}</p>
    {/if}

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

<SheetShell
    bind:open={monthSheetOpen}
    title="شهر التقرير"
    subtitle="الشهر يبدأ يوم نزول راتبك لا يوم 1"
    onClose={() => (monthSheetOpen = false)}
>
    <ul class="flex flex-col gap-1.5">
        {#each availableMonths as option (option.value)}
            {@const active = range === 'month' && month === option.value}
            <li>
                <button
                    type="button"
                    onclick={() => pickMonth(option.value)}
                    class="flex min-h-12 w-full items-center gap-2 rounded-2xl border px-3.5 text-[13.5px] transition-colors {active
                        ? 'border-primary bg-primary/8 font-semibold text-primary'
                        : 'border-input text-foreground/85 hover:bg-secondary'}"
                >
                    <span class="min-w-0 flex-1 text-start">{option.label}</span>
                    {#if active}
                        <Check class="size-4 shrink-0" />
                    {/if}
                </button>
            </li>
        {/each}
    </ul>
</SheetShell>
