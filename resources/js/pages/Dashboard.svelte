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
    import { router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Lock from 'lucide-svelte/icons/lock';
import Vault from 'lucide-svelte/icons/vault';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingCart from 'lucide-svelte/icons/shopping-cart';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Wallet from 'lucide-svelte/icons/wallet';

    import AppHead from '@/components/AppHead.svelte';
    import BudgetRow from '@/components/BudgetRow.svelte';
    import CategoryDonut from '@/components/CategoryDonut.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import MoneyStoryCard from '@/components/MoneyStoryCard.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import MonthlyBars from '@/components/MonthlyBars.svelte';
    import UpcomingStrip from '@/components/UpcomingStrip.svelte';
    import StatTile from '@/components/StatTile.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { formatAmount, formatDate, formatPercent } from '@/lib/format';

    interface Stats {
        totalIncome: number;
        totalExpenses: number;
        prevExpenses: number;
        bills: number;
        installments: number;
        savings: number;
        avgDaily: number;
        daysLeft: number;
        billsCount: number;
        installmentsCount: number;
        savingsRate: number;
        savingsTarget: number;
    }

    interface Category {
        id: number;
        name: string;
        icon: string;
        color: string;
        amount: number;
        budget: number;
        rollover: number;
    }

    interface MonthlyPoint {
        month: string;
        income: number;
        expenses: number;
    }

    interface CalEvent {
        date: string;
        kind: 'salary' | 'bill' | 'installment' | 'savings';
        label: string;
        amount: number;
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
            bills: 0,
            installments: 0,
            savings: 0,
            avgDaily: 0,
            daysLeft: 0,
            billsCount: 0,
            installmentsCount: 0,
            savingsRate: 0,
            savingsTarget: 10,
        } as Stats,
        categories = [] as Category[],
        monthly = [] as MonthlyPoint[],
        calendarEvents = [] as CalEvent[],
        recentTransactions = [] as Transaction[],
        month = '',
        availableMonths = [] as { value: string; label: string }[],
        hasData = false,
        onboardingComplete = true,
    }: {
        stats?: Stats;
        categories?: Category[];
        monthly?: MonthlyPoint[];
        calendarEvents?: CalEvent[];
        recentTransactions?: Transaction[];
        month?: string;
        availableMonths?: { value: string; label: string }[];
        hasData?: boolean;
        onboardingComplete?: boolean;
    } = $props();

    const expenseDelta = $derived(
        stats.prevExpenses > 0
            ? ((stats.totalExpenses - stats.prevExpenses) / stats.prevExpenses) * 100
            : 0,
    );

    const budgetedCategories = $derived(
        [...categories]
            .filter((c) => c.budget > 0)
            .sort((a, b) => b.amount / (b.budget || 1) - a.amount / (a.budget || 1)),
    );

    const currentMonthLabel = $derived(availableMonths.find((m) => m.value === month)?.label ?? month);

    let monthOpen = $state(false);

    // فلتر شهر حقيقي — يعيد الطلب للسيرفر، لا يغيّر متغيّراً محلياً فقط
    function selectMonth(value: string) {
        monthOpen = false;
        router.get('/dashboard', { month: value }, { preserveScroll: true, preserveState: true });
    }
</script>

<AppHead title="لوحة التحكم" />
<MobileHeader
    title="لوحة التحكم"
    subtitle={stats.daysLeft > 0
        ? `صورة ميزانيتك الكاملة — باقي ${stats.daysLeft} يوم على الراتب.`
        : 'صورة ميزانيتك الكاملة لهذا الشهر.'}
/>

