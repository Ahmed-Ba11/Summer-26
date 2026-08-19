<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'المساعد الذكي', href: '/assistant' }],
    };
</script>

<script lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import Bot from 'lucide-svelte/icons/bot';
    import Send from 'lucide-svelte/icons/send';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import type { AssistantMessage } from '@/types';

    interface AssistantProps {
        messages?: AssistantMessage[];
        status?: string;
    }

    interface ChatResponse {
        message?: AssistantMessage;
    }

    let { messages: initialMessages = [], status = 'stub' }: AssistantProps = $props();
    let messages = $state<AssistantMessage[]>([]);
    const http = useHttp({ message: '' });
    let requestError = $state('');

    $effect(() => {
        messages = [...initialMessages];
    });

    const suggestions = [
        'كم صرفت على الطعام هذا الشهر؟',
        'وين أقدر أقلّل مصاريفي؟',
        'هل أقدر أشتري شيئاً جديداً؟',
        'سوّي لي خطة ادخار لهدف معيّن',
    ];

    function errorText(): string {
        const value = Object.values(http.errors ?? {})[0];

        return Array.isArray(value) ? (value[0] ?? '') : (value ?? requestError);
    }

    function submitMessage(event?: SubmitEvent): void {
        event?.preventDefault();
        const content = http.message.trim();

        if (!content || http.processing) {
            return;
        }

        requestError = '';
        http.errors = {};
        messages = [...messages, { role: 'user', content }];
        http.message = '';
        http.post('/assistant/chat', {
            onSuccess: (data) => {
                const response = data as ChatResponse;
                if (response.message?.content) {
                    messages = [...messages, response.message];
                }
            },
            onError: (errors) => {
                requestError =
                    (Object.values(errors)[0] as string | string[] | undefined)?.toString() ??
                    'تعذر إرسال الرسالة حالياً.';
            },
        });
    }

    function useSuggestion(suggestion: string): void {
        http.message = suggestion;
        submitMessage();
    }
</script>

<AppHead title="المساعد الذكي" />

<main class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <section class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold">المساعد الذكي</h1>
                    <span class="rounded-full bg-accent px-2 py-0.5 text-[11px] font-semibold text-primary">تجريبي</span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">واجهة محادثة أولية، والردود الذكية ستضاف لاحقاً.</p>
            </div>
            <Sparkles class="size-5 text-primary" aria-hidden="true" />
        </header>

        <Card class="flex min-h-[28rem] flex-1 flex-col">
            <CardContent class="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                {#if messages.length === 0 && !http.processing}
                    <div class="flex flex-1 flex-col items-center justify-center text-center">
                        <div class="mb-4 grid size-14 place-items-center rounded-2xl bg-accent text-primary"><Bot class="size-7" /></div>
                        <h2 class="text-lg font-semibold">اسألني أي شيء عن ميزانيتك</h2>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">سأعتمد على بياناتك المسجّلة فقط. هذه الواجهة لا تدّعي وجود ذكاء اصطناعي قبل ربط الخدمة.</p>
                        <div class="mt-6 grid w-full gap-2 sm:grid-cols-2">
                            {#each suggestions as suggestion}
                                <button type="button" class="rounded-xl border border-border p-3 text-start text-sm transition-colors hover:bg-secondary" onclick={() => useSuggestion(suggestion)}>{suggestion}</button>
                            {/each}
                        </div>
                    </div>
                {:else}
                    <div class="flex flex-1 flex-col gap-4 overflow-y-auto" aria-live="polite">
                        {#each messages as message, index (`${message.role}-${index}`)}
                            <div class="flex gap-3 {message.role === 'user' ? 'justify-start' : 'justify-end'}">
                                {#if message.role === 'assistant'}<div class="grid size-8 shrink-0 place-items-center rounded-xl bg-accent text-primary"><Bot class="size-4" /></div>{/if}
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm {message.role === 'user' ? 'rounded-es-md bg-primary text-primary-foreground' : 'rounded-ee-md border border-border bg-card'}">{message.content}</div>
                            </div>
                        {/each}
                        {#if http.processing}
                            <div class="flex items-center gap-3 text-sm text-muted-foreground" aria-label="جاري تجهيز الرد">
                                <div class="grid size-8 place-items-center rounded-xl bg-accent text-primary"><Bot class="size-4" /></div>
                                <span class="flex gap-1"><i class="size-1.5 animate-pulse rounded-full bg-current"></i><i class="size-1.5 animate-pulse rounded-full bg-current [animation-delay:150ms]"></i><i class="size-1.5 animate-pulse rounded-full bg-current [animation-delay:300ms]"></i></span>
                            </div>
                        {/if}
                    </div>
                {/if}

                {#if errorText()}
                    <p class="flex items-center gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert"><TriangleAlert class="size-4 shrink-0" />{errorText()}</p>
                {/if}

                <form class="border-t border-border pt-4" onsubmit={submitMessage}>
                    <div class="flex items-end gap-2">
                        <textarea bind:value={http.message} rows="1" placeholder="اكتب سؤالك هنا..." aria-label="رسالتك" onkeydown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submitMessage(); } }} class="min-h-11 flex-1 resize-y rounded-xl border border-input bg-background px-3 py-3 text-sm outline-none focus:ring-2 focus:ring-ring"></textarea>
                        <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground transition-opacity disabled:opacity-50" aria-label="إرسال الرسالة" disabled={http.processing || !http.message.trim()}><Send class="size-4" /></button>
                    </div>
                    <p class="mt-2 text-center text-[11px] text-muted-foreground">المساعد يعتمد على بياناتك المسجّلة فقط، وقد يخطئ. راجع الأرقام المهمة بنفسك.</p>
                </form>
            </CardContent>
        </Card>
        {#if status === 'stub'}
            <p class="text-center text-xs text-muted-foreground">حالة الخدمة: واجهة أولية بانتظار endpoint المحادثة.</p>
        {/if}
    </section>
</main>
