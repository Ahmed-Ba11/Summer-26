<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\FinanceAssistant;
use App\Http\Requests\AssistantStreamRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * بث رد المساعد المالي عبر SSE.
 *
 * ── لماذا POST لا EventSource ──
 * `EventSource` لا يدعم POST ولا ترويسات مخصّصة، ونحتاج الاثنين: تاريخ
 * المحادثة أكبر من أن يُحشر في query string، ورمز CSRF يجب أن يُرسَل.
 * فالعميل يقرأ الجسم بـ`fetch` + `ReadableStream`.
 *
 * ── لماذا StreamedResponse لا response()->eventStream() ──
 * المساعد الجاهز يضيف سطر `event: update` لكل إطار ويختم بـ`</stream>`،
 * وهو بروتوكول لا نريده. إطاراتنا سطر `data:` واحد بـJSON مصنّف.
 *
 * ── المهلات ──
 * ٣٠٠ ثانية على أربع طبقات، ويكفي أن تسقط واحدة ليموت البث في منتصفه:
 *   1. PHP هنا (`set_time_limit`) — هذا الملف
 *   2. عميل HTTP داخل laravel/ai — `#[Timeout(300)]` على الوكيل
 *   3. المتصفّح — `AbortController` في الواجهة
 *   4. سيرفر الويب — nginx/PHP-FPM، انظر `docs/assistant-deployment.md`
 *
 * ── الأخطاء ──
 * أي استثناء يصير إطار `error` برسالة عربية عامة ثم `done`. لا رسالة
 * استثناء خام ولا stack trace يصل المستخدم — تُسجَّل في الـlog وحدها.
 */
final class AssistantStreamController extends Controller
{
    /** كل كم ثانية يُرسَل تعليق `: ping` أثناء الصمت. */
    private const HEARTBEAT_SECONDS = 15;

    /** أدوات لا معنى لاستدعائها بلا معطيات — استدعاء فارغ لها علّة. */
    private const TOOLS_REQUIRING_ARGUMENTS = [
        'CreateTransactions',
        'UpdateTransactions',
        'DeleteTransactions',
    ];

    /** آخر لحظة كُتب فيها شيء على الاتصال. */
    private float $lastWrite = 0.0;

