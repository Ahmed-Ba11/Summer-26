# المساعد الذكي — ما يجب ضبطه قبل النشر

هذا الملف **توثيق لا تنفيذ**. لم يُعدَّل أي إعداد إنتاج من داخل المشروع.

`POST /assistant/stream` يبقي اتصالاً مفتوحاً حتى **٣٠٠ ثانية** ويرسل
بيانات تدريجياً. الافتراضات الجاهزة في معظم السيرفرات تكسر الاثنين: تقطع
الاتصال عند ٣٠ ثانية، وتبفّر الرد فيصل دفعةً واحدة عند النهاية. يكفي أن
تسقط طبقة واحدة ليموت البث في منتصفه.

---

## ١) متغيّرات البيئة

```dotenv
OPENCODE_BASE_URL=https://opencode.ai/zen/go/v1
OPENCODE_API_KEY=            # يُملأ يدوياً — لا يُكتب في الكود ولا في git
AI_MODEL=hy3

# لكلّها قيم افتراضية في config/ai.php، ولا يلزم ضبطها إلا للتغيير
AI_TIMEZONE=Asia/Riyadh      # «اليوم» و«أمس» بتوقيت المستخدم
AI_LIMIT_PER_HOUR=20
AI_LIMIT_PER_DAY=100
AI_HISTORY_MESSAGES=20
AI_TIMEOUT=300
```

`OPENCODE_BASE_URL` **يجب** أن ينتهي بـ`/v1`. المسار
`https://opencode.ai/inference/openai/v1` لا يعمل — يرجّع فارغاً.

---

## ٢) المهلة — أربع طبقات

| # | الطبقة | الموضع | الحالة |
|---|---|---|---|
| 1 | PHP | `AssistantStreamController::prepareRuntime()` — `set_time_limit(300)` | ✅ في الكود |
| 2 | عميل HTTP في `laravel/ai` | `#[Timeout(300)]` على `FinanceAssistant` | ✅ في الكود |
| 3 | المتصفّح | `AbortController` بمهلة ٣٠٠ ثانية | ✅ في الواجهة |
| 4 | سيرفر الويب | nginx · Apache · PHP-FPM | ⚠️ **يدوي — أدناه** |

### nginx

```nginx
location ~ \.php$ {
    fastcgi_read_timeout 300;
    fastcgi_buffering off;        # بدونها يصل الرد دفعةً واحدة عند النهاية
}

# لو كان nginx أمام تطبيق آخر (Octane / FrankenPHP)
location / {
    proxy_read_timeout 300;
    proxy_buffering off;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding on;
}
```

التطبيق يرسل `X-Accel-Buffering: no` مع كل استجابة بث، وهي وحدها كافية
لتعطيل تخزين nginx المؤقّت لهذا المسار — لكن `fastcgi_read_timeout` لا
بديل عنها.

### PHP-FPM

```ini
; www.conf
request_terminate_timeout = 300
```

قيمة أقلّ من ٣٠٠ تقتل العملية بصرف النظر عن `set_time_limit`.

### Apache

```apache
ProxyTimeout 300
```
مع `mod_proxy_fcgi`، وتعطيل `mod_deflate` على `text/event-stream`:
الضغط يبفّر المخرجات فيضيع البث.

### Cloudflare أو أي CDN

الوسيط الذي يبفّر SSE يلغي البث كلّه. الحل: قاعدة تتخطّى التخزين المؤقّت
على `/assistant/stream`، والتأكّد من أن **Auto Minify / Rocket Loader**
لا يمسّان `text/event-stream`. مهلة Cloudflare المجانية ١٠٠ ثانية لا
يمكن رفعها — الحلّ عندها إمّا Enterprise وإمّا تمرير هذا المسار خارج
الوكيل (grey-cloud على نطاق فرعي).

### Octane / FrankenPHP

`set_time_limit` و`ob_end_flush` تعملان تحت FrankenPHP بلا تعديل. تحت
**Swoole** تحديداً لا يعمل `echo` + `flush()` كما هنا؛ يلزم عندها
`Response::write()` من Swoole. المشروع حالياً لا يستخدم Octane، فلو
أُضيف لاحقاً فهذا الملف هو ما يجب مراجعته.

---

## ٣) الجلسات — شرط لا اقتراح

