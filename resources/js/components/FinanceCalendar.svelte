<script lang="ts">
    /**
     * التقويم المالي — الأيام الأربعة عشر القادمة.
     *
     * يمنع أكثر مفاجأة مزعجة في إدارة الميزانية:
     * «انسحب مني قسط ما كنت متوقّعه».
     *
     * النقاط الملوّنة ترمز لنوع الحدث، ومفتاح الرسم أسفلها يحمل المعنى —
     * اللون وحده لا ينقل شيئاً.
     */
    import { formatCurrency } from '@/lib/format';

    type EventKind = 'salary' | 'bill' | 'installment' | 'savings';

    interface CalEvent {
        date: string; // YYYY-MM-DD
        kind: EventKind;
        label: string;
        amount: number;
    }

    let { events = [], days = 14 }: { events?: CalEvent[]; days?: number } = $props();

    const KIND = {
        salary: { color: 'var(--success)', label: 'راتب' },
        bill: { color: 'var(--chart-7)', label: 'فاتورة مستحقة' },
        installment: { color: 'var(--chart-2)', label: 'قسط' },
        savings: { color: 'var(--chart-3)', label: 'تحويل ادخار' },
    } as const;

    const WEEKDAYS = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

    const grid = $derived.by(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return Array.from({ length: days }, (_, i) => {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            const iso = d.toISOString().slice(0, 10);
            return {
                iso,
                dayNumber: d.getDate(),
                weekday: WEEKDAYS[d.getDay()],
                isToday: i === 0,
                events: events.filter((e) => e.date === iso),
            };
        });
    });

    let openDay = $state<string | null>(null);
</script>

<div>
    <div class="flex gap-1.5 overflow-x-auto pb-2">
        {#each grid as day (day.iso)}
            <div class="relative shrink-0">
                <button
                    type="button"
                    class="w-[46px] rounded-[10px] border py-2 text-center transition-colors {day.isToday
                        ? 'border-primary bg-accent ring-2 ring-primary/20'
                        : 'border-border bg-secondary hover:border-input'}"
                    onclick={() => (openDay = openDay === day.iso ? null : day.iso)}
                    aria-label="{day.weekday} {day.dayNumber} — {day.events.length} أحداث"
                >
                    <span class="block text-[10px] text-muted-foreground">{day.weekday}</span>
                    <span class="block text-[15px] font-semibold tabular-nums">{day.dayNumber}</span>
                    <span class="mt-1 flex h-1.5 justify-center gap-[3px]">
                        {#each day.events.slice(0, 3) as e}
                            <i class="block size-1.5 rounded-full" style="background-color: {KIND[e.kind].color}"></i>
                        {/each}
                    </span>
                </button>

                {#if openDay === day.iso && day.events.length}
                    <div
                        class="absolute top-full z-30 mt-1.5 w-56 rounded-xl border border-border bg-popover p-2 shadow-lg"
                        style="inset-inline-start: 0"
                    >
                        {#each day.events as e}
                            <div class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-secondary">
                                <i class="size-2 shrink-0 rounded-full" style="background-color: {KIND[e.kind].color}"></i>
                                <span class="min-w-0 flex-1 truncate text-[12.5px]">{e.label}</span>
                                <span class="text-[12px] font-semibold tabular-nums">{formatCurrency(e.amount)}</span>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        {/each}
    </div>

    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-foreground/75">
        {#each Object.entries(KIND) as [key, v]}
            <span class="inline-flex items-center gap-1.5">
                <i class="inline-block size-2 rounded-full" style="background-color: {v.color}"></i>
                {v.label}
            </span>
        {/each}
    </div>
</div>
