<script lang="ts">
    import type { Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppNav from '@/components/AppNav.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import QuickAddFab from '@/components/QuickAddFab.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    interface NavStats {
        remaining: number;
        dailySafe: number;
        daysLeft: number;
        budgetUsedPct: number;
        transactionsCount: number;
        dueCommitments: number;
        savingsPct: number;
        incomeSplit: { key: string; pct: number; color: string }[];
    }

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    const navStats = $derived(page.props.navStats as NavStats);
    let expanded = $state(page.props.railExpanded === true);
    let quickAddOpen = $state(false);
</script>

<AppShell variant="sidebar">
    <AppNav stats={navStats} bind:expanded onQuickAdd={() => (quickAddOpen = true)} />
    <AppContent variant="sidebar" class="overflow-x-hidden pb-[calc(72px+env(safe-area-inset-bottom))] md:pb-0">
        <div class="hidden md:block">
            <AppSidebarHeader {breadcrumbs} />
        </div>
        {@render children?.()}
    </AppContent>
    <QuickAddFab bind:sheetOpen={quickAddOpen} />
    <Toaster />
</AppShell>
