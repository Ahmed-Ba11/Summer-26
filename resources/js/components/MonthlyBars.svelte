<script lang="ts">
    /**
     * أعمدة مزدوجة: الدخل مقابل المصاريف عبر ٦ أشهر.
     *
     * قواعد مطبّقة:
     *  - ٦ أشهر فقط لا ١٢ — الاثنا عشر عموداً غير مقروءة على الجوال.
     *  - **محور واحد فقط.** ممنوع منعاً باتاً محوران رأسيان مختلفان.
     *  - الأشهر السابقة بشفافية ٤٢٪، والشهر الحالي بلون كامل واسمه عريض.
     *  - في RTL يقرأ الزمن من اليمين (الأقدم) لليسار (الأحدث).
     */
    import { formatAmount } from '@/lib/format';

    interface Point {
        month: string;
        income: number;
        expenses: number;
    }

    let { data = [], months = 6 }: { data?: Point[]; months?: number } = $props();

    const series = $derived(data.slice(-months));
    const max = $derived(Math.max(1, ...series.flatMap((d) => [d.income, d.expenses])));
    const lastIndex = $derived(series.length - 1);
</script>

<div>
    <div class="flex h-[172px] items-end gap-2.5 pt-1.5">
        {#each series as d, i (d.month)}
            {@const isCurrent = i === lastIndex}
            <div class="flex min-w-0 flex-1 flex-col items-center gap-[7px]">
                <div class="flex h-[146px] w-full items-end justify-center gap-[2px]">
                    <span
                        class="block w-[11px] rounded-t-[4px] transition-all"
                        style="height: {(d.income / max) * 100}%; background-color: var(--chart-3); opacity: {isCurrent
                            ? 1
                            : 0.42}"
                        title="{d.month} — دخل {formatAmount(d.income)} ر.س"
                    ></span>
                    <span
                        class="block w-[11px] rounded-t-[4px] transition-all"
                        style="height: {(d.expenses / max) * 100}%; background-color: var(--chart-1); opacity: {isCurrent
                            ? 1
                            : 0.42}"
                        title="{d.month} — مصاريف {formatAmount(d.expenses)} ر.س"
                    ></span>
                </div>
                <span
                    class="text-[11px] whitespace-nowrap {isCurrent
                        ? 'font-semibold text-foreground'
                        : 'text-muted-foreground'}"
                >
                    {d.month}
                </span>
            </div>
        {/each}
    </div>

    <div class="mt-3.5 flex justify-center gap-4 text-xs text-foreground/75">
        <span class="inline-flex items-center gap-1.5">
            <i class="inline-block size-2.5 rounded-[3px]" style="background-color: var(--chart-3)"></i> دخل
        </span>
        <span class="inline-flex items-center gap-1.5">
            <i class="inline-block size-2.5 rounded-[3px]" style="background-color: var(--chart-1)"></i> مصاريف
        </span>
        <span class="text-muted-foreground">الشهر الحالي بلون كامل</span>
    </div>
</div>
