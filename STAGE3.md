# المرحلة 3 — الهيكل الجديد + محرّك القواعد المالية

## ما وجدته في الريبو

فحصت `redesign/ui` بعد آخر رفع. opencode أنجز أكثر بكثير من قاعدة بيانات:

| ✅ منجَز | التفاصيل |
|---|---|
| معالج الإعداد | `OnboardingController` + `Onboarding.svelte` + 3 مسارات |
| التقارير | `ReportsController` + تصدير |
| المساعد الذكي | `AssistantController` + جدول `assistant_messages` + الصفحة |
| **محرّك المتكررات** | `RecurringTransactionService` + أمر مجدول يومياً |
| ربط المعاملات بمصدرها المتكرر | migration مخصّصة |

**وخدمة المتكررات مكتوبة بجودة إنتاجية فعلاً** — `DB::transaction` مع `lockForUpdate`، فحص عدم التكرار قبل الإنشاء، وحلقة تعويض الأيام الفائتة. هذا كود سليم، لا تعيد كتابته.

---

## الفجوات الخمس المتبقية

| # | الفجوة | الخطورة |
|---|---|---|
| 1 | **صفر تحقّق مالي** — تقدر تحفظ ادخار 50,000 ودخلك 8,000 | 🔴 حرجة |
| 2 | الإضافة السريعة **تنقلك لصفحة** بدل لوح لوحة الأرقام | 🟠 عالية |
| 3 | السايدبار القديم بـ 9 عناصر | 🟠 عالية |
| 4 | أيقونة المساعد لسّه `Sparkles` الافتراضية | 🟡 متوسطة |
| 5 | الصفحات غير مدموجة (مصاريف/دخل · فواتير/أقساط) | 🟡 متوسطة |

---

## الملفات الجاهزة في هذه الحزمة

```
app/Services/BudgetGuard.php                       ← التحقّق الملزم (سيرفر)
resources/js/lib/money-rules.ts                    ← نفس القواعد للعرض الفوري
resources/js/components/QuickAddSheet.svelte       ← لوح الإضافة السريعة
resources/js/components/AppRail.svelte             ← الشريط الحي الهجين
resources/js/components/icons/AiAssistantIcon.svelte ← عملات + شرارة
```

**لا تعيد كتابة أي منها.** مطابقة للنماذج البصرية المعتمدة.

---

## قواعد المال — القرارات المعتمدة

> **المبدأ الحاكم: الحقائق تُسجَّل، والخطط تُراجَع.**
>
> المصروف واقعة حصلت — لا يُمنع أبداً. منعه يحوّل أرقام التطبيق إلى خيال،
> لأن المستخدم ببساطة لن يسجّله ثم يهجر التطبيق.
>
> الميزانية والادخار والالتزامات قرارات مستقبلية — تُمنع إذا كانت
> **مستحيلة حسابياً**، لا مجرد محفوفة بالمخاطر.

| الحالة | السلوك |
|---|---|
| مصروف يتجاوز ميزانية فئته | ⚠️ تحذير + يُحفظ |
| مصروف يخلّي المتبقي سالباً | 🔴 تحذير قوي + **ضغطة تأكيد** + يُحفظ |
| مصروف ≥ 3× متوسط الفئة | ❓ تأكيد ذكي: «تقصد 120؟» مع زر تصحيح |
| مجموع الميزانيات > المتاح | 🚫 **يُمنع** + اقتراح المبلغ الصحيح |
| هدف ادخار يفوق المتاح شهرياً | 🚫 **يُمنع** + اقتراح مدّ المدة |
| التزام يخلّي المجموع > الدخل | 🚫 **يُمنع** |
| مجموع الميزانيات < المتاح | ℹ️ «يتبقّى X غير مخصّص» |

---

## الرسالة — الصقها في opencode

