<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'المساعد الذكي', href: '/assistant' }],
    };
</script>

<script lang="ts">
    /**
     * صفحة المحادثة مع المساعد المالي.
     *
     * ── البث ──
     * `fetch` + `ReadableStream` لا `EventSource`: الأخير لا يدعم POST ولا
     * الترويسات المخصّصة، ونحتاج الاثنين (تاريخ المحادثة + رمز CSRF).
     *
     * ── الرندرة أثناء البث ──
     * مع كل حزمة تصل نعيد تحليل **النصّ المتراكم كاملاً** — Markdown لا
     * يُحلَّل تدريجياً، فتحليل الحزمة وحدها يعطي عبثاً. الإعادة مخنوقة عند
     * ~60ms (١٦ مرة/ثانية): رندرة كل حرف تخنق المتصفّح، وأبطأ من ذلك
     * يُرى تقطيعاً. وعند `done` رندرة أخيرة **بلا خنق** فلا يضيع آخر ما وصل.
     *
     * ── الذاكرة ──
     * على الفرونت وحده. تُرسَل كاملةً مع كل طلب، و«محادثة جديدة» تمسحها.
     * إعادة تحميل الصفحة تبدأ من الصفر — مقصود في هذه المرحلة.
     */
    import Bot from 'lucide-svelte/icons/bot';
    import Plus from 'lucide-svelte/icons/plus';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Send from 'lucide-svelte/icons/send';
    import Square from 'lucide-svelte/icons/square';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import { onDestroy } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import MarkdownBody from '@/components/assistant/MarkdownBody.svelte';
    import ToolCallCard from '@/components/assistant/ToolCallCard.svelte';
    import MobileHeader from '@/components/MobileHeader.svelte';
    import { renderMarkdown } from '@/lib/markdown';
    import type {
        AssistantMessage,
        AssistantPart,
        ChatTurn,
        StreamFrame,
        ToolInvocation,
    } from '@/types';

    let { categories = [] }: { categories?: string[] } = $props();

    /** مهلة العميل — الطبقة الثالثة من أربع، انظر docs/assistant-deployment.md */
    const TIMEOUT_MS = 300_000;

    /** ~16 رندرة في الثانية. أسرع يخنق المتصفّح، أبطأ يُرى تقطيعاً. */
    const RENDER_EVERY_MS = 60;

    let turns = $state<ChatTurn[]>([]);
    let draft = $state('');
    let streaming = $state(false);
    let error = $state('');
    /** آخر رسالة أُرسلت — لإعادة المحاولة بعد فشل. */
    let lastSent = $state('');

    let controller: AbortController | null = null;
    let timeoutId: ReturnType<typeof setTimeout> | null = null;
    let renderTimer: ReturnType<typeof setTimeout> | null = null;
    let scroller = $state<HTMLElement | null>(null);
    let input = $state<HTMLTextAreaElement | null>(null);

    const suggestions = $derived([
        'كم صرفت هذا الشهر؟',
        `أضف مصروف 50 ريال قهوة أمس`,
        'اعرض أكبر 5 مصروفات',
        categories.length > 0
            ? `كم صرفت على ${categories[0]}؟`
            : 'احذف آخر عملية',
    ]);

    const canSend = $derived(draft.trim().length > 0 && !streaming);

    /* ── التمرير ───────────────────────────────────────────────────── */

    /** لا نجرّ المستخدم لأسفل وهو يقرأ أعلى المحادثة. */
    let pinned = true;

    function onScroll() {
        if (!scroller) {
            return;
        }

        pinned =
            scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight <
            60;
    }

    function scrollDown() {
        if (pinned && scroller) {
            scroller.scrollTop = scroller.scrollHeight;
        }
    }

    /* ── الرندرة المخنوقة ──────────────────────────────────────────── */

    function currentText(): AssistantPart | null {
        const turn = turns.at(-1);

        if (!turn || turn.role !== 'assistant') {
            return null;
        }

        const part = turn.parts.at(-1);

        return part?.kind === 'text' ? part : null;
    }

    function paint(immediate = false) {
        const part = currentText();

        if (!part || part.kind !== 'text') {
            return;
        }

        if (immediate) {
            if (renderTimer) {
                clearTimeout(renderTimer);
                renderTimer = null;
            }

            part.html = renderMarkdown(part.raw, false);
            scrollDown();

            return;
        }

        if (renderTimer) {
            return;
        }

        renderTimer = setTimeout(() => {
            renderTimer = null;
            const live = currentText();

            if (live && live.kind === 'text') {
                live.html = renderMarkdown(live.raw, true);
            }

            scrollDown();
        }, RENDER_EVERY_MS);
    }

    /* ── بناء الدور الجاري ─────────────────────────────────────────── */

    function assistantTurn(): Extract<ChatTurn, { role: 'assistant' }> {
        const turn = turns.at(-1);

        if (turn && turn.role === 'assistant') {
            return turn;
        }

        const fresh = {
            role: 'assistant' as const,
            parts: [] as AssistantPart[],
        };
        turns.push(fresh);

        return turns.at(-1) as Extract<ChatTurn, { role: 'assistant' }>;
    }

    function appendText(delta: string) {
        const turn = assistantTurn();
        const last = turn.parts.at(-1);

        if (last?.kind === 'text') {
            last.raw += delta;
        } else {
            turn.parts.push({ kind: 'text', raw: delta, html: '' });
        }

        paint();
    }

    function applyFrame(frame: StreamFrame) {
        if (frame.type === 'text') {
            appendText(frame.delta);

            return;
        }

        if (frame.type === 'tool_call') {
            // النصّ الذي سبق الأداة يُثبَّت الآن: أي نصّ لاحق كتلة جديدة
            // بعد البطاقة، لا امتداد لما قبلها.
            paint(true);

            assistantTurn().parts.push({
                kind: 'tool',
                id: frame.id,
                name: frame.name,
                arguments: frame.arguments ?? {},
                status: 'running',
                summary: '',
                data: null,
            });

            scrollDown();

            return;
        }

        if (frame.type === 'tool_result') {
            const turn = assistantTurn();
            const card = turn.parts.find(
                (p): p is ToolInvocation =>
                    p.kind === 'tool' &&
                    p.id === frame.id &&
                    p.status === 'running',
            );

            if (card) {
                card.status = frame.ok ? 'done' : 'failed';
                card.summary = frame.summary || (frame.ok ? 'تم' : 'لم يتم');
                card.data = frame.data;
            }

            scrollDown();

            return;
        }

        if (frame.type === 'error') {
            error = frame.message;
        }
    }

    /* ── الإرسال ───────────────────────────────────────────────────── */

    /**
     * التاريخ المُرسَل: نصّ فقط. بطاقات الأدوات لا تُرسَل — نتائجها ليست
     * جزءاً من المحادثة على السيرفر (انظر `FinanceAssistant::messages()`).
     */
    function history(): AssistantMessage[] {
        return turns
            .map((turn): AssistantMessage | null => {
                if (turn.role === 'user') {
                    return { role: 'user', content: turn.content };
                }

                const text = turn.parts
                    .filter((p) => p.kind === 'text')
                    .map((p) => p.raw)
                    .join('\n')
                    .trim();

                return text ? { role: 'assistant', content: text } : null;
            })
            .filter((m): m is AssistantMessage => m !== null);
    }

    /**
     * رمز CSRF من كوكي `XSRF-TOKEN`.
     *
     * المشروع لا يضع وسم `<meta name="csrf-token">` في القالب، وإضافته
     * تعديلٌ في ملف قائم لا تفرضه المهمة. الكوكي موجود أصلاً في كل
     * استجابة، و`VerifyCsrfToken` يفكّ تشفير ترويسة `X-XSRF-TOKEN`
     * ويقبلها — وهو المسار نفسه الذي يسلكه Axios في تطبيقات Laravel.
     */
    function csrf(): string {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

        return match ? decodeURIComponent(match[1]) : '';
    }

    async function send(text: string) {
        const message = text.trim();

        if (!message || streaming) {
            return;
        }

        error = '';
        lastSent = message;

        const payload = { message, history: history() };

        turns.push({ role: 'user', content: message });
        draft = '';
        pinned = true;
        streaming = true;
        scrollDown();

        controller = new AbortController();
        timeoutId = setTimeout(() => controller?.abort('timeout'), TIMEOUT_MS);

        try {
            const response = await fetch('/assistant/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                    Accept: 'text/event-stream',
                },
                body: JSON.stringify(payload),
                signal: controller.signal,
            });

            if (!response.ok || !response.body) {
                throw new Error(`HTTP ${response.status}`);
            }

            await consume(response.body);
        } catch {
            if (
                controller?.signal.aborted &&
                controller.signal.reason === 'timeout'
            ) {
                error = 'استغرق الرد وقتاً أطول من المتوقّع. جرّب مرة ثانية.';
            } else if (!controller?.signal.aborted) {
                error =
                    'تعذّر الاتصال بالمساعد. تحقّق من الشبكة وجرّب مرة ثانية.';
            }

            const turn = turns.at(-1);

            if (turn?.role === 'assistant') {
                turn.failed = true;
            }
        } finally {
            finish();
        }
    }

    /** يقرأ الجسم سطراً سطراً ويطبّق كل إطار فور وصوله. */
    async function consume(body: ReadableStream<Uint8Array>) {
        const reader = body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        for (;;) {
            const { done, value } = await reader.read();

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            // آخر سطر قد يكون نصفَ إطار — يبقى في الذاكرة حتى تكتمل حزمته
            const lines = buffer.split('\n');
            buffer = lines.pop() ?? '';

            for (const line of lines) {
                // `: ping` نبضة إبقاء الاتصال، لا محتوى
                if (!line.startsWith('data: ')) {
                    continue;
                }

                try {
                    applyFrame(JSON.parse(line.slice(6)) as StreamFrame);
                } catch {
                    // إطار مشوّه: نتجاهله بدل أن نكسر البث كلّه على حزمة واحدة
                }
            }
        }
    }

    function finish() {
        // رندرة أخيرة بلا خنق — وبوضع «غير بثّي» فيظهر النصّ كما هو
        // تماماً، بلا العناصر المؤقّتة التي تُغلق أثناء البث.
        paint(true);

        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = null;
        controller = null;
        streaming = false;

        // فقاعة فارغة تماماً (خطأ قبل أي حرف) لا معنى لها
        const turn = turns.at(-1);

        if (turn?.role === 'assistant' && turn.parts.length === 0) {
            turns.pop();
        }

        input?.focus();
    }

    function stop() {
        controller?.abort('user');
    }

    function retry() {
        if (lastSent) {
            // الرسالة الفاشلة تُسحب قبل إعادة الإرسال لئلّا تتكرّر في التاريخ
            const turn = turns.at(-1);

            if (turn?.role === 'assistant' && turn.failed) {
                turns.pop();
            }

            const previous = turns.at(-1);

            if (previous?.role === 'user' && previous.content === lastSent) {
                turns.pop();
            }

            void send(lastSent);
        }
    }

    function reset() {
        stop();
        turns = [];
        error = '';
        lastSent = '';
        input?.focus();
    }

    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void send(draft);
        }
    }

    onDestroy(() => {
        controller?.abort('unmount');

        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        if (renderTimer) {
            clearTimeout(renderTimer);
        }
    });
