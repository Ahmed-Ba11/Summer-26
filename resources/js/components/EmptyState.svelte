<script lang="ts">
    /**
     * حالة فارغة موحّدة. استخدمها في كل جدول وكل قائمة.
     * قائمة فارغة بلا شرح ولا إجراء = مستخدم عالق.
     */
    import type { Snippet } from 'svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { iconFor } from '@/lib/category-icons';
    import type { IconComponent } from '@/types';

    let {
        icon,
        title,
        description = '',
        actionLabel = '',
        onaction,
        onAction,
        href = '',
        children,
    }: {
        /** مكوّن أيقونة أو اسم أيقونة من خريطة category-icons (مثل "receipt"). */
        icon?: IconComponent | string;
        title: string;
        description?: string;
        actionLabel?: string;
        onaction?: () => void;
        /** صيغة camelCase — تستخدمها بعض الصفحات. */
        onAction?: () => void;
        href?: string;
        children?: Snippet;
    } = $props();

    const Icon = $derived(typeof icon === 'string' ? iconFor(icon) : icon);
    const handleAction = $derived(onAction ?? onaction);
</script>

<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    {#if Icon}
        <div class="mb-3 grid size-12 place-items-center rounded-2xl bg-secondary text-muted-foreground">
            <Icon class="size-6" />
        </div>
    {/if}

    <p class="text-[15px] font-semibold">{title}</p>

    {#if description}
        <p class="mt-1 max-w-sm text-[13px] text-muted-foreground">{description}</p>
    {/if}

    {#if actionLabel}
        <div class="mt-4">
            {#if href}
                <Button size="sm" {href}>{actionLabel}</Button>
            {:else}
                <Button size="sm" onclick={handleAction}>{actionLabel}</Button>
            {/if}
        </div>
    {/if}

    {@render children?.()}
</div>
