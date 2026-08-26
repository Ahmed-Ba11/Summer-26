# المرحلة 3 — قاعدة البيانات والترابط

**النموذج:** القوي · **السبب:** مرحلة حسّاسة، خطأ فيها يفسد كل الأرقام بعدها.

**الهدف:** الأعمدة والجداول والعلاقات التي تعتمد عليها كل المراحل التالية.

## قواعد ثابتة — التزم بها حرفياً في هذه المرحلة

**التصميم (لا تحتاج فتح `DESIGN.md` — كل ما يلزمك هنا):**
- بطاقات: `rounded-2xl border border-border bg-card shadow-xs`
- مسافات: `p-3 gap-3` جوال · `md:p-6 md:gap-5` ديسكتوب
- عناوين `text-[14px] font-semibold` · نص مساند `text-[11.5px] text-muted-foreground`
- **لا نص أصغر من 11px · لا إيموجي · لا تمرير أفقي · كل زر `min-h-11`**
- تنسيقات منطقية فقط (`ms/me/ps/pe/start/end`) — ممنوع `left/right`
- **أرقام لاتينية دائماً** — لا `٠١٢٣` ولا تواريخ عربية-هندية
- أيقونة في بطاقة: حاوية 38–40px والرمز 19–20px · في زر أو رأس: 17–18px
  · شريط التنقّل 24px · زر «+» 58px · `stroke-width: 1.9`
- **إحساس تطبيق جوال**: كل مودال لوح سفلي، أزرار متجاورة لا مكدّسة،
  `active:scale-[.98]` عند الضغط، احترام `env(safe-area-inset-*)`
- كل المبالغ **بالهللات (integer)** — التحويل في `@/lib/format` فقط

**اقتصاد السياق:**
- لا تقرأ المشروع كاملاً. ابحث أولاً (grep) ثم افتح الملفات التي ظهرت فقط.
- لا تفتح ملفاً «لفهم المشروع أكثر». لا تقرأ `node_modules` · `vendor` · `dist` · lock files.
- لا تعِد قراءة ملف قرأته في هذه الجلسة إلا إن تغيّر.
- لا تشرح الكود لي ولا تعرض محتوى الملفات ولا diff كامل — نفّذ مباشرة.
- لا تعمل refactor خارج نطاق هذه المرحلة.
- استعمل المكوّنات الموجودة، ولا تعيد كتابة مكوّن المطلوب إعادة استخدامه فقط.
- ردودك أثناء التنفيذ مختصرة جداً.

**إن فشل نفس الإصلاح مرّتين:** توقّف، واذكر في ثلاثة أسطر: المشكلة · الملفات ·
ما جُرّب. لا تحاول ثالثة.

---

## ابدأ بالبحث عن

```
Schema::create('users      savings_goals      current_amount
increment(                 hasMany            cascadeOnDelete
```

**لا تفتح أي مكوّن واجهة في هذه المرحلة.** اقرأ: آخر migration للمستخدمين ·
`app/Models/*.php` · نموذج `SavingsGoal` والخدمة التي تودع فيه.

---

## التنفيذ

### 1 · `users` — أعمدة جديدة

| العمود | النوع | ملاحظات |
|---|---|---|
| `display_name` | string nullable | |
| `monthly_income` | bigInteger default 0 | هللات |
| `salary_day` | tinyInteger default 27 | 1..28 |
| `monthly_savings_target` | bigInteger default 0 | |
| `currency` | string(3) default 'SAR' | |
| `locale` | string(5) default 'ar' | ar / en |
| `theme` | enum(light,dark,system) default system | |
| `font_scale` | enum(sm,md,lg) default md | |
| `biometric_lock` | boolean default false | |
| `notify_due` · `notify_budget` · `notify_salary` | boolean default true | |
| `onboarding_step` | tinyInteger default 0 | 0..4 |

(`onboarding_completed_at` موجود — أبقِه.)

### 2 · جدول `salary_periods`

`user_id` · `period_key` (فريد مع المستخدم، مثل `2026-08`) · `starts_on` ·
`ends_on` · `income_total` · `expenses_total` · `commitments_total` ·
`savings_total` · `surplus` (موجب فائض / سالب عجز) ·
`surplus_action` enum(saved,rolled,split,pending) default pending · `closed_at` nullable

**هذا ذاكرة التطبيق التاريخية** — منه يُبنى تقرير PDF والمقارنات بين الشهور.

### 3 · جدول `savings_deposits`

`user_id` · `savings_goal_id` · `amount` · `deposited_at` · `period_key`

**كل إيداع يمرّ من هنا** — امنع `increment` المباشر على `current_amount`
واستبدل كل موضع يستعمله. بدون هذا الجدول يستحيل معرفة «كم أُودع هذا الشهر»
فينكسر «المتبقي لك».

### 4 · الترابط

- `User` hasMany: incomes · expenses · budgets · commitments · savingsGoals ·
  savingsDeposits · salaryPeriods · recurringTransactions · categories
- `Commitment` hasMany payments · `SavingsGoal` hasMany deposits
- **كل** مفتاح أجنبي `cascadeOnDelete`
- **كل** استعلام مالي مقيَّد بـ `user_id` — لا تعتمد على المسار وحده

---

## التحقّق

```
php artisan migrate
php artisan test --compact --filter=Savings
```

واختبار واحد جديد: إيداع في هدف ادخار يُنشئ صفّاً في `savings_deposits`
ويحدّث `current_amount` معاً.

---

## عند الانتهاء

اعرض **فقط**:
1. ما نُفِّذ (نقاط قصيرة)
2. أسماء الملفات المتغيّرة
3. نتيجة التحقّق
4. أي شيء يمنع المرحلة التالية

ثم **توقّف. لا تبدأ المرحلة التالية.**

```
git add -A
git commit -m "feat(stage-3): financial schema and relationships"
```
