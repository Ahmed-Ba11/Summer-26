<script lang="ts">
    /**
     * لوح إضافة/تعديل التزام — لوح سفلي على الجوال، مركزي على الديسكتوب.
     *
     * قرارات مقصودة:
     *   • النوع بضغطة واحدة (أربع بطاقات) — لا قائمة منسدلة.
     *   • المبلغ بخط ضخم أعلى اللوح ويُدخَل بلوحة أرقام، لأنه أكثر حقل
     *     يُلمَس في التطبيق كله.
     *   • القسط الشهري **يُحسب ولا يُكتب** — يمنع تناقض «36,000 على 36 شهر
     *     بقسط 1,200» الذي يفسد كل الأرقام بعده.
     *   • «تلقائي مقابل يدوي» سؤال إجباري: بدونه ينسى المستخدم الضغط على
     *     «تم الدفع» فتصير صورته المالية كاذبة.
     *   • كل تحقّق يظهر **قبل** الحفظ لا بعده، ومع اقتراح قابل للتنفيذ.
     */
    import Check from 'lucide-svelte/icons/check';
    import Hand from 'lucide-svelte/icons/hand';
    import Info from 'lucide-svelte/icons/info';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import X from 'lucide-svelte/icons/x';
    import Zap from 'lucide-svelte/icons/zap';
    import AmountInput from '@/components/AmountInput.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount, formatCurrency, toHalalas } from '@/lib/format';
    import {
        type CommitmentKind,
        type DueType,
        type PaymentMethod,
        finishLabel,
        KIND_COLOR,
        KIND_ICON,
        KIND_LABEL,
        KIND_ORDER,
        monthlyOf,
    } from '@/lib/commitments';

    let {
        open = $bindable(false),
        income = 0,
        currentObligations = 0,
        salaryDay = 27,
        processing = false,
        onSave,
    }: {
        open?: boolean;
        /** دخل شهر الراتب الحالي — بالهللات */
        income?: number;
        /** مجموع الالتزامات الشهرية القائمة — بالهللات */
        currentObligations?: number;
        salaryDay?: number;
        processing?: boolean;
        onSave?: (payload: Record<string, unknown>) => void;
    } = $props();

    let kind = $state<CommitmentKind>('bill');
    let name = $state('');
    let amount = $state(0); // بالهللات — للأقساط: المبلغ الكامل
    let months = $state(12);
    let isVariable = $state(false);
    let paymentMethod = $state<PaymentMethod>('manual');
    let dueType = $state<DueType>('month_day');
    let dueDay = $state(1);
    let dueDate = $state('');
    let notifyBefore = $state(true);
    let notifyOnDue = $state(true);
    let notifyLate = $state(true);
    let reserve = $state(true);

    const isInstallment = $derived(kind === 'installment');
    const monthly = $derived(isInstallment ? monthlyOf(amount, months) : amount);

    /** الالتزام بعد الإضافة كنسبة من الدخل. */
    const shareOfIncome = $derived(income > 0 ? (monthly / income) * 100 : 0);
    const totalAfter = $derived(currentObligations + monthly);

    // ── التحقّقات ─────────────────────────────────────────────────────
    // القاعدة الحاكمة: الحقائق تُسجَّل، والخطط تُراجَع.
    //   فاتورة/إيجار/اشتراك = واقعة وصلتك → لا تُمنع أبداً، تُحذَّر فقط.
    //   قسط = قرار إرادي → يُمنع إذا كان مستحيلاً حسابياً.

    const errors = $derived.by(() => {
        const e: string[] = [];
        if (!name.trim()) e.push('اكتب اسم الالتزام حتى تعرفه لاحقاً.');
        if (!isVariable && amount <= 0) e.push('حدّد المبلغ، أو فعّل «المبلغ متغيّر» إذا كان يختلف كل شهر.');
        if (isInstallment) {
            if (months < 2) e.push('عدد الأشهر لا يقل عن شهرين — أقل من ذلك ليس قسطاً.');
            if (months > 480) e.push('عدد الأشهر كبير جداً.');
            if (amount <= 0) e.push('أدخل المبلغ الكامل للقسط.');
        }
        if (dueType === 'month_day' && (dueDay < 1 || dueDay > 31)) e.push('يوم الاستحقاق بين 1 و 31.');
        if (dueType === 'fixed_date' && !dueDate) e.push('اختر تاريخ الاستحقاق.');
        return e;
    });

    /** المنع الحقيقي الوحيد: قسط جديد يجعل الالتزامات تفوق الدخل. */
    const blocked = $derived(isInstallment && income > 0 && totalAfter > income);

    const warning = $derived.by(() => {
        if (blocked) return null;
        if (income <= 0) return null;
        if (totalAfter > income * 0.7)
            return `التزاماتك بعد الإضافة ${Math.round((totalAfter / income) * 100)}٪ من دخلك — فوق 70٪ يترك مجالاً ضيقاً للمصاريف.`;
        if (isInstallment && shareOfIncome > 30)
            return `القسط وحده ${Math.round(shareOfIncome)}٪ من دخلك. تقدر تمدّه على أشهر أكثر لتخفيف الضغط.`;
        return null;
    });

    const canSave = $derived(!processing && errors.length === 0 && !blocked);

    function reset() {
        kind = 'bill';
        name = '';
        amount = 0;
        months = 12;
        isVariable = false;
        paymentMethod = 'manual';
        dueType = 'month_day';
        dueDay = 1;
        dueDate = '';
        reserve = true;
    }

    function submit() {
        if (!canSave) return;
        onSave?.({
            kind,
            name: name.trim(),
            amount: isInstallment ? monthly : isVariable ? null : amount,
            total_amount: isInstallment ? amount : 0,
            months_count: isInstallment ? months : 0,
            is_variable: isVariable,
            payment_method: paymentMethod,
            due_type: dueType,
            due_day: dueType === 'month_day' ? dueDay : null,
            due_date: dueType === 'fixed_date' ? dueDate : null,
            notify_before: notifyBefore,
            notify_on_due: notifyOnDue,
            notify_late: notifyLate,
            reserve_in_budget: reserve,
        });
        reset();
    }

    function close() {
        open = false;
    }

    function onKeydown(e: KeyboardEvent) {
        if (e.key === 'Escape') close();
    }
