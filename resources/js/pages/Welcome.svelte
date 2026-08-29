<script module lang="ts">
    export const layout = null;
</script>

<script lang="ts">
    /**
     * شاشة الترحيب — أول ما يراه المستخدم، وعليها يُبنى الانطباع كلّه.
     *
     * صفحة واحدة تخدم حالتين:
     *   • الضيف        → «ابدأ» ينشئ حساباً
     *   • المسجَّل الجديد → «ابدأ» يفتح الإعداد مباشرة
     *
     * لماذا شاشة قبل اللوحة أصلاً: اللوحة قبل الإعداد أصفار في كل بطاقة،
     * وهي أسوأ صورة أولى ممكنة. هنا نقول ماذا يكسب في ثلاث نقاط، ثم نطلب
     * دقيقتين — لا نطلبهما قبل أن نقول لماذا.
     */
    import { Link, page } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import CalendarCheck from 'lucide-svelte/icons/calendar-check';
    import FileText from 'lucide-svelte/icons/file-text';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';

    const user = $derived(page.props.auth?.user ?? null);

    /** المسجَّل الجديد يذهب للإعداد، والضيف ينشئ حساباً أولاً. */
    const startHref = $derived(user ? '/setup' : '/register');
    const startLabel = 'رتّب راتبك الآن';
    const startHint = 'إعداد بسيط ما يأخذ أكثر من دقيقتين';

    /**
     * الفوائد الثلاث — بلا شرطات فاصلة بين الجمل.
     *
     * الشرطة توقف القراءة وتوحي بجملة اعتراضية، والوعد الأول لا يحتمل
     * توقّفاً. الفاصلة تصل، والنقطة تُنهي.
     */
    const BENEFITS = [
        {
            icon: CalendarCheck,
            title: 'اعرف كم تقدر تصرف اليوم',
            detail: 'نحسب لك مبلغًا يوميًا آمنًا بناءً على راتبك، التزاماتك والباقي من الشهر.',
            color: 'var(--chart-3)',
        },
        {
            icon: ShieldCheck,
            title: 'التزاماتك محسوبة من البداية',
            detail: 'الإيجار، الأقساط والفواتير تنحسب قبل ميزانية الصرف، عشان ما تتفاجأ آخر الشهر.',
            color: 'var(--chart-1)',
        },
        {
            icon: FileText,
            title: 'تقرير شهري يوضح وين راح راتبك',
            detail: 'تعرف كم صرفت، على إيش صرفت، وكم قدرت تدخر خلال الشهر.',
            color: 'var(--chart-7)',
        },
    ];
</script>

<AppHead title="موفّر — راتبك من أول يوم لآخره" />

<main
    class="flex min-h-svh flex-col bg-background px-5 text-foreground"
    style="padding-top: calc(2rem + env(safe-area-inset-top)); padding-bottom: calc(1.25rem + env(safe-area-inset-bottom))"
>
    <div class="mx-auto flex w-full max-w-md flex-1 flex-col">
        <!-- الهوية -->
        <div class="flex flex-1 flex-col justify-center py-6">
            <div class="flex flex-col items-center text-center">
                <img
                    src="/icon.svg"
                    alt=""
                    width="88"
                    height="88"
                    class="size-[88px] rounded-[22px]"
                />

                <h1
                    class="mt-5 text-[33px] leading-none font-bold tracking-tight"
                    style="font-family: var(--font-display)"
                >
                    موفّر
                </h1>

                <p
                    class="mt-3 max-w-[19rem] text-[14px] leading-relaxed text-muted-foreground"
                >
                    راتبك من أول يوم لآخره، تعرف وين راح وكم باقي
                </p>
            </div>

            <!-- الفوائد الثلاث -->
            <ul class="mt-8 flex flex-col gap-3">
                {#each BENEFITS as benefit (benefit.title)}
                    {@const Icon = benefit.icon}
                    <li
                        class="flex items-start gap-3 rounded-2xl border border-border bg-card p-3 shadow-xs"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl"
                            style="background-color: color-mix(in srgb, {benefit.color} 12%, transparent); color: {benefit.color}"
                        >
                            <Icon class="size-5" stroke-width="1.9" />
                        </span>
                        <span class="min-w-0 pt-0.5">
                            <b class="block text-[14px] font-semibold"
                                >{benefit.title}</b
                            >
                            <span
                                class="mt-0.5 block text-[11.5px] leading-relaxed text-muted-foreground"
                            >
                                {benefit.detail}
                            </span>
                        </span>
                    </li>
                {/each}
            </ul>
        </div>

        <!-- الإجراء -->
        <div class="flex flex-col gap-3 pt-2">
            <Link
                href={startHref}
                class="inline-flex min-h-[52px] items-center justify-center gap-2 rounded-2xl bg-primary text-[15px] font-semibold text-primary-foreground no-underline shadow-sm transition-transform active:scale-[.98]"
            >
                {startLabel}
                <ArrowLeft class="size-[18px]" stroke-width="1.9" />
            </Link>

            <p class="text-center text-[12px] text-muted-foreground">
                {startHint}
            </p>

            {#if user}
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-2xl text-[13px] text-muted-foreground no-underline"
                >
                    تسجيل الخروج
                </Link>
            {:else}
                <Link
                    href="/login"
                    class="inline-flex min-h-11 items-center justify-center rounded-2xl text-[13px] text-muted-foreground no-underline"
                >
                    عندي حساب؟ <span class="ms-1 font-semibold text-primary"
                        >سجّل دخولك</span
                    >
                </Link>
            {/if}
        </div>
    </div>
</main>
