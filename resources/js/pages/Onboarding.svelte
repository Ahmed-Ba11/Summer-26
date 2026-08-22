<script module lang="ts">
    export const layout = null;
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Check from 'lucide-svelte/icons/check';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Plus from 'lucide-svelte/icons/plus';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import AppHead from '@/components/AppHead.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { formatCurrency, toHalalas } from '@/lib/format';
    import type {
        OnboardingCategory,
        ValidationErrors,
    } from '@/types';

    interface IncomeDraft {
        source: string;
        amount: string;
        is_recurring: boolean;
    }

    interface CommitmentOption {
        id: string;
        label: string;
        icon: string;
    }

    interface CommitmentDraft extends CommitmentOption {
        amount: string;
    }

    interface OnboardingProps {
        categories?: OnboardingCategory[];
        salaryDay?: number | null;
    }

    let { categories = [], salaryDay = null }: OnboardingProps = $props();

    const commitmentOptions: CommitmentOption[] = [
        { id: 'utilities', label: 'كهرباء وماء', icon: 'zap' },
        { id: 'rent', label: 'إيجار', icon: 'house' },
        { id: 'internet', label: 'إنترنت وجوال', icon: 'wifi' },
        { id: 'insurance', label: 'تأمين', icon: 'heart-pulse' },
        { id: 'car-installment', label: 'قسط سيارة', icon: 'car' },
        { id: 'device-installment', label: 'قسط جهاز', icon: 'laptop' },
        { id: 'subscriptions', label: 'اشتراكات', icon: 'repeat' },
    ];

    let step = $state(1);
    let incomes = $state<IncomeDraft[]>([
        { source: '', amount: '', is_recurring: false },
    ]);
    let commitments = $state<CommitmentDraft[]>([]);
    let customCommitmentCount = $state(0);
    let allocations = $state<Record<number, string>>({});
    let salaryDayValue = $state('');
    let errors = $state<ValidationErrors>({});
    let submitting = $state(false);

    const serverErrors = $derived(
        (page.props.errors ?? {}) as ValidationErrors,
    );

    $effect(() => {
        salaryDayValue = salaryDay ? String(salaryDay) : '';
    });

    const incomeHalalas = $derived(
        incomes.reduce((sum, item) => sum + toHalalas(item.amount), 0),
    );
    const commitmentHalalas = $derived(
        commitments.reduce((sum, item) => sum + toHalalas(item.amount), 0),
    );
    const availableHalalas = $derived(incomeHalalas - commitmentHalalas);
    const allocationHalalas = $derived(
        Object.values(allocations).reduce(
            (sum, amount) => sum + toHalalas(amount),
            0,
        ),
    );
    const allocationDifference = $derived(
        allocationHalalas - Math.max(0, availableHalalas),
    );
    const hasAllocationOverflow = $derived(allocationDifference > 0);
    const canFinish = $derived(
        categories.length > 0 && !hasAllocationOverflow,
    );

    function errorText(key: string): string {
        const value = errors[key] ?? serverErrors[key];

        return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
    }

    function generalErrorText(): string {
        for (const key of ['error', 'message', 'general', '_']) {
            const message = errorText(key);

            if (message) {
                return message;
            }
        }

        return '';
    }

    function todayDate(): string {
        return new Date().toISOString().slice(0, 10);
    }

    function clearErrors(): void {
        errors = {};
    }

    function addIncome(): void {
        incomes = [
            ...incomes,
            { source: '', amount: '', is_recurring: false },
        ];
    }

    function removeIncome(index: number): void {
        if (incomes.length === 1) {
            incomes[0] = { source: '', amount: '', is_recurring: false };

            return;
        }

        incomes = incomes.filter((_, itemIndex) => itemIndex !== index);
    }

    function toggleCommitment(option: CommitmentOption): void {
        const existing = commitments.find((item) => item.id === option.id);

        if (existing) {
            commitments = commitments.filter((item) => item.id !== option.id);

            return;
        }

        commitments = [...commitments, { ...option, amount: '' }];
    }

    function addCustomCommitment(): void {
        customCommitmentCount += 1;
        const custom = {
            id: `custom-${customCommitmentCount}`,
            label: 'التزام آخر',
            icon: 'ellipsis',
        };
        commitments = [...commitments, { ...custom, amount: '' }];
    }

    function updateCommitmentAmount(id: string, amount: string): void {
        commitments = commitments.map((item) =>
            item.id === id ? { ...item, amount } : item,
        );
    }

    function updateAllocation(id: number, amount: string): void {
        allocations = { ...allocations, [id]: amount };
    }

    function suggestAllocation(): void {
        if (availableHalalas <= 0 || categories.length === 0) {
            return;
        }

        const ratios: Record<string, number> = {
            طعام: 0.3,
            مواصلات: 0.15,
            ترفيه: 0.1,
            صحة: 0.1,
            ادخار: 0.1,
            أخرى: 0.1,
        };
        let assigned = 0;
        const next: Record<number, string> = {};

        categories.forEach((category, index) => {
            const ratio = ratios[category.name] ?? 0;
            const amount =
                index === categories.length - 1
                    ? Math.max(0, availableHalalas - assigned)
                    : Math.round(availableHalalas * ratio);
            assigned += amount;
            next[category.id] = (amount / 100).toFixed(2);
        });

        allocations = next;
    }

    function validateStep(): boolean {
        clearErrors();

        if (step === 1) {
            const invalidIncomeIndex = incomes.findIndex(
                (item) =>
                    !item.source.trim() || toHalalas(item.amount) <= 0,
            );

            if (invalidIncomeIndex >= 0) {
                const invalidIncome = incomes[invalidIncomeIndex];
                errors = {
                    income: 'أكمل المصدر والمبلغ لكل دخل قبل المتابعة.',
                    ...(invalidIncome.source.trim()
                        ? {}
                        : {
                              [`incomes.${invalidIncomeIndex}.source`]:
                                  'مصدر الدخل مطلوب.',
                          }),
                    ...(toHalalas(invalidIncome.amount) > 0
                        ? {}
                        : {
                              [`incomes.${invalidIncomeIndex}.amount`]:
                                  'المبلغ مطلوب ويجب أن يكون أكبر من صفر.',
                          }),
                };

                return false;
            }

            if (incomeHalalas <= 0) {
                errors = { income: 'أدخل دخلاً شهرياً واحداً على الأقل.' };

                return false;
            }
        }

        if (step === 2) {
            const invalidCommitmentIndex = commitments.findIndex(
                (item) => !item.amount || toHalalas(item.amount) <= 0,
            );

            if (invalidCommitmentIndex >= 0) {
                errors = {
                    commitments: 'أدخل مبلغاً صحيحاً لكل التزام اخترته.',
                    [`commitments.${invalidCommitmentIndex}.amount`]:
                        'المبلغ مطلوب ويجب أن يكون أكبر من صفر.',
                };

                return false;
            }

            if (!salaryDayValue) {
                errors = { salary_day: 'اختر يوم استحقاق راتبك.' };

                return false;
            }
        }

        if (step === 3) {
            if (categories.length === 0) {
                errors = { budget: 'لا توجد فئات ميزانية متاحة بعد.' };

                return false;
            }

            if (hasAllocationOverflow) {
                errors = { budget: 'خفّض التوزيع حتى لا يتجاوز المبلغ المتاح.' };

                return false;
            }
        }

        return true;
    }

    function submitStep(): void {
        if (!validateStep()) {
            return;
        }

        submitting = true;
        const endpoint = ['/onboarding/income', '/onboarding/commitments', '/onboarding/budget'][
            step - 1
        ];
        const payload =
            step === 1
                ? {
                      incomes: incomes.map((item) => ({
                          source: item.source.trim(),
                          amount: Number(item.amount),
                          income_date: todayDate(),
                          is_recurring: item.is_recurring,
                      })),
                  }
                : step === 2
                  ? {
                        salary_day: Number(salaryDayValue),
                        commitments: commitments.map((item) => {
                            const isInstallment = [
                                'car-installment',
                                'device-installment',
                            ].includes(item.id);

                            return {
                                type: isInstallment ? 'installment' : 'bill',
                                name: item.label,
                                icon: item.icon,
                                amount: Number(item.amount),
                                ...(isInstallment
                                    ? {
                                          monthly_amount: Number(item.amount),
                                          total_amount: Number(item.amount),
                                          total_months: 1,
                                          start_date: todayDate(),
                                      }
                                    : {}),
                            };
                        }),
                    }
                  : {
                        budgets: categories
                            .filter(
                                (category) =>
                                    (allocations[category.id] ?? '').trim() !== '',
                            )
                            .map((category) => ({
                                category_id: category.id,
                                amount: Number(allocations[category.id]),
                            })),
                    };

        router.post(endpoint, payload, {
            preserveScroll: true,
            onSuccess: () => {
                clearErrors();
                if (step < 3) {
                    step += 1;
                }
            },
            onError: (serverErrors) => {
                errors = serverErrors as ValidationErrors;
            },
            onFinish: () => {
                submitting = false;
            },
        });
    }

    function goBack(): void {
        clearErrors();
        step = Math.max(1, step - 1);
    }
