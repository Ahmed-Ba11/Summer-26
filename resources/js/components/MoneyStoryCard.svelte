<script lang="ts">
    /**
     * بطاقة القصّة المالية — نسخة مضغوطة، mobile-first.
     *
     * ملاحظات المستخدم المطبّقة:
     *   • البطاقة كانت كبيرة جداً — الرقم البطل نزل من 40px إلى 28px على
     *     الجوال، والحشوة من 24px إلى 14px.
     *   • «دخلك» كان يطفو بجانب الرقم — صار ضمن صف ثلاثي مرتّب خلف فاصل.
     *   • «متوسط صرفك اليومي 0» كان صندوقاً كبيراً بقيمة صفر — صار يختفي
     *     تماماً قبل أول مصروف، وبعدها سطر واحد.
     *   • قطع شريط التدفّق ومفتاحه: البنود الصفرية لا تُرسم إطلاقاً.
     */
    import Wallet from 'lucide-svelte/icons/wallet';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Check from 'lucide-svelte/icons/check';
    import { formatAmount, formatCurrency, formatPercent } from '@/lib/format';

    let {
        income = 0,
        commitments = 0,
        savings = 0,
        expenses = 0,
        daysLeft = 0,
        avgDaily = 0,
    }: {
        income?: number;
        commitments?: number;
        savings?: number;
        expenses?: number;
        daysLeft?: number;
        avgDaily?: number;
    } = $props();

    const reserved = $derived(commitments + savings);
    const remaining = $derived(income - reserved - expenses);
    const isNegative = $derived(remaining < 0);
    const safeDaily = $derived(daysLeft > 0 ? Math.floor(remaining / daysLeft) : remaining);
    const onTrack = $derived(avgDaily > 0 && safeDaily > 0 && avgDaily <= safeDaily);

    const slices = $derived(
        [
            { key: 'commitments', label: 'التزامات', amount: commitments, color: 'var(--chart-7)' },
            { key: 'save', label: 'ادخار', amount: savings, color: 'var(--success)' },
            { key: 'exp', label: 'مصاريف', amount: expenses, color: 'var(--chart-1)' },
        ].filter((s) => s.amount > 0),
    );

    const base = $derived(Math.max(income, reserved + expenses, 1));
    const restPct = $derived(Math.max(0, (Math.max(0, remaining) / base) * 100));

    const figures = $derived([
        { label: 'دخلك', value: income },
        { label: 'محجوز', value: reserved },
        { label: 'صرفت', value: expenses },
    ]);
</script>

