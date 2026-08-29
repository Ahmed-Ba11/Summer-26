/**
 * منطق الالتزامات — مصدر واحد للحقيقة، مشترك بين كل مكوّنات الصفحة.
 * كل المبالغ بالهللات (عدد صحيح).
 */

export type CommitmentKind = 'bill' | 'rent' | 'installment' | 'subscription';
export type PaymentMethod = 'auto' | 'manual';
/**
 * نوعان فقط للاستحقاق — لا ثالث لهما:
 *   salary_day → يتحرّك مع يوم راتبك تلقائياً
 *   month_day  → يوم ثابت من كل شهر يظهر فوراً في التقويم المالي
 * («تاريخ واحد» حُذف: كل التزام في التطبيق متكرّر شهرياً بطبيعته،
 *  ووجود خيار ثالث كان يربك بلا فائدة.)
 */
export type DueType = 'salary_day' | 'month_day';

/**
 * ليس كل التزام شهرياً.
 *   monthly → ظهور في كل فترة راتب من تاريخ الإنشاء (وحتى `ends_on` إن وُجد)
 *   once    → ظهور واحد في الفترة التي يقع فيها `due_on` — اشتراك شهر ثم يُلغى
 */
export type Recurrence = 'monthly' | 'once';

/** تنبيه واحد لا أكثر — تعدّد التنبيهات لنفس الالتزام إزعاج لا فائدة. */
export type NotifyWhen = 'before_3' | 'on_due' | 'none';

export const NOTIFY_LABEL: Record<NotifyWhen, string> = {
    before_3: 'قبل 3 أيام',
    on_due: 'يوم الاستحقاق',
    none: 'لا تنبّهني',
};

export interface Commitment {
    id: number;
    kind: CommitmentKind;
    name: string;
    icon: string;
    color: string;
    /** null فقط للفواتير المتغيّرة قبل تسجيل مبلغ الشهر */
    amount: number | null;
    is_variable: boolean;
    average_amount: number;
    /** للأقساط */
    total_amount: number;
    months_count: number;
    months_paid: number;
    payment_method: PaymentMethod;
    due_type: DueType;
    /** يوم الشهر — `null` حين يتبع الاستحقاق يوم الراتب */
    due_day: number | null;
    notify_when: NotifyWhen;
    recurrence: Recurrence;
    /** تاريخ الاستحقاق الوحيد لغير المتكرّر — ISO */
    due_on: string | null;
    /** أُوقف المتكرّر من هذا التاريخ — لا ظهور بعده، والسابق يبقى. ISO */
    ends_on: string | null;
    /** متكرّر أُوقف ومضى تاريخ إيقافه */
    is_stopped: boolean;
    /** التاريخ المحسوب لاستحقاق هذا الظهور — ISO */
    due_date: string;
    reserve_in_budget: boolean;
    /** فترة الراتب التي يخصّها هذا الظهور — 2026-08 */
    period_key: string;
    /** حالة هذا الظهور، محسوبة في الخادم من `commitment_payments` */
    status: CommitmentState;
    is_paid_this_month: boolean;
    paid_at: string | null;
}

export const KIND_LABEL: Record<CommitmentKind, string> = {
    bill: 'فاتورة',
    rent: 'إيجار',
    installment: 'قسط',
    subscription: 'اشتراك',
};

export const KIND_LABEL_PLURAL: Record<CommitmentKind, string> = {
    bill: 'فواتير',
    rent: 'إيجارات',
    installment: 'أقساط',
    subscription: 'اشتراكات',
};

export const KIND_ICON: Record<CommitmentKind, string> = {
    bill: 'receipt',
    rent: 'house',
    installment: 'credit-card',
    subscription: 'repeat',
};

export const KIND_COLOR: Record<CommitmentKind, string> = {
    bill: 'var(--chart-7)',
    rent: 'var(--chart-5)',
    installment: 'var(--chart-2)',
    subscription: 'var(--chart-3)',
};

export const KIND_ORDER: CommitmentKind[] = [
    'bill',
    'rent',
    'installment',
    'subscription',
];

/** الأيام المتبقّية للاستحقاق — سالب = متأخر. */
export function daysUntil(due: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const d = new Date(due);
    d.setHours(0, 0, 0, 0);
    return Math.round((d.getTime() - today.getTime()) / 86_400_000);
}

