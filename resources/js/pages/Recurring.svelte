<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'المعاملات المتكررة', href: '/recurring' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Repeat2 from 'lucide-svelte/icons/repeat-2';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { formatCurrency, formatDate } from '@/lib/format';

    interface RecurringTransaction {
        id: number;
        type: 'income' | 'expense' | string;
        description: string | null;
        category: string | null;
        source: string | null;
        amount: number;
        frequency: string;
        next_due_date: string;
        is_active: boolean;
    }

    interface PageProps {
        transactions?: RecurringTransaction[];
        error?: string | null;
    }

    let { transactions = [], error = null }: PageProps = $props();

    function reloadTransactions(): void {
        router.reload({ only: ['transactions'] });
    }
</script>

<AppHead title="المعاملات المتكررة" />
<MobileHeader title="المعاملات المتكررة" subtitle="راجع الدخل والمصاريف التي تتكرر تلقائياً" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div
        class="hidden flex-col gap-3 md:flex md:flex-row md:items-center md:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">المعاملات المتكررة</h1>
            <p class="text-muted-foreground">
                راجع الدخل والمصاريف التي تتكرر تلقائياً.
            </p>
        </div>
        <Button variant="outline" class="gap-1.5" onclick={reloadTransactions}>
            <RefreshCw class="size-4" />
            تحديث
        </Button>
    </div>

    {#if error}
        <Card class="border-destructive/40">
            <CardContent class="flex items-center gap-3 py-5 text-destructive">
                <CalendarClock class="size-5" />
                <p>{error}</p>
            </CardContent>
        </Card>
    {:else if transactions.length === 0}
        <Card>
            <EmptyState
                icon={Repeat2 as any}
                title="لا توجد معاملات متكررة"
                description="ستظهر هنا المعاملات الدورية التي ينشئها النظام من بياناتك."
            />
        </Card>
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each transactions as transaction (transaction.id)}
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <CategoryIcon
                                    icon="repeat"
                                    color={transaction.type === 'income'
                                        ? 'var(--chart-3)'
                                        : 'var(--chart-2)'}
                                    size="md"
                                />
                                <div class="min-w-0">
                                    <CardTitle class="truncate text-base"
                                        >{transaction.description ||
                                            transaction.source ||
                                            'معاملة متكررة'}</CardTitle
                                    >
                                    <p class="text-sm text-muted-foreground">
                                        {transaction.category ||
                                            transaction.source ||
                                            (transaction.type === 'income'
                                                ? 'دخل'
                                                : 'مصروف')}
                                    </p>
                                </div>
                            </div>
                            {#if transaction.is_active}
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[10px] font-semibold text-success-text"
                                >
                                    <CircleCheck class="size-3" />
                                    نشطة
                                </span>
                            {/if}
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >المبلغ</span
                            >
                            <span class="font-bold tabular-nums"
                                >{formatCurrency(transaction.amount)}</span
                            >
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">التكرار</span>
                            <span>{transaction.frequency}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground"
                                >الاستحقاق القادم</span
                            >
                            <span class="tabular-nums"
                                >{formatDate(transaction.next_due_date)}</span
                            >
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}
</div>
