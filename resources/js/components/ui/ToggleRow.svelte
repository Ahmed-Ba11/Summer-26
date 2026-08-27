<script lang="ts">
    /**
     * صف مفتاح — عنوان + وصف قصير + مفتاح، والصف كلّه هدف اللمس.
     *
     * المفتاح وحده هدف 32×18px، أصغر من الحد الأدنى بكثير. جعل الصف كلّه
     * زراً يرفع الهدف إلى 44px بلا تكبير المفتاح نفسه بصرياً.
     */
    import type { IconComponent } from '@/types';

    let {
        checked = $bindable(false),
        label,
        detail = '',
        icon,
        disabled = false,
        onchange,
    }: {
        checked?: boolean;
        label: string;
        detail?: string;
        icon?: IconComponent;
        disabled?: boolean;
        onchange?: (value: boolean) => void;
    } = $props();

    const Icon = $derived(icon);

    function toggle() {
        if (disabled) return;
        checked = !checked;
        onchange?.(checked);
    }
</script>

<button
    type="button"
    role="switch"
    aria-checked={checked}
    {disabled}
    onclick={toggle}
    class="flex min-h-11 w-full items-center gap-3 rounded-2xl px-1 py-2 text-start transition-transform active:scale-[.99] disabled:opacity-45"
>
    {#if Icon}
        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-secondary text-muted-foreground">
            <Icon class="size-[18px] [stroke-width:1.9]" />
        </span>
    {/if}

    <span class="min-w-0 flex-1">
        <b class="block text-[14px] font-semibold">{label}</b>
        {#if detail}
            <span class="mt-0.5 block text-[11.5px] leading-relaxed text-muted-foreground">{detail}</span>
        {/if}
    </span>

    <span
        class="relative inline-flex h-[26px] w-[46px] shrink-0 items-center rounded-full transition-colors {checked
            ? 'bg-primary'
            : 'bg-input'}"
    >
        <span
            class="absolute size-[20px] rounded-full bg-white shadow-sm transition-[inset-inline-start] duration-200"
            style="inset-inline-start: {checked ? '23px' : '3px'}"
        ></span>
    </span>
</button>
