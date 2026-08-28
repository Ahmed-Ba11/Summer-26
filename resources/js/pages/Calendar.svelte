<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'التقويم المالي', href: '/calendar' }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Pencil from 'lucide-svelte/icons/pencil';
    import AppHead from '@/components/AppHead.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import FinanceCalendar from '@/components/FinanceCalendar.svelte';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import { calendar } from '@/routes';
    import { formatCurrency, formatDate, formatFullDate } from '@/lib/format';

    type EventKind = 'salary' | 'bill' | 'installment' | 'savings';

    /** حالة الظهور — يحسبها الخادم من `commitment_payments`. */
    type EventStatus = 'paid' | 'overdue' | 'upcoming';

    interface CalendarEvent {
        id: number | null;
        date: string;
        kind: EventKind;
        label: string;
        amount: number;
        /** فترة الراتب التي يخصّها هذا الظهور — 2026-08 */
        periodKey?: string;
        status?: EventStatus;
        isPaid: boolean;
        paidAt?: string | null;
        canPay: boolean;
        editUrl: string | null;
    }

    /**
     * حالة كل حدث — نصّاً صريحاً لا لوناً.
     *
     * الاستحقاق المنقضي لا يُترك بلا حالة أبداً: إمّا «تم السداد» وإمّا
     * «فات موعده»، والفرق بينهما من `commitment_payments` لا من التاريخ
     * وحده — فالمدفوع مبكراً ليس متأخّراً، والمنقضي غير المدفوع ليس قادماً.
     */
    type StatusTone = 'ok' | 'bad' | 'muted';

    const STATUS_TONE: Record<StatusTone, string> = {
        ok: 'text-success-text',
        bad: 'text-destructive',
        muted: 'text-muted-foreground',
    };

    const TODAY = new Date().toISOString().slice(0, 10);

    function isPast(date: string): boolean {
        return date < TODAY;
    }

    function statusOf(event: CalendarEvent): { text: string; tone: StatusTone } {
        if (event.kind === 'salary') {
            return isPast(event.date)
                ? { text: 'نزل', tone: 'ok' }
                : { text: 'قادم', tone: 'muted' };
        }

        if (event.kind === 'savings') {
            return { text: 'تم الإيداع', tone: 'ok' };
        }

        if (event.status === 'paid') {
            return { text: 'تم السداد', tone: 'ok' };
        }

        if (event.status === 'overdue') {
            return { text: 'فات موعده', tone: 'bad' };
        }

        return { text: 'قادم', tone: 'muted' };
    }

    interface CalendarDay {
        date: string;
        day: number;
        events: CalendarEvent[];
    }

    let {
        month = '',
        monthLabel = '',
        previousMonth = '',
        nextMonth = '',
        events = [],
    }: {
        month?: string;
        monthLabel?: string;
        previousMonth?: string;
        nextMonth?: string;
        events?: CalendarEvent[];
    } = $props();

    const WEEKDAYS = [
        'أحد',
        'اثنين',
        'ثلاثاء',
        'أربعاء',
        'خميس',
        'جمعة',
        'سبت',
    ];
    const EVENT_KIND = {
        salary: { label: 'راتب', color: 'var(--success)' },
        bill: { label: 'فاتورة', color: 'var(--chart-7)' },
        installment: { label: 'قسط', color: 'var(--chart-2)' },
        savings: { label: 'ادخار', color: 'var(--chart-3)' },
    } as const;

    const days = $derived.by<CalendarDay[]>(() => {
        if (!/^\d{4}-\d{2}$/.test(month)) {
            return [];
        }

        const [year, monthNumber] = month.split('-').map(Number);
        const daysInMonth = new Date(
            Date.UTC(year, monthNumber, 0),
        ).getUTCDate();
        const eventsByDate = new Map<string, CalendarEvent[]>();

        for (const event of events) {
            const current = eventsByDate.get(event.date) ?? [];
            current.push(event);
            eventsByDate.set(event.date, current);
        }

        return Array.from({ length: daysInMonth }, (_, index) => {
            const day = index + 1;
            const date = `${month}-${String(day).padStart(2, '0')}`;

            return {
                date,
                day,
                events: eventsByDate.get(date) ?? [],
            };
        });
    });

    const leadingEmptyDays = $derived.by(() => {
        if (!/^\d{4}-\d{2}$/.test(month)) {
            return 0;
        }

        const [year, monthNumber] = month.split('-').map(Number);

        return new Date(Date.UTC(year, monthNumber - 1, 1)).getUTCDay();
    });

    let selectedDate = $state<string | null>(null);
    let sheetOpen = $state(false);
    const selectedEvents = $derived(
        selectedDate
            ? (days.find((day) => day.date === selectedDate)?.events ?? [])
            : [],
    );
    const selectedDateLabel = $derived(
        selectedDate ? formatFullDate(selectedDate) : '',
    );

    function selectDay(date: string): void {
        selectedDate = date;
        sheetOpen = true;
    }

    function closeSheet(): void {
        selectedDate = null;
        sheetOpen = false;
    }

    /**
     * السداد يمرّ من مسار الالتزامات — كان ينادي `/bills/{id}/pay` و
     * `/installments/{id}/pay` القديمين بمعرّف التزام، فيصيب سجلاً آخر
     * أو لا يصيب شيئاً. المعرّف الآن معرّف التزام، والمسار مسار الالتزامات.
     */
    function markPaid(event: CalendarEvent): void {
        if (event.id === null || !event.canPay) {
            return;
        }

        router.post(
            `/commitments/${event.id}/pay`,
            { amount: event.amount },
            { preserveScroll: true, onSuccess: closeSheet },
        );
    }