> نفّذ **المرحلة 3**. الملفات التالية موجودة وجاهزة — **لا تعيد كتابتها ولا تعدّل تصميمها**، فقط اربطها:
> `app/Services/BudgetGuard.php` · `resources/js/lib/money-rules.ts` · `resources/js/components/QuickAddSheet.svelte` · `resources/js/components/AppRail.svelte` · `resources/js/components/icons/AiAssistantIcon.svelte`
>
> ---
>
> **1 · فعّل التحقّق المالي في السيرفر**
>
> استخدم `BudgetGuard` في كل مسار كتابة:
> - `POST /budgets` → `BudgetGuard::for($user)->assertBudgetFits($newAmount, $previousAmount, $month)`
> - `POST /savings` → `assertSavingsGoalFits($target, $current, $targetDate)`
> - `POST /installments` و `POST /bills` (الثابتة فقط) → `assertCommitmentFits($monthlyAmount, $previousAmount)`
> - `POST /expenses` → **لا تمنع أبداً**. احفظ أولاً ثم `inspectExpense(...)` ومرّر النتيجة في `flash('warnings')` لتعرضها الواجهة.
>
> اكتب اختبارات feature لكل حالة منع وكل حالة تحذير.
>
> ---
>
> **2 · شغّل الإضافة السريعة فعلياً**
>
> عدّل `QuickAddFab.svelte`: بدل `router.visit('/expenses?new=1')`، يفتح `QuickAddSheet` مباشرة بالوضع المناسب (`expense` / `income`). الفاتورة والادخار تبقى انتقالاً للصفحة.
>
> `QuickAddSheet` يحتاج `context` و `categories` و `learned` — مرّرها كمشاركة عامة `quickAdd` من `HandleInertiaRequests::share()`:
> ```php
> 'quickAdd' => $request->user() ? [
>     'context' => Arr::only(BudgetGuard::for($request->user())->context(), [
>         'monthlyIncome','obligations','spent','budgetedTotal',
>     ]) + ['daysUntilSalary' => /* احسبها من users.salary_day */],
>     'categories' => /* id,name,icon,color,budget,spent,averageEntry */,
>     'lastCategoryId' => /* فئة آخر مصروف للمستخدم */,
>     'learned' => /* أكثر 3 تركيبات (مبلغ+فئة+وصف) تكراراً آخر 60 يوم */,
>     'recurringIncome' => /* مجموع الدخل المتكرر النشط */,
> ] : null,
> ```
>
> ⚠️ `averageEntry` = متوسط قيمة المصروف الواحد في تلك الفئة (لا المجموع) — يعتمد عليه كشف الأخطاء المطبعية.
>
> ---
>
> **3 · استبدل السايدبار بالشريط الحي**
>
> في `AppSidebarLayout.svelte` استبدل `AppSidebar` بـ `AppRail`. احذف `AppSidebar.svelte` و `NavMain.svelte` نهائياً.
>
> `AppRail` يحتاج `stats` — أضفها للمشاركة العامة:
> ```php
> 'navStats' => [
>     'remaining' => ..., 'dailySafe' => ..., 'daysLeft' => ...,
>     'budgetUsedPct' => ..., 'transactionsCount' => ...,
>     'dueCommitments' => ..., 'savingsPct' => ...,
>     'incomeSplit' => [ ['key'=>'bills','pct'=>18,'color'=>'var(--chart-7)'], ... ],
> ],
> ```
>
> حالة التوسّع تُقرأ من كوكي `rail_expanded` وتُمرّر كـ prop أوّلي (لتفادي وميض التخطيط عند التحميل).
>
> ---
>
> **4 · ادمج الصفحات**
>
> - `/transactions` — صفحة واحدة بتبويبين: **مصاريف** و **دخل**. انقل منطق `Expenses.svelte` و `Income.svelte` إليها كتبويبين. أبقِ `/expenses` و `/income` كإعادة توجيه (301) للحفاظ على الروابط القديمة.
> - `/commitments` — صفحة واحدة بتبويبين: **فواتير** و **أقساط**، مبنية من `Bills.svelte` و `Installments.svelte`.
>
> ---
>
> **5 · الأيقونات**
>
> - المساعد الذكي: استبدل `Sparkles` بـ `AiAssistantIcon` في كل مكان.
> - الادخار: استبدل `PiggyBank` بـ `Vault` من lucide في كل مكان (التنقّل، الرقائق، الأيقونات) — يتفادى الالتباس مع أيقونة المساعد.
>
> ---
>
> **6 · الأقساط والفواتير — التوقيت**
>
> أضف لجدولَي `bills` و `installments` عمود `due_mode` (enum: `salary_day` أو `fixed_date`، افتراضي `fixed_date`).
> - `salary_day` → تاريخ الاستحقاق يُشتقّ من `users.salary_day` ويتحرّك معه تلقائياً.
> - `fixed_date` → يبقى ثابتاً كما هو.
>
> في نموذج الإضافة: رقاقتان «مع نزول الراتب» و «تاريخ محدّد».
>
> ---
>
> **7 · الأقساط في الرسم الدائري**
>
> `DashboardController` يضيف قطعة مستقلة اسمها **«أقساط»** لمجموع `monthly_amount` للأقساط النشطة، بلون `var(--chart-2)`. لا تُربط بأي فئة إنفاق ولا تُخصم من ميزانية فئة. إذا لم توجد أقساط، لا تظهر القطعة.
>
> ---
>
> **8 · تحقّق**
>
> `php artisan migrate` ثم `npm run build` ثم `php artisan test --compact`. أصلح أي خطأ.
>
> **قواعد ملزمة:** اقرأ `AGENTS.md` و `DESIGN.md` أولاً. **لا تعدّل** أي ملف في `resources/js/components/` عدا `QuickAddFab` و `AppSidebarLayout`. المبالغ بالهللات ولا تُقسم على 100 في الباك إند. الحالة لا تُنقل باللون وحده.
>
> وقف عندي بعد كل بند من 1 إلى 3 واعرض الملفات المتغيّرة.

---

## بعد التنفيذ

```
php artisan migrate
npm run build
composer run dev
```

**اختبر القواعد يدوياً:**
1. افتح `/budgets` وحاول تحط ميزانية مجموعها أكبر من دخلك ← لازم **يُمنع** برسالة تقول كم التجاوز.
2. افتح `/savings` وحاول هدف 100,000 خلال 3 أشهر ← لازم **يُمنع** مع اقتراح مدّ المدة.
3. من الزر العائم، أضف مصروف أكبر من المتبقي ← لازم **يُحفظ** بعد تحذير أحمر وضغطة تأكيد.
4. اختر فئة «أخرى» بدون وصف ← زر الحفظ **معطّل**.

```
git add -A
git commit -m "feat: stage 3 - rail nav, quick add sheet, money rules"
git push
```
