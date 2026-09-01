<script lang="ts">
    /**
     * بطاقة رقم مختصرة. السطر السياقي في الأسفل هو ما يجعلها مفيدة —
     * رقم بلا مقارنة أو تفصيل لا يحمل معنى.
     */
    import { formatCurrency } from '@/lib/format';
    import type { IconComponent } from '@/types';

    let {
        label,
        amount,
        icon,
        color = 'var(--primary)',
        note = '',
        tone = 'neutral',
    }: {
        label: string;
        amount: number;
        icon: IconComponent;
        color?: string;
        note?: string;
        tone?: 'neutral' | 'good' | 'bad';
    } = $props();

    const Icon = $derived(icon);

    const noteClass = $derived(
        tone === 'good' ? 'text-success-text' : tone === 'bad' ? 'text-destructive' : 'text-muted-foreground',
    );
</script>

<div class="rounded-2xl border border-border bg-card px-4 py-4 shadow-xs">
    <div class="flex items-center justify-between gap-2">
        <span class="heading-extended text-[12.5px] text-muted-foreground">{label}</span>
        <span
            class="grid size-8 place-items-center rounded-[9px]"
            style="background-color: color-mix(in srgb, {color} 12%, transparent); color: {color}"
        >
            <Icon class="size-4" />
        </span>
    </div>

    <p class="mt-2 text-xl font-semibold tracking-tight tabular-nums">
        {formatCurrency(amount)}
    </p>

    {#if note}
        <p class="mt-0.5 text-[11.5px] {noteClass}">{note}</p>
    {/if}
</div>
