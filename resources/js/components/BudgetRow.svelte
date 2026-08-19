<script lang="ts">
    /**
     * صف ميزانية فئة — مدمج (لا بطاقة كاملة الارتفاع، أوفر للمساحة).
     *
     * قاعدة إلزامية: الحالة لا تُنقل باللون وحده. كل حالة تحمل أيقونة + نص.
     * شريط التقدّم لا يتجاوز ١٠٠٪ بصرياً؛ التجاوز يُنقل بالشارة والنص.
     */
    import Check from 'lucide-svelte/icons/check';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount, formatCurrency } from '@/lib/format';

    let {
        name,
        icon = 'ellipsis',
        color = '#8a8b90',
        spent = 0,
        budget = 0,
        rollover = 0,
        onclick,
    }: {
        name: string;
        icon?: string;
        color?: string;
        spent?: number;
        budget?: number;
        rollover?: number;
        onclick?: () => void;
    } = $props();

    const effective = $derived(budget + rollover);
    const pct = $derived(effective > 0 ? (spent / effective) * 100 : 0);

    const state = $derived(pct > 100 ? 'over' : pct >= 70 ? 'near' : 'safe');

    const barColor = $derived(
        state === 'over' ? 'var(--destructive)' : state === 'near' ? 'var(--warning)' : color,
    );

    const badge = $derived(
        state === 'over'
            ? { text: 'تجاوزت', cls: 'bg-destructive/10 text-destructive' }
            : state === 'near'
              ? { text: 'اقترب', cls: 'bg-warning/15 text-warning-text' }
              : { text: 'آمن', cls: 'bg-success/10 text-success-text' },
    );
</script>

{#snippet content()}
    <CategoryIcon {icon} {color} size="md" />

    <div class="min-w-0 flex-1">
        <div class="mb-1.5 flex items-baseline justify-between gap-2">
            <span class="text-[13.5px] font-medium">{name}</span>
            <span
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold {badge.cls}"
            >
                {#if state === 'safe'}
                    <Check class="size-2.5" />
                {:else}
                    <TriangleAlert class="size-2.5" />
                {/if}
                {badge.text}
            </span>
        </div>

        <div class="h-[7px] overflow-hidden rounded-full border border-border bg-secondary">
            <div
                class="h-full rounded-full transition-[width] duration-300"
                style="width: {Math.min(pct, 100)}%; background-color: {barColor}"
            ></div>
        </div>

        <div class="mt-1.5 flex justify-between text-[11.5px] text-muted-foreground">
            <span class="tabular-nums">{formatAmount(spent)} من {formatCurrency(effective)}</span>
            {#if state === 'over'}
                <span class="font-medium text-destructive tabular-nums">
                    تجاوز {formatAmount(spent - effective)}
                </span>
            {:else}
                <span class="tabular-nums">باقي {formatAmount(effective - spent)}</span>
            {/if}
        </div>
    </div>
{/snippet}

{#if onclick}
    <button
        type="button"
        {onclick}
        class="flex w-full items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-start transition-colors cursor-pointer hover:bg-secondary"
    >
        {@render content()}
    </button>
{:else}
    <div class="flex w-full items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-start">
        {@render content()}
    </div>
{/if}
