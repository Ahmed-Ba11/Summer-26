<script lang="ts">
    /**
     * بطاقة نوع = ملخّص + فلتر في آنٍ واحد.
     *
     * أربعة أنواع، أربع بطاقات بنفس القياس تماماً (شبكة 2×2 على الجوال).
     * الضغط عليها يفلتر القائمة تحتها — نفس فائدة التبويبات بلا تنقّل،
     * ومع إبقاء أرقام كل نوع ظاهرة طوال الوقت.
     */
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount } from '@/lib/format';
    import { type CommitmentKind, KIND_ICON, KIND_LABEL_PLURAL } from '@/lib/commitments';

    let {
        kind,
        color,
        count = 0,
        total = 0,
        paid = 0,
        note = '',
        active = false,
        onToggle,
    }: {
        kind: CommitmentKind;
        color: string;
        count?: number;
        total?: number;
        paid?: number;
        note?: string;
        active?: boolean;
        onToggle?: (k: CommitmentKind) => void;
    } = $props();

    const paidPct = $derived(total > 0 ? Math.min(100, (paid / total) * 100) : 0);
</script>

<button
    type="button"
    aria-pressed={active}
    onclick={() => onToggle?.(kind)}
    class="min-h-[92px] rounded-2xl border bg-card p-2.5 text-start transition-colors {active
        ? 'border-2 border-current'
        : 'border-border hover:border-input'}"
    style={active ? `color:${color}` : ''}
>
    <div class="flex items-center gap-2">
        <CategoryIcon icon={KIND_ICON[kind]} {color} size="sm" />
        <span class="truncate text-[12px] font-semibold text-foreground">{KIND_LABEL_PLURAL[kind]}</span>
        <span class="ms-auto text-[11.5px] text-muted-foreground tabular-nums">{count}</span>
    </div>

    <p class="mt-1.5 text-[15px] font-semibold tracking-tight text-foreground tabular-nums">
        {formatAmount(total)}<span class="ms-1 text-[11.5px] font-medium text-muted-foreground">ر.س</span>
    </p>

    <div class="mt-1.5 flex h-1 gap-px overflow-hidden rounded-full bg-secondary">
        {#if paidPct > 0}
            <div style="width:{paidPct}%;background:var(--success)"></div>
        {/if}
        <div style="width:{100 - paidPct}%;background:{color}"></div>
    </div>

    <p class="mt-1 truncate text-[11.5px] {paidPct === 100 ? 'text-success-text' : 'text-muted-foreground'}">
        {note}
    </p>
</button>
