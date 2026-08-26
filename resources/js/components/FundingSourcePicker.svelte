<script lang="ts">
    /**
     * الخطوة 2 — «من وين جاء المبلغ؟»
     *
     * ══════════════════════════════════════════════════════════════════
     *  الفلوس لازم تجي من مكان.
     * ══════════════════════════════════════════════════════════════════
     *
     * المنع المجرّد كذب — المستخدم فعلاً صرفها. والحفظ الصامت خيال محاسبي —
     * رقم سالب بلا مصدر. الحل: نقفل الطريق الصامت، ونفتح الطريق الصادق.
     *
     * شاشة مستقلة لا بطاقة داخل شاشة الإدخال: هذا السؤال يستحق كامل
     * الانتباه، والخيار غير المتاح يبقى ظاهراً **معطّلاً مع سببه** لا مخفياً —
     * الاختفاء يترك المستخدم يتساءل وين راح الخيار.
     */
    import Vault from 'lucide-svelte/icons/vault';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Check from 'lucide-svelte/icons/check';
    import Pencil from 'lucide-svelte/icons/pencil';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import { formatAmount, formatCurrency } from '@/lib/format';

    export interface SavingsGoalOption {
        id: number;
        name: string;
        icon: string;
        color: string;
        current: number;
        target: number;
        monthsBehindPerRiyal?: number;
    }

    export type FundingSource = 'savings' | 'unlogged_income' | 'overspend';

    export interface Funding {
        source: FundingSource | null;
        savingsGoalId: number | null;
        incomeAmount: number;
        incomeSource: string;
    }

    export interface FundingNote {
        title: string;
        detail?: string;
    }

    let {
        /** المصروف كاملاً — بالهللات */
        amount = 0,
        /** الجزء الذي يتجاوز المتبقي — بالهللات */
        shortfall = 0,
        goals = [] as SavingsGoalOption[],
        /** تحذيرات ثانوية تُعرض كخبر أسفل الخيارات */
        notes = [] as FundingNote[],
        value = $bindable<Funding>({
            source: null,
            savingsGoalId: null,
            incomeAmount: 0,
            incomeSource: '',
        }),
    }: {
        amount?: number;
        shortfall?: number;
        goals?: SavingsGoalOption[];
        notes?: FundingNote[];
        value?: Funding;
    } = $props();

    const usableGoals = $derived(goals.filter((g) => g.current >= shortfall));
    const selectedGoal = $derived(goals.find((g) => g.id === value.savingsGoalId) ?? null);

    let amountSheetOpen = $state(false);

    // المبلغ المقترح للدخل غير المسجّل = العجز بالضبط، لأنه الأرجح
    $effect(() => {
        if (value.source === 'unlogged_income' && value.incomeAmount === 0) {
            value.incomeAmount = shortfall;
        }
        if (value.source === 'savings' && value.savingsGoalId === null && usableGoals.length === 1) {
            value.savingsGoalId = usableGoals[0].id;
        }
    });

    const OPTIONS = [
        {
            key: 'savings' as const,
            icon: Vault,
            title: 'من مدخراتي',
            detail: 'أسحب من هدف ادخار',
            color: 'var(--chart-3)',
        },
        {
            key: 'unlogged_income' as const,
            icon: TrendingUp,
            title: 'دخل ما سجّلته',
            detail: 'وصلني مبلغ ونسيت أسجّله',
            color: 'var(--success)',
        },
        {
            key: 'overspend' as const,
            icon: TriangleAlert,
            title: 'تجاوزت وأعرف',
            detail: 'سجّله كتجاوز',
            color: 'var(--destructive)',
        },
    ];

    function pick(k: FundingSource) {
        value.source = k;
        if (k !== 'savings') value.savingsGoalId = null;
        if (k !== 'unlogged_income') {
            value.incomeAmount = 0;
            value.incomeSource = '';
        }
    }
</script>

