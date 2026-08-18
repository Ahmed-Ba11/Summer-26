<script lang="ts">
    /**
     * أيقونة فئة داخل حاوية ملوّنة — البديل المعتمد عن الإيموجي.
     *
     *   <CategoryIcon icon="utensils" color="#2a78d6" />
     *   <CategoryIcon icon="car" color="#eb6834" size="sm" />
     */
    import { iconFor, ICON_LABELS, type IconKey } from '@/lib/category-icons';

    let {
        icon = 'ellipsis',
        color = '#8a8b90',
        size = 'md',
        label,
        class: className = '',
    }: {
        icon?: string;
        color?: string;
        size?: 'xs' | 'sm' | 'md' | 'lg';
        label?: string;
        class?: string;
    } = $props();

    const Icon = $derived(iconFor(icon));

    const box = {
        xs: 'size-6 rounded-[7px]',
        sm: 'size-7 rounded-[8px]',
        md: 'size-8 rounded-[9px]',
        lg: 'size-10 rounded-[11px]',
    } as const;

    const glyph = {
        xs: 'size-3',
        sm: 'size-3.5',
        md: 'size-4',
        lg: 'size-5',
    } as const;

    const aria = $derived(label ?? ICON_LABELS[icon as IconKey] ?? '');
</script>

<span
    class="inline-flex shrink-0 items-center justify-center {box[size]} {className}"
    style="background-color: color-mix(in srgb, {color} 12%, transparent); color: {color}"
    role="img"
    aria-label={aria}
>
    <Icon class={glyph[size]} />
</span>
