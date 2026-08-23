<script lang="ts">
    /**
     * «التقويم المالي» — عرض مضغوط لأقرب الاستحقاقات في لوحة التحكم.
     *
     * التقويم بأربعة عشر يوماً يحتاج تمريراً أفقياً على الجوال، وهذا
     * بالضبط ما نحاربه. ثلاثة أسطر تعطي نفس المعلومة بلا تمرير:
     * ما الحدث · متى · كم.
     *
     * التقويم الكامل ينتقل لصفحة /calendar المستقلة.
     */
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Vault from 'lucide-svelte/icons/vault';
    import Zap from 'lucide-svelte/icons/zap';
    import { Link } from '@inertiajs/svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import { formatAmount, formatRelativeDays } from '@/lib/format';

    interface CalEvent {
        date: string;
        kind: 'salary' | 'bill' | 'installment' | 'savings';
        label: string;
        amount: number;
    }

    let { events = [], limit = 3 }: { events?: CalEvent[]; limit?: number } = $props();

    const KIND = {
        salary: { color: 'var(--success)', icon: Banknote, label: 'راتب' },
        bill: { color: 'var(--chart-7)', icon: Zap, label: 'فاتورة' },
        installment: { color: 'var(--chart-2)', icon: CreditCard, label: 'قسط' },
        savings: { color: 'var(--chart-3)', icon: Vault, label: 'ادخار' },
    } as const;

    const upcoming = $derived(
        [...events].sort((a, b) => a.date.localeCompare(b.date)).slice(0, limit),
    );

    /** الحدث المتأخر أو المستحق اليوم يستحق تمييزاً. */
    function isUrgent(date: string): boolean {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d.getTime() <= today.getTime() + 2 * 86_400_000;
    }
</script>

<section class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
    <header class="flex items-center justify-between border-b border-border px-4 py-3 md:px-5 md:py-4">
        <h2 class="text-[13px] font-semibold md:text-[14.5px]">التقويم المالي</h2>
        <Link
            href="/calendar"
            class="inline-flex items-center gap-1 text-[11.5px] text-primary no-underline md:text-[12.5px]"
        >
            التقويم الكامل
            <ArrowLeft class="size-3.5" />
        </Link>
    </header>

    {#if upcoming.length}
        <ul>
            {#each upcoming as e (e.date + e.label)}
                {@const k = KIND[e.kind]}
                {@const urgent = isUrgent(e.date)}
                <li class="flex items-center gap-3 border-b border-border px-4 py-3 last:border-0 md:px-5">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-[10px]"
                        style="background-color: color-mix(in srgb, {k.color} 12%, transparent); color: {k.color}"
                    >
                        <k.icon class="size-[17px]" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium">{e.label}</p>
                        <p class="text-[11px] {urgent ? 'font-medium text-warning-text' : 'text-muted-foreground'}">
                            {formatRelativeDays(e.date)}
                        </p>
                    </div>

                    <span
                        class="shrink-0 text-[13px] font-semibold tabular-nums {e.kind === 'salary'
                            ? 'text-success-text'
                            : 'text-foreground'}"
                    >
                        {e.kind === 'salary' ? '+' : '−'} {formatAmount(e.amount)}
                    </span>
                </li>
            {/each}
        </ul>
    {:else}
        <EmptyState
            icon={CalendarDays}
            title="ما فيه استحقاقات قريبة"
            description="سجّل فواتيرك وأقساطك عشان ننبّهك قبل موعدها."
            actionLabel="أضف التزاماً"
            href="/commitments"
        />
    {/if}
</section>