<div class="flex flex-col gap-3">
    <!-- ── بطاقة الفجوة ─────────────────────────────────────────────── -->
    <div class="rounded-2xl border border-destructive/35 bg-destructive/[0.06] px-3 py-3.5 text-center">
        <p class="text-[11.5px] text-foreground/75">
            مصروف {formatCurrency(amount)} يتجاوز المتبقي لك بـ
        </p>
        <p class="mt-1 text-[34px] leading-none font-semibold text-destructive tabular-nums">
            {formatAmount(shortfall)}<span class="ms-1.5 text-[13px] font-medium">ر.س</span>
        </p>
        <p class="mt-1.5 text-[11.5px] text-foreground/75">
            لازم نعرف مصدرها عشان أرقامك تبقى صادقة.
        </p>
    </div>

    <!-- ── الخيارات ─────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-2">
        {#each OPTIONS as o (o.key)}
            {@const active = value.source === o.key}
            {@const disabled = o.key === 'savings' && usableGoals.length === 0}
            <button
                type="button"
                {disabled}
                onclick={() => pick(o.key)}
                aria-pressed={active}
                class="flex min-h-[70px] w-full items-center gap-3 rounded-2xl border px-3 text-start transition-transform active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100 {active
                    ? 'border-current bg-card shadow-xs'
                    : 'border-border bg-card'}"
                style={active ? `color:${o.color}` : ''}
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl"
                    style="background-color: color-mix(in srgb, {o.color} 12%, transparent); color: {o.color}"
                >
                    <o.icon class="size-5" stroke-width="1.9" />
                </span>
                <span class="min-w-0 flex-1">
                    <b class="block text-[14px] font-semibold text-foreground">{o.title}</b>
                    <span class="mt-0.5 block text-[11.5px] text-muted-foreground">
                        {disabled ? 'ما فيه هدف رصيده يكفي' : o.detail}
                    </span>
                </span>
                {#if active}
                    <Check class="size-5 shrink-0" stroke-width="1.9" />
                {/if}
            </button>
        {/each}
    </div>

    <!-- ── تفاصيل: من مدخراتي ───────────────────────────────────────── -->
    {#if value.source === 'savings' && usableGoals.length}
        <div class="rounded-2xl border border-border bg-card p-3 shadow-xs">
            <p class="mb-2 text-[14px] font-semibold">من أي هدف؟</p>
            <div class="flex flex-col gap-1">
                {#each usableGoals as g (g.id)}
                    {@const on = value.savingsGoalId === g.id}
                    <button
                        type="button"
                        onclick={() => (value.savingsGoalId = g.id)}
                        aria-pressed={on}
                        class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-2 text-start transition-colors {on
                            ? 'bg-secondary'
                            : 'hover:bg-secondary/60'}"
                    >
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color:{g.color}"></span>
                        <span class="min-w-0 flex-1 truncate text-[13px]">{g.name}</span>
                        <span class="shrink-0 text-[11.5px] text-muted-foreground tabular-nums">
                            {formatAmount(g.current)}
                        </span>
                        {#if on}<Check class="size-4 shrink-0 text-foreground" stroke-width="1.9" />{/if}
                    </button>
                {/each}
            </div>

            {#if selectedGoal}
                <div class="mt-2.5 border-t border-border pt-2.5 text-[11.5px]">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">رصيد {selectedGoal.name}</span>
                        <span class="whitespace-nowrap">
                            <b class="font-semibold tabular-nums">{formatAmount(selectedGoal.current)}</b>
                            <span class="mx-0.5 text-input">←</span>
                            <b class="font-semibold text-destructive tabular-nums">
                                {formatAmount(selectedGoal.current - shortfall)}
                            </b> ر.س
                        </span>
                    </div>
                    {#if selectedGoal.monthsBehindPerRiyal}
                        {@const behind = Math.ceil(shortfall * selectedGoal.monthsBehindPerRiyal)}
                        {#if behind >= 1}
                            <p class="mt-1 text-warning-text">
                                بتتأخر {behind} {behind === 1 ? 'شهر' : 'أشهر'} عن هدفك.
                            </p>
                        {/if}
                    {/if}
                </div>
            {/if}
        </div>
    {/if}

    <!-- ── تفاصيل: دخل ما سجّلته ─────────────────────────────────────── -->
    {#if value.source === 'unlogged_income'}
        <div class="flex flex-col gap-2 rounded-2xl border border-border bg-card p-3 shadow-xs">
            <p class="text-[14px] font-semibold">بنسجّل هذا الدخل عشان تتوازن أرقامك</p>
            <button
                type="button"
                onclick={() => (amountSheetOpen = true)}
                class="flex min-h-11 w-full items-center justify-between gap-2 rounded-xl border border-input bg-secondary px-3 text-start transition-transform active:scale-[.98]"
            >
                <span class="text-[11.5px] text-muted-foreground">المبلغ</span>
                <span class="flex items-center gap-2">
                    <b class="text-[15px] font-semibold tabular-nums">{formatAmount(value.incomeAmount)}</b>
                    <span class="text-[11.5px] text-muted-foreground">ر.س</span>
                    <Pencil class="size-[17px] text-muted-foreground" stroke-width="1.9" />
                </span>
            </button>
            <input
                bind:value={value.incomeSource}
                placeholder="المصدر — مثال: عمل حر، هدية"
                class="min-h-11 w-full rounded-xl border border-input bg-secondary px-3 text-[13px] outline-none focus:border-ring focus:bg-card"
            />
            {#if value.incomeAmount > 0 && value.incomeAmount < shortfall}
                <p class="text-[11.5px] text-warning-text">
                    لسّه ناقص {formatCurrency(shortfall - value.incomeAmount)} — زد المبلغ أو اختر مصدراً آخر.
                </p>
            {/if}
        </div>
    {/if}

    <!-- ── تفاصيل: تجاوزت وأعرف ─────────────────────────────────────── -->
    {#if value.source === 'overspend'}
        <p class="rounded-2xl border border-border bg-card px-3 py-2.5 text-[11.5px] text-foreground/75 shadow-xs">
            بينحفظ بعلامة <b class="font-semibold">«تجاوز»</b> وبيظهر في تقريرك الشهري.
            «المتبقي لك» بيصير بالسالب — وهذا صحيح، لأنك فعلاً صرفت أكثر من دخلك.
        </p>
    {/if}

    <!-- ── تحذيرات ثانوية — خبر لا منافس على الانتباه ────────────────── -->
    {#each notes as n (n.title)}
        <div class="rounded-2xl border border-border bg-secondary px-3 py-2 text-[11.5px] text-muted-foreground">
            <p class="font-medium text-foreground/75">{n.title}</p>
            {#if n.detail}<p class="mt-0.5">{n.detail}</p>{/if}
        </div>
    {/each}
</div>

<AmountSheet
    bind:open={amountSheetOpen}
    bind:value={value.incomeAmount}
    title="مبلغ الدخل غير المسجّل"
    subtitle="لازم يغطّي {formatCurrency(shortfall)} على الأقل"
    saveLabel="تأكيد"
/>
