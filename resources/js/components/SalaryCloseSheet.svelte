<script lang="ts">
    /**
     * إقفال شهر الراتب.
     *
     * ══════════════════════════════════════════════════════════════════════
     *  الفائض لا يُرحَّل صامتاً.
     * ══════════════════════════════════════════════════════════════════════
     *
     * الترحيل الصامت يضخّم «المتبقي لك» في الراتب الجديد، فيتحوّل الفائض
     * إلى صرف بدل ادخار — المستخدم يشوف رقماً أكبر ولا يعرف أنه فائض
     * الشهر الماضي. لذلك يُسأل صراحةً، ووجهة الفائض تُكتب في `salary_periods`.
     *
     * المبالغ تصل بالهللات وتُحوَّل للعرض في `@/lib/format` فقط.
     */
    import { router } from '@inertiajs/svelte';
    import ArrowLeftRight from 'lucide-svelte/icons/arrow-left-right';
    import FileText from 'lucide-svelte/icons/file-text';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';
    import Split from 'lucide-svelte/icons/split';
    import TrendingDown from 'lucide-svelte/icons/trending-down';

    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import { formatAmount } from '@/lib/format';

    interface Goal {
        id: number;
        name: string;
        icon: string;
        remaining: number;
    }

    interface SalaryClose {
        key: string;
        label: string;
        range: string;
        income: number;
        expenses: number;
        commitments: number;
        savings: number;
        surplus: number;
        nextLabel: string;
        goals: Goal[];
    }

    let { data }: { data: SalaryClose | null } = $props();

    let open = $state(true);
    let action = $state<'saved' | 'rolled' | 'split'>('saved');
    let goalId = $state<number | null>(null);
    let saving = $state(false);

    const surplus = $derived(data?.surplus ?? 0);
    const isDeficit = $derived(surplus < 0);
    const goals = $derived(data?.goals ?? []);

    // الهدف الأقرب للاكتمال هو المقترح — أقصر مسافة لإنجاز يشوفه المستخدم.
    $effect(() => {
        if (goalId === null && goals.length) goalId = goals[0].id;
    });

    /** الادخار غير ممكن بلا هدف مفتوح — عندها يبقى الترحيل هو الخيار. */
    const canSave = $derived(goals.length > 0);

    $effect(() => {
        if (!canSave && (action === 'saved' || action === 'split')) action = 'rolled';
    });

    const options = $derived(
        [
            {
                value: 'saved' as const,
                icon: PiggyBank,
                title: 'حوّلها لهدف ادخار',
                hint: 'المقترح — الفائض يصير إنجازاً بدل ما يذوب في مصاريف الشهر الجديد.',
                disabled: !canSave,
            },
            {
                value: 'rolled' as const,
                icon: ArrowLeftRight,
                title: `ضمّها ل${data?.nextLabel ?? 'الراتب الجديد'}`,
                hint: 'تُضاف كدخل معلوم في الراتب الجديد، وتظهر باسمها في معاملاتك.',
                disabled: false,
            },
            {
                value: 'split' as const,
                icon: Split,
                title: 'قسّمها',
                hint: 'نصفها ادخار والنصف الآخر يُضاف للراتب الجديد.',
                disabled: !canSave,
            },
        ],
    );

    function submit() {
        if (!data || saving) return;
        saving = true;

        router.post(
            '/salary-month/close',
            {
                period_key: data.key,
                action,
                savings_goal_id: action === 'rolled' ? null : goalId,
            },
            {
                preserveScroll: true,
                onSuccess: () => (open = false),
                onFinish: () => (saving = false),
            },
        );
    }
</script>

