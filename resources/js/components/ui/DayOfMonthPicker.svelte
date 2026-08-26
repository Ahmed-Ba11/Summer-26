<script lang="ts">
    /**
     * منتقي «يوم من الشهر» — بديل القائمة المنسدلة بـ 31 عنصراً.
     *
     * القائمة المنسدلة على الجوال تفتح عجلة تغطّي نصف الشاشة لاختيار رقم
     * واحد، وتخفي بقيّة النموذج. البديل هنا:
     *
     *   رقائق للأيام الشائعة (1 · 5 · 10 · 15 · 20 · 25 · آخر يوم)
     *   + «يوم آخر» يفتح شبكة 7×5 مضغوطة **داخل** النموذج لا فوقه.
     *
     * الأيام الشائعة تغطّي أغلب الفواتير والإيجارات، فأكثر المستخدمين
     * ينهون الاختيار بضغطة واحدة بلا فتح الشبكة أصلاً.
     */
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import Info from 'lucide-svelte/icons/info';

    let {
        value = $bindable(1),
        /** 32 = آخر يوم في الشهر مهما كان طوله */
        showLastDay = true,
        hint = '',
    }: { value?: number; showLastDay?: boolean; hint?: string } = $props();

    const COMMON = [1, 5, 10, 15, 20, 25];
    let gridOpen = $state(false);

    const isCommon = $derived(COMMON.includes(value) || (showLastDay && value === 32));
    const days = Array.from({ length: 31 }, (_, i) => i + 1);

    function pick(d: number) {
        value = d;
        gridOpen = false;
    }
</script>

<div>
    <div class="flex flex-wrap gap-1.5">
        {#each COMMON as d (d)}
            <button
                type="button"
                aria-pressed={value === d}
                onclick={() => pick(d)}
                class="inline-flex min-h-11 min-w-12 items-center justify-center rounded-xl border px-3 text-[13px] transition-colors {value ===
                d
                    ? 'border-primary bg-primary/8 font-semibold text-primary'
                    : 'border-input text-foreground/85'}"
            >
                {d}
            </button>
        {/each}

        {#if showLastDay}
            <button
                type="button"
                aria-pressed={value === 32}
                onclick={() => pick(32)}
                class="inline-flex min-h-11 items-center justify-center rounded-xl border px-3 text-[12.5px] transition-colors {value ===
                32
                    ? 'border-primary bg-primary/8 font-semibold text-primary'
                    : 'border-input text-foreground/85'}"
            >
                آخر يوم
            </button>
        {/if}

        <button
            type="button"
            aria-expanded={gridOpen}
            onclick={() => (gridOpen = !gridOpen)}
            class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border px-3 text-[12.5px] transition-colors {gridOpen ||
            !isCommon
                ? 'border-primary bg-primary/8 font-semibold text-primary'
                : 'border-input text-foreground/85'}"
        >
            <CalendarDays class="size-3.5" />
            {!isCommon ? `يوم ${value}` : 'يوم آخر'}
        </button>
    </div>

    {#if gridOpen || !isCommon}
        <div class="mt-2 grid grid-cols-7 gap-1.5">
            {#each days as d (d)}
                <button
                    type="button"
                    aria-pressed={value === d}
                    onclick={() => pick(d)}
                    class="grid min-h-9 place-items-center rounded-[10px] border text-[12.5px] transition-colors {value === d
                        ? 'border-primary bg-primary font-semibold text-primary-foreground'
                        : 'border-border bg-secondary text-foreground/85'}"
                >
                    {d}
                </button>
            {/each}
        </div>
    {/if}

    {#if hint}
        <p class="mt-2 flex items-start gap-2 rounded-xl border border-primary/20 bg-primary/6 px-3 py-2 text-[11px] text-foreground/85">
            <Info class="mt-px size-3.5 shrink-0 text-primary" />
            {hint}
        </p>
    {/if}
</div>
