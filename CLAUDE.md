# موفّر — Summer-26

تطبيق ميزانية شخصية بالعربية. Laravel + Inertia + Svelte 5 + Tailwind.

## خطة العمل الحالية

الدفعة الشاملة موزّعة على مراحل في `prompts/`. **اقرأ
`prompts/STAGE8-00-START.md` أولاً** — فيه ترتيب المراحل وقواعد التنفيذ.

نفّذ **مرحلة واحدة في كل جلسة**، ثم `/clear` قبل المرحلة التالية.

## ممنوع — قاعدة البيانات

**ممنوع منعاً باتاً** تشغيل أيٍّ من هذه في هذا المشروع:

```
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

قاعدة التطوير فيها بيانات حقيقية لا نسخة احتياطية لها (ملف sqlite خارج
git)، وهذه الأوامر تُسقط الجداول فتضيع بلا رجعة. سبق أن ضاعت بها بيانات.

**أي تغيير في الجداول يكون بـmigration جديدة تحافظ على البيانات** —
`php artisan migrate` وحده. لا تراجُع عن ترحيل ولا إعادة بناء.

للتحقّق من أن الترحيل يعمل من الصفر: `php artisan test` كافٍ — الاختبارات
تبني قاعدة منفصلة بـ`RefreshDatabase` ولا تمسّ قاعدة التطوير.

## قواعد ثابتة

- **الأرقام لاتينية دائماً** (2026 لا ٢٠٢٦) — في كل الواجهة بلا استثناء.
- **المبالغ بالهللات** في كل الطبقات (قاعدة البيانات · الـAPI · الـprops).
  التحويل للريالات **عند العرض فقط** عبر `resources/js/lib/money.ts`.
- **كل مبلغ** يمرّ من `AmountSheet.svelte` — لا حقول نصّية للمبالغ.
- **كل تاريخ** يمرّ من `DateSheet.svelte` — لا `<input type="date">`.
- **كل يوم-من-الشهر** يمرّ من `DayOfMonthPicker.svelte` — لا قوائم 31 عنصراً.
- **كل لوح/مودال** مبني على `SheetShell.svelte` — لا بنى مخصّصة.
- الحد الأدنى لمساحة اللمس: `min-h-11` (44px).
- اتجاه الواجهة RTL.

## أوامر

```bash
npm run build          # بناء الواجهة
npm run dev            # تطوير
php artisan migrate    # الترحيلات — هذا وحده، اقرأ «ممنوع» أعلاه
php artisan test       # الاختبارات
```

## ملفات جاهزة — لا تُعاد كتابتها

```
public/icon.svg
public/fonts/*.woff2
resources/css/fonts.css
resources/js/lib/money.ts
resources/js/components/ui/{SheetShell,AmountSheet,DateSheet,DayOfMonthPicker}.svelte
```
