# دفعة تصحيحات صفحة الالتزامات

> كل الملاحظات بعد الاختبار: الأيقونات · الاستحقاق · التنبيهات · القسط اليدوي ·
> الأخطاء تحت الحقول · زر الحفظ · والمزامنة الغائبة مع لوحة التحكم.

---

## السبب الجذري لأهم مشكلة

**«الالتزامات ما تظهر في لوحة التحكم»** ليست مشكلة واجهة — `DashboardController`
لا يزال يقرأ من جدولَي `bills` و `installments` القديمين، ولا يعرف بجدول
`commitments` أصلاً. لذلك أي التزام جديد لا يظهر لا في الأرقام ولا في التقويم.

**«ايقونة الفاتورة والايجار ثلاث نقاط»** نفس النوع من الخلل: `commitments.ts`
يطلب أيقونة اسمها `receipt` و `home`، وخريطة `ICONS` في `category-icons.ts`
لا تحتوي على أيّهما، فتسقط الاثنتان على `ellipsis` (النقاط الثلاث).

---

## الملفات الجاهزة في الحزمة (استبدل بها الموجود)

```
resources/js/lib/commitments.ts                    ← نوعا استحقاق فقط + تنبيه واحد + rent → house
resources/js/components/AddCommitmentSheet.svelte  ← إعادة بناء كاملة
resources/js/pages/Commitments.svelte              ← زر «إضافة التزام» داخل التدفّق
```

---

## الرسالة — الصقها في opencode

