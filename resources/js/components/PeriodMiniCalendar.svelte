<script lang="ts">
    /**
     * التقويم المصغّر — شبكة أيام فترة الراتب الحالية في اللوحة.
     *
     * حلّ محلّ قائمة «أقرب ثلاثة أحداث»: القائمة تقول ما القادم، والشبكة
     * تقول **متى** — والسؤال الذي يقلق المستخدم زمنيّ لا اسميّ: «متى تنسحب
     * الأقساط من هذا الراتب؟». ثلاثة أسطر لا تجيبه، شبكةُ الفترة تجيبه بنظرة.
     *
     * الشبكة مصفوفة على أيام الأسبوع (لا سبعة متتالية) — تقويم حقيقي يقرأه
     * المستخدم كما يقرأ أي تقويم، والأيام السابقة ليوم الراتب فراغات.
     *
     * البطاقة كلّها رابط واحد إلى /calendar — لا ضغط على يوم مفرد، فلا
     * يتنازع هدفان للمس في خانة عرضها 45px.
     *
     * ⚠️ اللون وحده لا ينقل معنى — لكل رمز مفتاحٌ نصّي أسفل الشبكة.
     */
    import { Link } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import { formatAmount, formatRelativeDays } from '@/lib/format';

    type EventKind =
        'salary' | 'bill' | 'rent' | 'installment' | 'subscription' | 'savings';

    interface PeriodEvent {
        date: string;
        kind: EventKind;
        label: string;
        amount: number;
        status?: 'paid' | 'overdue' | 'upcoming';
    }

    interface PeriodCalendar {
        start: string;
        end: string;
        salaryDate: string | null;
        salaryAmount: number;
        label: string;
        range: string;
        events: PeriodEvent[];
    }

    let { data = null }: { data?: PeriodCalendar | null } = $props();

    const KIND_COLOR: Record<EventKind, string> = {
        salary: 'var(--success)',
        bill: 'var(--chart-7)',
        rent: 'var(--chart-5)',
        installment: 'var(--chart-2)',
        subscription: 'var(--chart-3)',
        savings: 'var(--chart-3)',
    };

    /** أحرف أيام الأسبوع — حرف واحد يتّسع في خانة 45px، والاسم لا يتّسع. */
    const WEEKDAY_LETTERS = ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'];
    const WEEKDAY_NAMES = [
        'الأحد',
        'الاثنين',
        'الثلاثاء',
        'الأربعاء',
        'الخميس',
        'الجمعة',
        'السبت',
    ];

    const TODAY = new Date().toISOString().slice(0, 10);

    /** تاريخ ISO → Date بتوقيت UTC، فلا تُزيح المناطق الزمنية يوماً. */
    function parse(iso: string): Date {
        const [y, m, d] = iso.split('-').map(Number);

        return new Date(Date.UTC(y, m - 1, d));
    }

    function iso(date: Date): string {
        return date.toISOString().slice(0, 10);
    }

    interface Mark {
        color: string;
        overdue: boolean;
    }

    interface Cell {
        date: string;
        day: number;
        weekday: string;
        isToday: boolean;
        isPast: boolean;
        isSalary: boolean;
        marks: Mark[];
        /** عدد الأحداث الفعلي — قد يفوق النقاط المرسومة */
        count: number;
    }

    const cells = $derived.by<Cell[]>(() => {
        if (!data?.start || !data?.end) {
            return [];
        }

        const start = parse(data.start);
        const end = parse(data.end);
        const byDate = new Map<string, PeriodEvent[]>();

        for (const event of data.events) {
            const list = byDate.get(event.date) ?? [];
            list.push(event);
            byDate.set(event.date, list);
        }

        const out: Cell[] = [];

        for (
            let d = new Date(start);
            d.getTime() <= end.getTime();
            d.setUTCDate(d.getUTCDate() + 1)
        ) {
            const key = iso(d);
            const events = byDate.get(key) ?? [];

            out.push({
                date: key,
                day: d.getUTCDate(),
                weekday: WEEKDAY_NAMES[d.getUTCDay()],
                isToday: key === TODAY,
                isPast: key < TODAY,
                isSalary: key === data.salaryDate,
                // ثلاث نقاط كحدّ أقصى — ما زاد لا يُرسم، والرقم يبقى في العدّ
                marks: events.slice(0, 3).map((event) => ({
                    color: KIND_COLOR[event.kind] ?? 'var(--chart-7)',
                    overdue: event.status === 'overdue',
                })),
                count: events.length,
            });
        }

        return out;
    });

    /** الفراغات قبل أول يوم في الفترة — لتصطفّ الأعمدة على أيام الأسبوع. */
    const leading = $derived(data?.start ? parse(data.start).getUTCDay() : 0);

    /** أقرب استحقاق لم يمضِ موعده — السطر الوحيد الباقي من القائمة القديمة. */
    const nextDue = $derived(
        data?.events.find(
            (event) => event.status !== 'paid' && event.date >= TODAY,
        ) ?? null,
    );

    /**
     * علامة يوم الراتب وحدها لا تكفي لرسم شبكة — الشبكة تُرسم للاستحقاقات،
     * وبلا استحقاق واحد تبقى الحالةُ فارغةً وتنكمش إلى سطر.
     */
    const hasAnything = $derived((data?.events.length ?? 0) > 0);
