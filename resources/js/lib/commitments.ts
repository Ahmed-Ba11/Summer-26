/**
 * منطق الالتزامات — مصدر واحد للحقيقة، مشترك بين كل مكوّنات الصفحة.
 * كل المبالغ بالهللات (عدد صحيح).
 */

export type CommitmentKind = 'bill' | 'rent' | 'installment' | 'subscription';
export type PaymentMethod = 'auto' | 'manual';
export type DueType = 'salary_day' | 'month_day' | 'fixed_date';

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
    /** التاريخ المحسوب لاستحقاق هذا الشهر — ISO */
    due_date: string;
    reserve_in_budget: boolean;
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
    rent: 'home',
    installment: 'credit-card',
    subscription: 'repeat',
};

export const KIND_COLOR: Record<CommitmentKind, string> = {
    bill: 'var(--chart-7)',
    rent: 'var(--chart-5)',
    installment: 'var(--chart-2)',
    subscription: 'var(--chart-3)',
};

export const KIND_ORDER: CommitmentKind[] = ['bill', 'rent', 'installment', 'subscription'];

/** الأيام المتبقّية للاستحقاق — سالب = متأخر. */
export function daysUntil(due: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const d = new Date(due);
    d.setHours(0, 0, 0, 0);
    return Math.round((d.getTime() - today.getTime()) / 86_400_000);
}

export type CommitmentState = 'paid' | 'overdue' | 'due_soon' | 'reserved';

export function stateOf(c: Commitment): CommitmentState {
    if (c.is_paid_this_month) return 'paid';
    const d = daysUntil(c.due_date);
    if (d < 0) return 'overdue';
    if (d <= 3) return 'due_soon';
    return 'reserved';
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
        const amt = expectedAmount(c);
        if (c.is_paid_this_month) {
            paid += c.amount ?? amt;
            paidCount++;
        } else {
            if (c.reserve_in_budget) reserved += amt;
            if (daysUntil(c.due_date) < 0) overdueCount++;
        }
    }

    return { total: paid + reserved, paid, reserved, count: list.length, paidCount, overdueCount };
}

/** نسبة الالتزامات من الدخل — مؤشّر صحة معياري. */
export function healthOf(total: number, income: number): { pct: number; level: 'good' | 'warn' | 'bad' } {
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
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', { month: 'long', year: 'numeric' }).format(d);
}

/** «يوم تحرّرك»: آخر قسط ينتهي، وكم يرجع لك شهرياً بعده. */
export function freedomDay(installments: Commitment[]): { label: string; monthly: number } | null {
    const active = installments.filter((c) => c.months_count > c.months_paid);
    if (!active.length) return null;
    const monthsLeft = Math.max(...active.map((c) => c.months_count - c.months_paid));
    const monthly = active.reduce((s, c) => s + (c.amount ?? 0), 0);
    return { label: finishLabel(monthsLeft), monthly };
}