> **ابدأ التنفيذ الآن.** دفعة تصحيحات لصفحة الالتزامات.
> ملفات `resources/js/lib/commitments.ts` و `AddCommitmentSheet.svelte` و
> `pages/Commitments.svelte` **مستبدَلة بالفعل — لا تعدّلها ولا تعيد كتابتها**.
> مهمّتك البنود التالية:
>
> ═══════════════════════════════════════════════════
>
> ### 1 · الأيقونات الناقصة — سبب «النقاط الثلاث»
>
> في `resources/js/lib/category-icons.ts` أضف:
>
> ```ts
> import Receipt from 'lucide-svelte/icons/receipt';
> import FileText from 'lucide-svelte/icons/file-text';
> ```
>
> وفي خريطة `ICONS` أضف `receipt: Receipt` و `'file-text': FileText`،
> وفي `ICON_LABELS` أضف `receipt: 'فاتورة'` و `'file-text': 'مستند'`.
>
> ثم في `app/Services/CommitmentService.php` غيّر `KIND_ICON` إلى:
>
> ```php
> 'bill' => 'receipt', 'rent' => 'house',
> 'installment' => 'credit-card', 'subscription' => 'repeat',
> ```
>
> (`home` غير موجودة في الخريطة — الصحيح `house`.)
> وشغّل migration يصلح صفوف `commitments` المحفوظة بـ `icon='home'` → `'house'`.
>
> ### 2 · المزامنة مع لوحة التحكم — الإصلاح الأهم
>
> `DashboardController` لا يزال يقرأ `bills` و `installments`. استبدلها بالكامل:
>
> - احذف `$billsDue` · `$billsCount` · `$installmentsMonthly` · `$installmentsCount`
>   وكل استعلامات `$user->bills()` و `$user->installments()`.
> - استخدم `CommitmentService::for($user)` وأضف إلى `stats`:
>   - `commitmentsTotal` = `obligationsForPeriod()`
>   - `commitmentsReserved` = `reservedForPeriod()`
>   - `commitmentsPaid` = مدفوعات فترة الراتب الحالية
>   - `commitmentsDueSoon` = `dueSoonCount(7)`
> - **شريط التدفّق** في اللوحة: قطعة باسم «التزامات» بلون `var(--chart-7)`
>   بقيمة `commitmentsTotal`، و«المتبقي لك» = الدخل − المصاريف − الالتزامات − الادخار.
> - **`calendarEvents()`**: احذف حلقتَي `bills` و `installments` واستبدلهما
>   بحلقة واحدة على `commitments()->active()` تستخدم
>   `CommitmentService::dueDateFor()`، مع `kind` = نوع الالتزام و`label` = اسمه.
>   بهذا يظهر أي التزام في **التقويم المالي فور إضافته**.
> - `$hasData` يشمل `$user->commitments()->exists()` بدل `bills`/`installments`.
>
> ### 3 · «احجزه من ميزانيتي» لازم يكون فعّالاً
>
> تأكّد أن `reserve_in_budget` يُحفظ فعلاً من الطلب (لا يُتجاهل)، وأن
> `CommitmentService::reservedForPeriod()` تحترمه، وأن قيمته تنعكس مباشرة
> على «المتبقي لك» في اللوحة. أضف اختباراً: التزام بـ `reserve_in_budget=false`
> **لا** يغيّر «المتبقي لك»، وبـ `true` ينقصه بمقداره.
>
> ### 4 · القسط الشهري يكتبه المستخدم
>
> الأقساط الواقعية فيها رسوم وفوائد، فلا تساوي المبلغ ÷ الأشهر.
>
> - أضف عمود `monthly_amount` أو استخدم `amount` مباشرة كقيمة القسط الشهري
>   **كما أرسلها المستخدم**.
> - **احذف** `calculateInstallmentMonthly()` من مسار الحفظ ولا تفرض القسمة.
> - القاعدة الجديدة: `amount` (القسط الشهري) مطلوب للأقساط، ويُستخدم كما هو.
> - `total_amount` يبقى للعرض («باقي X» و«يخلص في…») ولا يُشترط أن يساوي
>   `amount × months_count` — الفرق يُعرض كمعلومة لا كخطأ.
> - `BudgetGuard::assertCommitmentFits` يستقبل **القسط الشهري المُرسَل**.
>
> ### 5 · موعد الاستحقاق — خياران فقط
>
> - احذف `fixed_date` من التحقّق ومن الواجهة والقاعدة:
>   `'due_type' => ['required', 'in:salary_day,month_day']`.
> - migration يحوّل أي صف `due_type='fixed_date'` إلى `month_day` بـ
>   `due_day = day(due_date)`، ثم أسقط عمود `due_date`.
>
> ### 6 · التنبيه واحد لا ثلاثة
>
> - استبدل `notify_before` · `notify_on_due` · `notify_late` بعمود واحد
>   `notify_when` enum(`before_3`,`on_due`,`none`) افتراضيّه `before_3`.
> - migration يحوّل الصفوف القديمة (أي صف فيه `notify_before=true` → `before_3`،
>   وإلا `on_due`، وإلا `none`).
> - أمر `commitments:post-due` يقرأ العمود الجديد.
>
> ### 7 · الاسم مطلوب — بلا لوحة حمراء
>
> `name` مطلوب في الخادم أيضاً (`required|string|max:60`)، ورسالته:
> «الاسم مطلوب — بدونه لن تعرف الالتزام في القائمة».
> الواجهة تعرضها **تحت الحقل** لا أسفل الصفحة (منفَّذ في اللوح الجديد).
>
> ### 8 · التحقّق من الحفظ والإشعار
>
> بعد `store` الناجح: `redirect()->back()` مع
> `flash('success', 'تمت إضافة {النوع} «{الاسم}»')`، وتأكّد أن
> `flash-toast.ts` يعرضها كـ `toast.success` — المستخدم لم يكن يرى أي تأكيد.
>
> ### 9 · تحقّق
>
> `php artisan migrate` ← `npm run build` ← `php artisan test --compact`.
> ثم: أضف التزاماً بيوم استحقاق 12 → لازم يظهر **فوراً** في التقويم المالي
> في اللوحة، وينقص «المتبقي لك» بمقداره.
>
> ═══════════════════════════════════════════════════
>
> اقرأ `AGENTS.md` و `DESIGN.md`. المبالغ بالهللات.
> بعد الانتهاء اعرض ملخّص الملفات المتغيّرة.

---

## اختبر بعدها

| # | الاختبار | المتوقع |
|---|---|---|
| 1 | افتح لوح الإضافة | أيقونات فاتورة/إيجار **صحيحة** لا نقاط ثلاث |
| 2 | افتحه بدون كتابة شي | **لا نص أحمر** في الأسفل |
| 3 | اضغط حفظ فارغاً | خطأ **تحت كل حقل** ناقص |
| 4 | موعد الاستحقاق | **خياران فقط** · اختيار يوم = قائمة مرتّبة |
| 5 | اختر يوم 12 واحفظ | يظهر في **التقويم المالي** في اللوحة |
| 6 | نبّهني | **خيار واحد** فقط يُختار |
| 7 | قسط 36,000 / 36 شهر | تقدر تكتب القسط الشهري **بنفسك** |
| 8 | زر الحفظ | **ظاهر دائماً** أسفل اللوح مهما طال النموذج |
| 9 | بعد الحفظ | **إشعار** «تمت إضافة …» |
| 10 | التزام محجوز | «المتبقي لك» في اللوحة **ينقص** بمقداره |

```
git add -A
git commit -m "fix(commitments): dashboard sync, icons, due types, manual installment"
git push
```