</script>

<AppHead title="المساعد الذكي" />
<MobileHeader title="المساعد الذكي" subtitle="اسألني عن ميزانيتك" />

<main class="flex min-h-0 flex-1 flex-col">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 sm:px-6">
        <header class="hidden items-center justify-between gap-3 py-4 md:flex">
            <div>
                <h1 class="text-xl font-semibold">المساعد الذكي</h1>
                <p class="mt-0.5 text-[12.5px] text-muted-foreground">
                    اسألني عن مصاريفك، أو اطلب مني تسجيلها وتعديلها.
                </p>
            </div>
            {#if turns.length > 0}
                <button
                    type="button"
                    onclick={reset}
                    class="flex min-h-11 items-center gap-2 rounded-xl border border-border px-3 text-[13px] font-medium transition-colors hover:bg-secondary"
                >
                    <Plus class="size-4" />
                    محادثة جديدة
                </button>
            {/if}
        </header>

        <!-- ══ الرسائل ══ -->
        <div
            bind:this={scroller}
            onscroll={onScroll}
            class="flex-1 space-y-4 overflow-y-auto py-4"
            role="log"
            aria-live="polite"
            aria-label="المحادثة"
        >
            {#if turns.length === 0}
                <div
                    class="flex h-full flex-col items-center justify-center py-8 text-center"
                >
                    <div
                        class="mb-4 grid size-14 place-items-center rounded-2xl bg-accent text-primary"
                    >
                        <Bot class="size-7" />
                    </div>
                    <h2 class="text-[17px] font-semibold">
                        اسألني عن ميزانيتك
                    </h2>
                    <p
                        class="mt-1.5 max-w-sm text-[13px] text-muted-foreground"
                    >
                        أقرأ عملياتك المسجّلة وأقدر أضيف وأعدّل وأحذف بطلبك.
                    </p>
                    <div class="mt-6 grid w-full max-w-lg gap-2 sm:grid-cols-2">
                        {#each suggestions as suggestion (suggestion)}
                            <button
                                type="button"
                                onclick={() => void send(suggestion)}
                                class="min-h-11 rounded-xl border border-border bg-card px-3 py-2.5 text-start text-[13px] transition-colors hover:bg-secondary"
                            >
                                {suggestion}
                            </button>
                        {/each}
                    </div>
                </div>
            {/if}

            {#each turns as turn, index (index)}
                {#if turn.role === 'user'}
                    <!-- رسالة المستخدم نصّ عادي: لا تُرندَر كـMarkdown أبداً -->
                    <div class="flex justify-end">
                        <p
                            class="max-w-[85%] rounded-2xl rounded-ee-md bg-primary px-3.5 py-2.5 text-[13.5px] whitespace-pre-wrap text-primary-foreground"
                            dir="auto"
                        >
                            {turn.content}
                        </p>
                    </div>
                {:else}
                    <div class="flex gap-2.5">
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-xl bg-accent text-primary"
                        >
                            <Bot class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            {#each turn.parts as part, partIndex (partIndex)}
                                {#if part.kind === 'tool'}
                                    <ToolCallCard tool={part} />
                                {:else if part.html}
                                    <MarkdownBody html={part.html} />
                                {/if}
                            {/each}

                            {#if streaming && index === turns.length - 1}
                                <span
                                    class="mt-1 flex gap-1 text-muted-foreground"
                                    aria-label="جارٍ الكتابة"
                                >
                                    <i
                                        class="size-1.5 animate-pulse rounded-full bg-current"
                                    ></i>
                                    <i
                                        class="size-1.5 animate-pulse rounded-full bg-current [animation-delay:150ms]"
                                    ></i>
                                    <i
                                        class="size-1.5 animate-pulse rounded-full bg-current [animation-delay:300ms]"
                                    ></i>
                                </span>
                            {/if}
                        </div>
                    </div>
                {/if}
            {/each}

            {#if error}
                <div
                    class="flex flex-wrap items-center gap-2 rounded-xl bg-destructive/10 px-3 py-2.5 text-[13px] text-destructive"
                    role="alert"
                >
                    <TriangleAlert class="size-4 shrink-0" />
                    <span class="flex-1">{error}</span>
                    {#if lastSent && !streaming}
                        <button
                            type="button"
                            onclick={retry}
                            class="flex min-h-9 items-center gap-1.5 rounded-lg border border-destructive/30 px-2.5 text-[12.5px] font-medium transition-colors hover:bg-destructive/10"
                        >
                            <RotateCcw class="size-3.5" />
                            إعادة المحاولة
                        </button>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- ══ الإدخال ══ -->
        <div class="sticky bottom-0 bg-background pt-2 pb-4">
            <form
                class="flex items-end gap-2"
                onsubmit={(event) => {
                    event.preventDefault();
                    void send(draft);
                }}
            >
                <textarea
                    bind:this={input}
                    bind:value={draft}
                    onkeydown={onKeydown}
                    rows="1"
                    disabled={streaming}
                    placeholder="اكتب سؤالك…"
                    aria-label="رسالتك"
                    dir="auto"
                    class="max-h-40 min-h-11 flex-1 resize-y rounded-2xl border border-input bg-card px-3.5 py-3 text-[13.5px] outline-none focus:ring-2 focus:ring-ring disabled:opacity-60"
                ></textarea>

                {#if streaming}
                    <button
                        type="button"
                        onclick={stop}
                        aria-label="إيقاف الرد"
                        class="grid size-11 shrink-0 place-items-center rounded-2xl border border-border transition-colors hover:bg-secondary"
                    >
                        <Square class="size-4 fill-current" />
                    </button>
                {:else}
                    <button
                        type="submit"
                        disabled={!canSend}
                        aria-label="إرسال"
                        class="grid size-11 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground transition-opacity disabled:opacity-40"
                    >
                        <Send class="size-4" />
                    </button>
                {/if}

                {#if turns.length > 0}
                    <button
                        type="button"
                        onclick={reset}
                        aria-label="محادثة جديدة"
                        class="grid size-11 shrink-0 place-items-center rounded-2xl border border-border transition-colors hover:bg-secondary md:hidden"
                    >
                        <Plus class="size-4" />
                    </button>
                {/if}
            </form>

            <p class="mt-2 text-center text-[11px] text-muted-foreground">
                يعتمد على بياناتك المسجّلة، وقد يخطئ. راجع الأرقام المهمة بنفسك.
            </p>
        </div>
    </div>
</main>
