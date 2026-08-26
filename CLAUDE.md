# موفّر — Summer-26

تطبيق ميزانية شخصية بالعربية. Laravel + Inertia + Svelte 5 + Tailwind.

## خطة العمل الحالية

الدفعة الشاملة موزّعة على مراحل في `prompts/`. **اقرأ
`prompts/STAGE8-00-START.md` أولاً** — فيه ترتيب المراحل وقواعد التنفيذ.

نفّذ **مرحلة واحدة في كل جلسة**، ثم `/clear` قبل المرحلة التالية.

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
php artisan migrate    # الترحيلات
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
