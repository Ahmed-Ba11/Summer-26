/**
 * منطق «شهر الراتب» في الواجهة — مصدر واحد للحقيقة.
 *
 * الفكرة: شهر المستخدم يبدأ يوم نزول راتبه لا يوم 1 من التقويم.
 * بدون هذا، أهم رقمين في التطبيق («المتبقي لك» و«الحد اليومي الآمن»)
 * يكونان غلطاً 26 يوماً من كل 30 لأي موظّف راتبه بعد يوم 20.
 *
 * المصطلح المعروض للمستخدم دائماً: «راتب أغسطس» — كلمة «دورة» ممنوعة.
 */

const MONTHS = [
    'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
];

export interface SalaryMonth {
    /** مفتاح الفترة: 2026-08 */
    key: string;
    startsOn: Date;
    endsOn: Date;
    /** «راتب أغسطس» */
    label: string;
    /** «27 أغسطس ← 26 سبتمبر» */
    range: string;
    /** أيام باقية حتى الراتب القادم */
    daysLeft: number;
    /** كم يوماً مضى من الفترة (1 = يوم الراتب) */
    dayIndex: number;
    totalDays: number;
}

/** يوم الراتب داخل شهر معيّن — يُثبَّت على آخر يوم لو كان الشهر أقصر. */
function salaryDateIn(year: number, month: number, salaryDay: number): Date {
    const lastDay = new Date(year, month + 1, 0).getDate();
    return new Date(year, month, Math.min(salaryDay, lastDay));
}

export function salaryMonth(salaryDay = 27, today = new Date()): SalaryMonth {
    const t = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    let start = salaryDateIn(t.getFullYear(), t.getMonth(), salaryDay);
    if (start > t) {
        const prev = new Date(t.getFullYear(), t.getMonth() - 1, 1);
        start = salaryDateIn(prev.getFullYear(), prev.getMonth(), salaryDay);
    }

    const nextMonth = new Date(start.getFullYear(), start.getMonth() + 1, 1);
    const nextSalary = salaryDateIn(nextMonth.getFullYear(), nextMonth.getMonth(), salaryDay);
    const end = new Date(nextSalary.getFullYear(), nextSalary.getMonth(), nextSalary.getDate() - 1);

    const day = 86_400_000;
    const totalDays = Math.round((end.getTime() - start.getTime()) / day) + 1;

    return {
        key: `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}`,
        startsOn: start,
        endsOn: end,
        label: `راتب ${MONTHS[start.getMonth()]}`,
        range: `${start.getDate()} ${MONTHS[start.getMonth()]} ← ${end.getDate()} ${MONTHS[end.getMonth()]}`,
        daysLeft: Math.max(0, Math.round((nextSalary.getTime() - t.getTime()) / day)),
        dayIndex: Math.round((t.getTime() - start.getTime()) / day) + 1,
        totalDays,
    };
}

/**
 * الحد اليومي الآمن = المتبقي ÷ الأيام الباقية.
 * يُحسب على أيام **شهر الراتب** لا الشهر التقويمي — وإلا صار الرقم متفائلاً
 * في أول الشهر ومستحيلاً في آخره.
 */
export function safeDailyLimit(remaining: number, daysLeft: number): number {
    if (remaining <= 0) return 0;
    return Math.floor(remaining / Math.max(1, daysLeft));
}

/**
 * حصّة الادخار المقترحة من الدخل.
 * 10٪ هو المعيار الشائع، ونقترح أقل إذا كانت الالتزامات ثقيلة أصلاً —
 * اقتراح غير واقعي يُهجَر من أول شهر.
 */
export function suggestedSavings(income: number, obligations: number): number {
    const free = Math.max(0, income - obligations);
    const target = Math.round(income * 0.1);
    return Math.min(target, Math.round(free * 0.35));
}

/** نسبة الالتزامات من الدخل ومستوى صحّتها. */
export function obligationHealth(obligations: number, income: number) {
    if (income <= 0) return { pct: 0, level: 'good' as const };
    const pct = (obligations / income) * 100;
    return { pct, level: pct > 70 ? ('bad' as const) : pct > 50 ? ('warn' as const) : ('good' as const) };
}

/** توزيع مقترح للميزانية على الفئات — نقطة بداية لا حكم نهائي. */
export const DEFAULT_SPLIT: { name: string; icon: string; share: number }[] = [
    { name: 'طعام وقهوة', icon: 'utensils', share: 0.36 },
    { name: 'مواصلات ووقود', icon: 'car', share: 0.17 },
    { name: 'تسوّق', icon: 'shopping-cart', share: 0.14 },
    { name: 'صحة', icon: 'heart-pulse', share: 0.1 },
    { name: 'ترفيه', icon: 'gamepad-2', share: 0.12 },
];

export function suggestBudgets(spendable: number) {
    const rows = DEFAULT_SPLIT.map((c) => ({
        ...c,
        amount: Math.round((spendable * c.share) / 100) * 100, // تقريب لأقرب ريال
    }));
    const allocated = rows.reduce((s, r) => s + r.amount, 0);
    return { rows, unallocated: Math.max(0, spendable - allocated) };
}
