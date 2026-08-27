<script lang="ts">
    /**
     * اختصار المظهر في رأس كل صفحة — فاتح ← داكن ← تلقائي بضغطة.
     *
     * دفن التبديل في الإعدادات يعني أربع لمسات لتغيير يُطلَب مرّات في اليوم
     * (شمس الظهر ثم سرير الليل). ارتفاع الزر 36px وهدف لمسه 44px عبر
     * `after` ممتدّة — الحجمان لا يتناقضان.
     *
     * الحالة مكتوبة بجانب الأيقونة لا مضمَّنة فيها: أيقونة الشمس والقمر
     * تُقرأ وحدها، لكن أيقونة الشاشة لا تقول «تلقائي» لأحد — ومع ثلاث حالات
     * لا يعرف المستخدم أين هو في الدورة ولا ما الذي ستعطيه الضغطة التالية.
     * `aria-live` يعلن الحالة الجديدة بعد الضغط لمن لا يرى الزر.
     *
     * التغيير يُحفظ في الحساب أيضاً فيصل الجهاز التالي بنفس المظهر.
     */
    import { router } from '@inertiajs/svelte';
    import Monitor from 'lucide-svelte/icons/monitor';
    import Moon from 'lucide-svelte/icons/moon';
    import Sun from 'lucide-svelte/icons/sun';
    import { cycleAppearance, themeState } from '@/lib/theme.svelte';

    let {
        persist = true,
        /** الأيقونة وحدها — للمواضع الضيّقة جداً؛ التسمية تبقى في `aria-label` */
        iconOnly = false,
    }: { persist?: boolean; iconOnly?: boolean } = $props();

    const { appearance } = themeState();

    const LABELS = { light: 'فاتح', dark: 'داكن', system: 'تلقائي' } as const;
    const label = $derived(LABELS[appearance.value]);
    const Icon = $derived(
        appearance.value === 'dark'
            ? Moon
            : appearance.value === 'light'
              ? Sun
              : Monitor,
    );

    function toggle() {
        const next = cycleAppearance();

        if (!persist) {
            return;
        }

        router.patch(
            '/settings/preferences',
            { theme: next, silent: true },
            { preserveScroll: true, preserveState: true },
        );
    }
</script>

<button
    type="button"
    onclick={toggle}
    aria-label="المظهر: {label} — اضغط للتبديل"
    title="المظهر: {label}"
    class="relative inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-border bg-card text-muted-foreground transition-transform after:absolute after:-inset-1 active:scale-95 hover:text-foreground {iconOnly
        ? 'w-9'
        : 'ps-2.5 pe-3'}"
>
    <Icon class="size-[17px]" stroke-width="1.9" />

    {#if !iconOnly}
        <span class="text-[12.5px] font-medium" aria-live="polite">{label}</span
        >
    {/if}
</button>
