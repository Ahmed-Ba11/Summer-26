/**
 * تنسيق موحّد للمبالغ والتواريخ والنسب.
 *
 * قاعدة ذهبية: كل المبالغ في قاعدة البيانات مخزّنة بـ «الهللات» (integer).
 * كل دوال هذا الملف تستقبل هللات وترجع نصاً معروضاً — فلا يتكرّر خطأ الضرب في ١٠٠.
 *
 * ملاحظة على اللغة:
 *  - `-u-nu-latn`      → يفرض الأرقام اللاتينية (1234) بدل الهندية (١٢٣٤).
 *                        الأرقام اللاتينية أسرع في القراءة داخل الجداول المالية.
 *  - `-u-ca-gregory`   → يفرض التقويم الميلادي، لأن `ar-SA` يعطي الهجري افتراضياً
 *                        وهذا يكسر مطابقة التواريخ مع كشف الحساب البنكي.
 */

const LOCALE = 'ar-SA-u-nu-latn';

const NUM = new Intl.NumberFormat(LOCALE, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const NUM_INT = new Intl.NumberFormat(LOCALE, {
    maximumFractionDigits: 0,
});

/** المدخل بالهللات → نص معروض بالريال. */
export function formatCurrency(
    halalas: number,
    opts?: { compact?: boolean; withSymbol?: boolean },
): string {
    const riyals = (halalas ?? 0) / 100;
    const symbol = opts?.withSymbol === false ? '' : ' ر.س';

    if (opts?.compact && Math.abs(riyals) >= 1000) {
        return NUM.format(Math.round(riyals / 100) / 10) + ' ألف' + symbol;
    }

    return NUM.format(riyals) + symbol;
}

/** المدخل بالهللات → رقم فقط بدون رمز العملة (للأرقام الكبيرة المستقلة). */
export function formatAmount(halalas: number): string {
    return NUM.format((halalas ?? 0) / 100);
}

/** ريالات (من حقل إدخال) → هللات للإرسال إلى السيرفر. */
export function toHalalas(riyals: number | string): number {
    const n = typeof riyals === 'string' ? parseFloat(riyals) : riyals;
    if (!Number.isFinite(n)) return 0;
    return Math.round(n * 100);
}

/** هللات → ريالات لعرضها داخل حقل إدخال. */
export function toRiyals(halalas: number): number {
    return (halalas ?? 0) / 100;
}

export function formatDate(iso: string): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
        day: 'numeric',
        month: 'long',
    }).format(new Date(iso));
}

export function formatFullDate(iso: string): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(iso));
}

export function formatPercent(n: number): string {
    return NUM_INT.format(Math.round(n ?? 0)) + '٪';
}

/** «بعد ٣ أيام» / «اليوم» / «متأخرة ٥ أيام» */
export function formatRelativeDays(iso: string): string {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(iso);
    target.setHours(0, 0, 0, 0);
    const days = Math.round((target.getTime() - today.getTime()) / 86_400_000);

    if (days === 0) return 'اليوم';
    if (days === 1) return 'غداً';
    if (days > 1) return `بعد ${NUM_INT.format(days)} أيام`;
    if (days === -1) return 'متأخرة يوم';
    return `متأخرة ${NUM_INT.format(Math.abs(days))} أيام`;
}

/** عدد الأيام المتبقية حتى يوم الراتب القادم. */
export function daysUntilSalary(salaryDay: number, from: Date = new Date()): number {
    const d = new Date(from);
    d.setHours(0, 0, 0, 0);

    const lastDayOfMonth = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
    const day = salaryDay === 0 ? lastDayOfMonth : Math.min(salaryDay, lastDayOfMonth);

    let next = new Date(d.getFullYear(), d.getMonth(), day);
    if (next <= d) {
        const nextMonthLast = new Date(d.getFullYear(), d.getMonth() + 2, 0).getDate();
        const nextDay = salaryDay === 0 ? nextMonthLast : Math.min(salaryDay, nextMonthLast);
        next = new Date(d.getFullYear(), d.getMonth() + 1, nextDay);
    }

    return Math.max(0, Math.round((next.getTime() - d.getTime()) / 86_400_000));
}
