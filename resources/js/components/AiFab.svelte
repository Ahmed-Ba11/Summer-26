<script lang="ts">
    /**
     * الزر العائم الوحيد للوصول إلى المساعد الذكي — يظهر في كل صفحة
     * ما عدا `/assistant` نفسها (يُركَّب مرّةً واحدة في `AppSidebarLayout`).
     *
     * عنصر واحد لا اثنان: الدائرة والشريط شكلان لنفس الزرّ، لا زرّ
     * وتلميح منفصل بجانبه. التمدّد بـ`max-width` على عنصر عرضه الطبيعي
     * (`width: max-content`) — الأيقونة ثابتة 38px عند الحافة المثبَّتة
     * (`end`)، والنص يُكشف أو يُخفى بقصّ ما يفيض. الترتيب في الشجرة
     * [نصّ، أيقونة] لا [أيقونة، نصّ]: في اتجاه RTL آخر عنصر في صفّ
     * `flex` ينتهي عند `inline-end` — وهي نفس الحافة المثبَّتة بـ`end-4`.
     * لو انعكس الترتيب لالتصقت الأيقونة بالحافة المتحرّكة بدل الثابتة.
     *
     * `z-[56]`: أعلى من شريط التنقّل السفلي (`z-[55]` في `AppNav`) فلا
     * يُحجب خلفه، وأقلّ من قاعدة الألواح (`z-index: 60` في `SheetShell`)
     * فلا يطفو فوق أي لوح مفتوح.
     */
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AiAssistantIcon from '@/components/icons/AiAssistantIcon.svelte';

    let expanded = $state(false);

    onMount(() => {
        // لا تمدّد لمن يفضّل تقليل الحركة — الزر يبقى دائرة ثابتة.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const openTimer = setTimeout(() => {
            expanded = true;
        }, 50);

        const closeTimer = setTimeout(() => {
            expanded = false;
        }, 3050);

        return () => {
            clearTimeout(openTimer);
            clearTimeout(closeTimer);
        };
    });
</script>

<Link
    href="/assistant"
    aria-label="المساعد الذكي"
    data-test="ai-fab"
    class="fixed end-4 z-[56] flex h-[38px] items-center justify-end gap-1 overflow-hidden rounded-full text-white no-underline shadow-lg transition-[max-width] duration-300 ease-out motion-reduce:transition-none active:scale-95"
    style="background: linear-gradient(145deg, #2c4a6e, #1baf7a); width: max-content; max-width: {expanded
        ? '160px'
        : '38px'}; bottom: calc(5rem + env(safe-area-inset-bottom))"
>
    <span class="ps-3.5 text-[12.5px] font-medium whitespace-nowrap"
        >المساعد الذكي</span
    >
    <span class="grid size-[38px] shrink-0 place-items-center">
        <!-- نفس أيقونة بطاقة «اسأل المساعد الذكي» في Dashboard.svelte،
             وبنفس نسبة الحجم إلى الدائرة (20px داخل 40px هناك = ٪50
             ← 19px داخل 38px هنا). -->
        <AiAssistantIcon class="size-[19px]" />
    </span>
</Link>