> **`SESSION_DRIVER` يجب ألّا يكون `file`.** الإعداد الحالي `database`،
> وهو صحيح. تحويله إلى `file` يكسر التطبيق كلّه أثناء كل رد من المساعد.

سائق `file` يقفل ملف الجلسة طوال الطلب (`flock` حصري في
`FileSessionHandler`). البثّ يبقي الطلب مفتوحاً حتى ٣٠٠ ثانية، وطوال
تلك المدة **كل** طلب آخر من نفس المستخدم ينتظر على القفل: التصفّح،
إضافة مصروف، حتى تسجيل الخروج. المستخدم يرى تطبيقاً متجمّداً ولا يربط
ذلك بالمساعد أصلاً، فيصعب تشخيصه.

`database` (الحالي) و`redis` كلاهما بلا قفل حصري. لو اضطُررت إلى `file`
لسببٍ ما، فالمخرج الوحيد هو `$request->session()->save()` قبل بدء البث
لتحرير القفل مبكّراً — وهو حلّ هشّ لأن أي كتابة لاحقة في الجلسة تُفقَد.
الأسلم ألّا تُغيّر السائق.

---

## ٤) حدّ الاستخدام

كل الاستدعاءات تمرّ بمفتاح API واحد مشترك، فمستخدم واحد مسيء يستهلك
حصّة الجميع.

- **٢٠ رسالة/ساعة** و**١٠٠ رسالة/يوم** لكل مستخدم، المفتاح `user()->id`
  لا الـIP (عدّة مستخدمين خلف NAT واحد يتقاسمون الـIP، والمستخدم الواحد
  يغيّره).
- القيمتان في `config('ai.assistant.limits')` لا في الكود، فتُعدَّلان
  بمتغيّر بيئة بلا نشر جديد.
- عند التجاوز: إطار `error` برسالة عربية داخل الشات ثم `done` — لا صفحة
  429 خام ولا انقطاع صامت.
- `RateLimiter` يستخدم `CACHE_STORE`، وهو `database` هنا. لو نُشر التطبيق
  على أكثر من خادم فاجعله `redis`، وإلا صار لكل خادم عدّاده وضُرب الحدّ
  في عدد الخوادم.

---

## ٥) متابعة الاستهلاك

بعد كل استدعاء يُكتب سطر في الـlog:

```
assistant.usage  {"user_id":1,"model":"hy3","prompt_tokens":7292,
                  "completion_tokens":301,"total_tokens":7593}
```

وأسطر التحذير والخطأ التي تستحقّ تنبيهاً:

| المفتاح | المعنى |
|---|---|
| `assistant.rate_limited` | مستخدم تجاوز حدّه |
| `assistant.stream.provider_error` | المزوّد أرجع خطأ |
| `assistant.stream.failed` | استثناء أثناء البث |
| `assistant.stream.aborted` | العميل أغلق الاتصال |
| `assistant.tool_call.missing_name` | ⚠️ **تغيّر سلوك المزوّد** |
| `assistant.tool_call.unknown_name` | ⚠️ الموديل نادى أداة غير موجودة |
| `assistant.tool_call.unparsable_arguments` | ⚠️ معطيات أداة وصلت مكسورة |

المفاتيح الثلاثة الأخيرة **يُتوقّع ألّا تظهر أبداً** مع `hy3`. ظهور أيّ
منها يعني أن استدعاء أداة وصل ناقصاً — انظر التوثيق داخل
`AssistantStreamController::detectMalformedToolCall()`.

---

## ٦) حدّ معروف: النبضة

`: ping` تُرسَل كل ١٥ ثانية عند **حدود الأحداث**. PHP هنا أحادي الخيط،
فأثناء انتظار أوّل رمز من المزوّد (٥–٦ ثوانٍ عادةً) يكون التنفيذ محجوباً
داخل قراءة شبكة ولا يعمل أيّ كود لنا. النبضة تغطّي الفجوات بين خطوات
الأدوات — وهي الأطول — ولا تغطّي حجباً واحداً يتجاوز ١٥ ثانية.

عملياً لم نرصد حجباً بهذا الطول مع `hy3`. لو ظهر، فالحلّ ليس ضبط
السيرفر بل نقل الاستدعاء إلى طابور مع بثّ عبر broadcasting.
