<script lang="ts">
    /**
     * بطاقة القصّة المالية — مُعاد بناؤها mobile-first.
     *
     * ══════════════════════════════════════════════════════════════════
     *  الجوال هو التخطيط الأساسي، والديسكتوب توسّع له.
     * ══════════════════════════════════════════════════════════════════
     *
     * على الجوال (< 768px):
     *   الرقم البطل، سطر الحد اليومي، شريط التدفّق، ثم صف ثلاثي مضغوط
     *   (دخلك · محجوز · صرفت) خلف فاصل. لا صناديق جانبية ولا أرقام عائمة.
     *
     * على الديسكتوب (≥ 768px):
     *   نفس العناصر في صف أفقي واحد، وبطاقة «متوسط صرفك اليومي» جانبية.
     *
     * قواعد إخفاء الضجيج:
     *   • قطع شريط التدفّق التي قيمتها صفر لا تُرسم أصلاً
     *   • مفتاح الرسم يعرض البنود غير الصفرية فقط
     *   • «متوسط صرفك اليومي» يظهر فقط بعد أول مصروف
     */
    import Wallet from 'lucide-svelte/icons/wallet';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Check from 'lucide-svelte/icons/check';
    import { formatAmount, formatCurrency, formatPercent } from '@/lib/format';

    let {
        income = 0,
        bills = 0,
        installments = 0,
        savings = 0,
        expenses = 0,
        daysLeft = 0,
        avgDaily = 0,
    }: {
        income?: number;
        bills?: number;
        installments?: number;
        savings?: number;
        expenses?: number;
        daysLeft?: number;
        avgDaily?: number;
    } = $props();

    const reserved = $derived(bills + installments + savings);
    const remaining = $derived(income - reserved - expenses);
    const isNegative = $derived(remaining < 0);
    const safeDaily = $derived(daysLeft > 0 ? Math.floor(remaining / daysLeft) : remaining);
    const onTrack = $derived(avgDaily > 0 && safeDaily > 0 && avgDaily <= safeDaily);

    /** القطع غير الصفرية فقط — الصفر لا يُرسم ولا يظهر في المفتاح. */
    const slices = $derived(
        [
            { key: 'bills', label: 'فواتير', amount: bills, color: 'var(--chart-7)' },
            { key: 'inst', label: 'أقساط', amount: installments, color: 'var(--chart-2)' },
            { key: 'save', label: 'ادخار', amount: savings, color: 'var(--chart-3)' },
            { key: 'exp', label: 'مصاريف', amount: expenses, color: 'var(--chart-1)' },
        ].filter((s) => s.amount > 0),
    );

    const base = $derived(Math.max(income, reserved + expenses, 1));
    const restPct = $derived(Math.max(0, (Math.max(0, remaining) / base) * 100));

    /** الأرقام الثلاثة المساندة. */
    const figures = $derived([
        { label: 'دخلك', value: income },
        { label: 'محجوز', value: reserved },
        { label: 'صرفت', value: expenses },
    ]);
</script>

