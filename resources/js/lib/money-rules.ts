/**
 * محرّك قواعد المال — مصدر واحد لكل تحقّق مالي في الواجهة.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  المبدأ الحاكم: الحقائق تُسجَّل، والخطط تُراجَع.
 * ══════════════════════════════════════════════════════════════════════
 *
 *  • المصروف واقعة حصلت — لا يُمنع أبداً مهما كان. منعه يحوّل أرقام
 *    التطبيق إلى خيال، لأن المستخدم ببساطة لن يسجّله.
 *  • الميزانية والادخار والأقساط قرارات مستقبلية — تُمنع إذا كانت
 *    مستحيلة حسابياً (لا «محفوفة بالمخاطر»، بل مستحيلة).
 *
 *  كل المبالغ بالهللات (integer).
 *
 *  ⚠️ هذا الملف للواجهة فقط. التحقّق الملزم يتم في السيرفر عبر
 *     App\Services\BudgetGuard — لا تعتمد على هذا وحده أبداً.
 */

import { formatCurrency } from '@/lib/format';

export type Severity = 'info' | 'warn' | 'danger' | 'block';

export interface Check {
    severity: Severity;
    title: string;
    detail?: string;
    /** اقتراح قابل للتنفيذ بضغطة — يظهر كزر بجانب الرسالة */
    suggestion?: { label: string; value: number };
}

export interface FinancialContext {
    monthlyIncome: number;
    /** الفواتير الثابتة + الأقساط + الادخار المخطط */
    obligations: number;
    /** المصاريف المسجّلة هذا الشهر */
    spent: number;
    /** مجموع ميزانيات كل الفئات */
    budgetedTotal: number;
    daysUntilSalary: number;
}

export interface CategoryContext {
    name: string;
    budget: number;
    spent: number;
    /** متوسط المصروف الواحد في هذه الفئة — لكشف الأخطاء المطبعية */
    averageEntry: number;
}

/** المتاح للصرف بعد كل الالتزامات والمصاريف المسجّلة. */
export function availableToSpend(ctx: FinancialContext): number {
    return ctx.monthlyIncome - ctx.obligations - ctx.spent;
}

/** المتاح للتخصيص في الميزانية (قبل خصم المصاريف الفعلية). */
export function availableToBudget(ctx: FinancialContext): number {
    return ctx.monthlyIncome - ctx.obligations;
}

/** المبلغ غير المخصّص — فلوس بلا خطة. */
export function unallocated(ctx: FinancialContext): number {
    return availableToBudget(ctx) - ctx.budgetedTotal;
}

// ═══════════════════════════════════════════════════════════════════════
//  ١ · المصاريف — تُحفظ دائماً، لكن مع تحذير صادق
// ═══════════════════════════════════════════════════════════════════════

export function checkExpense(
    amount: number,
    ctx: FinancialContext,
    cat?: CategoryContext,
): Check[] {
    const checks: Check[] = [];

    if (amount <= 0) {
        checks.push({ severity: 'block', title: 'المبلغ لازم يكون أكبر من صفر' });
        return checks;
    }

    // ── كشف الأخطاء المطبعية ──────────────────────────────────────────
    // أكثر خطأ حقيقي ليس الإسراف، بل كتابة 1200 بدل 120.
    if (cat && cat.averageEntry > 0 && amount >= cat.averageEntry * 3) {
        const likely = Math.round(amount / 10);
        checks.push({
            severity: 'info',
            title: `${formatCurrency(amount)} على ${cat.name}؟`,
            detail: `متوسطك المعتاد ${formatCurrency(cat.averageEntry)}.`,
            suggestion: { label: `تقصد ${formatCurrency(likely)}؟`, value: likely },
        });
    } else if (ctx.monthlyIncome > 0 && amount >= ctx.monthlyIncome / 2) {
        checks.push({
            severity: 'info',
            title: `${formatCurrency(amount)}؟ هذا نص دخلك الشهري`,
            detail: 'تأكّد من الرقم قبل الحفظ.',
        });
    }

    // ── تجاوز ميزانية الفئة ───────────────────────────────────────────
    if (cat && cat.budget > 0) {
        const after = cat.spent + amount;
        if (after > cat.budget) {
            checks.push({
                severity: 'warn',
                title: `بتتجاوز ميزانية ${cat.name} بـ ${formatCurrency(after - cat.budget)}`,
                detail: `${formatCurrency(after)} من ${formatCurrency(cat.budget)}`,
            });
        } else if (after >= cat.budget * 0.85) {
            checks.push({
                severity: 'info',
                title: `بتوصل ${Math.round((after / cat.budget) * 100)}٪ من ميزانية ${cat.name}`,
                detail: `يتبقّى ${formatCurrency(cat.budget - after)} لباقي الشهر`,
            });
        }
    }

    // ── المتبقي يصير سالباً ───────────────────────────────────────────
    const after = availableToSpend(ctx) - amount;
    if (after < 0) {
        checks.push({
            severity: 'danger',
            title: `المتبقي لك بيصير ${formatCurrency(after)}`,
            detail: 'يعني بتصرف من فلوس محجوزة لالتزاماتك. المصروف بينحفظ — بس انتبه.',
        });
    } else if (ctx.daysUntilSalary > 0) {
        const dailyAfter = Math.floor(after / ctx.daysUntilSalary);
        if (dailyAfter < 2000) {
            // أقل من 20 ر.س يومياً
            checks.push({
                severity: 'warn',
                title: `بيتبقّى لك ${formatCurrency(dailyAfter)} يومياً فقط`,
                detail: `على مدى ${ctx.daysUntilSalary} يوم حتى الراتب.`,
            });
        }
    }

    return checks;
}