</script>

{#if !data}
    <!-- لا شيء يُرسم قبل وصول البيانات -->
{:else if !hasAnything}
    <!--
        حالة فارغة: سطر واحد بلا هيكل بطاقة. شبكةٌ بلا علامة واحدة مساحةٌ
        مهدورة، فالفارغ يستحق سطراً لا بطاقة.
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
    <Link
        href="/calendar"
        aria-label="التقويم المالي — {data.label}، افتح الشهر الكامل"
        class="block overflow-hidden rounded-2xl border border-border bg-card text-foreground no-underline shadow-xs transition-colors hover:border-input"
    >
        <header
            class="flex items-center gap-2 border-b border-border px-3 py-1.5 md:px-5"
        >
            <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
            <h2 class="shrink-0 text-[13px] font-semibold">التقويم المالي</h2>
            <span
                class="min-w-0 flex-1 truncate text-[11px] text-muted-foreground"
            >
                {data.label} · {data.range}
            </span>
            <ArrowLeft class="size-3.5 shrink-0 text-primary" />
        </header>

        <div class="px-3 pt-2 pb-2.5 md:px-5">
            <!-- أحرف الأيام -->
            <div
                class="grid grid-cols-7 gap-0.5 text-center text-[10px] text-muted-foreground"
                aria-hidden="true"
            >
                {#each WEEKDAY_LETTERS as letter (letter)}
                    <span>{letter}</span>
                {/each}
            </div>

            <div class="mt-1 grid grid-cols-7 gap-0.5">
                {#each Array(leading) as _, index (index)}
                    <span class="h-[26px]"></span>
                {/each}

                {#each cells as cell (cell.date)}
                    {@const marked = cell.marks.length > 0 || cell.isSalary}
                    <span
                        class="relative grid h-[26px] place-items-center rounded-md text-[11px] leading-none tabular-nums {marked
                            ? 'pb-[5px]'
                            : ''} {cell.isToday
                            ? 'bg-accent font-bold text-accent-foreground ring-1 ring-primary'
                            : cell.isPast
                              ? 'text-foreground/55'
                              : 'text-foreground/85'}"
                        title="{cell.weekday} {cell.day}{cell.count
                            ? ` — ${cell.count} استحقاق`
                            : ''}"
                    >
                        {cell.day}

                        {#if marked}
                            <span
                                class="pointer-events-none absolute bottom-[1px] flex gap-[2px]"
                            >
                                {#if cell.isSalary}
                                    <i
                                        class="block size-[4px] rounded-full"
                                        style="background-color: var(--success)"
                                    ></i>
                                {/if}
                                {#each cell.marks as mark, index (index)}
                                    {#if mark.overdue}
                                        <i
                                            class="block size-0 border-x-[3px] border-b-[5px] border-x-transparent"
                                            style="border-bottom-color: var(--destructive)"
                                        ></i>
                                    {:else}
                                        <i
                                            class="block size-[4px] rounded-full"
                                            style="background-color: {mark.color}"
                                        ></i>
                                    {/if}
                                {/each}
                            </span>
                        {/if}
                    </span>
                {/each}
            </div>

            <!-- المفتاح — اللون وحده لا ينقل معنى -->
            <div
                class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10.5px] text-muted-foreground"
            >
                <span class="inline-flex items-center gap-1">
                    <i
                        class="block size-[4px] rounded-full"
                        style="background-color: var(--chart-7)"
                    ></i>
                    مستحق
                </span>
                <span class="inline-flex items-center gap-1">
                    <i
                        class="block size-0 border-x-[3px] border-b-[5px] border-x-transparent"
                        style="border-bottom-color: var(--destructive)"
                    ></i>
                    متأخّر
                </span>
                <span class="inline-flex items-center gap-1">
                    <i
                        class="block size-[4px] rounded-full"
                        style="background-color: var(--success)"
                    ></i>
                    أول الراتب
                </span>
            </div>

            {#if nextDue}
                <p
                    class="mt-1.5 truncate border-t border-border pt-1.5 text-[11.5px] text-foreground/80"
                >
                    أقرب استحقاق: <b class="font-semibold">{nextDue.label}</b>
                    — {formatRelativeDays(nextDue.date)} ·
                    <span class="font-semibold tabular-nums"
                        >{formatAmount(nextDue.amount)} ر.س</span
                    >
                </p>
            {/if}
        </div>
    </Link>
{/if}
