# إضافة على المرحلة 3 — «من وين جاء المبلغ؟»

> اقرأ هذا **بعد** `STAGE3.md`. هذي إضافة تعدّل سلوك البند 1 والبند 2.

---

## المشكلة

في الوضع الحالي، لو دخل المستخدم 990 ر.س وسجّل مصروفاً 1,000، ينحفظ بصمت
ويصير «المتبقي لك» **−10 ر.س**.

هذا **ليس محاسبة، هذا خيال** — العشرة ريال جت من مكان ما، والتطبيق يسجّلها
كأنها خرجت من العدم.

لكن **المنع المجرّد كذب** أيضاً: المستخدم فعلاً صرفها. لو منعناه، ما راح
يسجّلها، وتضيع من التطبيق نهائياً.

---

## الحل: نقفل الطريق الصامت، ونفتح الطريق الصادق

لما يتجاوز المصروف المتبقي للصرف، لا نحذّر فقط — **نوقف ونسأل**:

> ⚠️ **يتجاوز المتبقي لك بـ 10 ر.س**
> من وين جاء هذا المبلغ؟ لازم نعرف عشان أرقامك تبقى صادقة.

| الخيار | الأثر في قاعدة البيانات |
|---|---|
| 🏦 **من مدخراتي** | `savings_goals.current_amount` ينقص فعلياً + عرض التأخير عن الهدف |
| 💵 **دخل ما سجّلته** | يُنشأ سجل في `incomes` بالمبلغ الناقص أو أكثر |
| 😐 **تجاوزت وأعرف** | يُحفظ المصروف بعلامة `funding_source = 'overspend'` |

**زر الحفظ معطّل حتى يختار مصدراً صالحاً.** لا يوجد طريق صامت.

**النتيجة:** «المتبقي لك» ما ينزل تحت الصفر إلا باختيار صريح وواعٍ من
المستخدم — وحينها يكون الرقم السالب **صادقاً** لأنه موسوم ومقصود.

> **ملاحظة:** تتبّع الديون (سلفة / قرض / بطاقة ائتمان) **مؤجّل**. لو أضفناه
> لاحقاً، يدخل كخيار رابع في نفس القائمة بلا تغيير في البنية.

---

## الملفات الجاهزة

```
app/Services/ExpenseFundingService.php                ← منطق التمويل (سيرفر)
resources/js/components/FundingSourcePicker.svelte    ← واجهة الاختيار
```

**لا تعيد كتابتهما.**

---

## الرسالة — الصقها في opencode

