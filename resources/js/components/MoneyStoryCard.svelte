<script lang="ts">
    /**
     * «بطاقة القصّة المالية» — العنصر الأول في لوحة التحكم.
     *
     * تجيب على السؤال الوحيد اللي يهمّ المستخدم فعلاً:
     *   «كم أقدر أصرف اليوم بدون ما أورّط نفسي؟»
     *
     * المعادلات:
     *   المحجوز        = فواتير غير مدفوعة + أقساط شهرية + تحويلات ادخار
     *   المتبقي لك     = الدخل − المحجوز − المصاريف المسجّلة
     *   الحد اليومي    = المتبقي لك ÷ الأيام حتى الراتب القادم
     */
    import Wallet from 'lucide-svelte/icons/wallet';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Check from 'lucide-svelte/icons/check';
    import FlowBar from '@/components/FlowBar.svelte';
    import { formatAmount, formatCurrency } from '@/lib/format';

    let {
        income = 0,
        bills = 0,
        installments = 0,
        savings = 0,
        expenses = 0,
        daysLeft = 0,
        avgDaily = 0,
    }: {
        income?: number;
        bills?: number;
        installments?: number;
        savings?: number;
        expenses?: number;
        daysLeft?: number;
        avgDaily?: number;
    } = $props();

    const reserved = $derived(bills + installments + savings);
    const remaining = $derived(income - reserved - expenses);
    const isNegative = $derived(remaining < 0);
    const safeDaily = $derived(daysLeft > 0 ? Math.floor(remaining / daysLeft) : remaining);
    const onTrack = $derived(avgDaily > 0 && safeDaily > 0 && avgDaily <= safeDaily);

    const slices = $derived([
        { key: 'bills', label: 'فواتير', amount: bills, color: 'var(--chart-7)' },
        { key: 'inst', label: 'أقساط', amount: installments, color: 'var(--chart-2)' },
        { key: 'save', label: 'ادخار', amount: savings, color: 'var(--chart-3)' },
        { key: 'exp', label: 'مصاريف', amount: expenses, color: 'var(--chart-1)' },
    ].filter((s) => s.amount > 0));
</script>

<section class="overflow-hidden rounded-[22px] border border-border bg-card shadow-xs">
    <!-- الصف العلوي: الرقم البطل + الأرقام المساندة -->
    <div class="flex flex-wrap items-end gap-x-7 gap-y-6 px-6 pt-6 pb-5 sm:px-7">
        <div>
            <p class="flex items-center gap-1.5 text-[12.5px] text-muted-foreground">
                <Wallet class="size-3.5" />
                المتبقي لك للصرف
            </p>
            <p
                class="mt-1 text-4xl leading-[1.1] font-semibold tracking-tight {isNegative
                    ? 'text-destructive'
                    : ''}"
            >
                {formatAmount(remaining)}<span class="ms-1 text-[17px] font-medium text-foreground/70">
                    ر.س
                </span>
            </p>

            {#if isNegative}
                <p class="mt-1.5 text-[12.5px] text-destructive">
                    تجاوزت دخلك بـ <b class="font-semibold">{formatCurrency(Math.abs(remaining))}</b> هذا الشهر
                </p>
            {:else if daysLeft === 0}
                <p class="mt-1.5 text-[12.5px] text-foreground/75">راتبك اليوم — ميزانية جديدة تبدأ</p>
            {:else}
                <p class="mt-1.5 text-[12.5px] text-foreground/75">
                    تقدر تصرف
                    <b class="font-semibold text-success-text">{formatCurrency(safeDaily)} يومياً</b>
                    وتوصل آخر الشهر بأمان
                </p>
            {/if}
        </div>

        <div class="hidden w-px self-stretch bg-border sm:block"></div>

        <div class="flex flex-col gap-0.5">
            <span class="text-xs text-muted-foreground">دخلك</span>
            <span class="text-[19px] font-semibold tracking-tight tabular-nums">{formatAmount(income)}</span>
        </div>

        <div class="flex flex-col gap-0.5">
            <span class="text-xs text-muted-foreground">محجوز (التزامات)</span>
            <span class="text-[19px] font-semibold tracking-tight tabular-nums">{formatAmount(reserved)}</span>
        </div>

        <div class="flex flex-col gap-0.5">
            <span class="text-xs text-muted-foreground">صرفت هذا الشهر</span>
            <span class="text-[19px] font-semibold tracking-tight tabular-nums">{formatAmount(expenses)}</span>
        </div>

        <div
            class="flex flex-col items-end gap-0.5 rounded-xl border border-border bg-secondary px-4 py-2.5 sm:ms-auto"
        >
            <span class="text-[11.5px] text-muted-foreground">متوسط صرفك اليومي</span>
            <span class="text-xl font-semibold tracking-tight tabular-nums">{formatAmount(avgDaily)} ر.س</span>
            {#if avgDaily > 0}
                <span
                    class="inline-flex items-center gap-1 text-[11.5px] {onTrack
                        ? 'text-success-text'
                        : 'text-destructive'}"
                >
                    {#if onTrack}
                        <Check class="size-3" /> أقل من الحد الآمن
                    {:else}
                        <TriangleAlert class="size-3" /> أعلى من الحد الآمن
                    {/if}
                </span>
            {/if}
        </div>
    </div>

    <!-- شريط التدفّق -->
    <div class="px-6 pb-5 sm:px-7">
        <FlowBar {income} {slices} />
        <p class="mt-2.5 text-[11.5px] text-muted-foreground">
            شريط واحد يقرأ من اليمين لليسار: من وين دخل الراتب، ووين راح، وكم بقي.
        </p>
    </div>
</section>
