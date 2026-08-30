/**
 * تحويل Markdown إلى HTML آمن — للعرض **أثناء** البث لا بعده.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  كل ناتج للموديل يمرّ من هنا. لا `{@html}` في المشروع خارج هذا الملف.
 * ══════════════════════════════════════════════════════════════════════
 *
 * التعقيم ليس احتياطاً نظرياً: الموديل يعكس محتوى قادماً من قاعدة
 * البيانات — وصف عملية كتبه المستخدم بنفسه. من كتب وصفاً كـ
 * `<img src=x onerror=alert(1)>` ثم طلب عرض عملياته، يمرّ نصّه من
 * الموديل إلى `{@html}` مباشرةً. `DOMPurify` هو ما يمنع ذلك.
 *
 * ── لماذا instance واحد ──
 * `marked.setOptions()` عالمية، فاستدعاؤها من مكوّن يغيّر سلوك كل مكوّن
 * آخر. `new Marked({…})` هنا مرّةً واحدة، والجميع يستورد `renderMarkdown`.
 *
 * ── marked 18 ──
 * `parse()` **متزامنة** ما لم يُفعَّل الوضع async، ونحن لا نفعّله: البث
 * يحتاج ناتجاً فورياً لا وعداً. و`mangle`/`headerIds` أُزيلتا من الحزمة
 * منذ v5 فلا تُمرَّران — تمريرهما اليوم يرمي تحذيراً بلا فائدة.
 */

import DOMPurify from 'dompurify';
import { Marked } from 'marked';

const marked = new Marked({
    /** سطر جديد = `<br>` — أقرب لسلوك الشات من قاعدة Markdown الأصلية. */
    breaks: true,
    /** جداول وقوائم مهام — الوكيل يستعمل الجداول كثيراً. */
    gfm: true,
    walkTokens: (token) => {
        // `h1` و`h2` بحجم عنوان الصفحة، فيبتلعان تسلسل العناوين ويجعلان
        // فقاعة الرد تصرخ. نحصر العناوين في h3–h4 مهما طلب الموديل.
        if (token.type === 'heading') {
            token.depth = Math.min(Math.max(token.depth, 3), 4);
        }
    },
});

/**
 * روابط الموديل تُفتح في تبويب جديد.
 *
 * `noopener` ليس تجميلاً: بدونه تحصل الصفحة المفتوحة على `window.opener`
 * وتستطيع توجيه تبويبنا إلى صفحة تصيّد.
 */
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank');
        node.setAttribute('rel', 'noopener noreferrer');
    }
});

/** عدد مرّات ورود نصّ ثابت. */
function countOf(text: string, needle: string): number {
    return text.split(needle).length - 1;
}

/**
 * يغلق ما فُتح ولم يُغلق بعد.
 *
 * أثناء البث تصل العناصر مبتورة: ` ``` ` بلا خاتمة، `**` بلا نظير.
 * بلا معالجة تظهر الرموز الخام في الواجهة ثم تختفي فجأة — وميضٌ مزعج
 * وقفزٌ في التخطيط. نغلقها مؤقّتاً فتُرندَر منسّقةً من أول حرف.
 */
