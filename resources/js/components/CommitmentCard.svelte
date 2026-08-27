<script lang="ts">
    /**
     * بطاقة التزام — فاتورة · إيجار · قسط · اشتراك بمكوّن واحد.
     *
     * تخطيط ثابت مهما اختلف النوع (اتّساق بصري مقصود):
     *   [أيقونة]  الاسم + وسم الطريقة
     *             الاستحقاق ................ شارة الحالة
     *   المبلغ ................................. معلومة جانبية
     *   [شريط تقدّم — للأقساط فقط]
     *   [تم الدفع] [تعديل]
     *
     * الاسم يُقصّ ولا يلتفّ، وسطر الاستحقاق يُقصّ ولا يلتفّ — فلا يتمدّد
     * ارتفاع البطاقة ولا تختلف البطاقات عن بعضها في الطول.
     */
    import Check from 'lucide-svelte/icons/check';
    import Hand from 'lucide-svelte/icons/hand';
    import Lock from 'lucide-svelte/icons/lock';
    import Pencil from 'lucide-svelte/icons/pencil';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Undo2 from 'lucide-svelte/icons/undo-2';
    import Zap from 'lucide-svelte/icons/zap';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount, formatRelativeDays } from '@/lib/format';
    import {
        type Commitment,
        finishLabel,
        isDueSoon,
        stateOf,
    } from '@/lib/commitments';

    let {
        commitment,
        onPay,
        onEdit,
        onUndo,
    }: {
        commitment: Commitment;
        onPay?: (c: Commitment) => void;
        onEdit?: (c: Commitment) => void;
        onUndo?: (c: Commitment) => void;
    } = $props();

    const c = $derived(commitment);
    const state = $derived(stateOf(c));

    const monthsLeft = $derived(Math.max(0, c.months_count - c.months_paid));
    const progress = $derived(
        c.months_count > 0 ? (c.months_paid / c.months_count) * 100 : 0,
    );
    const remainingAmount = $derived(
        Math.max(0, c.total_amount - (c.amount ?? 0) * c.months_paid),
    );

    /** فاتورة متغيّرة لم يُسجَّل مبلغها بعد. */
    const unknownAmount = $derived(c.is_variable && c.amount === null);

    /** مقارنة الفاتورة المتغيّرة بمتوسّطها — سياق يحوّل الرقم إلى إشارة. */
    const vsAverage = $derived.by(() => {
        if (!c.is_variable || c.amount === null || c.average_amount <= 0)
            return null;
        const diff = ((c.amount - c.average_amount) / c.average_amount) * 100;
        if (Math.abs(diff) < 5) return null;
        return { pct: Math.round(Math.abs(diff)), higher: diff > 0 };
    });

    /** «قريب» تمييز بصري داخل «قادم» لا حالة رابعة. */
    const dueSoon = $derived(isDueSoon(c));

    const borderClass = $derived(
        state === 'overdue'
            ? 'border-destructive/40'
            : dueSoon
              ? 'border-warning/45'
              : 'border-border',
    );
</script>

<article
    class="rounded-2xl border bg-card p-3 shadow-xs {borderClass} {state ===
    'paid'
        ? 'opacity-70'
        : ''}"
