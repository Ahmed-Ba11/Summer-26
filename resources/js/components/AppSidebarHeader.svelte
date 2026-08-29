<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Bell from 'lucide-svelte/icons/bell';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import ThemeToggle from '@/components/ThemeToggle.svelte';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    const dueCommitments = $derived(page.props.navStats?.dueCommitments ?? 0);
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

    <div class="flex items-center gap-1.5">
        <!-- التنبيهات — الالتزامات المستحقة -->
        <Link
            href="/commitments"
            aria-label="التنبيهات{dueCommitments > 0 ? ` — ${dueCommitments} مستحق` : ''}"
            title="التنبيهات"
            class="relative inline-flex size-9 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground transition-transform after:absolute after:-inset-1 active:scale-95 hover:text-foreground"
        >
            <Bell class="size-[17px]" stroke-width="1.9" />
            {#if dueCommitments > 0}
                <span class="absolute top-1 grid h-4 min-w-4 place-items-center rounded-full bg-destructive px-1 text-[10px] font-bold text-white tabular-nums" style="inset-inline-end:6px">
                    {dueCommitments}
                </span>
            {/if}
        </Link>

        <!-- اختصار المظهر — في متناول اليد في كل صفحة لا مدفوناً في الإعدادات -->
        <ThemeToggle />
    </div>
</header>
