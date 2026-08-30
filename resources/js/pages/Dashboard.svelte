<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'لوحة التحكم', href: '/dashboard' }],
    };
</script>

<script lang="ts">
    /**
     * لوحة التحكم — مُعاد بناؤها.
     *
     * ما حُذف عن النسخة السابقة (مقصود، لا تُعده):
     *  1. كل البيانات الوهمية المثبّتة كقيم افتراضية في $props(). كانت تعرض
     *     452,000 ر.س لمستخدم ليس لديه أي بيانات — أخطر ما كان في الصفحة.
     *  2. فلتر الشهر الصوري (selectedMonth + showMonthDropdown) — كان يغيّر
     *     متغيّراً محلياً ولا يجلب أي بيانات. صار فلتراً حقيقياً على السيرفر.
     *  3. بطاقة الرسالة التحفيزية — مساحة كبيرة مقابل معلومة يعرفها المستخدم
     *     أصلاً من ألوان الفئات. مضمونها انتقل لسطر واحد تحت الرقم البطل.
     *  4. دالة donutPath اليدوية — استُبدلت بـ stroke-dasharray.
     *  5. البطاقة الخامسة (الفواتير المستحقة) — مكرّرة مع «المحجوز» والتقويم.
     */
    import { page, router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Lock from 'lucide-svelte/icons/lock';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingCart from 'lucide-svelte/icons/shopping-cart';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Vault from 'lucide-svelte/icons/vault';
    import Wallet from 'lucide-svelte/icons/wallet';

    import AppHead from '@/components/AppHead.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import AiAssistantIcon from '@/components/icons/AiAssistantIcon.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import MoneyStoryCard from '@/components/MoneyStoryCard.svelte';
    import SalaryCloseSheet from '@/components/SalaryCloseSheet.svelte';
    import StatTile from '@/components/StatTile.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import UpcomingStrip from '@/components/UpcomingStrip.svelte';
    import { formatAmount, formatDate, formatPercent } from '@/lib/format';

    interface Stats {
        totalIncome: number;
        totalExpenses: number;
        prevExpenses: number;
        commitmentsTotal: number;
        commitmentsReserved: number;
        commitmentsPaid: number;
        commitmentsDueSoon: number;
        savings: number;
        avgDaily: number;
        daysLeft: number;
        savingsRate: number;
        savingsTarget: number;
    }

    interface DueEvent {
        id: number | null;
        date: string;
        kind:
            | 'salary'
            | 'bill'
            | 'rent'
            | 'installment'
            | 'subscription'
            | 'savings';
        label: string;
        amount: number;
        status?: 'paid' | 'overdue' | 'upcoming';
        isPaid?: boolean;
        canPay?: boolean;
        periodLabel?: string;
        editUrl?: string | null;
    }

    /** أيام فترة الراتب الحالية واستحقاقاتها — مصدر شريط التقويم. */
    interface PeriodCalendar {
        start: string;
        end: string;
        label: string;
        range: string;
        events: DueEvent[];
    }

    /** شهر الراتب المعروض — يبدأ يوم نزول الراتب لا يوم 1. */
    interface SalaryMonth {
        key: string;
        label: string;
        range: string;
        daysLeft: number;
        dayIndex: number;
        totalDays: number;
        isCurrent: boolean;
    }

    interface Transaction {
        id: number;
        type: 'expense' | 'income';
        desc: string;
        category: string;
        icon: string;
        color: string;
        amount: number;
        date: string;
    }

    // ⚠️ كل القيم الافتراضية فارغة — لا بيانات وهمية إطلاقاً.
    let {
        stats = {
            totalIncome: 0,
            totalExpenses: 0,
            prevExpenses: 0,
            commitmentsTotal: 0,
            commitmentsReserved: 0,
            commitmentsPaid: 0,
            commitmentsDueSoon: 0,
            savings: 0,
            avgDaily: 0,
            daysLeft: 0,
            savingsRate: 0,
            savingsTarget: 10,
        } as Stats,
        periodCalendar = null,
        recentTransactions = [] as Transaction[],
        month = '',
        availableMonths = [] as { value: string; label: string }[],
        hasData = false,
        onboardingComplete = true,
        salaryMonth = null,
        salaryClose = null,
    }: {
        stats?: Stats;
        periodCalendar?: PeriodCalendar | null;
        recentTransactions?: Transaction[];
        month?: string;
        availableMonths?: { value: string; label: string }[];
        hasData?: boolean;
        onboardingComplete?: boolean;
        salaryMonth?: SalaryMonth | null;
        salaryClose?: any;
    } = $props();

    const expenseDelta = $derived(
        stats.prevExpenses > 0
            ? ((stats.totalExpenses - stats.prevExpenses) /
                  stats.prevExpenses) *
                  100
            : 0,
    );

    const currentMonthLabel = $derived(
        availableMonths.find((m) => m.value === month)?.label ??
            salaryMonth?.label ??
            month,
    );

    /**
     * سطر الفترة تحت العنوان.
     *
     * شهر المستخدم يبدأ يوم راتبه، فالمدى المعروض («27 أغسطس ← 26 سبتمبر»)
     * هو ما يجعل بقية الأرقام مفهومة — بدونه يقرأ «باقي 26 يوم» على أنه
     * باقي من الشهر التقويمي.
     */
    const pageTitle = $derived(salaryMonth?.label ?? 'لوحة التحكم');

    const periodLine = $derived(
        salaryMonth
            ? salaryMonth.isCurrent && salaryMonth.daysLeft > 0
                ? `باقي ${salaryMonth.daysLeft} يوم للراتب الجاي · ${salaryMonth.range}`
                : salaryMonth.range
            : 'صورة ميزانيتك الكاملة.',
    );

    /**
     * التحية — جملة واحدة لا عنصران.
     *
     * كانت «أهلًا» رمادية صغيرة والاسم أسود عريضاً كبيراً، فقرآ كقطعتين
     * التقتا مصادفةً لا كجملة. التحية جملة، وكلماتها تتساوى في الخط
     * والحجم واللون والوزن — فلا تمييز للاسم بأيٍّ منها.
     *
     * والأول وحده لا الاسم الرباعي: «أهلًا محمد عبدالله سعد القحطاني»
     * يلتفّ سطرين ويقرأ كترويسة مستند رسمي. والاسم يُعرض كما سجّله
     * صاحبه — الإنجليزي إنجليزياً بلا ترجمة، والنصّ الواحد يجعل محرّك
     * الاتجاه ثنائيَّ الاتجاه يرتّبه داخل السطر العربي من تلقائه.
     */
    const firstName = $derived(
        (page.props.auth?.user?.name ?? '').trim().split(/\s+/)[0] ?? '',
    );

    const greeting = $derived(firstName ? `أهلًا ${firstName}` : 'أهلًا');

    let monthOpen = $state(false);

    // فلتر شهر حقيقي — يعيد الطلب للسيرفر، لا يغيّر متغيّراً محلياً فقط
    function selectMonth(value: string) {
        monthOpen = false;
        router.get(
            '/dashboard',
            { month: value },
            { preserveScroll: true, preserveState: true },
        );
    }
</script>

<AppHead title="لوحة التحكم" />
<MobileHeader title={pageTitle} subtitle={periodLine} />

<!-- إقفال الراتب السابق — يُعرض قبل أي رقم، لأنه يغيّر الأرقام كلها -->
<SalaryCloseSheet data={salaryClose} />

<div class="flex flex-1 flex-col gap-3 p-3 md:gap-5 md:p-6">
    <!-- رأس الصفحة — التحية ثم الاسم -->
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1
                class="truncate text-[26px] leading-tight font-bold tracking-tight md:text-[31px]"
                style="font-family: var(--font-display)"
            >
                {greeting}
            </h1>
            <p class="mt-1.5 hidden text-[13px] text-muted-foreground md:block">
                {pageTitle} · {periodLine}
            </p>
        </div>

        <div class="hidden gap-2 md:flex">
            <div class="relative">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    onclick={() => (monthOpen = !monthOpen)}
                >
                    {currentMonthLabel}
                    <ChevronDown class="size-3.5 text-muted-foreground" />
                </Button>
                {#if monthOpen}
                    <div
                        class="absolute z-50 mt-1 max-h-64 w-40 overflow-y-auto rounded-lg border border-border bg-popover shadow-lg"
                        style="inset-inline-start: 0"
                    >
                        {#each availableMonths as m (m.value)}
                            <button
                                type="button"
                                class="w-full px-3 py-1.5 text-start text-xs hover:bg-secondary {m.value ===
                                month
                                    ? 'bg-secondary font-medium'
                                    : ''}"
                                onclick={() => selectMonth(m.value)}
                            >
                                {m.label}
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>
            <Button variant="outline" size="sm" href="/reports">التقارير</Button
            >
        </div>
    </div>

    {#if !hasData}
        <!-- لا بيانات إطلاقاً: لا تعرض بطاقات بأصفار -->
        <div class="rounded-2xl border border-border bg-card">
            <EmptyState
                icon={Wallet}
                title="ابدأ بإعداد ميزانيتك"
                description="سجّل دخلك والتزاماتك الثابتة، ووزّع الباقي على فئاتك — بأقل من دقيقتين، وبتشوف كل أرقامك هنا."
                actionLabel="ابدأ الإعداد"
                href="/onboarding"
            />
        </div>
    {:else}
        {#if !onboardingComplete}
            <a
                href="/onboarding"
                class="flex items-center gap-3 rounded-xl border border-primary/25 bg-accent px-4 py-3 text-[13px] no-underline"
            >
                <Sparkles class="size-4 shrink-0 text-primary" />
                <span class="flex-1">
                    <b class="font-semibold">أكمل إعدادك</b> — باقي خطوات بسيطة عشان
                    تصير أرقامك دقيقة تماماً.
                </span>
                <ArrowLeft class="size-4 text-primary" />
            </a>
        {/if}

        <!-- ١ · بطاقة القصّة المالية -->
        <MoneyStoryCard
            income={stats.totalIncome}
            commitments={stats.commitmentsTotal}
            savings={stats.savings}
            expenses={stats.totalExpenses}
            daysLeft={stats.daysLeft}
            avgDaily={stats.avgDaily}
        />

        <!-- ٢ · شريط الأيام — الاسم تحت الرقم، والضغط يفتح لوح اليوم -->
        <UpcomingStrip data={periodCalendar} />

        <!-- ٣ · بطاقات مختصرة -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatTile
                label="الدخل"
                amount={stats.totalIncome}
                icon={TrendingUp}
                color="var(--success)"
                note="راتب + دخل إضافي"
            />
            <StatTile
                label="المصاريف"
                amount={stats.totalExpenses}
                icon={ShoppingCart}
                color="var(--chart-1)"
                tone={expenseDelta <= 0 ? 'good' : 'bad'}
                note={stats.prevExpenses > 0
                    ? `${expenseDelta <= 0 ? 'أقل' : 'أعلى'} ${formatPercent(Math.abs(expenseDelta))} عن الشهر الماضي`
                    : ''}
            />
            <StatTile
                label="المحجوز"
                amount={stats.commitmentsTotal}
                icon={Lock}
                color="var(--chart-7)"
                note={`${formatAmount(stats.commitmentsPaid)} مدفوع · ${stats.commitmentsDueSoon} قريب`}
            />
            <StatTile
                label="الادخار"
                amount={stats.savings}
                icon={Vault}
                color="var(--chart-3)"
                note="{formatPercent(
                    stats.savingsRate,
                )} من دخلك · الهدف {formatPercent(stats.savingsTarget)}"
            />
        </div>

        <!-- ٣.٥ · مدخل المساعد — بعد الأرقام مباشرةً: القارئ للتوّ رأى
             مجاميعه، وأقرب سؤال يخطر له سؤال عنها. -->
        <a
            href="/assistant"
            class="flex min-h-[60px] items-center gap-3 rounded-2xl border border-border bg-card px-4 no-underline shadow-xs transition-colors hover:bg-secondary"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-xl text-white"
                style="background:linear-gradient(145deg,#2c4a6e,#1baf7a)"
            >
                <AiAssistantIcon class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <b class="block text-[13.5px] font-semibold"
                    >اسأل المساعد الذكي</b
                >
                <span
                    class="block truncate text-[11.5px] text-muted-foreground"
                >
                    «كم صرفت على المطاعم؟» · «أضف مصروف 50 ريال قهوة أمس»
                </span>
            </span>
            <ArrowLeft class="size-4 shrink-0 text-primary" />
        </a>

        <!-- ٤ · آخر المعاملات -->
        <section class="rounded-2xl border border-border bg-card shadow-xs">
            <header
                class="flex items-center justify-between border-b border-border px-5 py-4"
            >
                <h2 class="text-[14.5px] font-semibold">آخر المعاملات</h2>
                <a
                    href="/expenses"
                    class="text-[12.5px] text-primary no-underline">عرض الكل</a
                >
            </header>

            {#if recentTransactions.length}
                <!-- جدول على الشاشات المتوسطة فأكبر -->
                <table class="hidden w-full text-[13px] md:table">
                    <thead>
                        <tr
                            class="border-b border-border text-muted-foreground"
                        >
                            <th
                                class="px-5 py-2.5 text-start text-xs font-medium"
                                >الوصف</th
                            >
                            <th
                                class="px-5 py-2.5 text-start text-xs font-medium"
                                >الفئة</th
                            >
                            <th
                                class="px-5 py-2.5 text-start text-xs font-medium"
                                >التاريخ</th
                            >
                            <th class="px-5 py-2.5 text-end text-xs font-medium"
                                >المبلغ</th
                            >
                        </tr>
                    </thead>
                    <tbody>
                        {#each recentTransactions as t (t.id)}
                            <tr
                                class="border-b border-border transition-colors last:border-0 hover:bg-secondary"
                            >
                                <td class="px-5 py-3">
                                    <span class="flex items-center gap-2.5">
                                        <CategoryIcon
                                            icon={t.icon}
                                            color={t.color}
                                            size="xs"
                                        />
                                        {t.desc}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-foreground/80"
                                    >{t.category}</td
                                >
                                <td
                                    class="px-5 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                                >
                                    {formatDate(t.date)}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-semibold tabular-nums {t.type ===
                                    'expense'
                                        ? 'text-destructive'
                                        : 'text-success-text'}"
                                >
                                    {t.type === 'expense' ? '−' : '+'}
                                    {formatAmount(t.amount)} ر.س
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>

                <!-- بطاقات مكدّسة على الجوال — صفر تمرير أفقي -->
                <ul class="divide-y divide-border md:hidden">
                    {#each recentTransactions as t (t.id)}
                        <li class="flex items-center gap-3 px-4 py-3">
                            <CategoryIcon
                                icon={t.icon}
                                color={t.color}
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px]">{t.desc}</p>
                                <p class="text-[11.5px] text-muted-foreground">
                                    {t.category} · {formatDate(t.date)}
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-[13px] font-semibold tabular-nums {t.type ===
                                'expense'
                                    ? 'text-destructive'
                                    : 'text-success-text'}"
                            >
                                {t.type === 'expense' ? '−' : '+'}
                                {formatAmount(t.amount)}
                            </span>
                        </li>
                    {/each}
                </ul>
            {:else}
                <EmptyState
                    icon={ReceiptText}
                    title="ما فيه معاملات بعد"
                    description="أول ما تسجّل مصروف أو دخل بيظهر هنا."
                    actionLabel="أضف أول مصروف"
                    href="/expenses?new=1"
                />
            {/if}
        </section>
    {/if}
</div>
