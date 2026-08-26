<script lang="ts">
    /**
     * لوح التاريخ الموحّد — **كل** تاريخ في التطبيق يمرّ من هنا.
     *
     * لماذا تقويم مبني بأيدينا بدل `<input type="date">`:
     *   • حقل التاريخ الأصلي يعرض التاريخ بأرقام عربية-هندية على أجهزة كثيرة
     *     (٢٠/٠٨/٢٠٢٦) — وهذا يخالف قاعدة الأرقام اللاتينية في DESIGN.md.
     *   • شكله يختلف بين iOS وأندرويد وويندوز، فتنكسر وحدة الواجهة.
     *   • لا يمكن تمييز يوم الراتب ولا الاستحقاقات داخله.
     */
    import Check from 'lucide-svelte/icons/check';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import SheetShell from '@/components/ui/SheetShell.svelte';

    let {
        open = $bindable(false),
        /** ISO: 2026-08-20 */
        value = $bindable(''),
        title,
        subtitle = '',
        /** يوم الراتب — يُعلَّم بنقطة في التقويم */
        salaryDay = 0,
        saveLabel = 'حفظ',
        onSave,
    }: {
        open?: boolean;
        value?: string;
        title: string;
        subtitle?: string;
        salaryDay?: number;
        saveLabel?: string;
        onSave?: (iso: string) => void;
    } = $props();

    const MONTHS = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];
    const WEEK = ['أحد', 'اثن', 'ثلا', 'أرب', 'خمي', 'جمع', 'سبت'];

    const today = new Date();
    const initial = $derived(value ? new Date(value) : today);

    let cursor = $state(new Date(today.getFullYear(), today.getMonth(), 1));
    let picked = $state<string>('');

    $effect(() => {
        if (!open) return;
        picked = value || iso(today);
        const d = value ? new Date(value) : today;
        cursor = new Date(d.getFullYear(), d.getMonth(), 1);
    });

    function iso(d: Date): string {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    /** خلايا الشبكة: أيام الشهر السابق الباهتة ثم أيام الشهر. */
    const cells = $derived.by(() => {
        const y = cursor.getFullYear();
        const m = cursor.getMonth();
        const firstWeekday = new Date(y, m, 1).getDay();
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        const prevDays = new Date(y, m, 0).getDate();

        const out: { day: number; date: Date; dim: boolean }[] = [];
        for (let i = firstWeekday - 1; i >= 0; i--) {
            out.push({ day: prevDays - i, date: new Date(y, m - 1, prevDays - i), dim: true });
        }
        for (let d = 1; d <= daysInMonth; d++) {
            out.push({ day: d, date: new Date(y, m, d), dim: false });
        }
        while (out.length % 7 !== 0) {
            const d = out.length - firstWeekday - daysInMonth + 1;
            out.push({ day: d, date: new Date(y, m + 1, d), dim: true });
        }
        return out;
    });

    const label = $derived(`${MONTHS[cursor.getMonth()]} ${cursor.getFullYear()}`);
    const pickedLabel = $derived.by(() => {
        if (!picked) return '';
        const d = new Date(picked);
        return `${d.getDate()} ${MONTHS[d.getMonth()]}`;
    });

    function shift(months: number) {
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() + months, 1);
    }

    function save() {
        value = picked;
        onSave?.(picked);
        open = false;
    }

    const todayIso = iso(today);
    const tomorrowIso = iso(new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1));
    const salaryIso = $derived.by(() => {
        if (!salaryDay) return '';
        const last = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
        return iso(new Date(today.getFullYear(), today.getMonth(), Math.min(salaryDay, last)));
    });
</script>

<SheetShell bind:open {title} {subtitle}>
    <!-- التنقّل بين الأشهر -->
    <div class="mb-2.5 flex items-center justify-between gap-2">
        <button
            type="button"
            onclick={() => shift(-1)}
            aria-label="الشهر السابق"
            class="inline-flex size-9 items-center justify-center rounded-xl border border-input text-muted-foreground"
        >
            <ChevronRight class="size-4" />
        </button>
        <b class="text-[13.5px] font-semibold">{label}</b>
        <button
            type="button"
            onclick={() => shift(1)}
            aria-label="الشهر التالي"
            class="inline-flex size-9 items-center justify-center rounded-xl border border-input text-muted-foreground"
        >
            <ChevronLeft class="size-4" />
        </button>
    </div>

    <div class="mb-1.5 grid grid-cols-7 gap-1 text-center text-[10px] text-muted-foreground">
        {#each WEEK as w (w)}<span>{w}</span>{/each}
    </div>

    <div class="grid grid-cols-7 gap-1">
        {#each cells as c (c.date.toISOString())}
            {@const key = iso(c.date)}
            <button
                type="button"
                onclick={() => (picked = key)}
                class="relative grid min-h-[38px] place-items-center rounded-xl text-[13px] transition-colors
                    {picked === key
                    ? 'bg-primary font-semibold text-primary-foreground'
                    : key === todayIso
                      ? 'border border-primary/25 font-semibold text-primary'
                      : c.dim
                        ? 'text-muted-foreground/45'
                        : 'text-foreground hover:bg-secondary'}"
            >
                {c.day}
                {#if salaryDay && c.date.getDate() === salaryDay && !c.dim && picked !== key}
                    <span class="absolute bottom-1.5 size-1 rounded-full bg-chart-7"></span>
                {/if}
            </button>
        {/each}
    </div>

    <!-- اختصارات -->
    <div class="mt-3 flex flex-wrap gap-1.5">
        <button
            type="button"
            onclick={() => (picked = todayIso)}
            class="inline-flex min-h-11 items-center rounded-xl border border-input px-3 text-[12.5px] text-foreground/85"
        >
            اليوم
        </button>
        <button
            type="button"
            onclick={() => (picked = tomorrowIso)}
            class="inline-flex min-h-11 items-center rounded-xl border border-input px-3 text-[12.5px] text-foreground/85"
        >
            بكرة
        </button>
        {#if salaryIso}
            <button
                type="button"
                onclick={() => (picked = salaryIso)}
                class="inline-flex min-h-11 items-center rounded-xl border border-input px-3 text-[12.5px] text-foreground/85"
            >
                مع الراتب · {salaryDay}
            </button>
        {/if}
    </div>

    {#snippet footer()}
        <button
            type="button"
            onclick={() => (open = false)}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85"
        >
            إلغاء
        </button>
        <button
            type="button"
            disabled={!picked}
            onclick={save}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            <Check class="size-[18px]" />
            {saveLabel} {pickedLabel}
        </button>
    {/snippet}
</SheetShell>
