# المرحلة ٢ — تشغيل لوحة التحكم فعلياً

## وش المشكلة بالضبط

المرحلة ١ نجحت تماماً — تأكّدت بنفسي من الريبو:

| ✅ تم | الحالة |
|---|---|
| خط `IBM Plex Sans Arabic` | موجود في `app.blade.php` قبل `@vite` |
| نظام الألوان الجديد | موجود في `app.css` |
| إصلاحات RTL | تمّت |
| `lib/format.ts` | موجود |
| كل المكوّنات الجديدة (١١ مكوّن) | موجودة في `components/` |
| `Dashboard.svelte` الجديدة | موجودة |

**لكن اللوحة ما تشتغل، والسبب واحد:**

`routes/web.php` لسّه يرسل البيانات **بالأسماء القديمة**. نسخة اللوحة الجديدة تتوقّع أسماء مختلفة تماماً:

| ترسله الآن | تتوقّعه اللوحة |
|---|---|
| `monthlyExpenses` | `monthly` |
| `totalBillsDue` | `stats.bills` |
| `totalInstallmentsMonthly` | `stats.installments` |
| `totalSavings` | `stats.savings` |
| — | `stats.avgDaily`, `stats.daysLeft`, `stats.billsCount` |
| — | `calendarEvents` |
| — | `availableMonths` |
| — | **`hasData`** ← وهذا الأخطر |

`hasData` ما وصلها ← قيمتها الافتراضية `false` ← **الصفحة تعرض الحالة الفارغة بدل اللوحة كاملة**.

يعني: الواجهة الجديدة جاهزة، والباك إند يكلّمها بلغة قديمة.

**وبالنسبة للإيموجي:** لسّه موجود في `Budgets` و `Installments` و `Savings` — وهذا **متوقّع**، حذفه من ضمن هذي المرحلة.

---

## الملف الجاهز

`app/Http/Controllers/DashboardController.php` — **مكتوب بالكامل وجاهز**. فيه:

- كل الـ props بالأسماء الصحيحة
- فلتر شهر حقيقي (`?month=YYYY-MM`)
- حساب «الأيام حتى الراتب» و «متوسط الصرف اليومي»
- بناء أحداث التقويم المالي من الفواتير والأقساط ويوم الراتب
- **مُحوِّل إيموجي → أيقونة lucide مدمج** كشبكة أمان، فاللوحة تشتغل صح حتى قبل migration التحويل
- تعبئة ألوان الفئات من البالتة المعتمدة لو كانت ناقصة

انسخه إلى `app/Http/Controllers/` قبل ما تبدأ.

---

## الرسالة — الصقها في opencode

> نفّذ **المرحلة ٢**. الملف `app/Http/Controllers/DashboardController.php` موجود وجاهز — **لا تعيد كتابته ولا تعدّله**، فقط اربطه واشتغل على الباقي.
>
> **١ · اربط الكونترولر**
> في `routes/web.php`، احذف closure المسار `GET /dashboard` بالكامل (السطور ١٨ إلى ٧٨ تقريباً) واستبدله بـ:
> ```php
> Route::get('/dashboard', DashboardController::class)->name('dashboard');
> ```
> مع إضافة `use App\Http\Controllers\DashboardController;` في أعلى الملف.
>
> **٢ · أضف أعمدة المستخدم**
> اكتب migration يضيف لجدول `users`:
> - `salary_day` — tinyint، nullable، افتراضي `27`
> - `onboarding_completed_at` — timestamp، nullable
>
> وأضفهما لـ `$fillable` و `casts` في `App\Models\User`.
>
> **٣ · حوّل الإيموجي في قاعدة البيانات**
> اكتب migration يحوّل عمود `categories.icon` من الإيموجي إلى اسم أيقونة lucide، حسب الخريطة الموجودة في ثابت `EMOJI_TO_ICON` داخل `DashboardController`. أي قيمة غير معروفة تصير `'ellipsis'`.
> حدّث أيضاً `database/seeders` لو فيه فئات افتراضية بإيموجي — استخدم القائمة في `resources/js/lib/category-icons.ts` (ثابت `DEFAULT_CATEGORIES`).
>
> **٤ · مشاركة عامة للفواتير المستحقة**
> في `app/Http/Middleware/HandleInertiaRequests.php`، أضف لدالة `share()`:
> ```php
> 'dueBillsCount' => $request->user()
>     ? $request->user()->bills()
>         ->where('is_paid', false)
>         ->whereBetween('due_date', [now(), now()->addDays(7)])
>         ->count()
>     : 0,
> ```
> (يحتاجها `AppSidebar.svelte` لشارة الفواتير.)
>
> **٥ · حدّث نوع `NavItem`**
> في `resources/js/types/navigation.ts` أضف الحقلين الاختياريين:
> ```ts
> badge?: number;
> tag?: string;
> ```
>
> **٦ · اربط زر الإضافة السريعة**
> في `resources/js/layouts/app/AppSidebarLayout.svelte`، استورد `@/components/QuickAddFab.svelte` واعرضه مرة واحدة بعد المحتوى، فيظهر في كل الصفحات.
>
> **٧ · احذف الإيموجي من الواجهة نهائياً**
> في `Budgets.svelte` و `Installments.svelte` و `Savings.svelte`:
> - احذف مصفوفات الإيموجي (`const icons = ['🍔', ...]`) وحقول إدخال الإيموجي.
> - استبدلها بمنتقي أيقونات lucide يعتمد على `ICON_PICKER` و `ICON_LABELS` من `@/lib/category-icons`.
> - استبدل كل عرض للإيموجي بمكوّن `@/components/CategoryIcon.svelte`.
> - في `Budgets.svelte`، استبدل بطاقات الفئات الحالية بمكوّن `@/components/BudgetRow.svelte`.
>
> **٨ · أصلح باق موجود**
> في `Budgets.svelte`، دالة `handleBudgetSave` تبحث عن الفئة بالاسم:
> `categories.find(c => c.name === editingBudget.name)`
> هذا ينكسر عند تكرار الأسماء. مرّر `category_id` داخل كائن `BudgetRecord` من السيرفر واستخدمه مباشرة.
>
> **٩ · تحقّق**
> شغّل `php artisan migrate`، ثم `npm run build`، ثم `php artisan test --compact`. أصلح أي خطأ.
>
> **قواعد ملزمة:** اقرأ `AGENTS.md` و `DESIGN.md` أولاً. **لا تعدّل** أي ملف داخل `resources/js/components/` عدا `AppSidebarLayout` — كلها جاهزة ومطابقة للنموذج البصري. **لا تعدّل** `resources/js/pages/Dashboard.svelte`. المبالغ كلها بالهللات ولا تُقسم على ١٠٠ في الباك إند.
>
> وقف عندي بعد ما تخلص واعرض ملخّص الملفات المتغيّرة.

---

## بعد ما يخلص

**١ · شغّل:**
```
php artisan migrate
npm run dev
```

**٢ · افتح `localhost:8000/dashboard`** — لازم تكون **مسجّل دخول**.

> ⚠️ `localhost:8000/` هي الصفحة الترحيبية، **مو اللوحة**. لازم `/dashboard`.

**٣ · لو ظهرت لك «ابدأ بإعداد ميزانيتك»** — معناها كل شي شغّال، بس حسابك فاضي. سجّل دخل واحد ومصروف واحد وميزانية لفئة، وارجع للوحة.

**٤ · قارن بالنموذج:** افتح `docs/redesign/mockup.html` جنب الموقع.

**٥ · احفظ:**
```
git add -A
git commit -m "feat: stage 2 - wire dashboard + remove emoji"
```
