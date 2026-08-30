<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AiFab from '@/components/AiFab.svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppNav from '@/components/AppNav.svelte';
    import AppShell from '@/components/AppShell.svelte';
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
    // المدخل الوحيد العائم إلى المساعد — في كل صفحة إلا صفحته نفسها.
    const showAiFab = $derived(!(page.url ?? '').startsWith('/assistant'));
    let expanded = $state(page.props.railExpanded === true);
    /** لوح الأربعة إجراءات */
    let quickMenuOpen = $state(false);
    /** لوح المصروف/الدخل — يُفتح من القائمة أو بالضغطة المطوّلة */
    let quickAddOpen = $state(false);
    let quickAddMode = $state<'expense' | 'income'>('expense');

    function openExpenseDirectly() {
        quickAddMode = 'expense';
        quickAddOpen = true;
    }
</script>

<AppShell variant="sidebar">
    <AppNav
        stats={navStats}
        bind:expanded
        onQuickAdd={() => (quickMenuOpen = true)}
        onQuickAddHold={openExpenseDirectly}
    />
    <AppContent
        variant="sidebar"
        class="overflow-x-hidden pb-[calc(72px+env(safe-area-inset-bottom))] md:pb-0"
    >
        <div class="hidden md:block">
            <AppSidebarHeader {breadcrumbs} />
        </div>
        {@render children?.()}
    </AppContent>
    <QuickAddFab
        bind:menuOpen={quickMenuOpen}
        bind:sheetOpen={quickAddOpen}
        bind:sheetMode={quickAddMode}
    />
    {#if showAiFab}
        <AiFab />
    {/if}
    <Toaster position="top-center" />
</AppShell>
