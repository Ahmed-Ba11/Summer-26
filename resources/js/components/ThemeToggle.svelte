<script lang="ts">
    /**
     * اختصار المظهر في رأس كل صفحة — فاتح ← داكن ← تلقائي بضغطة.
     *
     * دفن التبديل في الإعدادات يعني أربع لمسات لتغيير يُطلَب مرّات في اليوم
     * (شمس الظهر ثم سرير الليل). الزر هنا 36px بصرياً وهدف لمسه 44px عبر
     * `after` ممتدّة — الحجمان لا يتناقضان.
     *
     * التغيير يُحفظ في الحساب أيضاً فيصل الجهاز التالي بنفس المظهر.
     */
    import { router } from '@inertiajs/svelte';
    import Monitor from 'lucide-svelte/icons/monitor';
    import Moon from 'lucide-svelte/icons/moon';
    import Sun from 'lucide-svelte/icons/sun';
    import { cycleAppearance, themeState } from '@/lib/theme.svelte';

    let { persist = true }: { persist?: boolean } = $props();

    const { appearance } = themeState();

    const LABELS = { light: 'فاتح', dark: 'داكن', system: 'تلقائي' } as const;
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
    aria-label="المظهر: {LABELS[appearance.value]} — اضغط للتبديل"
    title="المظهر: {LABELS[appearance.value]}"
    class="relative grid size-9 shrink-0 place-items-center rounded-xl border border-border bg-card text-muted-foreground transition-transform after:absolute after:-inset-1 active:scale-95 hover:text-foreground"
>
    <Icon class="size-[17px]" stroke-width="1.9" />
</button>
