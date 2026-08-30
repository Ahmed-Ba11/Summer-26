<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * صفحة المساعد الذكي.
 *
 * لا تحمّل أي محادثة سابقة: الذاكرة في هذه المرحلة على الفرونت وحده،
 * وتُرسَل مع كل طلب إلى `POST /assistant/stream`. المحادثة تبدأ فارغة مع
 * كل تحميل للصفحة، وهذا مقصود لا نقص.
 *
 * TODO: عند الترقية إلى `RemembersConversations` (انظر `FinanceAssistant`)
 * تعود هذه الدالّة لتحميل المحادثة الأخيرة من قاعدة البيانات.
 */
class AssistantController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Assistant', [
            // أسماء الفئات تُعرض كأمثلة قابلة للنقر في الحالة الفارغة،
            // فتكون الاقتراحات من واقع المستخدم لا نصّاً ثابتاً.
            'categories' => auth()->user()->categories()->orderBy('id')->pluck('name')->values(),
        ]);
    }
}
