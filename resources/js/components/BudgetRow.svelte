<script lang="ts">
    /**
     * صف ميزانية فئة.
     *
     * قاعدة إلزامية: الحالة لا تُنقل باللون وحده. كل حالة تحمل أيقونة + نص.
     * شريط التقدّم لا يتجاوز ١٠٠٪ بصرياً؛ التجاوز يُنقل بالشارة والنص.
     *
     * فئة بلا ميزانية تعرض زراً بنصّ صريح «حدّد ميزانية {الاسم}» وحدوداً
     * متقطّعة — لا ضغط مخفي على البطاقة يكشف أيقونة.
     */
    import Check from 'lucide-svelte/icons/check';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount, formatPercent } from '@/lib/format';

    let {
        name,
        icon = 'ellipsis',
        color = '#8a8b90',
        spent = 0,
        budget = 0,
        rollover = 0,
        onEdit,
    }: {
        name: string;
        icon?: string;
        color?: string;
        spent?: number;
        budget?: number;
        rollover?: number;
        onEdit?: () => void;
    } = $props();

    const effective = $derived(budget + rollover);
    const hasBudget = $derived(effective > 0);
    const pct = $derived(hasBudget ? (spent / effective) * 100 : 0);

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

{#if hasBudget}
    <div class="flex w-full items-center gap-3 rounded-2xl border border-border bg-card p-3 shadow-xs">
        <CategoryIcon {icon} {color} size="md" />

        <div class="min-w-0 flex-1">
            <div class="mb-1.5 flex items-baseline justify-between gap-2">
                <span class="truncate text-[14px] font-semibold">{name}</span>
                <span
                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {badge.cls}"
                >
                    {#if state === 'safe'}
                        <Check class="size-3" stroke-width="1.9" />
                    {:else}
                        <TriangleAlert class="size-3" stroke-width="1.9" />
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

            <!-- الرقم الصريح أولاً، ثم النسبة والمتبقي -->
            <p class="mt-1.5 text-[13px] tabular-nums">
                <b class="font-semibold">{formatAmount(spent)}</b>
                <span class="text-muted-foreground">من {formatAmount(effective)} ر.س</span>
            </p>
            <p class="mt-0.5 flex flex-wrap gap-x-2 text-[11.5px] text-muted-foreground tabular-nums">
                <span>{formatPercent(pct)}</span>
                <span aria-hidden="true">·</span>
                {#if state === 'over'}
                    <span class="font-medium text-destructive">
                        تجاوز {formatAmount(spent - effective)} ر.س
                    </span>
                {:else}
                    <span>باقي {formatAmount(effective - spent)} ر.س</span>
                {/if}
            </p>
        </div>

        <button
            type="button"
            onclick={onEdit}
            aria-label="تعديل ميزانية {name}"
            class="grid size-10 shrink-0 place-items-center self-start rounded-xl border border-input text-foreground/75 transition-transform active:scale-[.98]"
        >
            <Pencil class="size-[18px]" stroke-width="1.9" />
        </button>
    </div>
{:else}
    <div class="flex w-full flex-col gap-2.5 rounded-2xl border border-dashed border-input bg-card p-3">
        <div class="flex items-center gap-3">
            <CategoryIcon {icon} {color} size="md" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-[14px] font-semibold">{name}</p>
                <p class="text-[11.5px] text-muted-foreground tabular-nums">
                    {#if spent > 0}
                        صرفت {formatAmount(spent)} ر.س بلا ميزانية
                    {:else}
                        بلا ميزانية بعد
                    {/if}
                </p>
            </div>
        </div>

        <button
            type="button"
            onclick={onEdit}
            class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-primary/30 bg-primary/8 px-3 text-[13px] font-semibold text-primary transition-transform active:scale-[.98]"
        >
            <Plus class="size-[18px]" stroke-width="1.9" />
            حدّد ميزانية {name}
        </button>
    </div>
{/if}
