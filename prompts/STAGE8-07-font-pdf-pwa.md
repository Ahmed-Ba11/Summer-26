# المرحلة 7 — الخط · PDF · PWA

**النموذج:** متوسّط (MiniMax M3) · **السبب:** إعدادات وقوالب لا تصميم
واجهات. صعّد للنموذج القوي إن ظهرت مشاكل RTL معقّدة في dompdf.

نفّذها **ثلاث مهام مستقلّة** بالترتيب، وبينها `npm run build`.

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

## 7A · خط ثمانية

الملفات جاهزة: `public/fonts/*.woff2` و `resources/css/fonts.css`.

1. في `resources/css/app.css` **أول سطر** (قبل `@import 'tailwindcss'`):
   ```css
   @import './fonts.css';
   ```
2. في `@theme inline`:
   ```css
   --font-sans: 'Thmanyah Sans', 'IBM Plex Sans Arabic', system-ui, sans-serif;
   --font-display: 'Thmanyah Display', 'Thmanyah Sans', serif;
   ```
3. **احذف كل** خطوط `fonts.googleapis.com` من `app.css` و
   `resources/views/app.blade.php` — لم يعد التطبيق يحتاج أي خط خارجي.
4. في `<head>` قبل ملفات Vite:
   ```blade
   <link rel="preload" href="/fonts/thmanyahsans-Regular.woff2" as="font" type="font/woff2" crossorigin>
   <link rel="preload" href="/fonts/thmanyahsans-Bold.woff2" as="font" type="font/woff2" crossorigin>
   ```
5. خط العرض (`--font-display`) لاسم **موفّر** في `/welcome` فقط.
   **لا يُستعمل في أي نص صغير** — الخطوط ذات الزوائد تفقد وضوحها تحت 16px.
6. العائلة **بلا وزن 600**؛ `fonts.css` يعالجها بمدى `600 700` — لا تغيّرها
   ولا تعدّل ملفات الخط ولا تقلّمها (subsetting) — الترخيص يمنع أي تعديل.
7. في `DESIGN.md` بند الخطوط يصير:
   `Thmanyah Sans — أوزان 300/400/500/700/900 · اسم التطبيق فقط Thmanyah Display · ممنوع أي خط خارجي`

**تحقّق:** `npm run build` ثم في تبويب Network: ملفات `thmanyahsans-*.woff2`
تُحمَّل، ولا طلب واحد إلى `fonts.googleapis.com`.

---

## 7B · تقرير PDF «وين راحت فلوسك»

- `barryvdh/laravel-dompdf` + **قالب Blade مستقل** (لا مكوّنات Svelte)
- يُنشأ **تلقائياً** عند إقفال كل شهر راتب، ويُصدَّر يدوياً من الإعدادات
  أو صفحة التقارير لأي فترة
- المحتوى: الراتب · الالتزامات (مدفوع/محجوز) · المصاريف بالفئة مع النسب ·
  الادخار · الفائض/العجز وقرارك فيه · مقارنة بالشهر السابق · قائمة المعاملات
- عربي RTL بأرقام لاتينية · A4 جاهز للطباعة
- ضمّن الخط بمسار مطلق (dompdf لا يقرأ مسارات الويب):
  ```php
  src: url("{{ public_path('fonts/thmanyahsans-Regular.woff2') }}") format('woff2');
  ```
  وإن رفض dompdf صيغة woff2، استعمل ملفات `.otf` من حزمة الخط الأصلية.

**تحقّق:** صدّر تقريراً وافتحه — عربي سليم، أرقام لاتينية، كل الأقسام موجودة.

---

## 7C · PWA

- `public/manifest.json`: الاسم «موفّر» · `display: standalone` ·
  `theme_color: #2c4a6e` · `background_color: #f5f4f0` · `dir: rtl` ·
  `lang: ar` · أيقونات 192/512 + `maskable` (من `public/icon.svg`)
- service worker يخزّن الصفحة والأصول **و `/fonts/*.woff2`**، ويعرض شاشة
  «ما فيه اتصال» لطيفة
- `apple-touch-icon` + `apple-mobile-web-app-capable` + شاشة بداية
- `viewport-fit=cover` + `env(safe-area-inset-*)` في الشريط السفلي

**تحقّق:** ثبّت التطبيق على الجوال → يفتح بلا شريط متصفّح وبأيقونة موفّر.

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
git commit -m "feat(stage-7): font, pdf and pwa"
```