> إضافة على المرحلة 3. الملفان `app/Services/ExpenseFundingService.php` و
> `resources/js/components/FundingSourcePicker.svelte` موجودان وجاهزان —
> **لا تعيد كتابتهما ولا تعدّل تصميمهما**. اربطهما فقط.
>
> ---
>
> **1 · عمود جديد**
>
> migration يضيف لجدول `expenses` عمود `funding_source` (string, nullable) —
> القيم: `savings` · `unlogged_income` · `overspend` · `null` (لا تجاوز).
> أضفه لـ `$fillable` في `App\Models\Expense`.
>
> ---
>
> **2 · مسار حفظ المصروف**
>
> في `POST /expenses`، استبدل الإنشاء المباشر بـ:
> ```php
> $expense = ExpenseFundingService::for($request->user())->record([
>     'amount' => $amountInHalalas,
>     'category_id' => $validated['category_id'] ?? null,
>     'description' => $validated['description'] ?? null,
>     'expense_date' => $validated['date'],
>     'funding_source' => $request->input('funding_source'),
>     'savings_goal_id' => $request->input('savings_goal_id'),
>     'income_amount' => $request->integer('income_amount'),
>     'income_source' => $request->input('income_source'),
> ]);
> ```
>
> الخدمة ترمي `ValidationException` تلقائياً إذا كان فيه تجاوز بلا مصدر
> صالح — فلا تحتاج تحقّقاً إضافياً. وكل شي داخل `DB::transaction`، فالخصم
> من الادخار وإنشاء المصروف يحصلان معاً أو لا يحصلان إطلاقاً.
>
> ⚠️ **لا تستدعِ** `BudgetGuard::assertCommitmentFits` على المصاريف. المصروف
> لا يُمنع أبداً — الخدمة تطلب مصدراً فقط.
>
> ---
>
> **3 · المشاركة العامة**
>
> أضف لـ `quickAdd` في `HandleInertiaRequests::share()`:
> ```php
> 'fundableGoals' => ExpenseFundingService::for($request->user())->fundableGoals(),
> ```
>
> ---
>
> **4 · اربط الواجهة في `QuickAddSheet.svelte`**
>
> أ) استورد المكوّن وعرّف الحالة:
> ```svelte
> import FundingSourcePicker, { type Funding } from '@/components/FundingSourcePicker.svelte';
>
> let { fundableGoals = [] } = $props();   // أضفها لقائمة props الموجودة
> let funding = $state<Funding>({ source: null, savingsGoalId: null, incomeAmount: 0, incomeSource: '' });
>
> const shortfall = $derived(
>     mode === 'expense' ? Math.max(0, amount - availableToSpend(context)) : 0
> );
>
> const fundingReady = $derived(
>     shortfall === 0
>     || (funding.source === 'savings' && funding.savingsGoalId !== null)
>     || (funding.source === 'unlogged_income' && funding.incomeAmount >= shortfall)
>     || funding.source === 'overspend'
> );
> ```
>
> ب) عدّل `canSave` ليشمل `fundingReady`:
> ```svelte
> const canSave = $derived(amount > 0 && !blocked && fundingReady && !submitting);
> ```
>
> ج) اعرض المكوّن **قبل** كتلة «معاينة الأثر» مباشرة:
> ```svelte
> {#if shortfall > 0}
>     <FundingSourcePicker {shortfall} goals={fundableGoals} bind:value={funding} />
> {/if}
> ```
>
> د) أضف حقول التمويل إلى `router.post` في دالة `submit`:
> ```js
> funding_source: shortfall > 0 ? funding.source : undefined,
> savings_goal_id: funding.savingsGoalId ?? undefined,
> income_amount: funding.incomeAmount || undefined,
> income_source: funding.incomeSource || undefined,
> ```
>
> هـ) صفّر `funding` في دالة `reset()`.
>
> و) لما `shortfall > 0`، **لا تعرض** تحذير «المتبقي بيصير سالب» من
> `money-rules` — المكوّن الجديد يغطّيه، والتكرار يشوّش.
>
> ---
>
> **5 · اختبارات**
>
> اكتب feature tests:
> - مصروف يتجاوز المتبقي **بلا** `funding_source` → 422
> - مصروف بمصدر `savings` → رصيد الهدف نقص بمقدار العجز بالضبط
> - مصروف بمصدر `savings` ورصيد الهدف أقل من العجز → 422
> - مصروف بمصدر `unlogged_income` → سجل دخل جديد أُنشئ
> - مصروف بمصدر `unlogged_income` ومبلغ أقل من العجز → 422
> - مصروف بمصدر `overspend` → انحفظ و `funding_source = 'overspend'`
> - مصروف **لا** يتجاوز المتبقي → ينحفظ بلا مصدر و `funding_source = null`
>
> ---
>
> **6 · تحقّق**
>
> `php artisan migrate` ثم `npm run build` ثم `php artisan test --compact`.
>
> **قواعد ملزمة:** اقرأ `AGENTS.md` و `DESIGN.md` أولاً. لا تعدّل أي ملف في
> `resources/js/components/` عدا `QuickAddFab` و `QuickAddSheet`. المبالغ
> بالهللات. وقف عندي بعد ما تخلص.

---

## الاختبار اليدوي بعد التنفيذ

بحسابك الحالي (دخل 990):

1. اضغط الزر العائم ← اكتب **1,000** ← اختر فئة
2. لازم يظهر صندوق أحمر: **«يتجاوز المتبقي لك بـ …»** مع ثلاثة خيارات
3. **زر الحفظ معطّل** حتى تختار
4. اختر **«من مدخراتي»** ← اختر هدفاً ← لازم تشوف: `6,500 ← 6,490` وسطر التأخير
5. احفظ ← افتح `/savings` وتأكّد إن الرصيد نقص فعلاً

```
git add -A
git commit -m "feat: expense funding source"
```