<section class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
    <div class="px-3.5 pt-3.5 pb-3 md:flex md:flex-wrap md:items-end md:gap-x-7 md:gap-y-5 md:px-6 md:pt-5 md:pb-4">
        <!-- الرقم البطل -->
        <div class="md:shrink-0">
            <p class="flex items-center gap-1.5 text-[12px] text-muted-foreground">
                <Wallet class="size-3.5" />
                المتبقي لك للصرف
            </p>
            <p
                class="mt-0.5 text-[28px] leading-none font-semibold tracking-tighter md:text-[34px] {isNegative
                    ? 'text-destructive'
                    : ''}"
            >
                {formatAmount(remaining)}<span class="ms-1 text-[13px] font-medium text-foreground/80 md:text-[16px]">
                    ر.س
                </span>
            </p>

            {#if isNegative}
                <p class="mt-1 text-[12px] text-destructive">
                    تجاوزت دخلك بـ <b class="font-semibold">{formatCurrency(Math.abs(remaining))}</b>
                </p>
            {:else if daysLeft === 0}
                <p class="mt-1 text-[12px] text-success-text">راتبك اليوم — ميزانية جديدة</p>
            {:else}
                <p class="mt-1 text-[12px] text-foreground/80">
                    تقدر تصرف <b class="font-semibold text-success-text">{formatCurrency(safeDaily)} يومياً</b>
                </p>
            {/if}
        </div>

        <div class="hidden w-px self-stretch bg-border md:block"></div>

        <!-- صف الأرقام الثلاثة -->
        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-border pt-2.5 md:mt-0 md:flex md:gap-7 md:border-0 md:pt-0">
            {#each figures as f (f.label)}
                <div class="min-w-0">
                    <p class="truncate text-[11px] text-muted-foreground">{f.label}</p>
                    <p class="mt-0.5 text-[15px] font-semibold tracking-tight tabular-nums md:text-[18px]">
                        {formatAmount(f.value)}
                    </p>
                </div>
            {/each}
        </div>

        {#if avgDaily > 0}
            <div class="hidden flex-col items-end gap-0.5 rounded-xl border border-border bg-secondary px-3.5 py-2 md:ms-auto md:flex">
                <span class="text-[11.5px] text-muted-foreground">متوسط صرفك اليومي</span>
                <span class="text-[18px] font-semibold tracking-tight tabular-nums">{formatAmount(avgDaily)} ر.س</span>
                <span class="inline-flex items-center gap-1 text-[11.5px] {onTrack ? 'text-success-text' : 'text-destructive'}">
                    {#if onTrack}<Check class="size-3" /> ضمن الحد الآمن{:else}<TriangleAlert class="size-3" /> أعلى من الحد الآمن{/if}
                </span>
            </div>
        {/if}
    </div>

    <!-- شريط التدفّق -->
    <div class="px-3.5 pb-3.5 md:px-6 md:pb-5">
        <div
            class="flex h-[26px] gap-[2px] overflow-hidden rounded-lg bg-secondary md:h-8"
            role="img"
            aria-label="توزيع الدخل على الالتزامات والمصاريف والمتبقي"
        >
            {#each slices as s (s.key)}
                {@const p = (s.amount / base) * 100}
                {#if p > 0.5}
                    <div
                        class="grid place-items-center overflow-hidden text-[11px] font-semibold whitespace-nowrap text-white"
                        style="flex: {p}; background-color: {s.color}"
                        title="{s.label} — {formatAmount(s.amount)} ر.س"
                    >
                        {#if p >= 11}{formatPercent(p)}{/if}
                    </div>
                {/if}
            {/each}

            {#if restPct > 0.5}
                <div
                    class="grid place-items-center overflow-hidden rounded-e-[6px] border border-dashed border-input text-[11px] font-semibold whitespace-nowrap text-foreground/80"
                    style="flex: {restPct}; background-image: repeating-linear-gradient(135deg, var(--secondary) 0 6px, var(--border) 6px 7px)"
                    title="متبقي لك — {formatAmount(Math.max(0, remaining))} ر.س"
                >
                    {#if restPct >= 24}متبقي {formatPercent(restPct)}{/if}
                </div>
            {/if}
        </div>

        {#if slices.length}
            <div class="mt-2 flex flex-wrap gap-x-3.5 gap-y-1 text-[11px] text-foreground/80 md:text-[12.5px]">
                {#each slices as s (s.key)}
                    <span class="inline-flex items-center gap-1.5">
                        <i class="inline-block size-2 rounded-[3px]" style="background-color: {s.color}"></i>
                        <span>{s.label}</span>
                        <b class="font-semibold text-foreground tabular-nums">{formatAmount(s.amount)}</b>
                    </span>
                {/each}
            </div>
        {:else}
            <p class="mt-2 text-[11px] text-muted-foreground">ما فيه التزامات ولا مصاريف بعد — كل دخلك متاح لك.</p>
        {/if}

        {#if avgDaily > 0}
            <p class="mt-2 inline-flex items-center gap-1.5 text-[11px] text-foreground/80 md:hidden">
                متوسط صرفك {formatAmount(avgDaily)} ر.س يومياً
                <span class="inline-flex items-center gap-0.5 {onTrack ? 'text-success-text' : 'text-destructive'}">
                    {#if onTrack}<Check class="size-3" />{:else}<TriangleAlert class="size-3" />{/if}
                </span>
            </p>
        {/if}
    </div>
</section>
