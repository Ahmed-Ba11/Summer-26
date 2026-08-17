<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import Target from 'lucide-svelte/icons/target';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Bot from 'lucide-svelte/icons/bot';
    import type { Snippet } from 'svelte';
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
            title: 'الدخل',
            href: '/income',
            icon: TrendingUp,
        },
        {
            title: 'الميزانية العامة',
            href: '/budgets',
            icon: Target,
        },
        {
            title: 'الادخار',
            href: '/savings',
            icon: PiggyBank,
        },
        {
            title: 'الأقساط',
            href: '/installments',
            icon: CreditCard,
        },
        {
            title: 'الفواتير',
            href: '/bills',
            icon: ReceiptText,
        },
        {
            title: 'المصاريف',
            href: '/expenses',
            icon: ArrowRightLeft,
        },
        {
            title: 'لوحة التحكم',
            href: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'المساعد الذكي',
            href: '#',
            icon: Bot,
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
                            class="flex items-center gap-3 w-full"
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