</script>

<AppHead title="إعداد ميزانيتك" />
<MobileHeader title="إعداد ميزانيتك" subtitle="ثلاث خطوات بسيطة لترتيب ميزانيتك" showAssistant={false} />

<main class="min-h-screen bg-background px-4 py-8 sm:px-6 sm:py-12">
    <section class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div class="hidden text-center md:block">
            <div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-primary text-primary-foreground">
                <Sparkles class="size-6" />
            </div>
            <h1 class="text-2xl font-semibold tracking-tight">خلّنا نرتّب فلوسك</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                ثلاث خطوات بسيطة، وبعدها تشوف صورتك المالية بوضوح.
            </p>
        </div>

        <div class="rounded-2xl border border-border bg-card p-4 shadow-xs sm:p-6">
            <div class="mb-8 grid grid-cols-3 gap-2" aria-label="تقدم الإعداد">
                {#each ['دخلك', 'التزاماتك', 'ميزانيتك'] as label, index}
                    {@const itemStep = index + 1}
                    <div class="flex flex-col items-center gap-2 text-center text-xs">
                        <div class="flex w-full items-center gap-2">
                            {#if index > 0}
                                <span class="h-px flex-1 {itemStep <= step ? 'bg-success' : 'bg-border'}"></span>
                            {/if}
                            <span
                                class="grid size-8 shrink-0 place-items-center rounded-full border text-xs font-semibold {itemStep < step
                                    ? 'border-success bg-success text-white'
                                    : itemStep === step
                                      ? 'border-primary bg-primary text-primary-foreground'
                                      : 'border-input text-muted-foreground'}"
                            >
                                {#if itemStep < step}<Check class="size-4" />{:else}{itemStep}{/if}
                            </span>
                            {#if index < 2}
                                <span class="h-px flex-1 {itemStep < step ? 'bg-success' : 'bg-border'}"></span>
                            {/if}
                        </div>
                        <span class={itemStep === step ? 'font-semibold text-foreground' : 'text-muted-foreground'}>{label}</span>
                    </div>
                {/each}
            </div>

            {#if step === 1}
                <div class="flex flex-col gap-5">
                    <div>
                        <p class="text-lg font-semibold">كم دخلك الشهري؟</p>
                        <p class="mt-1 text-sm text-muted-foreground">أضف كل مصادر دخلك المعتادة، بدون تقديرات غير مؤكدة.</p>
                    </div>
                    <div class="flex flex-col gap-3">
                        {#each incomes as income, index}
                            <div class="grid gap-3 rounded-xl border border-border bg-secondary p-3 sm:grid-cols-[1fr_9rem_auto] sm:items-end">
                                <label class="flex flex-col gap-1.5 text-sm">
                                    <span>المصدر</span>
                                    <input bind:value={income.source} placeholder="راتب، عمل حر..." class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" />
                                    {#if errorText(`incomes.${index}.source`)}
                                        <span class="text-xs text-destructive">{errorText(`incomes.${index}.source`)}</span>
                                    {/if}
                                </label>
                                <label class="flex flex-col gap-1.5 text-sm">
                                    <span>المبلغ الشهري (ر.س)</span>
                                    <input bind:value={income.amount} type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="0.00" class="rounded-lg border border-input bg-card px-3 py-2 text-end text-sm tabular-nums outline-none focus:ring-2 focus:ring-ring" />
                                    {#if errorText(`incomes.${index}.amount`)}
                                        <span class="text-xs text-destructive">{errorText(`incomes.${index}.amount`)}</span>
                                    {/if}
                                </label>
                                <div class="flex items-center gap-3 sm:flex-col sm:items-stretch">
                                    <label class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <input type="checkbox" bind:checked={income.is_recurring} class="size-4 accent-primary" />
                                        ثابت شهرياً
                                    </label>
                                    {#if incomes.length > 1}
                                        <button type="button" class="text-start text-xs text-destructive hover:underline" onclick={() => removeIncome(index)}>إزالة</button>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>
                    <button type="button" class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-primary hover:underline" onclick={addIncome}>
                        <Plus class="size-4" /> إضافة مصدر دخل آخر
                    </button>
                    <div class="rounded-xl bg-accent px-4 py-3 text-sm">
                        إجمالي دخلك الشهري: <strong class="tabular-nums">{formatCurrency(incomeHalalas)}</strong>
                    </div>
                </div>
            {:else if step === 2}
                <div class="flex flex-col gap-5">
                    <div>
                        <p class="text-lg font-semibold">وش التزاماتك الثابتة كل شهر؟</p>
                        <p class="mt-1 text-sm text-muted-foreground">اختر ما ينطبق عليك وأدخل المبلغ الشهري.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {#each commitmentOptions as option}
                            {@const selected = commitments.some((item) => item.id === option.id)}
                            <button type="button" class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm transition-colors {selected ? 'border-primary bg-accent text-primary' : 'border-input hover:bg-secondary'}" aria-pressed={selected} onclick={() => toggleCommitment(option)}>
                                <CategoryIcon icon={option.icon} color="var(--chart-7)" size="xs" />
                                {option.label}
                            </button>
                        {/each}
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-dashed border-input px-3 py-2 text-sm hover:bg-secondary" onclick={addCustomCommitment}>
                            <Plus class="size-3.5" /> التزام آخر
                        </button>
                    </div>
                    {#if commitments.length > 0}
                        <div class="flex flex-col gap-3">
                            {#each commitments as commitment, index}
                                <label class="flex items-center gap-3 rounded-xl border border-border p-3">
                                    <CategoryIcon icon={commitment.icon} color="var(--chart-7)" size="sm" />
                                    <span class="min-w-0 flex-1 text-sm">{commitment.label}</span>
                                    <input value={commitment.amount} oninput={(event) => updateCommitmentAmount(commitment.id, event.currentTarget.value)} type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="0.00" class="w-28 rounded-lg border border-input bg-background px-2 py-1.5 text-end text-sm tabular-nums outline-none focus:ring-2 focus:ring-ring" />
                                    <span class="text-xs text-muted-foreground">ر.س</span>
                                    {#if errorText(`commitments.${index}.amount`)}
                                        <span class="text-xs text-destructive">{errorText(`commitments.${index}.amount`)}</span>
                                    {/if}
                                </label>
                            {/each}
                        </div>
                    {:else}
                        <EmptyState title="لا توجد التزامات مختارة" description="اختر التزاماتك من الرقائق أعلاه، أو تابع بدون التزامات." icon={CircleAlert} />
                    {/if}
                    <label class="flex flex-col gap-1.5 text-sm">
                        <span>يوم استحقاق راتبك</span>
                        <select bind:value={salaryDayValue} class="rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring">
                            <option value="">اختر اليوم</option>
                            <option value="25">25</option>
                            <option value="27">27</option>
                            <option value="31">31 / آخر يوم في الشهر</option>
                        </select>
                        {#if errorText('salary_day')}
                            <span class="text-xs text-destructive">{errorText('salary_day')}</span>
                        {/if}
                    </label>
                    <div class="rounded-xl border border-primary/20 bg-accent px-4 py-3 text-sm text-primary">
                        هذه الخطوة تجعل رقم المتبقي لك في لوحة التحكم واقعياً.
                    </div>
                </div>
            {:else}
                <div class="flex flex-col gap-5">
                    <div>
                        <p class="text-lg font-semibold">وزّع الباقي على فئاتك</p>
                        <p class="mt-1 text-sm text-muted-foreground">ضع حدوداً شهرية من المبلغ المتاح بعد الالتزامات.</p>
                    </div>
                    <div class="rounded-xl bg-secondary p-4">
                        <p class="text-sm text-muted-foreground">الباقي للتوزيع</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{formatCurrency(Math.max(0, availableHalalas - allocationHalalas))}</p>
                    </div>
                    {#if categories.length === 0}
                        <EmptyState title="لا توجد فئات ميزانية" description="يجب أن يرسل السيرفر فئات حسابك قبل توزيع الميزانية." icon={CircleAlert} />
                    {:else}
                        <div class="flex flex-col gap-4">
                            {#each categories as category}
                                <label class="flex items-center gap-3">
                                    <CategoryIcon icon={category.icon} color={category.color} size="sm" />
                                    <span class="min-w-0 flex-1 truncate text-sm">{category.name}</span>
                                    <input value={allocations[category.id] ?? ''} oninput={(event) => updateAllocation(category.id, event.currentTarget.value)} type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="w-28 rounded-lg border border-input bg-background px-2 py-1.5 text-end text-sm tabular-nums outline-none focus:ring-2 focus:ring-ring" />
                                    <span class="text-xs text-muted-foreground">ر.س</span>
                                </label>
                            {/each}
                        </div>
                        <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline" onclick={suggestAllocation}>
                            <Sparkles class="size-4" /> اقترح توزيعاً تلقائياً
                        </button>
                        <div class="flex h-3 overflow-hidden rounded-full bg-secondary" aria-label="توزيع الميزانية">
                            {#each categories as category}
                                {@const width = availableHalalas > 0 ? Math.min(100, (toHalalas(allocations[category.id] ?? '') / availableHalalas) * 100) : 0}
                                <span class="h-full border-e-2 border-card" style="width: {width}%; background-color: {category.color}" title={`${category.name}: ${formatCurrency(toHalalas(allocations[category.id] ?? ''))}`}></span>
                            {/each}
                        </div>
                    {/if}
                </div>
            {/if}

            {#if generalErrorText() || errorText('income') || errorText('commitments') || errorText('salary_day') || errorText('budget')}
                <p class="mt-5 flex items-center gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                    <CircleAlert class="size-4 shrink-0" />
                    {generalErrorText() || errorText('income') || errorText('commitments') || errorText('salary_day') || errorText('budget')}
                </p>
            {/if}
            {#if hasAllocationOverflow}
                <p class="mt-3 flex items-center gap-2 text-sm text-destructive" role="alert">
                    <CircleAlert class="size-4" /> تجاوزت المتاح بـ {formatCurrency(allocationDifference)}
                </p>
            {/if}

            <div class="mt-8 flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="text-sm text-muted-foreground hover:text-foreground hover:underline" onclick={() => router.visit('/dashboard')}>تخطّي الآن، أكمل من اللوحة لاحقاً</button>
                <div class="flex items-center gap-2 sm:ms-auto">
                    {#if step > 1}
                        <Button type="button" variant="outline" onclick={goBack} disabled={submitting}>رجوع</Button>
                    {/if}
                    <Button type="button" onclick={submitStep} disabled={submitting || (step === 3 && !canFinish)}>
                        {submitting ? 'جاري الحفظ...' : step === 3 ? 'إنهاء الإعداد' : 'التالي'}
                        {#if !submitting && step < 3}<ArrowRight class="size-4" />{/if}
                    </Button>
                </div>
            </div>
        </div>
    </section>
</main>
