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
    import House from 'lucide-svelte/icons/house';
    import Repeat from 'lucide-svelte/icons/repeat';
    import Vault from 'lucide-svelte/icons/vault';
    import Zap from 'lucide-svelte/icons/zap';
    import { Link } from '@inertiajs/svelte';
    import { formatAmount, formatRelativeDays } from '@/lib/format';

    interface CalEvent {
        date: string;
        kind: 'salary' | 'bill' | 'rent' | 'installment' | 'subscription' | 'savings';
        label: string;
        amount: number;
    }

    let { events = [], limit = 3 }: { events?: CalEvent[]; limit?: number } = $props();

    const KIND = {
        salary: { color: 'var(--success)', icon: Banknote, label: 'راتب' },
        bill: { color: 'var(--chart-7)', icon: Zap, label: 'فاتورة' },
        rent: { color: 'var(--chart-5)', icon: House, label: 'إيجار' },
        installment: { color: 'var(--chart-2)', icon: CreditCard, label: 'قسط' },
        subscription: { color: 'var(--chart-3)', icon: Repeat, label: 'اشتراك' },
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
    <header class="flex items-center justify-between border-b border-border px-4 py-1 md:px-5">
        <h2 class="text-[13px] font-semibold">التقويم المالي</h2>
        <Link
            href="/calendar"
            class="inline-flex min-h-11 items-center gap-1 text-[11.5px] text-primary no-underline"
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
                <li class="flex items-center gap-2.5 border-b border-border px-4 py-2 last:border-0 md:px-5">
                    <span
                        class="grid size-8 shrink-0 place-items-center rounded-[9px]"
                        style="background-color: color-mix(in srgb, {k.color} 12%, transparent); color: {k.color}"
                    >
                        <k.icon class="size-4" stroke-width="1.9" />
                    </span>

                    <span class="min-w-0 flex-1 truncate text-[12.5px] font-medium">{e.label}</span>

                    <span
                        class="shrink-0 text-[11.5px] tabular-nums {urgent
                            ? 'font-medium text-warning-text'
                            : 'text-muted-foreground'}"
                    >
                        {formatRelativeDays(e.date)}
                    </span>

                    <span
                        class="shrink-0 text-[12.5px] font-semibold tabular-nums {e.kind === 'salary'
                            ? 'text-success-text'
                            : 'text-foreground'}"
                    >
                        {e.kind === 'salary' ? '+' : '−'} {formatAmount(e.amount)}
                    </span>
                </li>
            {/each}
        </ul>
    {:else}
        <!-- حالة فارغة: سطر واحد — لا تستحق مساحة بطاقة كاملة -->
        <p class="flex items-center gap-2 px-4 py-1 text-[12.5px] text-muted-foreground md:px-5">
            <CalendarDays class="size-4 shrink-0" />
            <span class="flex-1">ما فيه استحقاقات قريبة</span>
            <Link
                href="/commitments"
                class="inline-flex min-h-11 shrink-0 items-center text-[11.5px] text-primary no-underline"
            >
                أضف التزاماً
            </Link>
        </p>
    {/if}
</section>
