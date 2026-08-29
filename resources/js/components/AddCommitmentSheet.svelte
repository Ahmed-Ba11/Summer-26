<script lang="ts">
    /**
     * لوح إضافة التزام — إعادة بناء بعد ملاحظات الاختبار.
     *
     * ما تغيّر ولماذا:
     *   • **زر الحفظ مثبّت أسفل اللوح** ولا يتحرّك مع التمرير — كان في نهاية
     *     نموذج طويل فلا يراه المستخدم أصلاً.
     *   • **رسائل الخطأ تحت الحقل المعني**، ولا تظهر إلا بعد لمس الحقل أو بعد
     *     محاولة الحفظ — لا لوحة حمراء أسفل الصفحة قبل أن يكتب المستخدم شيئاً.
     *   • **القسط الشهري يكتبه المستخدم** — يُقترح عليه ناتج القسمة كنقطة بداية
     *     فقط، لأن الأقساط الواقعية فيها رسوم وفوائد ودفعة أولى، فلا تساوي
     *     المبلغ ÷ الأشهر بالضرورة.
     *   • **الاستحقاق خياران فقط**: مع الراتب · يوم من الشهر — واختيار اليوم
     *     يعرض فوراً أنه سيظهر في التقويم المالي.
     *   • **التنبيه خيار واحد** لا ثلاثة معاً.
     */
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import Check from 'lucide-svelte/icons/check';
    import Hand from 'lucide-svelte/icons/hand';
    import Info from 'lucide-svelte/icons/info';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import Wallet from 'lucide-svelte/icons/wallet';
    import Zap from 'lucide-svelte/icons/zap';
    import Repeat from 'lucide-svelte/icons/repeat';
    import CircleStop from 'lucide-svelte/icons/circle-stop';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import SheetField from '@/components/ui/SheetField.svelte';
    import AmountSheet from '@/components/ui/AmountSheet.svelte';
    import DateSheet from '@/components/ui/DateSheet.svelte';
    import DayOfMonthPicker from '@/components/ui/DayOfMonthPicker.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount, formatCurrency } from '@/lib/format';
    import {
        type Commitment,
        type CommitmentKind,
        type DueType,
        type NotifyWhen,
        type PaymentMethod,
        type Recurrence,
        finishLabel,
        KIND_COLOR,
        KIND_ICON,
        KIND_LABEL,
        KIND_ORDER,
        monthlyOf,
        NOTIFY_LABEL,
    } from '@/lib/commitments';

    let {
        open = $bindable(false),
        income = 0,
        currentObligations = 0,
        salaryDay = 27,
        processing = false,
        /** التزام قائم → وضع التعديل. `null` → إضافة جديدة. */
        editing = null,
        onSave,
    }: {
        open?: boolean;
        income?: number;
        currentObligations?: number;
        salaryDay?: number;
        processing?: boolean;
        editing?: Commitment | null;
        onSave?: (payload: Record<string, unknown>) => void;
    } = $props();

    const isEditing = $derived(editing !== null);

    let kind = $state<CommitmentKind>('bill');
    let name = $state('');
    let amount = $state(0); // هللات — للأقساط: المبلغ الكامل
    let monthlyAmount = $state(0); // هللات — القسط الشهري كما يكتبه المستخدم
    let monthlyTouched = $state(false);
    let months = $state(12);
    let isVariable = $state(false);
    let paymentMethod = $state<PaymentMethod>('manual');
    let dueType = $state<DueType>('month_day');
    let dueDay = $state(1);
    let recurrence = $state<Recurrence>('monthly');
    /** تاريخ الاستحقاق الوحيد لغير المتكرّر — ISO */
    let dueOn = $state('');
    /** إيقاف المتكرّر من تاريخ — ISO، وفارغ يعني «ما زال جارياً». */
    let endsOn = $state('');
    let notifyWhen = $state<NotifyWhen>('before_3');
    let reserve = $state(true);

    let amountSheetOpen = $state(false);
    let monthlySheetOpen = $state(false);
    let dueOnSheetOpen = $state(false);
    let endsOnSheetOpen = $state(false);

    /** الحقول التي لمسها المستخدم — لا نُظهر خطأ حقل لم يصله بعد. */
    let touched = $state<Record<string, boolean>>({});
    let submitted = $state(false);

    const isInstallment = $derived(kind === 'installment');
    /** القسط سلسلة أشهر بحكم تعريفه — لا يُعرض له خيار «مرة واحدة». */
    const isOnce = $derived(recurrence === 'once' && !isInstallment);

    /** «20 أغسطس 2026» — نفس صياغة التواريخ في بقية التطبيق. */
    function longDate(iso: string): string {
        if (!iso) return '';
        return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(new Date(iso));
    }

    /** الاقتراح الآلي: نقطة بداية فقط، والمستخدم يعدّله بحرية. */
    const suggestedMonthly = $derived(monthlyOf(amount, months));

    // ما دام المستخدم لم يلمس حقل القسط، نبقيه مطابقاً للاقتراح.
    $effect(() => {
        if (isInstallment && !monthlyTouched) monthlyAmount = suggestedMonthly;
    });

    const effectiveMonthly = $derived(isInstallment ? monthlyAmount : amount);
    const shareOfIncome = $derived(
        income > 0 ? (effectiveMonthly / income) * 100 : 0,
    );
    const totalAfter = $derived(currentObligations + effectiveMonthly);

    /** فرق مجموع الأقساط عن المبلغ الكامل — معلومة لا خطأ (رسوم/فوائد). */
    const installmentGap = $derived(
        isInstallment && monthlyAmount > 0
            ? monthlyAmount * months - amount
            : 0,
    );

    // ── أخطاء لكل حقل على حدة ─────────────────────────────────────────
    const fieldErrors = $derived.by(() => {
        const e: Record<string, string> = {};
        if (!name.trim())
            e.name = 'الاسم مطلوب — بدونه لن تعرف الالتزام في القائمة.';
        if (!isVariable && amount <= 0)
            e.amount = isInstallment
                ? 'أدخل المبلغ الكامل للقسط.'
                : 'أدخل المبلغ.';
        if (isInstallment) {
            if (months < 2) e.months = 'شهران على الأقل.';
            else if (months > 480) e.months = 'عدد الأشهر كبير جداً.';
            if (monthlyAmount <= 0) e.monthly = 'أدخل قيمة القسط الشهري.';
        }
        if (isOnce) {
            if (!dueOn) e.dueOn = 'اختر تاريخ الاستحقاق.';
        } else if (dueType === 'month_day' && (dueDay < 1 || dueDay > 31)) {
            e.dueDay = 'اختر يوماً بين 1 و 31.';
        }
        return e;
    });

    /** يظهر الخطأ فقط بعد لمس الحقل أو بعد الضغط على «حفظ». */
    function errorFor(field: string): string {
        return submitted || touched[field] ? (fieldErrors[field] ?? '') : '';
    }

    function touch(field: string) {
        touched = { ...touched, [field]: true };
    }

    /** المنع الوحيد في الصفحة: قسط يجعل الالتزامات تفوق الدخل. */
    const blocked = $derived(
        isInstallment && income > 0 && totalAfter > income,
    );

    const warning = $derived.by(() => {
        if (blocked || income <= 0) return null;
        if (totalAfter > income * 0.7)
            return `التزاماتك بعد الإضافة ${Math.round((totalAfter / income) * 100)}٪ من دخلك — فوق 70٪ يترك مجالاً ضيقاً للمصاريف.`;
        if (isInstallment && shareOfIncome > 30)
            return `القسط وحده ${Math.round(shareOfIncome)}٪ من دخلك. تقدر تمدّه على أشهر أكثر.`;
        return null;
    });

    const canSave = $derived(
        !processing && Object.keys(fieldErrors).length === 0 && !blocked,
    );

    /** يوم الاستحقاق كما سيظهر في التقويم المالي. */
    const dueHint = $derived(
        isOnce
            ? dueOn
                ? `يظهر في التقويم مرة واحدة يوم ${longDate(dueOn)} — ولا يتكرّر بعدها`
                : 'يظهر في التقويم مرة واحدة فقط بالتاريخ الذي تختاره'
            : dueType === 'salary_day'
              ? `يتحرّك مع راتبك — اليوم ${salaryDay} من كل شهر`
              : `يظهر في التقويم المالي يوم ${dueDay} من كل شهر`,
    );

    function reset() {
        kind = 'bill';
        name = '';
        amount = 0;
        monthlyAmount = 0;
        monthlyTouched = false;
        months = 12;
        isVariable = false;
        paymentMethod = 'manual';
        dueType = 'month_day';
        dueDay = 1;
        recurrence = 'monthly';
        dueOn = '';
        endsOn = '';
        notifyWhen = 'before_3';
        reserve = true;
        touched = {};
        submitted = false;
    }

    /**
     * تعبئة النموذج من التزام قائم عند فتحه للتعديل.
     *
     * بدونها يفتح اللوح فارغاً على التزام موجود، فيبدو التعديل كأنه لم
     * يُحفظ حتى لو حُفظ. `monthlyTouched` تُرفع لأن قيمة القسط المخزّنة
     * قرار المستخدم لا اقتراح النظام — وإلا داسها الاقتراح الآلي.
     */
    function fill(c: Commitment) {
        kind = c.kind;
        name = c.name;
        months = c.months_count || 12;
        isVariable = c.is_variable;
        // في اللوح: `amount` المبلغ الكامل للقسط، و`monthlyAmount` القسط الشهري
        amount = c.kind === 'installment' ? c.total_amount : (c.amount ?? 0);
        monthlyAmount = c.kind === 'installment' ? (c.amount ?? 0) : 0;
        monthlyTouched = c.kind === 'installment';
        paymentMethod = c.payment_method;
        dueType = c.due_type;
        dueDay = c.due_day ?? 1;
        recurrence = c.recurrence ?? 'monthly';
        dueOn = c.due_on ?? '';
        endsOn = c.ends_on ?? '';
        notifyWhen = c.notify_when ?? 'before_3';
        reserve = c.reserve_in_budget;
        touched = {};
        submitted = false;
    }

    // الفتح يعبّئ من الالتزام المُمرَّر، أو يبدأ من نموذج نظيف.
    $effect(() => {
        if (!open) {
            return;
        }

        if (editing) {
            fill(editing);
        } else {
            reset();
        }
    });

    function submit() {
        submitted = true;
        if (!canSave) return;

        onSave?.({
            kind,
            name: name.trim(),
            amount: isInstallment ? monthlyAmount : isVariable ? null : amount,
            monthly_amount: isInstallment ? monthlyAmount : null,
            total_amount: isInstallment ? amount : 0,
            months_count: isInstallment ? months : 0,
            is_variable: isVariable,
            payment_method: paymentMethod,
            due_type: dueType,
            due_day: !isOnce && dueType === 'month_day' ? dueDay : null,
            recurrence: isOnce ? 'once' : 'monthly',
            due_on: isOnce ? dueOn : null,
            ends_on: isOnce ? null : endsOn || null,
            notify_when: notifyWhen,
            reserve_in_budget: reserve,
        });
        // لا تفريغ هنا: الحقول كانت تُمسح قبل رجوع الطلب فيومض النموذج
        // فارغاً. التهيئة صارت عند الفتح ($effect) فتكفي.
    }

    function close() {
        open = false;
    }

    const KIND_HINT = $derived(
        kind === 'bill'
            ? 'كهرباء'
            : kind === 'rent'
              ? 'إيجار الشقة'
              : kind === 'installment'
                ? 'قسط السيارة'
                : 'اشتراك جوال',
    );
