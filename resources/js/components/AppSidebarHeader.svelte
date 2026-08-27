<script lang="ts">
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import ThemeToggle from '@/components/ThemeToggle.svelte';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();
</script>

<header
    class="hidden h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:flex md:px-4"
>
    <div class="flex items-center gap-2">
        <SidebarTrigger class="-ml-1" />
        {#if breadcrumbs && breadcrumbs.length > 0}
            <Breadcrumbs {breadcrumbs} />
        {/if}
    </div>

    <div class="flex-1"></div>

    <!-- اختصار المظهر — في متناول اليد في كل صفحة لا مدفوناً في الإعدادات -->
    <ThemeToggle />
</header>
