<script lang="ts">
    /**
     * رأس الشاشة على الجوال — يظهر تحت 768px فقط.
     *
     * يحمل عنوان الصفحة وسطر سياق قصير، وزر المساعد الذكي على اليسار.
     * المساعد هنا لا في الشريط السفلي: يبقى في متناول اليد دائماً بلا
     * ما يستهلك خانة من الخانات الأربع النادرة.
     *
     * لاصق في الأعلى (sticky) فيبقى العنوان ظاهراً أثناء التمرير.
     */
    import { Link } from '@inertiajs/svelte';
    import AiAssistantIcon from '@/components/icons/AiAssistantIcon.svelte';
    import ThemeToggle from '@/components/ThemeToggle.svelte';

    let {
        title,
        subtitle = '',
        showAssistant = true,
    }: {
        title: string;
        subtitle?: string;
        showAssistant?: boolean;
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

    <div class="flex shrink-0 items-center gap-2">
        <!-- اختصار المظهر — في رأس كل صفحة لا مدفوناً في الإعدادات -->
        <ThemeToggle />

        {#if showAssistant}
            <Link
                href="/assistant"
                aria-label="المساعد الذكي"
                class="grid size-11 shrink-0 place-items-center rounded-xl text-white no-underline transition-transform active:scale-95"
                style="background:linear-gradient(145deg,#2c4a6e,#1baf7a);box-shadow:0 3px 10px rgba(27,175,122,.26)"
            >
                <AiAssistantIcon class="size-[18px]" />
            </Link>
        {/if}
    </div>
</header>
