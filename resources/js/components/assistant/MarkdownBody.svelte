<script lang="ts">
    /**
     * نصّ المساعد منسّقاً — المكان الوحيد في المشروع الذي يستعمل `{@html}`.
     *
     * الـHTML الداخل هنا **معقَّم مسبقاً** في `lib/markdown.ts`. لا تمرّر
     * إليه شيئاً لم يمرّ من `renderMarkdown()`، ولا تستعمل `{@html}` في
     * مكوّن آخر: بوّابة واحدة أسهل في الحراسة من عشر.
     *
     * الستايلات كلها تحت `.md-body` بـ`:global` — الـHTML مُنشأ ديناميكياً
     * فلا تلتصق به أصناف Svelte، والحصر تحت الصنف يمنع تسرّبها للصفحة.
     */
    let { html = '' }: { html?: string } = $props();
</script>

<!-- dir="auto" لا "rtl": الرد قد يكون إنجليزياً كاملاً حسب لغة السؤال،
     والمتصفّح يستنتج الاتجاه من أول حرف قويّ في كل فقرة. -->

<!--
    القاعدة `svelte/no-at-html-tags` معطّلة هنا وحدها في المشروع كلّه،
    وعن قصد: عرض Markdown يستلزم حقن HTML، ولا سبيل لتحقيقه بدونه.

    التعطيل مشروط بأن يبقى المصدر واحداً: كل ما يدخل `html` يخرج من
    `renderMarkdown()` وقد مرّ على `DOMPurify.sanitize()`. لو مرّر أحد
    نصّاً لم يمرّ من هناك، فالثغرة في المُستدعي لا في هذا السطر — وهذا
    ما يجعل حصر `{@html}` في مكوّن واحد قابلاً للمراجعة أصلاً.
-->
<!-- eslint-disable-next-line svelte/no-at-html-tags -->
<div class="md-body" dir="auto">{@html html}</div>

<style>
    .md-body {
        font-size: 14px;
        line-height: 1.75;
        overflow-wrap: anywhere;
    }

    .md-body :global(> *:first-child) {
        margin-top: 0;
    }

    .md-body :global(> *:last-child) {
        margin-bottom: 0;
    }

    .md-body :global(p) {
        margin: 0 0 0.7em;
    }

    .md-body :global(h3),
    .md-body :global(h4) {
        margin: 1.1em 0 0.45em;
        font-weight: 600;
        line-height: 1.4;
    }

    .md-body :global(h3) {
        font-size: 15.5px;
    }

    .md-body :global(h4) {
        font-size: 14px;
        color: var(--muted-foreground);
    }

    .md-body :global(strong) {
        font-weight: 600;
        /* المبالغ والمجاميع تصل عريضة — تستحقّ تمييزاً بصرياً لا وزناً فقط */
        color: var(--foreground);
    }

    .md-body :global(ul),
    .md-body :global(ol) {
        margin: 0 0 0.7em;
        padding-inline-start: 1.35em;
    }

    .md-body :global(ul) {
        list-style: disc;
    }

    .md-body :global(ol) {
        list-style: decimal;
    }

    .md-body :global(li) {
        margin: 0.2em 0;
    }

    .md-body :global(li::marker) {
        color: var(--muted-foreground);
    }

    /* الجدول يمرَّر أفقياً داخل حاويته — على الجوال يتجاوز عرض الشاشة
       دائماً، وبلا هذا يمدّ الصفحة كلها ويكسر التخطيط. */
    .md-body :global(table) {
        display: block;
        overflow-x: auto;
        width: 100%;
        margin: 0 0 0.8em;
        border-collapse: collapse;
        font-size: 13px;
        white-space: nowrap;
    }

    .md-body :global(th),
    .md-body :global(td) {
        padding: 0.45em 0.7em;
        border: 1px solid var(--border);
        text-align: start;
    }

    .md-body :global(th) {
        background: var(--secondary);
        font-weight: 600;
        font-size: 12px;
        color: var(--muted-foreground);
    }

    /* الأرقام لاتينية ومحاذاتها ثابتة مهما اختلط النصّ حولها بالعربية */
    .md-body :global(td) {
        font-variant-numeric: tabular-nums;
    }

    .md-body :global(code) {
        padding: 0.12em 0.38em;
        border-radius: 5px;
        background: var(--secondary);
        border: 1px solid var(--border);
        font-size: 0.88em;
        /* الكود والأرقام تبقى LTR داخل الفقرة العربية */
        direction: ltr;
        display: inline-block;
        unicode-bidi: isolate;
    }

    .md-body :global(pre) {
        margin: 0 0 0.8em;
        padding: 0.75em 0.9em;
        overflow-x: auto;
        border-radius: var(--radius-md);
        background: var(--secondary);
        border: 1px solid var(--border);
        direction: ltr;
        text-align: left;
    }

    .md-body :global(pre code) {
        padding: 0;
        border: 0;
        background: none;
        display: block;
    }

    .md-body :global(blockquote) {
        margin: 0 0 0.7em;
        padding-inline-start: 0.85em;
        border-inline-start: 3px solid var(--border);
        color: var(--muted-foreground);
    }

    .md-body :global(a) {
        color: var(--primary);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .md-body :global(hr) {
        margin: 1em 0;
        border: 0;
        border-top: 1px solid var(--border);
    }
</style>