{#if data}
    <SheetShell bind:open title="إقفال {data.label}" subtitle={data.range}>
        <!-- الرقم البطل: الفائض أو التجاوز -->
        <div
            class="flex flex-col items-center gap-1.5 rounded-2xl border border-border px-4 py-4 text-center {isDeficit
                ? 'bg-destructive/5'
                : 'bg-success/5'}"
        >
            <div
                class="grid size-10 place-items-center rounded-2xl {isDeficit
                    ? 'bg-destructive/10 text-destructive'
                    : 'bg-success/10 text-success-text'}"
            >
                {#if isDeficit}
                    <TrendingDown class="size-[20px]" style="stroke-width: 1.9" />
                {:else}
                    <PiggyBank class="size-[20px]" style="stroke-width: 1.9" />
                {/if}
            </div>

            {#if isDeficit}
                <p class="text-[13px] text-muted-foreground">تجاوزت في {data.label} بـ</p>
                <p class="text-[26px] font-semibold tabular-nums text-destructive">
                    {formatAmount(-surplus)} <span class="text-[14px] font-normal">ر.س</span>
                </p>
            {:else}
                <p class="text-[13px] text-muted-foreground">فاض من {data.label}</p>
                <p class="text-[26px] font-semibold tabular-nums text-success-text">
                    {formatAmount(surplus)} <span class="text-[14px] font-normal">ر.س</span>
                </p>
            {/if}
        </div>

        <!-- تفصيل الفترة -->
        <dl class="mt-3 grid grid-cols-2 gap-2">
            {#each [{ label: 'الدخل', value: data.income }, { label: 'المصاريف', value: data.expenses }, { label: 'الالتزامات المدفوعة', value: data.commitments }, { label: 'المدّخر', value: data.savings }] as row (row.label)}
                <div class="rounded-2xl border border-border bg-card px-3 py-2.5">
                    <dt class="text-[11.5px] text-muted-foreground">{row.label}</dt>
                    <dd class="mt-0.5 text-[14px] font-semibold tabular-nums">
                        {formatAmount(row.value)} <span class="text-[11.5px] font-normal">ر.س</span>
                    </dd>
                </div>
            {/each}
        </dl>

        {#if !isDeficit && surplus > 0}
            <p class="mt-4 mb-2 text-[14px] font-semibold">وش نسوّي بالفائض؟</p>

            <div class="flex flex-col gap-2">
                {#each options as opt (opt.value)}
                    {@const OptIcon = opt.icon}
                    <button
                        type="button"
                        disabled={opt.disabled}
                        onclick={() => (action = opt.value)}
                        class="flex min-h-11 items-start gap-3 rounded-2xl border px-3 py-3 text-start transition-transform active:scale-[.98] disabled:opacity-45 {action ===
                        opt.value
                            ? 'border-primary bg-accent'
                            : 'border-border bg-card'}"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl {action === opt.value
                                ? 'bg-primary/10 text-primary'
                                : 'bg-secondary text-muted-foreground'}"
                        >
                            <OptIcon class="size-[19px]" style="stroke-width: 1.9" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13.5px] font-semibold">{opt.title}</span>
                            <span class="mt-0.5 block text-[11.5px] text-muted-foreground">
                                {opt.disabled ? 'يحتاج هدف ادخار مفتوح.' : opt.hint}
                            </span>
                        </span>
                        <span
                            class="mt-0.5 grid size-[18px] shrink-0 place-items-center rounded-full border {action ===
                            opt.value
                                ? 'border-primary'
                                : 'border-input'}"
                        >
                            {#if action === opt.value}
                                <span class="size-2.5 rounded-full bg-primary"></span>
                            {/if}
                        </span>
                    </button>
                {/each}
            </div>

            {#if action !== 'rolled' && canSave}
                <p class="mt-4 mb-2 text-[14px] font-semibold">لأي هدف؟</p>
                <div class="flex flex-col gap-2">
                    {#each goals as goal (goal.id)}
                        <button
                            type="button"
                            onclick={() => (goalId = goal.id)}
                            class="flex min-h-11 items-center gap-3 rounded-2xl border px-3 py-2.5 text-start transition-transform active:scale-[.98] {goalId ===
                            goal.id
                                ? 'border-primary bg-accent'
                                : 'border-border bg-card'}"
                        >
                            <CategoryIcon icon={goal.icon} color="var(--chart-3)" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13.5px]">{goal.name}</span>
                                <span class="block text-[11.5px] text-muted-foreground tabular-nums">
                                    باقي {formatAmount(goal.remaining)} ر.س
                                </span>
                            </span>
                        </button>
                    {/each}
                </div>
            {/if}
        {:else if isDeficit}
            <p class="mt-4 text-[13px] text-muted-foreground">
                ما فيه فائض يُوجَّه. نقفل {data.label} ونبدأ {data.nextLabel} بأرقام نظيفة.
            </p>
        {/if}

        <!-- تقرير الفترة — يُفعَّل في مرحلة الـPDF -->
        <div
            class="mt-4 flex min-h-11 items-center gap-2.5 rounded-2xl border border-dashed border-border px-3 py-2.5 text-muted-foreground"
        >
            <FileText class="size-[18px] shrink-0" style="stroke-width: 1.9" />
            <span class="min-w-0 flex-1 text-[12.5px]">تقرير {data.label} (PDF)</span>
            <span class="shrink-0 text-[11.5px]">قريباً</span>
        </div>

        {#snippet footer()}
            <button
                type="button"
                disabled={saving}
                onclick={submit}
                class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl bg-primary px-4 text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.98] disabled:opacity-45"
            >
                {saving ? 'جارٍ الإقفال…' : `ابدأ ${data.nextLabel}`}
            </button>
        {/snippet}
    </SheetShell>
{/if}