// ═══════════════════════════════════════════════════════════════════════
//  ٢ · الميزانية — تُمنع إذا تجاوز المجموع الدخل
// ═══════════════════════════════════════════════════════════════════════

export function checkBudget(
    newAmount: number,
    previousAmount: number,
    ctx: FinancialContext,
): Check[] {
    const checks: Check[] = [];

    if (newAmount < 0) {
        checks.push({ severity: 'block', title: 'الميزانية ما تكون سالبة' });
        return checks;
    }

    const totalAfter = ctx.budgetedTotal - previousAmount + newAmount;
    const capacity = availableToBudget(ctx);
    const over = totalAfter - capacity;

    if (over > 0) {
        checks.push({
            severity: 'block',
            title: `تجاوزت المتاح بـ ${formatCurrency(over)}`,
            detail:
                `دخلك ${formatCurrency(ctx.monthlyIncome)} ناقص التزاماتك ` +
                `${formatCurrency(ctx.obligations)} = ${formatCurrency(capacity)} متاح للتوزيع.`,
            suggestion: { label: `وزّع ${formatCurrency(newAmount - over)}`, value: newAmount - over },
        });
        return checks;
    }

    const left = capacity - totalAfter;
    if (left > 0) {
        checks.push({
            severity: 'info',
            title: `يتبقّى ${formatCurrency(left)} غير مخصّص`,
            detail: 'تقدر تضمّه لهدف ادخار بدل ما يضيع بلا خطة.',
        });
    }

    return checks;
}

// ═══════════════════════════════════════════════════════════════════════
//  ٣ · الادخار — يُمنع إذا كان المطلوب شهرياً يفوق المتاح
// ═══════════════════════════════════════════════════════════════════════

export function checkSavingsGoal(
    targetAmount: number,
    currentAmount: number,
    monthsToTarget: number,
    ctx: FinancialContext,
): Check[] {
    const checks: Check[] = [];

    if (targetAmount <= currentAmount) {
        checks.push({ severity: 'block', title: 'المبلغ المستهدف لازم يكون أكبر من المدّخر حالياً' });
        return checks;
    }
    if (monthsToTarget < 1) {
        checks.push({ severity: 'block', title: 'التاريخ المستهدف لازم يكون في المستقبل' });
        return checks;
    }

    const needed = Math.ceil((targetAmount - currentAmount) / monthsToTarget);
    const capacity = availableToBudget(ctx);
    const share = ctx.monthlyIncome > 0 ? (needed / ctx.monthlyIncome) * 100 : 0;

    if (needed > capacity) {
        // مستحيل حسابياً — نمنع، لكن نعطي بديلاً قابلاً للتنفيذ
        const feasibleMonths = capacity > 0
            ? Math.ceil((targetAmount - currentAmount) / capacity)
            : 0;

        checks.push({
            severity: 'block',
            title: `تحتاج ${formatCurrency(needed)} شهرياً، والمتاح ${formatCurrency(capacity)}`,
            detail: 'الهدف بهذا التاريخ غير ممكن بدخلك الحالي.',
            suggestion: feasibleMonths > 0
                ? { label: `مدّ المدة إلى ${feasibleMonths} شهر`, value: feasibleMonths }
                : undefined,
        });
        return checks;
    }

    if (share > 30) {
        checks.push({
            severity: 'warn',
            title: `${formatCurrency(needed)} شهرياً — ${Math.round(share)}٪ من دخلك`,
            detail: 'نسبة عالية. مدّ المدة يخفّف الضغط الشهري كثيراً.',
        });
    } else {
        checks.push({
            severity: 'info',
            title: `تحتاج تدّخر ${formatCurrency(needed)} شهرياً`,
            detail: `${Math.round(share)}٪ من دخلك — نسبة معقولة.`,
        });
    }

    return checks;
}