</script>

<svelte:window on:keydown={onKeydown} />

{#if open}
    <div class="fixed inset-0 z-50 flex items-end justify-center md:items-center">
        <button type="button" class="absolute inset-0 bg-black/45" aria-label="إغلاق" onclick={close}></button>

        <div
            role="dialog"
            aria-modal="true"
            aria-label="إضافة التزام"
            class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-card p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-lg md:max-w-md md:rounded-3xl md:pb-4"
        >
            <div class="mx-auto mb-3 h-1 w-9 rounded-full bg-border md:hidden"></div>

            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-[15px] font-semibold">إضافة التزام</h2>
                <button
                    type="button"
                    onclick={close}
                    aria-label="إغلاق"
                    class="inline-flex size-9 items-center justify-center rounded-xl border border-input text-muted-foreground"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- النوع -->
            <div class="grid grid-cols-4 gap-1.5">
                {#each KIND_ORDER as k (k)}
                    <button
                        type="button"
                        aria-pressed={kind === k}
                        onclick={() => (kind = k)}
                        class="flex min-h-[68px] flex-col items-center justify-center gap-1.5 rounded-2xl border transition-colors {kind ===
                        k
                            ? 'border-2 border-current'
                            : 'border-border'}"
                        style={kind === k ? `color:${KIND_COLOR[k]}` : ''}
                    >
                        <CategoryIcon icon={KIND_ICON[k]} color={KIND_COLOR[k]} size="sm" />
                        <span class="text-[11px] {kind === k ? 'font-semibold text-foreground' : 'text-muted-foreground'}">
                            {KIND_LABEL[k]}
                        </span>
                    </button>
                {/each}
            </div>

            <!-- المبلغ -->
            <div class="mt-4">
                <AmountInput bind:value={amount} disabled={isVariable} />
                <p class="mt-1 text-center text-[11px] text-muted-foreground">
                    {isInstallment ? 'المبلغ الكامل للقسط' : isVariable ? 'يُسجَّل عند الدفع كل شهر' : 'المبلغ الشهري'}
                </p>
            </div>

            <!-- الاسم -->
            <div class="mt-3">
                <label for="c-name" class="mb-1.5 block text-[11.5px] font-medium">الاسم</label>
                <input
                    id="c-name"
                    bind:value={name}
                    placeholder={kind === 'bill' ? 'كهرباء' : kind === 'rent' ? 'إيجار الشقة' : kind === 'installment' ? 'قسط السيارة' : 'اشتراك جوال'}
                    class="min-h-11 w-full rounded-xl border border-input bg-secondary px-3 text-[13px] outline-none focus:border-primary"
                />
            </div>

            <!-- خاص بالأقساط: عدد الأشهر → القسط يُحسب -->
            {#if isInstallment}
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div>
                        <label for="c-months" class="mb-1.5 block text-[11.5px] font-medium">عدد الأشهر</label>
                        <input
                            id="c-months"
                            type="number"
                            min="2"
                            max="480"
                            bind:value={months}
                            class="min-h-11 w-full rounded-xl border border-input bg-secondary px-3 text-[13px] tabular-nums outline-none focus:border-primary"
                        />
                    </div>
                    <div>
                        <span class="mb-1.5 block text-[11.5px] font-medium">القسط الشهري</span>
                        <output
                            class="flex min-h-11 w-full items-center rounded-xl border border-primary/25 bg-primary/8 px-3 text-[13px] font-semibold text-primary tabular-nums"
                        >
                            {formatAmount(monthly)}
                        </output>
                    </div>
                </div>

                {#if amount > 0 && months >= 2}
                    <p class="mt-2 rounded-xl border border-primary/20 bg-primary/6 px-3 py-2.5 text-[12px] text-foreground/85">
                        يُحسب تلقائياً · يخلص في <b class="font-semibold">{finishLabel(months)}</b>
                        {#if income > 0}
                            · <b class="font-semibold">{Math.round(shareOfIncome)}٪</b> من دخلك الشهري
                        {/if}
                    </p>
                {/if}
            {:else}
                <!-- المبلغ المتغيّر للفواتير فقط -->
                {#if kind === 'bill'}
                    <div class="mt-3 flex items-center gap-3 rounded-xl border border-border bg-secondary px-3 py-2.5">
                        <div class="flex-1">
                            <p class="text-[12.5px] font-medium">المبلغ متغيّر كل شهر</p>
                            <p class="text-[10.5px] text-muted-foreground">نحجز متوسّط آخر 3 أشهر حتى تسجّل المبلغ الفعلي</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={isVariable}
                            aria-label="المبلغ متغيّر"
                            onclick={() => (isVariable = !isVariable)}
                            class="relative h-6 w-11 shrink-0 rounded-full transition-colors {isVariable ? 'bg-success' : 'bg-border'}"
                        >
                            <span
                                class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-[inset-inline-start] {isVariable
                                    ? 'start-[22px]'
                                    : 'start-0.5'}"
                            ></span>
                        </button>
                    </div>
                {/if}
            {/if}

            <!-- طريقة الدفع -->
            <div class="mt-3">
                <span class="mb-1.5 block text-[11.5px] font-medium">طريقة الدفع</span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        aria-pressed={paymentMethod === 'auto'}
                        onclick={() => (paymentMethod = 'auto')}
                        class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-full border px-3 text-[12.5px] {paymentMethod ===
                        'auto'
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        <Zap class="size-3.5" /> خصم تلقائي
                    </button>
                    <button
                        type="button"
                        aria-pressed={paymentMethod === 'manual'}
                        onclick={() => (paymentMethod = 'manual')}
                        class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-full border px-3 text-[12.5px] {paymentMethod ===
                        'manual'
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        <Hand class="size-3.5" /> أدفعه بنفسي
                    </button>
                </div>
                <p class="mt-1.5 text-[10.5px] leading-relaxed text-muted-foreground">
                    التلقائي يُسجَّل مدفوعاً وحده يوم الاستحقاق مع زر «ما انخصم». اليدوي يبقى محجوزاً ويذكّرك.
                </p>
            </div>

            <!-- موعد الاستحقاق -->
            <div class="mt-3">
                <span class="mb-1.5 block text-[11.5px] font-medium">موعد الاستحقاق</span>
                <div class="flex flex-wrap gap-1.5">
                    {#each [{ v: 'salary_day', l: 'مع نزول الراتب' }, { v: 'month_day', l: 'يوم من الشهر' }, { v: 'fixed_date', l: 'تاريخ واحد' }] as o (o.v)}
                        <button
                            type="button"
                            aria-pressed={dueType === o.v}
                            onclick={() => (dueType = o.v as DueType)}
                            class="inline-flex min-h-11 items-center rounded-full border px-3.5 text-[12px] {dueType === o.v
                                ? 'border-primary bg-primary/8 font-semibold text-primary'
                                : 'border-input text-foreground/85'}"
                        >
                            {o.l}
                        </button>
                    {/each}
                </div>

                {#if dueType === 'salary_day'}
                    <p class="mt-2 inline-flex items-start gap-2 rounded-xl border border-primary/20 bg-primary/6 px-3 py-2.5 text-[11px] text-foreground/85">
                        <Info class="mt-px size-3.5 shrink-0 text-primary" />
                        راتبك يوم <b class="font-semibold">{salaryDay}</b> — يتحرّك الاستحقاق معه لو غيّرته. ولا يُسجَّل
                        الخصم تلقائياً إلا بعد تسجيل دخل ذلك الشهر فعلاً.
                    </p>
                {:else if dueType === 'month_day'}
                    <input
                        type="number"
                        min="1"
                        max="31"
                        bind:value={dueDay}
                        aria-label="يوم الاستحقاق من الشهر"
                        class="mt-2 min-h-11 w-24 rounded-xl border border-input bg-secondary px-3 text-[13px] tabular-nums outline-none focus:border-primary"
                    />
                {:else}
                    <input
                        type="date"
                        bind:value={dueDate}
                        aria-label="تاريخ الاستحقاق"
                        class="mt-2 min-h-11 w-full rounded-xl border border-input bg-secondary px-3 text-[13px] outline-none focus:border-primary"
                    />
                {/if}
            </div>

            <!-- التنبيهات -->
            <div class="mt-3">
                <span class="mb-1.5 block text-[11.5px] font-medium">نبّهني</span>
                <div class="flex flex-wrap gap-1.5">
                    {#each [{ get: () => notifyBefore, set: (v: boolean) => (notifyBefore = v), l: 'قبل 3 أيام' }, { get: () => notifyOnDue, set: (v: boolean) => (notifyOnDue = v), l: 'يوم الاستحقاق' }, { get: () => notifyLate, set: (v: boolean) => (notifyLate = v), l: 'لو تأخّر' }] as o (o.l)}
                        <button
                            type="button"
                            aria-pressed={o.get()}
                            onclick={() => o.set(!o.get())}
                            class="inline-flex min-h-11 items-center rounded-full border px-3.5 text-[12px] {o.get()
                                ? 'border-primary bg-primary/8 font-semibold text-primary'
                                : 'border-input text-foreground/85'}"
                        >
                            {o.l}
                        </button>
                    {/each}
                </div>
            </div>

            <!-- الحجز من الميزانية -->
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-border bg-secondary px-3 py-2.5">
                <div class="flex-1">
                    <p class="text-[12.5px] font-medium">احجزه من ميزانيتي</p>
                    <p class="text-[10.5px] text-muted-foreground">ينقص من «المتبقي لك» حتى تدفعه</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={reserve}
                    aria-label="احجزه من ميزانيتي"
                    onclick={() => (reserve = !reserve)}
                    class="relative h-6 w-11 shrink-0 rounded-full transition-colors {reserve ? 'bg-success' : 'bg-border'}"
                >
                    <span
                        class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-[inset-inline-start] {reserve
                            ? 'start-[22px]'
                            : 'start-0.5'}"
                    ></span>
                </button>
            </div>

            <!-- المنع والتحذير -->
            {#if blocked}
                <p class="mt-3 flex items-start gap-2 rounded-xl border border-destructive/35 bg-destructive/8 px-3 py-2.5 text-[12px] font-medium text-destructive">
                    <TriangleAlert class="mt-px size-4 shrink-0" />
                    <span>
                        هذا القسط يرفع التزاماتك إلى {formatCurrency(totalAfter)} وهي أكبر من دخلك
                        ({formatCurrency(income)}). القسط قرار إرادي — مدّه على أشهر أكثر، أو أنهِ التزاماً قائماً أولاً.
                    </span>
                </p>
            {:else if warning}
                <p class="mt-3 flex items-start gap-2 rounded-xl border border-warning/40 bg-warning/10 px-3 py-2.5 text-[12px] text-warning-text">
                    <TriangleAlert class="mt-px size-4 shrink-0" />
                    <span>{warning}</span>
                </p>
            {/if}

            {#if errors.length}
                <ul class="mt-3 space-y-1">
                    {#each errors as e (e)}
                        <li class="text-[11.5px] text-destructive">{e}</li>
                    {/each}
                </ul>
            {/if}

            <button
                type="button"
                disabled={!canSave}
                onclick={submit}
                class="mt-4 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
            >
                <Check class="size-[18px] " />
                حفظ {KIND_LABEL[kind]}
            </button>
        </div>
    </div>
{/if}
