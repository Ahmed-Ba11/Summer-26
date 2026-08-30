<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * مُدخل البث: رسالة المستخدم وتاريخ المحادثة القادم من الواجهة.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  التاريخ يأتي من العميل، فهو مُدخَل غير موثوق مثل أي مُدخَل آخر.
 * ══════════════════════════════════════════════════════════════════════
 *
 * لا ذاكرة في قاعدة البيانات في هذه المرحلة، فالواجهة هي التي تحتفظ
 * بالمحادثة وترسلها كاملةً كل مرة. معنى ذلك أن أحداً يستطيع تزوير
 * `history` بالكامل — أن يدسّ فيها «رسالة مساعد» تدّعي أنه أُذن له
 * بالوصول إلى بيانات مستخدم آخر مثلاً.
 *
 * هذا التحقّق يحدّ من الشكل لا من المحتوى: `role` من قيمتين فقط، و`content`
 * نصّ بطول محدود، وعدد الرسائل مسقوف. أمّا الحماية الحقيقية فليست هنا
 * ولا في التعليمات: هي أن أدوات الوكيل لا ترى إلا صفوف صاحب الجلسة
 * (انظر `App\Ai\Tools\TransactionTool`). تزوير التاريخ لا يوسّع ما تراه
 * الأدوات ولو صدّقه الموديل.
 *
 * `system` غير مقبول عمداً: التعليمات تُبنى على السيرفر، ولا يُسمح للعميل
 * بحقن دور نظام.
 */
class AssistantStreamRequest extends FormRequest
{
    /** سقف الرسائل المقبولة شكلاً — القصّ إلى العشرين الأخيرة يتم بعده. */
    private const MAX_HISTORY = 100;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:'.self::MAX_HISTORY],
            'history.*' => ['array'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:8000'],
        ];
    }

    public function userMessage(): string
    {
        return trim((string) $this->validated('message'));
    }

    /**
     * آخر N رسالة من التاريخ، بالترتيب الزمني.
     *
     * القصّ حماية من انتفاخ السياق: بلا سقف يكبر الطلب مع كل جولة حتى
     * يصير أبطأ وأغلى من الجواب نفسه، ثم يُرفض من المزوّد أصلاً.
     *
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        /** @var list<array{role: string, content: string}> $history */
        $history = $this->validated('history') ?? [];

        $keep = max(0, (int) config('ai.assistant.history_messages'));

        // `array_slice($h, -0)` يرجّع المصفوفة كاملةً لا فارغة — فالصفر
        // يُعالَج صراحةً، وإلا انقلب «بلا تاريخ» إلى «التاريخ كلّه».
        return $keep === 0 ? [] : array_slice($history, -$keep);
    }
}