function closeOpenMarks(text: string): string {
    // كتلة كود مفتوحة تبتلع كل ما بعدها، فتُعالَج أولاً ووحدها: داخل
    // الكود لا معنى لـ`**` ولا لغيرها.
    if (countOf(text, '```') % 2 === 1) {
        return `${text}\n\`\`\``;
    }

    // علامة نصفُها وصل: `صرفت *` والنجمة الثانية في الحزمة التالية.
    // إغلاقها يعطي `**` فارغةً تظهر خاماً لإطار كامل، وحذفها يخفيها
    // حتى تكتمل — وهو ما لا يراه المستخدم أصلاً.
    let out = text.replace(/[*_`~]+$/, '');

    const openBold = countOf(out, '**') % 2 === 1;
    // بعد استبعاد نجوم العريض يبقى ما هو مائل حقاً — وإلا حُسبت مرّتين.
    const openItalic = countOf(out.split('**').join(''), '*') % 2 === 1;
    // الكود المضمّن، بعد استبعاد الأسيجة الثلاثية.
    const openCode = countOf(out.split('```').join(''), '`') % 2 === 1;

    if (!openBold && !openItalic && !openCode) {
        return out;
    }

    // المسافة قبل علامة الإغلاق تُبطلها: `**165 **` تبقى نجوماً خاماً في
    // CommonMark. نقصّ الفراغ الذيلي قبل الإغلاق — والفقرة التي تحوي
    // علامةً مفتوحة هي الأخيرة دائماً، فلا يُدمَج شيء بما قبله.
    out = out.trimEnd();

    if (openBold) {
        out += '**';
    }

    if (openItalic) {
        out += '*';
    }

    if (openCode) {
        out += '`';
    }

    return out;
}

/**
 * يحجب جدولاً لم يكتمل رأسه بعد.
 *
 * الجدول لا يمكن تحليله تدريجياً: `| التاريخ | المبلغ |` وحدها فقرةٌ
 * عادية، ثم يصل `|---|---|` فتنقلب جدولاً. المستخدم يرى في تلك اللحظة
 * سطر أنابيب مكسوراً ثم يقفز التخطيط.
 *
 * فنمسك آخر كتلة جدول حتى يصل سطر الفاصل، ثم نطلقها. الحجب يدوم
 * أجزاء من الثانية، والمكسب أن الجدول يظهر جدولاً من أول ظهوره.
 */
function holdIncompleteTable(text: string): string {
    const lines = text.split('\n');

    // الأسطر الفارغة في الذيل تُتجاهَل عند البحث عن الكتلة: `\n` وحده
    // يصل قبل الصفّ التالي، ولولا هذا لأفلت رأس الجدول من الحجب لحظة
    // وصول ذلك السطر الفاصل.
    let end = lines.length;

    while (end > 0 && lines[end - 1].trim() === '') {
        end--;
    }

    let start = end;

    while (start > 0 && lines[start - 1].trimStart().startsWith('|')) {
        start--;
    }

    if (start === end) {
        return text;
    }

    const header = lines[start];
    const separator = lines[start + 1] ?? '';

    // شرطان لا شرط واحد. الشكل وحده لا يكفي: `|---|` تحت رأسٍ من أربعة
    // أعمدة سطرُ فاصلٍ سليم الشكل لكنه لا يصنع جدولاً في GFM — يبقى
    // فقرةً بأنابيب خام حتى يكتمل. فنطابق عدد الخلايا أيضاً.
    const looksLikeSeparator =
        /^[\s|:-]+$/.test(separator) && separator.includes('--');
    const complete =
        looksLikeSeparator && cellCount(separator) === cellCount(header);

    return complete ? text : lines.slice(0, start).join('\n');
}

/** عدد خلايا صفّ جدول — بلا اعتداد بالأنبوبين الطرفيين. */
function cellCount(line: string): number {
    return line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').length;
}

/**
 * Markdown → HTML مُعقَّم، جاهز لـ`{@html}`.
 *
 * @param streaming - أثناء البث نغلق المفتوح ونحجب الجدول الناقص. عند
 *   الانتهاء تُستدعى بـ`false` فيُرندَر النصّ كما هو تماماً.
 */
export function renderMarkdown(text: string, streaming = false): string {
    if (!text) {
        return '';
    }

    const source = streaming ? closeOpenMarks(holdIncompleteTable(text)) : text;

    return DOMPurify.sanitize(marked.parse(source) as string, {
        USE_PROFILES: { html: true },
        // `target` ليس ضمن الافتراضي، والخطّاف أعلاه يضيفه بعد التعقيم.
        ADD_ATTR: ['target'],
    });
}