</script>

<SheetShell
    bind:open
    title={isEditing ? `تعديل ${KIND_LABEL[kind]}` : 'إضافة التزام'}
    subtitle={isEditing ? name : 'فاتورة · إيجار · قسط · اشتراك'}
    onClose={close}
>
    <div class="flex flex-col">
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
                    <CategoryIcon
                        icon={KIND_ICON[k]}
                        color={KIND_COLOR[k]}
                        size="sm"
                    />
                    <span
                        class="text-[11px] {kind === k
                            ? 'font-semibold text-foreground'
                            : 'text-muted-foreground'}"
                    >
                        {KIND_LABEL[k]}
                    </span>
                </button>
            {/each}
        </div>

        <!-- المبلغ -->
        <div class="mt-4">
            {#if isVariable}
                <p
                    class="rounded-2xl border border-dashed border-input bg-secondary px-3 py-3 text-center text-[12px] text-muted-foreground"
                >
                    المبلغ يُسجَّل عند الدفع كل شهر — لا حاجة لإدخاله الآن
                </p>
            {:else}
                <SheetField
                    label={isInstallment
                        ? 'المبلغ الكامل للقسط'
                        : 'المبلغ الشهري'}
                    icon={Wallet}
                    value={amount > 0 ? `${formatAmount(amount)} ر.س` : ''}
                    placeholder="اضغط لإدخال المبلغ"
                    error={errorFor('amount')}
                    onclick={() => {
                        touch('amount');
                        amountSheetOpen = true;
                    }}
                />
            {/if}
        </div>

        <!-- الاسم -->
        <div class="mt-3">
            <label
                for="c-name"
                class="mb-1.5 block text-[11.5px] text-muted-foreground"
            >
                الاسم <span class="text-destructive">*</span>
            </label>
            <input
                id="c-name"
                bind:value={name}
                onblur={() => touch('name')}
                aria-invalid={!!errorFor('name')}
                placeholder={KIND_HINT}
                class="min-h-11 w-full rounded-2xl border bg-background px-3 text-[14px] outline-none focus:border-primary {errorFor(
                    'name',
                )
                    ? 'border-destructive'
                    : 'border-input'}"
            />
            {#if errorFor('name')}
                <p class="mt-1 text-[11.5px] text-destructive">
                    {errorFor('name')}
                </p>
            {/if}
        </div>

        <!-- الأقساط: عدد الأشهر + القسط الشهري (يكتبه المستخدم) -->
        {#if isInstallment}
            <div class="mt-3 grid grid-cols-2 gap-2">
                <div>
                    <label
                        for="c-months"
                        class="mb-1.5 block text-[11.5px] text-muted-foreground"
                        >عدد الأشهر</label
                    >
                    <input
                        id="c-months"
                        type="number"
                        inputmode="numeric"
                        min="2"
                        max="480"
                        bind:value={months}
                        onblur={() => touch('months')}
                        class="min-h-11 w-full rounded-2xl border bg-background px-3 text-[14px] font-semibold tabular-nums outline-none focus:border-primary {errorFor(
                            'months',
                        )
                            ? 'border-destructive'
                            : 'border-input'}"
                    />
                    {#if errorFor('months')}
                        <p class="mt-1 text-[11.5px] text-destructive">
                            {errorFor('months')}
                        </p>
                    {/if}
                </div>

                <SheetField
                    label="القسط الشهري"
                    value={monthlyAmount > 0
                        ? `${formatAmount(monthlyAmount)} ر.س`
                        : ''}
                    placeholder="اضغط لإدخاله"
                    error={errorFor('monthly')}
                    onclick={() => {
                        touch('monthly');
                        monthlySheetOpen = true;
                    }}
                />
            </div>

            {#if !monthlyTouched && suggestedMonthly > 0 && !errorFor('monthly')}
                <p class="mt-1 text-[11px] text-muted-foreground">
                    مقترح: {formatAmount(suggestedMonthly)} ر.س — عدّله لو مختلف
                </p>
            {/if}

            {#if monthlyAmount > 0 && months >= 2}
                <div
                    class="mt-2 rounded-2xl border border-primary/20 bg-primary/6 px-3 py-2.5 text-[12px] text-foreground/85"
                >
                    <p>
                        يخلص في <b class="font-semibold"
                            >{finishLabel(months)}</b
                        >
                        {#if income > 0}
                            · <b class="font-semibold"
                                >{Math.round(shareOfIncome)}٪</b
                            > من دخلك
                        {/if}
                    </p>
                    {#if Math.abs(installmentGap) > 100}
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            مجموع الأقساط {formatAmount(monthlyAmount * months)} مقابل
                            مبلغ
                            {formatAmount(amount)} — فرق {formatAmount(
                                Math.abs(installmentGap),
                            )}
                            {installmentGap > 0
                                ? '(رسوم أو فوائد)'
                                : '(دفعة أولى أو خصم)'}.
                        </p>
                    {/if}
                </div>
            {/if}
        {:else if kind === 'bill'}
            <!-- الفاتورة المتغيّرة -->
            <div
                class="mt-3 flex items-center gap-3 rounded-xl border border-border bg-secondary px-3 py-2.5"
            >
                <div class="flex-1">
                    <p class="text-[12.5px] font-medium">
                        المبلغ متغيّر كل شهر
                    </p>
                    <p class="text-[11.5px] text-muted-foreground">
                        نحجز متوسّط آخر 3 أشهر حتى تسجّل الفعلي
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={isVariable}
                    aria-label="المبلغ متغيّر"
                    onclick={() => (isVariable = !isVariable)}
                    class="relative h-6 w-11 shrink-0 rounded-full transition-colors {isVariable
                        ? 'bg-success'
                        : 'bg-border'}"
                >
                    <span
                        class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-[inset-inline-start] {isVariable
                            ? 'start-[22px]'
                            : 'start-0.5'}"
                    ></span>
                </button>
            </div>
        {/if}

        <!-- طريقة الدفع -->
        <div class="mt-3">
            <span class="mb-1.5 block text-[11.5px] font-medium"
                >طريقة الدفع</span
            >
            <div class="flex gap-2">
                <button
                    type="button"
                    aria-pressed={paymentMethod === 'auto'}
                    onclick={() => (paymentMethod = 'auto')}
                    class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 text-[12.5px] {paymentMethod ===
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
                    class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 text-[12.5px] {paymentMethod ===
                    'manual'
                        ? 'border-primary bg-primary/8 font-semibold text-primary'
                        : 'border-input text-foreground/85'}"
                >
                    <Hand class="size-3.5" /> أدفعه بنفسي
                </button>
            </div>
            <p
                class="mt-1.5 text-[11.5px] leading-relaxed text-muted-foreground"
            >
                التلقائي يُسجَّل مدفوعاً وحده يوم الاستحقاق. اليدوي يبقى محجوزاً
                ويذكّرك.
            </p>
        </div>

        <!--
            التكرار — سؤال قبل موعد الاستحقاق لأنه يحدّد شكله.
            اشتراك شهر واحد ثم يُلغى كان يبقى ظاهراً كل شهر بلا داعٍ.
        -->
        {#if !isInstallment}
            <div class="mt-3">
                <span class="mb-1.5 block text-[11.5px] font-medium"
                    >التكرار</span
                >
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        aria-pressed={recurrence === 'monthly'}
                        onclick={() => (recurrence = 'monthly')}
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-[12.5px] {recurrence ===
                        'monthly'
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        <Repeat class="size-3.5" /> يتكرّر كل شهر
                    </button>
                    <button
                        type="button"
                        aria-pressed={recurrence === 'once'}
                        onclick={() => {
                            recurrence = 'once';
                            touch('dueOn');
                        }}
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-center text-[12.5px] {recurrence ===
                        'once'
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        <CalendarDays class="size-3.5" /> مرة واحدة
                    </button>
                </div>
            </div>
        {/if}

        <!-- موعد الاستحقاق -->
        <div class="mt-3">
            <span class="mb-1.5 block text-[11.5px] font-medium"
                >موعد الاستحقاق</span
            >

            {#if isOnce}
                <SheetField
                    label="تاريخ الاستحقاق"
                    icon={CalendarDays}
                    value={longDate(dueOn)}
                    placeholder="اختر التاريخ"
                    error={errorFor('dueOn')}
                    onclick={() => {
                        touch('dueOn');
                        dueOnSheetOpen = true;
                    }}
                />
            {:else}
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        aria-pressed={dueType === 'salary_day'}
                        onclick={() => (dueType = 'salary_day')}
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-[12.5px] {dueType ===
                        'salary_day'
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        <Zap class="size-3.5" /> مع الراتب
                    </button>
                    <button
                        type="button"
                        aria-pressed={dueType === 'month_day'}
                        onclick={() => (dueType = 'month_day')}
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-[12.5px] {dueType ===
                        'month_day'
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        <CalendarDays class="size-3.5" /> يوم محدّد
                    </button>
                </div>

                {#if dueType === 'month_day'}
                    <div class="mt-2">
                        <DayOfMonthPicker
                            bind:value={dueDay}
                            showLastDay={false}
                        />
                    </div>
                    {#if errorFor('dueDay')}
                        <p class="mt-1 text-[11.5px] text-destructive">
                            {errorFor('dueDay')}
                        </p>
                    {/if}
                {/if}
            {/if}

            <p
                class="mt-2 inline-flex items-start gap-2 rounded-2xl border border-primary/20 bg-primary/6 px-3 py-2 text-[11px] text-foreground/85"
            >
                <Info class="mt-px size-3.5 shrink-0 text-primary" />
                {dueHint}
            </p>
        </div>

        <!--
            الإيقاف من تاريخ — «ألغيت الاشتراك» بدل الحذف: الأشهر السابقة
            تبقى في السجل والتقارير، ولا ظهور من ذلك التاريخ فصاعداً.
            يُعرض عند التعديل فقط؛ لا أحد يوقف التزاماً وهو يضيفه.
        -->
        {#if isEditing && !isOnce}
            <div class="mt-3">
                <SheetField
                    label="إيقافه من تاريخ (اختياري)"
                    icon={CircleStop}
                    value={longDate(endsOn)}
                    placeholder="ما زال جارياً"
                    onclick={() => (endsOnSheetOpen = true)}
                />
                {#if endsOn}
                    <div
                        class="mt-1.5 flex items-center justify-between gap-2 text-[11px] text-muted-foreground"
                    >
                        <span>
                            لن يُطالَب به من {longDate(endsOn)} فصاعداً، وما قبله
                            يبقى كما هو.
                        </span>
                        <button
                            type="button"
                            onclick={() => (endsOn = '')}
                            class="min-h-9 shrink-0 text-[11.5px] font-medium text-primary underline-offset-2 hover:underline"
                        >
                            إلغاء الإيقاف
                        </button>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- التنبيه — خيار واحد -->
        <div class="mt-3">
            <span class="mb-1.5 block text-[11.5px] font-medium">نبّهني</span>
            <div class="grid grid-cols-3 gap-1.5">
                {#each ['before_3', 'on_due', 'none'] as w (w)}
                    <button
                        type="button"
                        role="radio"
                        aria-checked={notifyWhen === w}
                        onclick={() => (notifyWhen = w as NotifyWhen)}
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border px-2 text-center text-[11.5px] {notifyWhen ===
                        w
                            ? 'border-primary bg-primary/8 font-semibold text-primary'
                            : 'border-input text-foreground/85'}"
                    >
                        {NOTIFY_LABEL[w as NotifyWhen]}
                    </button>
                {/each}
            </div>
        </div>

        <!-- الحجز من الميزانية -->
        <div
            class="mt-3 flex items-center gap-3 rounded-xl border border-border bg-secondary px-3 py-2.5"
        >
            <div class="flex-1">
                <p class="text-[12.5px] font-medium">احجزه من ميزانيتي</p>
                <p class="text-[11.5px] leading-relaxed text-muted-foreground">
                    {#if reserve}
                        ينقص {formatCurrency(effectiveMonthly)} من «المتبقي لك» في
                        اللوحة حتى تدفعه
                    {:else}
                        لن يظهر في «المتبقي لك» — استعمله للالتزامات غير
                        المؤكّدة فقط
                    {/if}
                </p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={reserve}
                aria-label="احجزه من ميزانيتي"
                onclick={() => (reserve = !reserve)}
                class="relative h-6 w-11 shrink-0 rounded-full transition-colors {reserve
                    ? 'bg-success'
                    : 'bg-border'}"
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
            <p
                class="mt-3 flex items-start gap-2 rounded-xl border border-destructive/35 bg-destructive/8 px-3 py-2.5 text-[12px] font-medium text-destructive"
            >
                <TriangleAlert class="mt-px size-4 shrink-0" />
                <span>
                    هذا القسط يرفع التزاماتك إلى {formatCurrency(totalAfter)} وهي
                    أكبر من دخلك ({formatCurrency(income)}) — مدّه على أشهر أكثر
                    أو أنهِ التزاماً قائماً أولاً.
                </span>
            </p>
        {:else if warning}
            <p
                class="mt-3 flex items-start gap-2 rounded-2xl border border-warning/40 bg-warning/10 px-3 py-2.5 text-[12px] text-warning-text"
            >
                <TriangleAlert class="mt-px size-4 shrink-0" />
                <span>{warning}</span>
            </p>
        {/if}
    </div>

    {#snippet footer()}
        <button
            type="button"
            onclick={close}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85"
        >
            إلغاء
        </button>
        <button
            type="button"
            disabled={processing}
            onclick={submit}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-50"
        >
            <Check class="size-[18px]" />
            {processing
                ? 'جارٍ الحفظ…'
                : isEditing
                  ? 'حفظ التعديل'
                  : `حفظ ${KIND_LABEL[kind]}`}
        </button>
    {/snippet}
</SheetShell>

<AmountSheet
    bind:open={amountSheetOpen}
    bind:value={amount}
    title={isInstallment ? 'المبلغ الكامل للقسط' : 'المبلغ الشهري'}
    quickAdd={[100, 500, 1000]}
/>

<DateSheet
    bind:open={dueOnSheetOpen}
    bind:value={dueOn}
    title="تاريخ الاستحقاق"
    subtitle="مرة واحدة — لا يتكرّر بعدها"
    {salaryDay}
/>

<DateSheet
    bind:open={endsOnSheetOpen}
    bind:value={endsOn}
    title="إيقافه من تاريخ"
    subtitle="آخر ظهور يكون قبل هذا التاريخ"
    {salaryDay}
/>

<AmountSheet
    bind:open={monthlySheetOpen}
    bind:value={monthlyAmount}
    title="القسط الشهري"
    hint={suggestedMonthly > 0
        ? `المقترح ${formatAmount(suggestedMonthly)} ر.س`
        : ''}
    averageAmount={suggestedMonthly}
    quickAdd={[100, 500]}
    onSave={() => (monthlyTouched = true)}
/>