</script>

<AppHead title="التقويم المالي" />
<MobileHeader title="التقويم المالي" subtitle="كل استحقاقاتك وأحداثك المالية" />

<div class="flex flex-1 flex-col gap-3 p-3 md:gap-6 md:p-6">
    <div class="flex items-center justify-between gap-3">
        <div class="hidden md:block">
            <h1 class="text-[22px] font-semibold tracking-tight">
                التقويم المالي
            </h1>
            <p class="text-[13px] text-muted-foreground">
                تابع استحقاقاتك وأحداثك المالية خلال الشهر.
            </p>
        </div>
        <div
            class="flex items-center gap-1.5 rounded-xl border border-border bg-card p-1"
        >
            <Link
                href={calendar.url({ query: { month: previousMonth } })}
                aria-label="الشهر السابق"
                class="grid min-h-11 min-w-11 place-items-center rounded-lg text-muted-foreground hover:bg-secondary hover:text-foreground"
            >
                <ChevronRight class="size-4" />
            </Link>
            <span class="min-w-24 text-center text-sm font-semibold"
                >{monthLabel}</span
            >
            <Link
                href={calendar.url({ query: { month: nextMonth } })}
                aria-label="الشهر التالي"
                class="grid min-h-11 min-w-11 place-items-center rounded-lg text-muted-foreground hover:bg-secondary hover:text-foreground"
            >
                <ChevronLeft class="size-4" />
            </Link>
        </div>
    </div>

    <section
        class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
    >
        <div
            class="grid grid-cols-7 border-b border-border bg-secondary text-center text-[11px] text-muted-foreground md:text-xs"
        >
            {#each WEEKDAYS as weekday}
                <span class="px-1 py-3">{weekday}</span>
            {/each}
        </div>

        <div class="grid grid-cols-7 gap-1 p-2 md:gap-2 md:p-4">
            {#each Array(leadingEmptyDays) as _}
                <span class="min-h-20 rounded-xl bg-background/50 md:min-h-28"
                ></span>
            {/each}

            {#each days as day (day.date)}
                {@const past = isPast(day.date)}
                {@const today = day.date === TODAY}
                {@const first = day.events[0]}
                {@const more = day.events.length - 1}
                <button
                    type="button"
                    onclick={() => selectDay(day.date)}
                    class="flex min-h-20 min-w-0 flex-col items-start rounded-xl border p-1.5 text-start transition-colors hover:border-primary md:min-h-28 md:p-2.5 {today
                        ? 'border-primary bg-accent'
                        : past
                          ? 'border-border bg-secondary/60'
                          : 'border-border bg-background'}"
                >
                    <span
                        class="text-[11.5px] font-semibold tabular-nums md:text-sm {past && !today
                            ? 'text-muted-foreground'
                            : ''}"
                    >
                        {day.day}
                    </span>

                    <!-- النقاط لا تقول شيئاً — الاسم المختصر يقوله -->
                    {#if first}
                        {@const state = statusOf(first)}
                        <span
                            class="mt-1 w-full truncate text-[11px] leading-tight font-medium {past
                                ? 'text-muted-foreground'
                                : ''}"
                            style={past ? '' : `color: ${EVENT_KIND[first.kind].color}`}
                        >
                            {first.label}
                        </span>

                        {#if past}
                            <span
                                class="w-full truncate text-[11px] leading-tight {STATUS_TONE[state.tone]}"
                            >
                                {state.text}
                            </span>
                        {/if}

                        {#if more > 0}
                            <span class="mt-auto text-[11px] text-muted-foreground tabular-nums">
                                +{more}
                            </span>
                        {/if}
                    {/if}
                </button>
            {/each}
        </div>
    </section>

    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-foreground/80">
        {#each Object.values(EVENT_KIND) as value}
            <span class="inline-flex items-center gap-1.5">
                <i
                    class="size-2 rounded-full"
                    style="background-color: {value.color}"
                ></i>
                {value.label}
            </span>
        {/each}
    </div>

    <!-- الأيام القادمة — إعادة استخدامFinanceCalendar -->
    {#if events.length}
        <section
            class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
        >
            <header
                class="flex items-center justify-between border-b border-border px-4 py-3 md:px-5 md:py-4"
            >
                <h2 class="text-[13px] font-semibold md:text-[14.5px]">
                    الأيام القادمة
                </h2>
            </header>
            <div class="px-4 py-4 md:px-5">
                <FinanceCalendar {events} />
            </div>
        </section>
    {/if}
</div>

<SheetShell
    bind:open={sheetOpen}
    title={selectedDateLabel}
    subtitle="أحداث هذا اليوم ومواعيد استحقاقها"
    onClose={closeSheet}
>
    {#if selectedEvents.length}
        <ul class="flex flex-col gap-2.5">
            {#each selectedEvents as event (event.id ?? event.kind + event.date)}
                {@const state = statusOf(event)}
                <li class="rounded-2xl border border-border bg-card p-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl"
                            style="background-color: color-mix(in srgb, {EVENT_KIND[
                                event.kind
                            ].color} 12%, transparent); color: {EVENT_KIND[
                                event.kind
                            ].color}"
                        >
                            {#if event.isPaid}
                                <CheckCircle2 class="size-[19px]" />
                            {:else}
                                <CircleAlert class="size-[19px]" />
                            {/if}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14px] font-semibold">
                                {event.label}
                            </p>
                            <!-- الحالات الثلاث كما حسبها الخادم لهذا الظهور -->
                            <p class="truncate text-[11.5px] text-muted-foreground">
                                {EVENT_KIND[event.kind].label}
                                <span class="font-medium {STATUS_TONE[state.tone]}">
                                    · {state.text}
                                </span>
                                {#if event.status === 'paid' && event.paidAt}
                                    · {formatDate(event.paidAt)}
                                {/if}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-[14px] font-semibold tabular-nums"
                            >{formatCurrency(event.amount)}</span
                        >
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
                                    <Pencil class="size-4" /> تعديل
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
            <p class="mt-2 text-[13px] font-medium">
                لا توجد أحداث في هذا اليوم
            </p>
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
