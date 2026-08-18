<script lang="ts">
    /**
     * مجموعة عناصر تنقّل. أُضيف دعم:
     *  - badge : عدد أحمر (الفواتير المستحقة)
     *  - tag   : وسم نصّي رمادي (تجريبي)
     */
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        items = [],
        label = 'القائمة الرئيسية',
    }: {
        items: NavItem[];
        label?: string;
    } = $props();

    const url = currentUrlState();
</script>

<SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel class="px-3 pt-3.5 pb-1.5 text-[11px] text-muted-foreground">
        {label}
    </SidebarGroupLabel>
    <SidebarMenu>
        {#each items as item (toUrl(item.href))}
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.isCurrentUrl(item.href, url.currentUrl)}
                    tooltip={item.title}
                >
                    {#snippet children(props)}
                        <Link {...(props || {})} href={toUrl(item.href)} class="flex w-full items-center gap-3">
                            {#if item.icon}
                                <item.icon class="size-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>

                            {#if item.badge}
                                <span
                                    class="ms-auto rounded-full bg-destructive px-1.5 text-[10px] leading-4 font-semibold text-white tabular-nums"
                                >
                                    {item.badge}
                                </span>
                            {:else if item.tag}
                                <span
                                    class="ms-auto rounded-full bg-secondary px-1.5 text-[10px] leading-4 text-muted-foreground"
                                >
                                    {item.tag}
                                </span>
                            {/if}
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