<section class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs md:rounded-[22px]">
    <div class="p-4 md:flex md:flex-wrap md:items-end md:gap-x-7 md:gap-y-6 md:px-7 md:pt-6 md:pb-5">
        <!-- ── الرقم البطل ─────────────────────────────────────────── -->
        <div class="md:shrink-0">
            <p class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground md:text-[12.5px]">
                <Wallet class="size-3.5" />
                المتبقي لك للصرف
            </p>
            <p
                class="mt-1 text-[32px] leading-none font-semibold tracking-tighter md:text-4xl md:leading-[1.1] {isNegative
                    ? 'text-destructive'
                    : ''}"
            >
                {formatAmount(remaining)}<span class="ms-1 text-[14px] font-medium text-foreground/70 md:text-[17px]">
                    ر.س
                </span>
            </p>

            {#if isNegative}
                <p class="mt-1.5 text-[11.5px] text-destructive md:text-[12.5px]">
                    تجاوزت دخلك بـ <b class="font-semibold">{formatCurrency(Math.abs(remaining))}</b>
                </p>
            {:else if daysLeft === 0}
                <p class="mt-1.5 text-[11.5px] text-foreground/75 md:text-[12.5px]">راتبك اليوم — ميزانية جديدة تبدأ 🎉</p>
            {:else}
                <p class="mt-1.5 text-[11.5px] text-foreground/75 md:text-[12.5px]">
                    تقدر تصرف
                    <b class="font-semibold text-success-text">{formatCurrency(safeDaily)} يومياً</b>
                    بأمان
                </p>
            {/if}
        </div>

        <div class="hidden w-px self-stretch bg-border md:block"></div>

        <!-- ── الأرقام المساندة: صف ثلاثي على الجوال ─────────────────── -->
        <div
            class="mt-3.5 grid grid-cols-3 gap-2 border-t border-border pt-3 md:mt-0 md:flex md:gap-7 md:border-0 md:pt-0"
        >
            {#each figures as f (f.label)}
                <div class="min-w-0">
                    <p class="truncate text-[10.5px] text-muted-foreground md:text-xs">{f.label}</p>
                    <p class="mt-0.5 text-[15px] font-semibold tracking-tight tabular-nums md:text-[19px]">
                        {formatAmount(f.value)}
                    </p>
                </div>
            {/each}
        </div>

        <!-- ── متوسط الصرف اليومي — بطاقة على الديسكتوب فقط ──────────── -->
        {#if avgDaily > 0}
            <div
                class="hidden flex-col items-end gap-0.5 rounded-xl border border-border bg-secondary px-4 py-2.5 md:ms-auto md:flex"
            >
                <span class="text-[11.5px] text-muted-foreground">متوسط صرفك اليومي</span>
                <span class="text-xl font-semibold tracking-tight tabular-nums">{formatAmount(avgDaily)} ر.س</span>
                <span
                    class="inline-flex items-center gap-1 text-[11.5px] {onTrack ? 'text-success-text' : 'text-destructive'}"
                >
                    {#if onTrack}
                        <Check class="size-3" /> أقل من الحد الآمن
                    {:else}
                        <TriangleAlert class="size-3" /> أعلى من الحد الآمن
                    {/if}
                </span>
            </div>
        {/if}
    </div>

    <!-- ── شريط التدفّق ──────────────────────────────────────────── -->
    <div class="px-4 pb-4 md:px-7 md:pb-5">
        <div
            class="flex h-7 gap-[2px] overflow-hidden rounded-[9px] bg-secondary md:h-[34px] md:rounded-[10px]"
            role="img"
            aria-label="توزيع الدخل على الالتزامات والمصاريف والمتبقي"
        >
            {#each slices as s (s.key)}
                {@const p = (s.amount / base) * 100}
                {#if p > 0.5}
                    <div
                        class="grid place-items-center overflow-hidden text-[10px] font-semibold whitespace-nowrap text-white md:text-[11.5px]"
                        style="flex: {p}; background-color: {s.color}"
                        title="{s.label} — {formatAmount(s.amount)} ر.س"
                    >
                        {#if p >= 10}{formatPercent(p)}{/if}
                    </div>
                {/if}
            {/each}

            {#if restPct > 0.5}
                <div
                    class="grid place-items-center overflow-hidden rounded-e-[7px] border border-dashed border-input text-[10px] font-semibold whitespace-nowrap text-foreground/70 md:text-[11.5px]"
                    style="flex: {restPct}; background-image: repeating-linear-gradient(135deg, var(--secondary) 0 6px, var(--border) 6px 7px)"
                    title="متبقي لك — {formatAmount(Math.max(0, remaining))} ر.س"
                >
                    {#if restPct >= 22}متبقي {formatPercent(restPct)}{/if}
                </div>
            {/if}
        </div>

        <!-- المفتاح — البنود غير الصفرية فقط -->
        {#if slices.length}
            <div class="mt-2.5 flex flex-wrap gap-x-3.5 gap-y-1.5 text-[10.5px] text-foreground/75 md:text-[12.5px]">
                {#each slices as s (s.key)}
                    <span class="inline-flex items-center gap-1.5">
                        <i class="inline-block size-2 rounded-[3px] md:size-2.5" style="background-color: {s.color}"></i>
                        <span>{s.label}</span>
                        <b class="font-semibold text-foreground tabular-nums">{formatAmount(s.amount)}</b>
                    </span>
                {/each}
            </div>
        {:else}
            <p class="mt-2.5 text-[10.5px] text-muted-foreground md:text-[11.5px]">
                ما سجّلت أي مصروف أو التزام بعد — كل دخلك متاح لك.
            </p>
        {/if}

        <!-- متوسط الصرف — سطر واحد على الجوال بدل بطاقة -->
        {#if avgDaily > 0}
            <p
                class="mt-2.5 inline-flex items-center gap-1.5 text-[10.5px] md:hidden {onTrack
                    ? 'text-success-text'
                    : 'text-destructive'}"
            >
                {#if onTrack}<Check class="size-3" />{:else}<TriangleAlert class="size-3" />{/if}
                متوسط صرفك {formatAmount(avgDaily)} ر.س يومياً
            </p>
        {/if}
    </div>
</section>