    public function __invoke(AssistantStreamRequest $request): StreamedResponse
    {
        $user = $request->user();
        $message = $request->userMessage();
        $history = $request->history();

        // يُفحص قبل فتح البث، ويُبلَّغ داخله: المواصفة تريد رسالة في الشات
        // لا صفحة 429 خام تترك المستخدم أمام واجهة صامتة.
        $rateLimitMessage = $this->rateLimitMessage($user);

        return new StreamedResponse(
            function () use ($user, $message, $history, $rateLimitMessage): void {
                $this->prepareRuntime();
                $this->lastWrite = microtime(true);

                if ($rateLimitMessage !== null) {
                    $this->frame(['type' => 'error', 'message' => $rateLimitMessage]);
                    $this->frame(['type' => 'done']);

                    return;
                }

                try {
                    $this->run($user, $message, $history);
                } catch (Throwable $e) {
                    Log::error('assistant.stream.failed', [
                        'user_id' => $user->id,
                        'exception' => $e,
                    ]);

                    $this->frame([
                        'type' => 'error',
                        'message' => 'تعذّر إكمال الرد. جرّب مرة ثانية بعد قليل.',
                    ]);
                } finally {
                    $this->frame(['type' => 'done']);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Connection' => 'keep-alive',
                // nginx يبفّر الاستجابات افتراضياً، فيصل الرد دفعةً واحدة
                // عند النهاية ويختفي البث كلّه. هذه الترويسة تعطّل ذلك.
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * يشغّل الوكيل ويحوّل أحداثه إلى إطارات.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    private function run(User $user, string $message, array $history): void
    {
        $agent = new FinanceAssistant($user, $history);
        $stream = $agent->stream($message);

        foreach ($stream as $event) {
            if (connection_aborted()) {
                // المستخدم أغلق التبويب أو ضغط «إيقاف». إكمال البث بعدها
                // إنفاقٌ على مفتاح مشترك بلا قارئ.
                //
                // في الغالب لا يُنفَّذ هذا الفرع أصلاً: `ignore_user_abort(false)`
                // يجعل PHP يقتل السكربت عند أوّل كتابة تفشل بعد انقطاع
                // العميل (تُحقّق عملياً — الطلب المقطوع لا يصل `logUsage`).
                // يبقى الفرع لأن السلوك يختلف بين SAPIs، والسقوط الصامت
                // في حالة لا يقتل فيها PHP السكربت يعني بثّاً بلا قارئ.
                Log::info('assistant.stream.aborted', ['user_id' => $user->id]);

                return;
            }

            $this->heartbeat();
            $this->emit($event, $user);
        }

        $this->logUsage($user, $stream->usage);
    }

    /** يحوّل حدثاً واحداً من الحزمة إلى إطار — أو يتجاهله. */
    private function emit(object $event, User $user): void
    {
        match (true) {
            $event instanceof Events\TextDelta => $this->frame([
                'type' => 'text',
                'delta' => $event->delta,
            ]),

            $event instanceof Events\ToolCall => $this->emitToolCall($event, $user),

            $event instanceof Events\ToolResult => $this->emitToolResult($event),

            $event instanceof Events\Error => $this->emitProviderError($event, $user),

            // كل ما عدا ذلك — StreamStart/TextStart/TextEnd/StreamEnd
            // وأحداث الاستدلال — لا يُرسَل. ReasoningDelta تحديداً **ممنوع**
            // وصولها للواجهة: تفكير الموديل ليس رداً.
            default => null,
        };
    }

    private function emitToolCall(Events\ToolCall $event, User $user): void
    {
        $name = $event->toolCall->name;
        $arguments = $event->toolCall->arguments;

        $this->detectMalformedToolCall($name, $arguments, $user);

        $this->frame([
            'type' => 'tool_call',
            'id' => $event->toolCall->id,
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    private function emitToolResult(Events\ToolResult $event): void
    {
        $result = $event->toolResult->result;
        $decoded = is_string($result) ? json_decode($result, true) : $result;

        $this->frame([
            'type' => 'tool_result',
            'id' => $event->toolResult->id,
            'name' => $event->toolResult->name,
            'ok' => $event->successful && ($decoded['ok'] ?? true) === true,
            'summary' => is_array($decoded)
                ? ($decoded['summary'] ?? '')
                : 'تم التنفيذ',
            'data' => is_array($decoded) ? ($decoded['data'] ?? null) : null,
        ]);
    }

    private function emitProviderError(Events\Error $event, User $user): void
    {
        Log::error('assistant.stream.provider_error', [
            'user_id' => $user->id,
            'code' => $event->type,
            'message' => $event->message,
        ]);

        $this->frame([
            'type' => 'error',
            'message' => 'المساعد غير متاح حالياً. جرّب بعد قليل.',
        ]);
    }

    /**
     * رصد الاستدعاء الناقص — صاخباً لا صامتاً.
     *
     * ══════════════════════════════════════════════════════════════════
     *  علّة معروفة في `laravel/ai`، لا في هذا الكود.
     * ══════════════════════════════════════════════════════════════════
     *
     * في `Gateway/OpenAiCompatible/Concerns/HandlesTextStreaming.php` يُلتقط
     * اسم الأداة ومعرّفها من **أوّل** chunk لكل `index` فقط؛ لو أرسلهما
     * مزوّدٌ في chunk تالٍ ضاعا. وفي نفس الملف تُفكّ المعطيات بـ
     * `json_decode(...) ?? []`، فالنصّ المكسور يصير مصفوفةً فارغة بلا شكوى.
     *
     * الحالتان من فئة واحدة: استدعاء أداة وصل ناقصاً. لا يمكن إصلاحهما
     * إلا داخل vendor، ونسخة تُدهس عند أول تحديث أسوأ من العلّة. فبدل
     * الإصلاح: نجعلهما **مرئيتين**. فشلٌ مفهوم في الـlog خيرٌ من أداة لا
     * تعمل بلا سبب ظاهر.
     *
     * `hy3` يرسل الاسم والمعرّف في أول chunk (تُحقّق بـcurl)، فالمتوقّع
     * ألّا يُسجَّل شيء هنا أبداً. سطرٌ واحد منه يعني تغيّر سلوك المزوّد.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function detectMalformedToolCall(string $name, array $arguments, User $user): void
    {
        if (trim($name) === '') {
            Log::error('assistant.tool_call.missing_name', [
                'user_id' => $user->id,
                'reason' => 'اسم الأداة وصل فارغاً — على الأرجح في chunk تالٍ لم يُلتقط.',
                'arguments_keys' => array_keys($arguments),
            ]);

            return;
        }

        if (! in_array($name, FinanceAssistant::TOOL_NAMES, true)) {
            Log::error('assistant.tool_call.unknown_name', [
                'user_id' => $user->id,
                'name' => $name,
                'known' => FinanceAssistant::TOOL_NAMES,
            ]);

            return;
        }

        // معطيات فارغة لأداة كتابة = إمّا نصّ JSON مكسور ابتلعه
        // `json_decode(...) ?? []`، وإمّا استدعاء ناقص. الاثنان علّة.
        if ($arguments === [] && in_array($name, self::TOOLS_REQUIRING_ARGUMENTS, true)) {
            Log::error('assistant.tool_call.unparsable_arguments', [
                'user_id' => $user->id,
                'name' => $name,
                'reason' => 'معطيات الأداة وصلت فارغة — نصّ JSON غير صالح أو استدعاء مبتور.',
            ]);
        }
    }

    /**
     * حدّ الاستخدام — بالساعة وباليوم، ومفتاحه المستخدم لا الـIP.
     *
     * كل الاستدعاءات تمرّ بمفتاح API واحد مشترك، فمستخدم واحد مسيء
     * يستهلك حصّة الجميع ويعطّل المساعد للكلّ. الـIP لا يصلح مفتاحاً:
     * عدّة مستخدمين خلف NAT واحد يتقاسمونه، ومستخدم واحد يغيّره.
     */
    private function rateLimitMessage(User $user): ?string
    {
        $limits = [
            ['key' => "assistant:hour:{$user->id}", 'max' => (int) config('ai.assistant.limits.per_hour'), 'window' => 3600, 'text' => 'وصلت حدّ الرسائل لهذه الساعة. جرّب بعد ساعة.'],
            ['key' => "assistant:day:{$user->id}", 'max' => (int) config('ai.assistant.limits.per_day'), 'window' => 86400, 'text' => 'وصلت حدّ الرسائل لهذا اليوم. جرّب بكرة.'],
        ];

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                Log::warning('assistant.rate_limited', [
                    'user_id' => $user->id,
                    'key' => $limit['key'],
                    'available_in' => RateLimiter::availableIn($limit['key']),
                ]);

                return $limit['text'];
            }
        }

        // لا يُحتسب إلا بعد اجتياز الحدّين — وإلا استهلكت المحاولةُ
        // المرفوضةُ حصّةً من النافذة الأخرى.
        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['window']);
        }

        return null;
    }

    /**
     * تسجيل الاستهلاك بعد كل استدعاء — هذا ما يسمح بمتابعة الفاتورة
     * قبل أن تفاجئنا. `total_tokens` غير موجود في DTO الحزمة فنجمعه.
     */
    private function logUsage(User $user, ?Usage $usage): void
    {
        Log::info('assistant.usage', [
            'user_id' => $user->id,
            'model' => FinanceAssistant::model(),
            'prompt_tokens' => $usage->promptTokens ?? 0,
            'completion_tokens' => $usage->completionTokens ?? 0,
            'total_tokens' => ($usage->promptTokens ?? 0) + ($usage->completionTokens ?? 0),
        ]);
    }

    /**
     * المهلة وإيقاف التخزين المؤقّت — يُنفَّذ داخل الـcallback لأنه لحظة
     * الإرسال الفعلية، ولئلّا تتسرّب هذه الإعدادات لبقية الطلبات.
     */
    private function prepareRuntime(): void
    {
        $timeout = (int) config('ai.assistant.timeout');

        set_time_limit($timeout);
        ini_set('max_execution_time', (string) $timeout);

        // نريد للسكربت أن يتوقّف فعلاً لو أغلق العميل الاتصال، فلا نكمل
        // الإنفاق على رد لا يقرأه أحد.
        ignore_user_abort(false);

        // أي طبقة تخزين مؤقّت قائمة تحبس الإطارات حتى النهاية، فيصل الرد
        // دفعةً واحدة ويضيع معنى البث.
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * تعليق SSE يُبقي الاتصال حيّاً في فترات الصمت.
     *
     * حدّ معروف: PHP هنا أحادي الخيط، والبث يتوقّف داخل قراءة شبكة
     * محجوبة أثناء انتظار أول رمز من المزوّد (٥–٦ ثوانٍ عادةً). فالنبضة
     * تُرسَل عند حدود الأحداث لا أثناء الحجب. هذا يغطّي الفجوات بين
     * الخطوات — وهي الأطول — ولا يغطّي حجباً أطول من ١٥ ثانية دفعةً واحدة.
     */
    private function heartbeat(): void
    {
        if ((microtime(true) - $this->lastWrite) < self::HEARTBEAT_SECONDS) {
            return;
        }

        $this->write(": ping\n\n");
    }

    /** @param array<string, mixed> $payload */
    private function frame(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        $this->write('data: '.$json."\n\n");
    }

    private function write(string $chunk): void
    {
        echo $chunk;

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();

        $this->lastWrite = microtime(true);
    }
}
