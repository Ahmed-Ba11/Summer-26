<script lang="ts">
    /**
     * شريط تدفّق الدخل — قلب لوحة التحكم.
     *
     * شريط أفقي واحد يقرأ من اليمين لليسار كقصّة:
     *   الراتب دخل → هذا انحجز للفواتير → هذا للأقساط → هذا للادخار
     *   → هذا صُرف → وهذا الباقي لك.
     *
     * قواعد التصميم المطبّقة:
     *  - فاصل 2px بلون السطح بين كل قطعة وأختها (لا تلتحم القطع بصرياً).
     *  - «المتبقي» يُرسم بخطوط مائلة وحدود متقطّعة، فيقرأ كـ«فراغ متاح»
     *    لا كالتزام آخر.
     *  - مفتاح الرسم يحمل **المبالغ الفعلية** لا النسب فقط — لأن بعض ألوان
     *    البالتة تحت 3:1 تباين على السطح الفاتح، والتسمية المباشرة هي
     *    المعالجة الإلزامية لذلك.
     *  - النص لا يلبس لون السلسلة؛ اللون يحمله المربّع المجاور فقط.
     */
    import { formatAmount, formatPercent } from '@/lib/format';

    interface Slice {
        key: string;
        label: string;
        amount: number; // بالهللات
        color: string;
    }

    let {
        income = 0,
        slices = [],
        remainingLabel = 'متبقي لك',
    }: {
        income?: number;
        slices?: Slice[];
        remainingLabel?: string;
    } = $props();

    const used = $derived(slices.reduce((s, x) => s + Math.max(0, x.amount), 0));
    const remaining = $derived(Math.max(0, income - used));
    const overspent = $derived(Math.max(0, used - income));
    const base = $derived(Math.max(income, used, 1));

    function pct(amount: number): number {
        return (amount / base) * 100;
    }
</script>

<div>
    <div
        class="flex h-[34px] gap-[2px] overflow-hidden rounded-[10px] bg-secondary"
        role="img"
        aria-label="توزيع الدخل على الالتزامات والمصاريف والمتبقي"
    >
        {#each slices as s (s.key)}
            {@const p = pct(s.amount)}
            {#if p > 0.5}
                <div
                    class="grid cursor-default place-items-center overflow-hidden text-[11.5px] font-semibold whitespace-nowrap text-white transition-[filter] hover:brightness-110"
                    style="flex: {p}; background-color: {s.color}"
                    title="{s.label} — {formatAmount(s.amount)} ر.س"
                >
                    {#if p >= 8}{formatPercent(p)}{/if}
                </div>
            {/if}
        {/each}

        {#if remaining > 0}
            <div
                class="grid place-items-center overflow-hidden rounded-e-[8px] border border-dashed border-input text-[11.5px] font-semibold whitespace-nowrap text-foreground/70"
                style="flex: {pct(remaining)}; background-image: repeating-linear-gradient(135deg, var(--secondary) 0 6px, var(--border) 6px 7px)"
                title="{remainingLabel} — {formatAmount(remaining)} ر.س"
            >
                {#if pct(remaining) >= 14}{remainingLabel} {formatPercent(pct(remaining))}{/if}
            </div>
        {/if}

        {#if overspent > 0}
            <div
                class="grid place-items-center text-[11.5px] font-semibold text-white"
                style="flex: {pct(overspent)}; background-color: var(--destructive)"
                title="تجاوز الدخل — {formatAmount(overspent)} ر.س"
            >
                تجاوز
            </div>
        {/if}
    </div>

    <!-- مفتاح الرسم بالمبالغ الفعلية (إلزامي — ليس خياراً) -->
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-[12.5px] text-foreground/75">
        {#each slices as s (s.key)}
            <span class="inline-flex items-center gap-1.5">
                <i class="inline-block size-2.5 rounded-[3px]" style="background-color: {s.color}"></i>
                <span>{s.label}</span>
                <b class="font-semibold text-foreground tabular-nums">{formatAmount(s.amount)}</b>
            </span>
        {/each}
        {#if remaining > 0}
            <span class="inline-flex items-center gap-1.5">
                <i
                    class="inline-block size-2.5 rounded-[3px] border border-dashed border-input"
                    style="background-image: repeating-linear-gradient(135deg, var(--border) 0 3px, transparent 3px 5px)"
                ></i>
                <span>{remainingLabel}</span>
                <b class="font-semibold text-foreground tabular-nums">{formatAmount(remaining)}</b>
            </span>
        {/if}
    </div>
</div>
