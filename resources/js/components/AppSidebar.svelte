<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Target from 'lucide-svelte/icons/target';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const mainNavItems: NavItem[] = [
        {
            title: 'لوحة التحكم',
            href: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'المصاريف',
            href: '/expenses',
            icon: ArrowRightLeft,
        },
        {
            title: 'الدخل',
            href: '/income',
            icon: TrendingUp,
        },
        {
            title: 'الميزانيات',
            href: '/budgets',
            icon: Target,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...(props || {})}
                            href={toUrl('/dashboard')}
                            class={props?.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