/**
 * الحالات الثلاث لظهور واحد — يحسبها الخادم من `commitment_payments`.
 *
 * كانت تُشتقّ هنا من `due_date` وحده، فكل ظهور لالتزام متكرّر يأخذ نفس
 * الحالة مهما اختلفت فترته. الاشتقاق انتقل إلى `CommitmentService`
 * ليقرأ من جدول الدفعات، والواجهة تعرض ما وصلها.
 */
export type CommitmentState = 'paid' | 'overdue' | 'upcoming';

export function stateOf(c: Commitment): CommitmentState {
    return c.status;
}

/**
 * «يستحق خلال ثلاثة أيام» — تمييز بصري داخل «قادم»، لا حالة رابعة.
 * الحالة تبقى `upcoming`؛ هذا لون الحدّ فقط.
 */
export function isDueSoon(c: Commitment): boolean {
    return (
        !c.is_stopped && c.status === 'upcoming' && daysUntil(c.due_date) <= 3
    );
}

/**
 * المبلغ المتوقّع لهذا الشهر.
 * الفاتورة المتغيّرة بلا مبلغ مسجَّل → نستخدم متوسّطها للحجز، لا صفراً،
 * وإلا انتفخ «المتبقي لك» بمبلغ سيخرج فعلاً.
 */
export function expectedAmount(c: Commitment): number {
    if (c.amount !== null) return c.amount;
    return c.is_variable ? c.average_amount : 0;
}

export interface CommitmentTotals {
    total: number;
    paid: number;
    /** محدَّد ولم يُدفع بعد — مطروح من «المتبقي لك» */
    reserved: number;
    count: number;
    paidCount: number;
    overdueCount: number;
}

export function totalsOf(list: Commitment[]): CommitmentTotals {
    let paid = 0;
    let reserved = 0;
    let paidCount = 0;
    let overdueCount = 0;

    for (const c of list) {
        // الموقوف لا ظهور له في هذه الفترة — لا يُحجز ولا يُعدّ متأخّراً،
        // تماماً كما يستبعده `CommitmentService::obligationsForPeriod`.
        if (c.is_stopped) continue;

        const amt = expectedAmount(c);
        if (c.is_paid_this_month) {
            paid += c.amount ?? amt;
            paidCount++;
        } else {
            if (c.reserve_in_budget) reserved += amt;
            if (daysUntil(c.due_date) < 0) overdueCount++;
        }
    }

    return {
        total: paid + reserved,
        paid,
        reserved,
        count: list.length,
        paidCount,
        overdueCount,
    };
}

/** نسبة الالتزامات من الدخل — مؤشّر صحة معياري. */
export function healthOf(
    total: number,
    income: number,
): { pct: number; level: 'good' | 'warn' | 'bad' } {
    if (income <= 0) return { pct: 0, level: 'good' };
    const pct = (total / income) * 100;
    return { pct, level: pct > 70 ? 'bad' : pct > 50 ? 'warn' : 'good' };
}

/**
 * القسط الشهري يُحسب ولا يُكتب — يمنع تناقض
 * «36,000 على 36 شهر بقسط 1,200».
 */
export function monthlyOf(totalAmount: number, months: number): number {
    if (months < 1) return 0;
    return Math.ceil(totalAmount / months);
}

/** تاريخ آخر قسط: «يخلص في مارس 2029». */
export function finishLabel(monthsLeft: number): string {
    if (monthsLeft <= 0) return '';
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() + monthsLeft);
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
        month: 'long',
        year: 'numeric',
    }).format(d);
}

/** «يوم الانتهاء»: آخر قسط ينتهي، وكم يرجع لك شهرياً بعده. */
export function freedomDay(
    installments: Commitment[],
): { label: string; monthly: number } | null {
    const active = installments.filter((c) => c.months_count > c.months_paid);
    if (!active.length) return null;
    const monthsLeft = Math.max(
        ...active.map((c) => c.months_count - c.months_paid),
    );
    const monthly = active.reduce((s, c) => s + (c.amount ?? 0), 0);
    return { label: finishLabel(monthsLeft), monthly };
}
