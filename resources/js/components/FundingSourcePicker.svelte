<script lang="ts">
    /**
     * «من وين جاء المبلغ؟» — يظهر فقط لما يتجاوز المصروف المتبقي للصرف.
     *
     * ══════════════════════════════════════════════════════════════════
     *  الفلوس لازم تجي من مكان.
     * ══════════════════════════════════════════════════════════════════
     *
     * المنع المجرّد كذب — المستخدم فعلاً صرفها. والحفظ الصامت خيال محاسبي —
     * رقم سالب بلا مصدر. الحل: نقفل الطريق الصامت، ونفتح الطريق الصادق.
     *
     * النتيجة: «المتبقي لك» ما ينزل تحت الصفر أبداً، لأن أي تجاوز إما
     * ينقص المدخرات أو يعني دخلاً غير مسجّل.
     *
     * زر الحفظ في اللوح الأب يبقى معطّلاً حتى يختار المستخدم مصدراً صالحاً.
     */
    import Vault from 'lucide-svelte/icons/vault';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Check from 'lucide-svelte/icons/check';
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

    let {
        shortfall = 0,
        goals = [] as SavingsGoalOption[],
        value = $bindable<Funding>({
            source: null,
            savingsGoalId: null,
            incomeAmount: 0,
            incomeSource: '',
        }),
    }: {
        shortfall?: number;
        goals?: SavingsGoalOption[];
        value?: Funding;
    } = $props();

    const usableGoals = $derived(goals.filter((g) => g.current >= shortfall));
    const selectedGoal = $derived(goals.find((g) => g.id === value.savingsGoalId) ?? null);

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

<div class="mx-5 mb-3.5 rounded-2xl border border-destructive/35 bg-destructive/[0.06] p-3.5">
    <div class="mb-3 flex items-start gap-2.5">
        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-destructive" />
        <div class="min-w-0">
            <p class="text-[12.5px] font-semibold text-destructive">
                يتجاوز المتبقي لك بـ {formatCurrency(shortfall)}
            </p>
            <p class="mt-0.5 text-[11.5px] text-foreground/75">
                من وين جاء هذا المبلغ؟ لازم نعرف عشان أرقامك تبقى صادقة.
            </p>
        </div>
    </div>

    <div class="space-y-1.5">
        {#each OPTIONS as o (o.key)}
            {@const active = value.source === o.key}
            {@const disabled = o.key === 'savings' && usableGoals.length === 0}
            <button
                type="button"
                {disabled}
                onclick={() => pick(o.key)}
                aria-pressed={active}
                class="flex w-full items-center gap-2.5 rounded-xl border px-3 py-2.5 text-start transition-colors disabled:cursor-not-allowed disabled:opacity-45 {active
                    ? 'border-current bg-card'
                    : 'border-border bg-card/60 hover:border-input'}"
                style={active ? `color:${o.color}` : ''}
            >
                <span
                    class="grid size-8 shrink-0 place-items-center rounded-[9px]"
                    style="background-color: color-mix(in srgb, {o.color} 12%, transparent); color: {o.color}"
                >
                    <o.icon class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <b class="block text-[12.5px] font-semibold text-foreground">{o.title}</b>
                    <span class="block text-[11px] text-muted-foreground">
                        {disabled ? 'ما فيه هدف رصيده يكفي' : o.detail}
                    </span>
                </span>
                {#if active}
                    <Check class="size-4 shrink-0" />
                {/if}
            </button>
        {/each}
    </div>

    <!-- ── تفاصيل: من مدخراتي ─────────────────────────────────────── -->
    {#if value.source === 'savings' && usableGoals.length}
        <div class="mt-3 rounded-xl border border-border bg-card p-3">
            <p class="mb-2 text-[11.5px] font-medium">من أي هدف؟</p>
            <div class="space-y-1">
                {#each usableGoals as g (g.id)}
                    {@const on = value.savingsGoalId === g.id}
                    <button
                        type="button"
                        onclick={() => (value.savingsGoalId = g.id)}
                        aria-pressed={on}
                        class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-start transition-colors {on
                            ? 'bg-secondary'
                            : 'hover:bg-secondary/60'}"
                    >
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color:{g.color}"></span>
                        <span class="min-w-0 flex-1 truncate text-[12.5px]">{g.name}</span>
                        <span class="shrink-0 text-[11.5px] text-muted-foreground tabular-nums">
                            {formatAmount(g.current)}
                        </span>
                        {#if on}<Check class="size-3.5 shrink-0 text-foreground" />{/if}
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

    <!-- ── تفاصيل: دخل ما سجّلته ───────────────────────────────────── -->
    {#if value.source === 'unlogged_income'}
        <div class="mt-3 space-y-2 rounded-xl border border-border bg-card p-3">
            <p class="text-[11.5px] font-medium">بنسجّل هذا الدخل عشان تتوازن أرقامك</p>
            <div class="flex items-center overflow-hidden rounded-lg border border-input bg-secondary">
                <input
                    type="text"
                    inputmode="decimal"
                    dir="ltr"
                    value={value.incomeAmount ? (value.incomeAmount / 100).toString() : ''}
                    oninput={(e) => {
                        const n = parseFloat((e.currentTarget as HTMLInputElement).value.replace(/[^\d.]/g, ''));
                        value.incomeAmount = Number.isFinite(n) ? Math.round(n * 100) : 0;
                    }}
                    placeholder="0.00"
                    class="min-w-0 flex-1 bg-transparent px-3 py-2 text-[15px] font-semibold tabular-nums outline-none"
                    style="text-align:start"
                />
                <span class="grid self-stretch place-items-center border-s border-border px-3 text-[11.5px] text-muted-foreground">
                    ر.س
                </span>
            </div>
            <input
                bind:value={value.incomeSource}
                placeholder="المصدر — مثال: عمل حر، هدية"
                class="w-full rounded-lg border border-border bg-secondary px-3 py-2 text-[12.5px] outline-none focus:border-ring focus:bg-card"
            />
            {#if value.incomeAmount > 0 && value.incomeAmount < shortfall}
                <p class="text-[11px] text-warning-text">
                    لسّه ناقص {formatCurrency(shortfall - value.incomeAmount)} — زد المبلغ أو اختر مصدراً آخر.
                </p>
            {/if}
        </div>
    {/if}

    <!-- ── تفاصيل: تجاوزت وأعرف ────────────────────────────────────── -->
    {#if value.source === 'overspend'}
        <p class="mt-3 rounded-xl border border-border bg-card px-3 py-2.5 text-[11.5px] text-foreground/75">
            بينحفظ بعلامة <b class="font-semibold">«تجاوز»</b> وبيظهر في تقريرك الشهري.
            «المتبقي لك» بيصير بالسالب — وهذا صحيح، لأنك فعلاً صرفت أكثر من دخلك.
        </p>
    {/if}
</div>
