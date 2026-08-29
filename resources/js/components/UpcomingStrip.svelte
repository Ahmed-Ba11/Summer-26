<script lang="ts">
    /**
     * «التقويم المالي» — شريط أيام فترة الراتب في اللوحة.
     *
     * ══════════════════════════════════════════════════════════════════
     *  اسم الالتزام تحت رقم اليوم — لا نقطة ملوّنة تُفسَّر بمفتاح.
     * ══════════════════════════════════════════════════════════════════
     *
     * النقطة تقول «هنا شيء» ثم تُحيل المستخدم إلى مفتاح أسفل الشريط
     * ليعرف أيّ شيء. خطوتان لمعلومة واحدة. الاسم يقولها في خطوة، ويبقى
     * المفتاح ثانوياً لمن يقرأ الألوان.
     *
     * والضغط على أي يوم يفتح لوحاً سفلياً فيه تفاصيله وإجراؤه المباشر
     * (تعليم كمسدَّد · فتح الالتزام) — السداد لا يستحقّ انتقالاً إلى صفحة.
     *
     * الشريط يمسح الفترة كاملةً ويتمرّر تلقائياً إلى اليوم، فالمنقضي
     * حاضرٌ للمراجعة (أبهت بوضوح) والقادم أمامه مباشرة.
     */
    import { Link, router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import House from 'lucide-svelte/icons/house';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Repeat from 'lucide-svelte/icons/repeat';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Vault from 'lucide-svelte/icons/vault';
    import Zap from 'lucide-svelte/icons/zap';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import {
        formatAmount,
        formatCurrency,
        formatFullDate,
        formatRelativeDays,
    } from '@/lib/format';

    type EventKind =
        'salary' | 'bill' | 'rent' | 'installment' | 'subscription' | 'savings';

    interface DueEvent {
        id: number | null;
        date: string;
        kind: EventKind;
        label: string;
        amount: number;
        status?: 'paid' | 'overdue' | 'upcoming';
        isPaid?: boolean;
        canPay?: boolean;
        periodLabel?: string;
        editUrl?: string | null;
    }

    interface PeriodCalendar {
        start: string;
        end: string;
        label: string;
        range: string;
        events: DueEvent[];
    }

    let { data = null }: { data?: PeriodCalendar | null } = $props();

    const KIND = {
        salary: { color: 'var(--success)', icon: Banknote, label: 'راتب' },
        bill: { color: 'var(--chart-7)', icon: Zap, label: 'فاتورة' },
        rent: { color: 'var(--chart-5)', icon: House, label: 'إيجار' },
        installment: {
            color: 'var(--chart-2)',
            icon: CreditCard,
            label: 'قسط',
        },
        subscription: {
            color: 'var(--chart-3)',
            icon: Repeat,
            label: 'اشتراك',
        },
        savings: { color: 'var(--chart-3)', icon: Vault, label: 'ادخار' },
    } as const;

    const WEEKDAYS = [
        'أحد',
        'اثنين',
        'ثلاثاء',
        'أربعاء',
        'خميس',
        'جمعة',
        'سبت',
    ];

    const TODAY = new Date().toISOString().slice(0, 10);

    /** تاريخ ISO → Date بتوقيت UTC، فلا تُزيح المناطق الزمنية يوماً. */
    function parse(iso: string): Date {
        const [y, m, d] = iso.split('-').map(Number);

        return new Date(Date.UTC(y, m - 1, d));
    }

    /**
     * الاسم المختصر — كلمة صادقة لا شظيّة مقصوصة.
     *
     * الخانة صارت 64px كخانة التقويم، ومحتواها 52px بعد الحشوة، فتسع نحو
     * تسعة أحرف عند 11px. ما جاوزها يُختصر إلى كلمته الأولى («فاتورة
     * الكهرباء» → «فاتورة»)، وما بقيت كلمته الأولى أطول يسقط إلى اسم
     * نوعه — وكل أسماء الأنواع ستة أحرف فأقل. «الـ…» ليست اسماً.
     */
    const MAX_CHARS = 9;

    function shortLabel(event: DueEvent): string {
        const name = event.label.trim();

        if (name.length <= MAX_CHARS) {
            return name;
        }

        const first = name.split(/\s+/)[0];

        return first.length <= MAX_CHARS ? first : KIND[event.kind].label;
    }

    interface Day {
        date: string;
        day: number;
        weekday: string;
        isToday: boolean;
        isPast: boolean;
        events: DueEvent[];
    }

    const days = $derived.by<Day[]>(() => {
        if (!data?.start || !data?.end) {
            return [];
        }

        const byDate: Record<string, DueEvent[]> = {};

        for (const event of data.events) {
            (byDate[event.date] ??= []).push(event);
        }

        const startMs = parse(data.start).getTime();
        const endMs = parse(data.end).getTime();
        const total = Math.round((endMs - startMs) / 86_400_000) + 1;

        return Array.from({ length: Math.max(0, total) }, (_, index) => {
            const at = new Date(startMs + index * 86_400_000);
            const date = at.toISOString().slice(0, 10);

            return {
                date,
                day: at.getUTCDate(),
                weekday: WEEKDAYS[at.getUTCDay()],
                isToday: date === TODAY,
                isPast: date < TODAY,
                events: byDate[date] ?? [],
            };
        });
    });

    /** أنواع الأحداث الحاضرة فعلاً — المفتاح لا يشرح ما ليس معروضاً. */
    const legend = $derived(
        [...new Set((data?.events ?? []).map((event) => event.kind))].map(
            (kind) => ({ kind, ...KIND[kind] }),
        ),
    );

    /** أقرب استحقاق لم يُسدَّد ولم يمضِ موعده. */
    const nextDue = $derived(
        (data?.events ?? []).find(
            (event) => !event.isPaid && event.date >= TODAY,
        ) ?? null,
    );

    const hasAnything = $derived(
        (data?.events ?? []).some((event) => event.kind !== 'salary'),
    );

    // ── لوح اليوم ───────────────────────────────────────────────────────

    let openDate = $state<string | null>(null);
    let sheetOpen = $state(false);

    const openDay = $derived(
        openDate ? (days.find((day) => day.date === openDate) ?? null) : null,
    );

    function selectDay(date: string): void {
        openDate = date;
        sheetOpen = true;
    }

    function closeSheet(): void {
        openDate = null;
        sheetOpen = false;
    }

    /** السداد من اللوحة مباشرة — نفس مسار صفحة الالتزامات. */
    function markPaid(event: DueEvent): void {
        if (event.id === null || !event.canPay) {
            return;
        }

        router.post(
            `/commitments/${event.id}/pay`,
            { amount: event.amount },
            { preserveScroll: true, onSuccess: closeSheet },
        );
    }

    // ── التمرير إلى اليوم ───────────────────────────────────────────────

    let strip = $state<HTMLDivElement | null>(null);
    let scrolled = false;

    /**
     * فرق المستطيلين لا `scrollLeft` المطلق: في RTL يبدأ `scrollLeft` من
     * الصفر عند اليمين ويسير سالباً، فالحساب المطلق يخطئ الاتجاه. الفرق
     * النسبي يصحّ في الاتجاهين معاً.
     */
    $effect(() => {
        if (!strip || scrolled || !days.length) {
            return;
        }

        const target = strip.querySelector<HTMLElement>('[data-today]');

        if (!target) {
            return;
        }

        scrolled = true;
        strip.scrollLeft +=
            target.getBoundingClientRect().left -
            strip.getBoundingClientRect().left -
            8;
    });
</script>

{#if !data}
    <!-- لا شيء يُرسم قبل وصول البيانات -->
{:else if !hasAnything}
    <!--
        حالة فارغة: سطر واحد بلا هيكل بطاقة. شريطٌ بلا استحقاق واحد
        مساحةٌ مهدورة، فالفارغ يستحق سطراً لا بطاقة.
    -->
    <p
        class="flex items-center gap-2 rounded-xl border border-border bg-card px-3 text-[12.5px] text-muted-foreground"
    >
        <CalendarDays class="size-4 shrink-0" />
        <span class="flex-1">ما فيه استحقاقات قريبة</span>
        <Link
            href="/commitments"
            class="inline-flex min-h-11 shrink-0 items-center text-[11.5px] text-primary no-underline"
        >
            أضف التزاماً
        </Link>
    </p>
{:else}
    <section
        class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
    >
        <header
            class="flex items-center gap-2 border-b border-border px-4 py-2 md:px-5"
        >
            <h2 class="shrink-0 text-[13px] font-semibold">التقويم المالي</h2>
            <span
                class="min-w-0 flex-1 truncate text-[11px] text-muted-foreground"
            >
                {data.label} · {data.range}
            </span>
            <Link
                href="/calendar"
                class="inline-flex min-h-11 shrink-0 items-center gap-1 text-[11.5px] text-primary no-underline"
            >
                التقويم الكامل
                <ArrowLeft class="size-3.5" />
            </Link>
        </header>

        <div class="px-4 pt-2.5 pb-3 md:px-5">
            <!--
                المقاسات منسوخة حرفياً من خانة اليوم في /calendar — نفس
                الارتفاع ونصف القطر والحشوة وأحجام الخط والفجوات، والعرض
                يساوي الارتفاع فتصير الخانة مربّعة كخانة الشبكة. خانتان
                لمعنى واحد في شاشتين يجب أن تتطابقا، وإلا قرأهما المستخدم
                عنصرين مختلفين.
            -->
            <div
                bind:this={strip}
                class="flex gap-1 overflow-x-auto pb-1.5 md:gap-2"
                role="group"
                aria-label="أيام {data.label}"
            >
                {#each days as day (day.date)}
                    {@const first = day.events[0]}
                    {@const overdue = day.events.some(
                        (event) => event.status === 'overdue',
                    )}
                    <button
                        type="button"
                        data-today={day.isToday ? '' : undefined}
                        onclick={() => selectDay(day.date)}
                        aria-label="{day.weekday} {day.day} — {day.events
                            .length} استحقاق"
                        class="relative flex min-h-16 w-16 shrink-0 flex-col items-start gap-1 overflow-hidden rounded-xl border p-1.5 text-start transition-colors md:min-h-24 md:w-24 md:p-2 {day.isToday
                            ? 'border-primary bg-accent ring-2 ring-primary/20'
                            : day.isPast
                              ? 'border-border bg-secondary/50'
                              : 'border-border bg-card hover:border-input'}"
                    >
                        <span
                            class="text-[11px] leading-none {day.isPast &&
                            !day.isToday
                                ? 'text-foreground/55'
                                : 'text-muted-foreground'}"
                        >
                            {day.weekday}
                        </span>
                        <span
                            class="text-[11.5px] font-semibold tabular-nums md:text-[13px] {day.isPast &&
                            !day.isToday
                                ? 'text-foreground/65'
                                : ''}"
                        >
                            {day.day}
                        </span>

                        <!--
                            الاسم لا النقطة — المستخدم يعرف ما الالتزام بلا
                            نزول إلى المفتاح.
                        -->
                        {#if first}
                            <span
                                class="text-[11px] leading-tight font-medium whitespace-nowrap"
                                style="color: {KIND[first.kind].color}"
                            >
                                {shortLabel(first)}
                            </span>
                            {#if day.events.length > 1}
                                <span
                                    class="text-[11px] text-muted-foreground tabular-nums"
                                >
                                    +{day.events.length - 1}
                                </span>
                            {/if}
                        {/if}

                        <!--
                            المثلّث في ركن الخانة كما في /calendar، لا في سطر
                            الاسم: السطر عرضه 52px، والمثلّث فيه يقتطع ثلث
                            الاسم فيعود مقصوصاً.
                        -->
                        {#if overdue}
                            <TriangleAlert
                                class="pointer-events-none absolute top-1 size-3 text-destructive md:size-3.5"
                                style="inset-inline-end: 4px"
                            />
                        {/if}
                    </button>
                {/each}
            </div>

            {#if nextDue}
                <p class="mt-1 truncate text-[11.5px] text-foreground/80">
                    أقرب استحقاق: <b class="font-semibold">{nextDue.label}</b>
                    — {formatRelativeDays(nextDue.date)} ·
                    <span class="font-semibold tabular-nums">
                        {formatAmount(nextDue.amount)} ر.س
                    </span>
                </p>
            {/if}

            <!-- المفتاح ثانوي بعد الأسماء — لمن يقرأ الألوان لا لمن يقرأ النص -->
            <div
                class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[10.5px] text-muted-foreground"
            >
                {#each legend as item (item.kind)}
                    <span class="inline-flex items-center gap-1">
                        <i
                            class="inline-block size-[5px] rounded-full"
                            style="background-color: {item.color}"
                        ></i>
                        {item.label}
                    </span>
                {/each}
            </div>
        </div>
    </section>
{/if}

<!-- لوح اليوم — التفاصيل والإجراء في مكانهما بلا انتقال -->
<SheetShell
    bind:open={sheetOpen}
    title={openDate ? formatFullDate(openDate) : ''}
    subtitle={openDay?.events.length
        ? 'استحقاقات هذا اليوم'
        : 'ما فيه استحقاق في هذا اليوم'}
    onClose={closeSheet}
>
    {#if openDay?.events.length}
        <ul class="flex flex-col gap-2.5">
            {#each openDay.events as event (event.id ?? event.kind + event.date)}
                {@const kind = KIND[event.kind]}
                <li class="rounded-2xl border border-border bg-card p-3">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl"
                            style="background-color: color-mix(in srgb, {kind.color} 12%, transparent); color: {kind.color}"
                        >
                            <kind.icon class="size-[19px]" stroke-width="1.9" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14px] font-semibold">
                                {event.label}
                            </p>
                            <p
                                class="truncate text-[11.5px] text-muted-foreground"
                            >
                                {kind.label}
                                {#if event.status === 'overdue'}
                                    <span class="font-medium text-destructive">
                                        · فات موعده
                                    </span>
                                {:else if event.isPaid}
                                    <span class="font-medium text-success-text">
                                        · تم السداد
                                    </span>
                                {:else}
                                    <span
                                        >· {formatRelativeDays(
                                            event.date,
                                        )}</span
                                    >
                                {/if}
                            </p>
                            {#if event.periodLabel}
                                <p
                                    class="mt-0.5 truncate text-[11px] text-foreground/70"
                                >
                                    يخصّ {event.periodLabel}
                                </p>
                            {/if}
                        </div>
                        <span
                            class="shrink-0 text-[14px] font-semibold tabular-nums"
                        >
                            {formatCurrency(event.amount)}
                        </span>
                    </div>

                    {#if event.canPay || event.editUrl}
                        <div class="mt-2.5 flex gap-2">
                            {#if event.canPay}
                                <button
                                    type="button"
                                    onclick={() => markPaid(event)}
                                    class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary px-3 text-[12.5px] font-semibold text-primary-foreground transition-transform active:scale-[.98]"
                                >
                                    <CheckCircle2 class="size-4" /> تم الدفع
                                </button>
                            {/if}
                            {#if event.editUrl}
                                <Link
                                    href={event.editUrl}
                                    onclick={closeSheet}
                                    class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border border-input px-3 text-[12.5px] font-medium no-underline"
                                >
                                    <Pencil class="size-4" /> فتح الالتزام
                                </Link>
                            {/if}
                        </div>
                    {/if}
                </li>
            {/each}
        </ul>
    {:else}
        <div class="rounded-2xl border border-border bg-card p-6 text-center">
            <CalendarDays class="mx-auto size-6 text-muted-foreground" />
            <p class="mt-2 text-[13px] font-medium">يوم خالٍ من الاستحقاقات</p>
        </div>
    {/if}

    {#snippet footer()}
        <button
            type="button"
            onclick={closeSheet}
            class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85"
        >
            إغلاق
        </button>
    {/snippet}
</SheetShell>
