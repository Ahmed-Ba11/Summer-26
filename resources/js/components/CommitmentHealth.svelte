<script lang="ts">
    /**
     * شريط صحة الالتزامات.
     *
     * يجيب على سؤال المستخدم «كم استهلك من ميزانيتي؟» بثلاثة أرقام فقط:
     * الإجمالي · نسبته من الدخل · وكم منه خرج فعلاً مقابل كم لا يزال محجوزاً.
     *
     * التفرقة بين «مدفوع» و«محجوز» هي جوهر الصفحة: المحجوز مطروح من
     * «المتبقي لك» لكن الفلوس لا تزال في الحساب.
     */
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import { formatAmount } from '@/lib/format';
    import { healthOf } from '@/lib/commitments';

    let {
        total = 0,
        paid = 0,
        reserved = 0,
        income = 0,
    }: { total?: number; paid?: number; reserved?: number; income?: number } = $props();

    const health = $derived(healthOf(total, income));
    const over = $derived(income > 0 && total > income);

    const pctColor = $derived(
        health.level === 'bad' ? 'var(--destructive)' : health.level === 'warn' ? 'var(--warning-text)' : 'var(--success-text)',
    );

    const paidFlex = $derived(total > 0 ? Math.max(paid / total, 0) : 0);
</script>

<section class="rounded-2xl border bg-card p-3.5 shadow-xs {over ? 'border-destructive/40' : 'border-border'}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[11.5px] text-muted-foreground">التزاماتك هذا الشهر</p>
            <p class="mt-0.5 text-[23px] font-semibold tracking-tight tabular-nums">
                {formatAmount(total)}<span class="ms-1 text-[12px] font-medium text-muted-foreground">ر.س</span>
            </p>
        </div>
        {#if income > 0}
            <div class="shrink-0 text-end">
                <p class="text-[17px] font-semibold tabular-nums" style="color:{pctColor}">
                    {Math.round(health.pct)}٪
                </p>
                <p class="text-[10.5px] text-muted-foreground">من دخلك</p>
            </div>
        {/if}
    </div>

    <!-- الشريط: أخضر خرج فعلاً · بنفسجي محجوز -->
    <div class="mt-2.5 flex h-5 gap-0.5 overflow-hidden rounded-lg bg-secondary">
        {#if paid > 0}
            <div style="flex:{paidFlex};background:var(--success)"></div>
        {/if}
        {#if reserved > 0}
            <div
                class="grid place-items-center px-2 text-[10.5px] font-semibold text-white"
                style="flex:{1 - paidFlex};background:var(--chart-7)"
            >
                محجوز · لم يخرج بعد
            </div>
        {/if}
    </div>

    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-foreground/85">
        <span class="inline-flex items-center gap-1.5">
            <i class="size-2 rounded-[3px]" style="background:var(--success)"></i>
            مدفوع <b class="font-semibold tabular-nums">{formatAmount(paid)}</b>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <i class="size-2 rounded-[3px]" style="background:var(--chart-7)"></i>
            محجوز <b class="font-semibold tabular-nums">{formatAmount(reserved)}</b>
        </span>
    </div>

    {#if over}
        <p class="mt-2.5 inline-flex items-start gap-1.5 rounded-xl bg-destructive/8 px-2.5 py-2 text-[11.5px] font-medium text-destructive">
            <TriangleAlert class="mt-px size-3.5 shrink-0" />
            التزاماتك تفوق دخلك بـ {formatAmount(total - income)} ر.س — راجع الأقساط أو أجّل التزاماً غير ضروري.
        </p>
    {/if}
</section>
