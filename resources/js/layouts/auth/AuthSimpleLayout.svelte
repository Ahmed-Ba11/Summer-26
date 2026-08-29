<script lang="ts">
    /**
     * هيكل صفحات الدخول.
     *
     * كان شعار Laravel الافتراضي يتصدّر الصفحة — أول ما يراه العائد إلى
     * حسابه شعارُ إطار عمل لا شعارُ تطبيقه. الأيقونة الآن أيقونة «موفّر»
     * نفسها التي في شاشة الترحيب والرأس، بنفس نصف قطر البلاطة، فتتّصل
     * الصفحة ببقيّة التطبيق بدل أن تبدو قادمة من مكان آخر.
     */
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="flex min-h-svh flex-col items-center justify-center bg-background px-5 py-10"
    style="padding-top: calc(2.5rem + env(safe-area-inset-top)); padding-bottom: calc(2.5rem + env(safe-area-inset-bottom))"
>
    <div class="w-full max-w-sm">
        <div class="flex flex-col gap-7">
            <div class="flex flex-col items-center gap-3 text-center">
                <Link
                    href={home()}
                    aria-label="موفّر"
                    class="no-underline transition-transform hover:scale-[1.03]"
                >
                    <img
                        src="/icon.svg"
                        alt=""
                        width="64"
                        height="64"
                        class="size-16 rounded-[16px]"
                    />
                </Link>

                <div class="space-y-1.5">
                    <h1
                        class="text-[22px] leading-tight font-bold tracking-tight"
                        style="font-family: var(--font-display)"
                    >
                        {title}
                    </h1>
                    {#if description}
                        <p
                            class="text-[13px] leading-relaxed text-muted-foreground"
                        >
                            {description}
                        </p>
                    {/if}
                </div>
            </div>
            {@render children?.()}
        </div>
    </div>
</div>