<div class="flex flex-1 flex-col gap-3 p-3 md:gap-[18px] md:p-6">
    <!-- رأس الصفحة -->
    <div class="hidden flex-wrap items-start justify-between gap-4 md:flex">
        <div>
            <h1 class="text-[22px] font-semibold tracking-tight">لوحة التحكم</h1>
            <p class="text-[13px] text-muted-foreground">
                {#if stats.daysLeft > 0}
                    صورة ميزانيتك الكاملة — باقي {stats.daysLeft} يوم على الراتب.
                {:else}
                    صورة ميزانيتك الكاملة لهذا الشهر.
                {/if}
            </p>
        </div>

        <div class="flex gap-2">
            <div class="relative">
                <Button variant="outline" size="sm" class="gap-1.5" onclick={() => (monthOpen = !monthOpen)}>
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
                                class="w-full px-3 py-1.5 text-start text-xs hover:bg-secondary {m.value === month
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
            <Button variant="outline" size="sm" href="/reports">تصدير التقرير</Button>
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
                    <b class="font-semibold">أكمل إعدادك</b> — باقي خطوات بسيطة عشان تصير أرقامك دقيقة تماماً.
                </span>
                <ArrowLeft class="size-4 text-primary" />
            </a>
        {/if}

        <!-- ١ · بطاقة القصّة المالية -->
        <MoneyStoryCard
            income={stats.totalIncome}
            bills={stats.bills}
            installments={stats.installments}
            savings={stats.savings}
            expenses={stats.totalExpenses}
            daysLeft={stats.daysLeft}
            avgDaily={stats.avgDaily}
        />

        <!-- ٢ · التقويم المالي -->
        <UpcomingStrip events={calendarEvents} />

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
                amount={stats.bills + stats.installments + stats.savings}
                icon={Lock}
                color="var(--chart-7)"
                note="{stats.billsCount} فواتير · {stats.installmentsCount} أقساط"
            />
            <StatTile
                label="الادخار"
                amount={stats.savings}
                icon={Vault}
                color="var(--chart-3)"
                note="{formatPercent(stats.savingsRate)} من دخلك · الهدف {formatPercent(stats.savingsTarget)}"
            />
        </div>

        <!-- ٤ · الرسوم -->
        <div class="grid gap-3.5 lg:grid-cols-2">
            <section class="rounded-2xl border border-border bg-card shadow-xs">
                <header class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 class="text-[14.5px] font-semibold">وين راحت مصاريفك؟</h2>
                    <a href="/expenses" class="text-[12.5px] text-primary no-underline">جدول البيانات</a>
                </header>
                <div class="p-5 [&>div>div:first-child]:hidden md:[&>div>div:first-child]:block">
                    {#if categories.some((c) => c.amount > 0)}
                        <CategoryDonut {categories} />
                    {:else}
                        <EmptyState
                            icon={ShoppingCart}
                            title="ما سجّلت أي مصروف هالشهر"
                            description="أضف أول مصروف وبتشوف التوزيع هنا."
                            actionLabel="أضف مصروف"
                            href="/expenses?new=1"
                        />
                    {/if}
                </div>
            </section>

            <section class="hidden rounded-2xl border border-border bg-card shadow-xs md:block">
                <header class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 class="text-[14.5px] font-semibold">الدخل مقابل المصاريف — ٦ أشهر</h2>
                    <a href="/reports" class="text-[12.5px] text-primary no-underline">آخر ١٢ شهر</a>
                </header>
                <div class="p-5">
                    {#if monthly.length}
                        <MonthlyBars data={monthly} />
                    {:else}
                        <EmptyState title="ما فيه بيانات كافية بعد" description="بعد شهر من الاستخدام بتشوف اتجاهك هنا." />
                    {/if}
                </div>
            </section>
        </div>

        <!-- ٥ · الميزانية حسب الفئة -->
        {#if budgetedCategories.length}
            <section class="rounded-2xl border border-border bg-card shadow-xs">
                <header class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 class="text-[14.5px] font-semibold">ميزانيتك حسب الفئة</h2>
                    <a href="/budgets" class="inline-flex items-center gap-1 text-[12.5px] text-primary no-underline">
                        تعديل الميزانية
                        <ArrowLeft class="size-3.5" />
                    </a>
                </header>
                <div class="grid grid-cols-1 gap-3 p-3 md:grid-cols-2 md:p-5">
                    {#each budgetedCategories.slice(0, 6) as c (c.id)}
                        <BudgetRow
                            name={c.name}
                            icon={c.icon}
                            color={c.color}
                            spent={c.amount}
                            budget={c.budget}
                            rollover={c.rollover}
                            onclick={() => router.visit('/budgets')}
                        />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- ٦ · آخر المعاملات -->
        <section class="rounded-2xl border border-border bg-card shadow-xs">
            <header class="flex items-center justify-between border-b border-border px-5 py-4">
                <h2 class="text-[14.5px] font-semibold">آخر المعاملات</h2>
                <a href="/expenses" class="text-[12.5px] text-primary no-underline">عرض الكل</a>
            </header>

            {#if recentTransactions.length}
                <!-- جدول على الشاشات المتوسطة فأكبر -->
                <table class="hidden w-full text-[13px] md:table">
                    <thead>
                        <tr class="border-b border-border text-muted-foreground">
                            <th class="px-5 py-2.5 text-start text-xs font-medium">الوصف</th>
                            <th class="px-5 py-2.5 text-start text-xs font-medium">الفئة</th>
                            <th class="px-5 py-2.5 text-start text-xs font-medium">التاريخ</th>
                            <th class="px-5 py-2.5 text-end text-xs font-medium">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each recentTransactions as t (t.id)}
                            <tr class="border-b border-border transition-colors last:border-0 hover:bg-secondary">
                                <td class="px-5 py-3">
                                    <span class="flex items-center gap-2.5">
                                        <CategoryIcon icon={t.icon} color={t.color} size="xs" />
                                        {t.desc}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-foreground/75">{t.category}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-muted-foreground tabular-nums">
                                    {formatDate(t.date)}
                                </td>
                                <td
                                    class="px-5 py-3 text-end font-semibold tabular-nums {t.type === 'expense'
                                        ? 'text-destructive'
                                        : 'text-success-text'}"
                                >
                                    {t.type === 'expense' ? '−' : '+'} {formatAmount(t.amount)} ر.س
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>

                <!-- بطاقات مكدّسة على الجوال — صفر تمرير أفقي -->
                <ul class="divide-y divide-border md:hidden">
                    {#each recentTransactions as t (t.id)}
                        <li class="flex items-center gap-3 px-4 py-3">
                            <CategoryIcon icon={t.icon} color={t.color} size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px]">{t.desc}</p>
                                <p class="text-[11.5px] text-muted-foreground">
                                    {t.category} · {formatDate(t.date)}
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-[13px] font-semibold tabular-nums {t.type === 'expense'
                                    ? 'text-destructive'
                                    : 'text-success-text'}"
                            >
                                {t.type === 'expense' ? '−' : '+'} {formatAmount(t.amount)}
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
