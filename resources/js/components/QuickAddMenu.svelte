<script lang="ts">
    /**
     * الوصول السريع — لوح واحد بأربعة إجراءات، كلّها تُنجَز داخله.
     *
     *   مصروف · دخل · إيداع ادخار · دفع التزام
     *
     * لماذا داخل اللوح لا بانتقال صفحة: أكثر إجراء يتكرّر يومياً كان يكلّف
     * انتقالاً ثم مودالاً ثم رجوعاً — ثلاث دورات انتظار لتسجيل عشرين ريالاً.
     * هنا الإجراء يبدأ وينتهي في نفس اللوح، ويُغلق بـ`toast` وزر تراجع.
     *
     * المصروف والدخل يذهبان إلى `QuickAddSheet` (فيه قواعد التمويل كاملة)،
     * والإيداع والدفع يتمّان هنا مباشرة لأنهما خطوة واحدة لا أكثر.
     */
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingCart from 'lucide-svelte/icons/shopping-cart';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import Vault from 'lucide-svelte/icons/vault';
    import { toast } from 'svelte-sonner';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import { formatAmount, formatRelativeDays, toRiyals } from '@/lib/format';

    interface Goal {
        id: number;
        name: string;
        icon: string;
        current: number;
        target: number;
    }

    interface DueCommitment {
        id: number;
        name: string;
        icon: string;
        color: string;
        amount: number;
        average_amount: number;
        is_variable: boolean;
        due_date: string;
    }

    let {
        open = $bindable(false),
        /** يُرفع لفتح لوح المصروف/الدخل من الأب */
        onPick,
        savingsGoals = [] as Goal[],
        dueCommitments = [] as DueCommitment[],
        dueTodayCount = 0,
    }: {
        open?: boolean;
        onPick?: (mode: 'expense' | 'income') => void;
        savingsGoals?: Goal[];
        dueCommitments?: DueCommitment[];
        dueTodayCount?: number;
    } = $props();

    /** الشاشة داخل اللوح: القائمة · اختيار هدف الادخار · اختيار الالتزام */
    let view = $state<'menu' | 'savings' | 'commitments'>('menu');
    let submitting = $state(false);

    let amountSheet = $state({
        open: false,
        value: 0,
        title: '',
        subtitle: '',
        apply: (_halalas: number) => {},
    });

    const ACTIONS = [
        {
            key: 'expense' as const,
            label: 'مصروف',
            detail: 'سجّل ما صرفته الآن',
            icon: ShoppingCart,
            color: 'var(--chart-1)',
        },
        {
            key: 'income' as const,
            label: 'دخل',
            detail: 'مبلغ دخل حسابك',
            icon: TrendingUp,
            color: 'var(--chart-6)',
        },
        {
            key: 'savings' as const,
            label: 'إيداع ادخار',
            detail: 'حوّل مبلغاً إلى هدف',
            icon: Vault,
            color: 'var(--chart-3)',
        },
        {
            key: 'commitment' as const,
            label: 'دفع التزام',
            detail: 'علّم التزاماً كمدفوع',
            icon: ReceiptText,
            color: 'var(--chart-7)',
        },
    ];

    function reset() {
        view = 'menu';
    }

    function pick(key: (typeof ACTIONS)[number]['key']) {
        if (key === 'expense' || key === 'income') {
            open = false;
            reset();
            onPick?.(key);

            return;
        }

        view = key === 'savings' ? 'savings' : 'commitments';
    }

    // ── إيداع ادخار ───────────────────────────────────────────────────
    function depositTo(goal: Goal) {
        amountSheet = {
            open: true,
            value: 0,
            title: `إيداع في «${goal.name}»`,
            subtitle: `الحالي ${formatAmount(goal.current)} من ${formatAmount(goal.target)} ر.س`,
            apply: (halalas) => {
                if (halalas <= 0) {
                    return;
                }

                submitting = true;

                // المسار يستقبل المبلغ بالريالات (Money::toHalalas في الخادم).
                router.put(
                    `/savings/${goal.id}`,
                    { amount: toRiyals(halalas) },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            open = false;
                            reset();
                            toast.success(
                                `تم إيداع ${formatAmount(halalas)} ر.س في ${goal.name}`,
                            );
                        },
                        onFinish: () => (submitting = false),
                    },
                );
            },
        };
    }

    // ── دفع التزام ────────────────────────────────────────────────────
    function pay(commitment: DueCommitment) {
        const expected = commitment.amount || commitment.average_amount;

        const send = (halalas: number) => {
            submitting = true;

            router.post(
                `/commitments/${commitment.id}/pay`,
                { amount: halalas },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        open = false;
                        reset();
                        toast.success(
                            `تم دفع ${commitment.name} ${formatAmount(halalas)} ر.س`,
                            {
                                duration: 5000,
                                action: {
                                    label: 'تراجع',
                                    onClick: () =>
                                        router.delete(
                                            `/commitments/${commitment.id}/pay`,
                                            {
                                                preserveScroll: true,
                                            },
                                        ),
                                },
                            },
                        );
                    },
                    onFinish: () => (submitting = false),
                },
            );
        };

        // الالتزام المتغيّر لا مبلغ ثابت له — يُسأل عنه، وغيره يُدفع بضغطة.
        if (commitment.is_variable || expected <= 0) {
            amountSheet = {
                open: true,
                value: expected,
                title: `دفع «${commitment.name}»`,
                subtitle:
                    expected > 0
                        ? `متوسّط آخر ثلاثة أشهر ${formatAmount(expected)} ر.س`
                        : '',
                apply: send,
            };

            return;
        }

        send(expected);
    }

    const titles = {
        menu: 'وش تبي تسجّل؟',
        savings: 'في أي هدف؟',
        commitments: 'أي التزام دفعت؟',
    } as const;