>
    <!-- الرأس -->
    <div class="flex items-start gap-2.5">
        <CategoryIcon icon={c.icon} color={c.color} size="lg" />

        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-1.5">
                <h3 class="min-w-0 truncate text-[13.5px] font-semibold">
                    {c.name}
                </h3>

                {#if c.kind === 'installment' || c.payment_method === 'auto'}
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full px-1.5 py-px text-[10px] font-semibold {c.payment_method ===
                        'auto'
                            ? 'bg-chart-2/12 text-chart-2'
                            : 'bg-secondary text-muted-foreground'}"
                    >
                        {#if c.payment_method === 'auto'}
                            <Zap class="size-2.5" /> تلقائي
                        {:else}
                            <Hand class="size-2.5" /> يدوي
                        {/if}
                    </span>
                {:else if c.is_variable}
                    <span
                        class="shrink-0 rounded-full bg-secondary px-1.5 py-px text-[10px] font-semibold text-muted-foreground"
                    >
                        متغيّرة
                    </span>
                {/if}
            </div>

            <div class="mt-0.5 flex items-center justify-between gap-2">
                <p
                    class="min-w-0 truncate text-[11px] {state === 'overdue'
                        ? 'font-medium text-destructive'
                        : dueSoon
                          ? 'font-medium text-warning-text'
                          : 'text-muted-foreground'}"
                >
                    {#if state === 'paid'}
                        {c.payment_method === 'auto' && c.paid_at
                            ? 'انخصم تلقائياً'
                            : 'دُفع'}
                        {#if c.paid_at}· {formatRelativeDays(c.paid_at)}{/if}
                    {:else}
                        {formatRelativeDays(c.due_date)}
                        {#if c.kind === 'installment' && c.months_count > 0}
                            · {c.months_paid + 1} من {c.months_count}
                        {/if}
                    {/if}
                </p>

                <!-- شارة الحالة -->
                {#if state === 'paid'}
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-success/10 px-1.5 py-px text-[10px] font-semibold text-success-text"
                    >
                        <Check class="size-2.5" /> مدفوع
                    </span>
                {:else if state === 'overdue'}
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-destructive/10 px-1.5 py-px text-[10px] font-semibold text-destructive"
                    >
                        <TriangleAlert class="size-2.5" /> متأخّر
                    </span>
                {:else if c.reserve_in_budget}
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-chart-7/12 px-1.5 py-px text-[10px] font-semibold text-chart-7"
                    >
                        <Lock class="size-2.5" /> محجوز
                    </span>
                {/if}
            </div>
        </div>
    </div>

    <!-- المبلغ -->
    <div class="mt-2.5 flex items-baseline justify-between gap-2">
        {#if unknownAmount}
            <p
                class="text-[19px] font-semibold whitespace-nowrap text-muted-foreground"
            >
                ؟ <span class="text-[11px] font-medium">لم يُسجَّل</span>
            </p>
            <p class="shrink-0 text-[11px] text-muted-foreground">
                متوسّط 3 أشهر <b
                    class="font-semibold text-foreground tabular-nums"
                    >{formatAmount(c.average_amount)}</b
                >
            </p>
        {:else}
            <p class="text-[20px] font-semibold tracking-tight tabular-nums">
                {formatAmount(c.amount ?? 0)}<span
                    class="ms-1 text-[11px] font-medium text-muted-foreground"
                >
                    ر.س{c.kind === 'installment' ? ' / شهر' : ''}
                </span>
            </p>

            {#if c.kind === 'installment' && monthsLeft > 0}
                <p class="shrink-0 text-[11px] text-muted-foreground">
                    باقي <b class="font-semibold text-foreground tabular-nums"
                        >{formatAmount(remainingAmount)}</b
                    >
                </p>
            {:else if state === 'paid' && onUndo}
                <button
                    type="button"
                    onclick={() => onUndo?.(c)}
                    class="inline-flex shrink-0 items-center gap-1 text-[11px] text-muted-foreground underline-offset-2 hover:underline"
                >
                    <Undo2 class="size-3" /> تراجع
                </button>
            {/if}
        {/if}
    </div>

    <!-- تقدّم القسط -->
    {#if c.kind === 'installment' && c.months_count > 0}
        <div class="mt-2">
            <div
                class="h-[6px] overflow-hidden rounded-full border border-border bg-secondary"
            >
                <div
                    class="h-full rounded-full transition-[width] duration-300"
                    style="width:{progress}%;background-color:{c.color}"
                ></div>
            </div>
            <p class="mt-1.5 truncate text-[11px] text-muted-foreground">
                {#if monthsLeft === 0}
                    <span class="font-semibold text-success-text"
                        >خلص — آخر قسط اندفع</span
                    >
                {:else}
                    يخلص في <b class="font-semibold text-foreground"
                        >{finishLabel(monthsLeft)}</b
                    >
                    · باقي {monthsLeft}
                    {monthsLeft === 1
                        ? 'شهر'
                        : monthsLeft === 2
                          ? 'شهرين'
                          : 'أشهر'}
                {/if}
            </p>
        </div>
    {/if}

    <!-- مقارنة الفاتورة المتغيّرة بمعدّلها -->
    {#if vsAverage}
        <p
            class="mt-2 inline-flex items-center gap-1.5 text-[11px] {vsAverage.higher
                ? 'text-warning-text'
                : 'text-success-text'}"
        >
            {#if vsAverage.higher}
                <TrendingUp class="size-3.5" /> أعلى {vsAverage.pct}٪ عن معدّلك
            {:else}
                <TrendingDown class="size-3.5" /> أقل {vsAverage.pct}٪ عن معدّلك
            {/if}
        </p>
    {/if}

    <!-- الإجراءات — ارتفاع موحّد 44px، ولا زر بأيقونة مجرّدة -->
    {#if state !== 'paid'}
        <div class="mt-2.5 flex gap-2 border-t border-border pt-2.5">
            <button
                type="button"
                onclick={() => onPay?.(c)}
                class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary px-3 text-[13px] font-medium text-primary-foreground transition-transform active:scale-[.98]"
            >
                <Check class="size-4" />
                {unknownAmount ? 'سجّل المبلغ ودفعت' : 'تم الدفع'}
            </button>
            <button
                type="button"
                onclick={() => onEdit?.(c)}
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-input px-3.5 text-[13px] text-foreground/85 transition-colors hover:bg-secondary"
            >
                <Pencil class="size-4" /> تعديل
            </button>
        </div>
    {/if}
</article>
