<script lang="ts">
    /**
     * صف داخل لوح: عنوان + قيمة + سهم — يفتح لوحاً فرعياً (مبلغ · تاريخ).
     */
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import type { IconComponent } from '@/types';

    let {
        label,
        value,
        placeholder = '',
        icon,
        error = '',
        onclick,
    }: {
        label: string;
        value?: string;
        placeholder?: string;
        icon?: IconComponent;
        error?: string;
        onclick: () => void;
    } = $props();

    const Icon = $derived(icon);
</script>

<div class="flex flex-col gap-1.5">
    <span class="text-[11.5px] text-muted-foreground">{label}</span>
    <button
        type="button"
        {onclick}
        class="inline-flex min-h-11 w-full items-center gap-2.5 rounded-2xl border bg-background px-3 text-start transition-transform active:scale-[.99] {error
            ? 'border-destructive'
            : 'border-input'}"
    >
        {#if Icon}
            <Icon class="size-[18px] shrink-0 text-muted-foreground" />
        {/if}
        <span class="min-w-0 flex-1 truncate text-[14px] font-semibold tabular-nums {value ? '' : 'font-normal text-muted-foreground'}">
            {value || placeholder}
        </span>
        <ChevronLeft class="size-4 shrink-0 text-muted-foreground" />
    </button>
    {#if error}
        <p class="text-[11.5px] text-destructive">{error}</p>
    {/if}
</div>