</script>

<AmountSheet
    bind:open={amountSheet.open}
    bind:value={amountSheet.value}
    title={amountSheet.title}
    subtitle={amountSheet.subtitle}
    saveLabel="تأكيد"
    onSave={(halalas) => amountSheet.apply(halalas)}
/>

<SheetShell
    bind:open
    title={titles[view]}
    subtitle={view === 'menu' && dueTodayCount > 0
        ? `${dueTodayCount} مستحقة اليوم`
        : ''}
    showBack={view !== 'menu'}
    onBack={reset}
    onClose={reset}
>
    {#if view === 'menu'}
        <div class="grid grid-cols-2 gap-2.5 py-1">
            {#each ACTIONS as action (action.key)}
                {@const Icon = action.icon}
                {@const badge = action.key === 'commitment' ? dueTodayCount : 0}
                <button
                    type="button"
                    onclick={() => pick(action.key)}
                    class="relative flex min-h-[104px] flex-col items-start gap-2 rounded-2xl border border-border bg-card p-3 text-start shadow-xs transition-transform active:scale-[.98]"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl"
                        style="background-color: color-mix(in srgb, {action.color} 12%, transparent); color: {action.color}"
                    >
                        <Icon class="size-5" stroke-width="1.9" />
                    </span>
                    <b class="text-[14px] font-semibold">{action.label}</b>
                    <span
                        class="text-[11.5px] leading-tight text-muted-foreground"
                        >{action.detail}</span
                    >

                    {#if badge > 0}
                        <span
                            class="absolute top-2.5 grid h-[19px] min-w-[19px] place-items-center rounded-full bg-destructive px-1.5 text-[11px] font-bold text-white tabular-nums"
                            style="inset-inline-end: 0.625rem"
                        >
                            {badge}
                        </span>
                    {/if}
                </button>
            {/each}
        </div>
    {:else if view === 'savings'}
        {#if savingsGoals.length === 0}
            <p class="py-6 text-center text-[13px] text-muted-foreground">
                ما عندك أهداف ادخار بعد — أنشئ هدفاً من صفحة الادخار أولاً.
            </p>
        {:else}
            <ul class="flex flex-col py-1">
                {#each savingsGoals as goal (goal.id)}
                    {@const pct =
                        goal.target > 0
                            ? Math.min(100, (goal.current / goal.target) * 100)
                            : 0}
                    <li class="border-b border-border last:border-b-0">
                        <button
                            type="button"
                            disabled={submitting}
                            onclick={() => depositTo(goal)}
                            class="flex min-h-11 w-full items-center gap-2.5 py-2.5 text-start transition-transform active:scale-[.99] disabled:opacity-45"
                        >
                            <CategoryIcon
                                icon={goal.icon}
                                color="var(--chart-3)"
                                size="lg"
                            />
                            <span class="min-w-0 flex-1">
                                <b
                                    class="block truncate text-[14px] font-semibold"
                                    >{goal.name}</b
                                >
                                <span
                                    class="mt-1 block h-[4px] overflow-hidden rounded-full bg-secondary"
                                >
                                    <span
                                        class="block h-full rounded-full"
                                        style="width: {pct}%; background-color: var(--chart-3)"
                                    ></span>
                                </span>
                            </span>
                            <span
                                class="shrink-0 text-[11.5px] text-muted-foreground tabular-nums"
                            >
                                {formatAmount(goal.current)} / {formatAmount(
                                    goal.target,
                                )}
                            </span>
                        </button>
                    </li>
                {/each}
            </ul>
        {/if}
    {:else if dueCommitments.length === 0}
        <div class="flex flex-col items-center gap-2 py-6 text-center">
            <span
                class="grid size-10 place-items-center rounded-2xl bg-success/10"
                style="color: var(--success-text)"
            >
                <Check class="size-5" stroke-width="1.9" />
            </span>
            <p class="text-[13px] text-muted-foreground">
                كل التزاماتك مدفوعة هذا الشهر.
            </p>
        </div>
    {:else}
        <ul class="flex flex-col py-1">
            {#each dueCommitments as commitment (commitment.id)}
                {@const expected =
                    commitment.amount || commitment.average_amount}
                <li class="border-b border-border last:border-b-0">
                    <button
                        type="button"
                        disabled={submitting}
                        onclick={() => pay(commitment)}
                        class="flex min-h-11 w-full items-center gap-2.5 py-2.5 text-start transition-transform active:scale-[.99] disabled:opacity-45"
                    >
                        <CategoryIcon
                            icon={commitment.icon}
                            color={commitment.color}
                            size="lg"
                        />
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-[14px] font-semibold"
                                >{commitment.name}</b
                            >
                            <span
                                class="block text-[11.5px] text-muted-foreground"
                            >
                                {formatRelativeDays(commitment.due_date)}
                            </span>
                        </span>
                        <span
                            class="shrink-0 text-[14px] font-semibold tabular-nums"
                        >
                            {expected > 0
                                ? `${formatAmount(expected)} ر.س`
                                : 'حدّد المبلغ'}
                        </span>
                    </button>
                </li>
            {/each}
        </ul>
    {/if}
</SheetShell>
