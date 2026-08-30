<script lang="ts">
    /**
     * رأس الشاشة على الجوال — يظهر تحت 768px فقط.
     *
     * يحمل عنوان الصفحة وسطر سياق قصير. مدخل المساعد الذكي انتقل إلى
     * `AiFab` (زرّ عائم واحد في كل الصفحات) — لا أثر له هنا.
     *
     * لاصق في الأعلى (sticky) فيبقى العنوان ظاهراً أثناء التمرير.
     */
    import { Link } from '@inertiajs/svelte';
    import ChartNoAxesColumn from 'lucide-svelte/icons/chart-no-axes-column';
    import ThemeToggle from '@/components/ThemeToggle.svelte';

    let {
        title,
        subtitle = '',
    }: {
        title: string;
        subtitle?: string;
    } = $props();
</script>

<header
    class="sticky top-0 z-40 flex items-center justify-between gap-3 border-b border-border bg-card/95 px-4 py-3 backdrop-blur-sm md:hidden"
    style="padding-top: calc(0.75rem + env(safe-area-inset-top))"
>
    <div class="min-w-0">
        <h1 class="truncate text-[15px] font-semibold tracking-tight">
            {title}
        </h1>
        {#if subtitle}
            <p class="truncate text-[11px] text-muted-foreground">{subtitle}</p>
        {/if}
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <!-- التقارير — أيقونة ثابتة، لا وجهة تُطلب من الإعدادات -->
        <Link
            href="/reports"
            aria-label="التقارير"
            class="grid size-11 shrink-0 place-items-center rounded-xl text-muted-foreground no-underline transition-colors hover:bg-secondary hover:text-foreground"
        >
            <ChartNoAxesColumn class="size-[19px]" />
        </Link>

        <!-- اختصار المظهر — في رأس كل صفحة لا مدفوناً في الإعدادات -->
        <ThemeToggle />
    </div>
</header>