// ═══════════════════════════════════════════════════════════════════════
//  ٤ · الالتزامات (فاتورة ثابتة أو قسط) — تُمنع إذا فاقت الدخل
// ═══════════════════════════════════════════════════════════════════════

export function checkCommitment(
    monthlyAmount: number,
    previousAmount: number,
    ctx: FinancialContext,
): Check[] {
    const checks: Check[] = [];

    if (monthlyAmount <= 0) {
        checks.push({ severity: 'block', title: 'المبلغ الشهري لازم يكون أكبر من صفر' });
        return checks;
    }

    const obligationsAfter = ctx.obligations - previousAmount + monthlyAmount;

    if (obligationsAfter > ctx.monthlyIncome) {
        checks.push({
            severity: 'block',
            title: `التزاماتك بتصير ${formatCurrency(obligationsAfter)} ودخلك ${formatCurrency(ctx.monthlyIncome)}`,
            detail: 'ما فيه مجال لهذا الالتزام. راجع التزاماتك الحالية أو حدّث دخلك.',
        });
        return checks;
    }

    const share = ctx.monthlyIncome > 0 ? (obligationsAfter / ctx.monthlyIncome) * 100 : 0;

    if (share > 70) {
        checks.push({
            severity: 'danger',
            title: `التزاماتك بتصير ${Math.round(share)}٪ من دخلك`,
            detail: `يتبقّى ${formatCurrency(ctx.monthlyIncome - obligationsAfter)} لكل مصاريفك الشهرية.`,
        });
    } else if (share > 50) {
        checks.push({
            severity: 'warn',
            title: `التزاماتك بتصير ${Math.round(share)}٪ من دخلك`,
            detail: 'المعدّل الصحي عادةً أقل من 50٪.',
        });
    }

    return checks;
}

// ═══════════════════════════════════════════════════════════════════════
//  ٥ · الدخل — لا سقف، لكن كشف الأخطاء المطبعية
// ═══════════════════════════════════════════════════════════════════════

export function checkIncome(amount: number, recurringIncome: number): Check[] {
    const checks: Check[] = [];

    if (amount <= 0) {
        checks.push({ severity: 'block', title: 'المبلغ لازم يكون أكبر من صفر' });
        return checks;
    }

    if (recurringIncome > 0 && amount >= recurringIncome * 10) {
        checks.push({
            severity: 'info',
            title: `${formatCurrency(amount)}؟`,
            detail: `دخلك المعتاد ${formatCurrency(recurringIncome)}.`,
            suggestion: {
                label: `تقصد ${formatCurrency(Math.round(amount / 10))}؟`,
                value: Math.round(amount / 10),
            },
        });
    }

    return checks;
}

// ═══════════════════════════════════════════════════════════════════════
//  أدوات مساعدة للواجهة
// ═══════════════════════════════════════════════════════════════════════

/** هل يوجد ما يمنع الحفظ؟ */
export function isBlocked(checks: Check[]): boolean {
    return checks.some((c) => c.severity === 'block');
}

/** هل نحتاج ضغطة تأكيد إضافية قبل الحفظ؟ */
export function needsConfirm(checks: Check[]): boolean {
    return checks.some((c) => c.severity === 'danger');
}

/** أعلى درجة خطورة موجودة — لتلوين الحافة أو الزر. */
export function topSeverity(checks: Check[]): Severity | null {
    const order: Severity[] = ['block', 'danger', 'warn', 'info'];
    return order.find((s) => checks.some((c) => c.severity === s)) ?? null;
}

/** أصناف Tailwind لكل درجة — مصدر واحد للألوان. */
export const SEVERITY_STYLES: Record<Severity, { box: string; icon: string }> = {
    info: { box: 'border-border bg-secondary text-foreground/75', icon: 'text-muted-foreground' },
    warn: { box: 'border-warning/30 bg-warning/10 text-warning-text', icon: 'text-warning' },
    danger: { box: 'border-destructive/30 bg-destructive/10 text-destructive', icon: 'text-destructive' },
    block: { box: 'border-destructive bg-destructive/10 text-destructive', icon: 'text-destructive' },
};
