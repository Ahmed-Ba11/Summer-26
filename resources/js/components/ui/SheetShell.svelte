<script module lang="ts">
    /**
     * عدّاد الألواح المفتوحة — مشترك بين كل نسخ المكوّن.
     *
     * اللوح المتداخل (مبلغ يُفتح من داخل لوح إضافة) يأخذ طبقة أعلى من أبيه،
     * فلا يُدفن تحته. والعدّاد نفسه يحدّد أيّ لوح يستجيب لـEscape: الأعلى فقط.
     */
    let openSheets = 0;

    /**
     * الطبقة الأساس **فوق** شريط التنقّل السفلي (`z-[55]` في `AppNav`).
     * بدونها يرسم الشريط فوق تذييل اللوح فيختفي زر الحفظ خلفه.
     */
    const BASE_LAYER = 60;
</script>

<script lang="ts">
    /**
     * الهيكل الموحّد لكل لوح في التطبيق.
     *
     * رأس ثابت → جسم قابل للتمرير → تذييل ثابت.
     */
    import X from 'lucide-svelte/icons/x';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import type { Snippet } from 'svelte';

    let {
        open = $bindable(false),
        title,
        subtitle = '',
        showBack = false,
        stepLabel = '',
        steps = 0,
        currentStep = 0,
        onBack,
        onClose,
        children,
        footer,
    }: {
        open?: boolean;
        title: string;
        subtitle?: string;
        showBack?: boolean;
        stepLabel?: string;
        steps?: number;
        currentStep?: number;
        onBack?: () => void;
        onClose?: () => void;
        children: Snippet;
        footer?: Snippet;
    } = $props();

    let panel = $state<HTMLElement | null>(null);

    /** ترتيب هذا اللوح في مكدّس الألواح المفتوحة. */
    let layer = $state(0);

    $effect(() => {
        if (!open) return;

        layer = ++openSheets;

        return () => {
            openSheets = Math.max(0, openSheets - 1);
            layer = 0;
        };
    });

    /**
     * ينقل اللوح إلى document.body حتى لا يُقص داخل أي حاوية أب.
     */
    function portal(node: HTMLElement) {
        document.body.appendChild(node);

        return {
            destroy() {
                node.remove();
            },
        };
    }

    function close() {
        if (showBack) {
            onBack?.();
        } else {
            open = false;
            onClose?.();
        }
    }

    function onKeydown(e: KeyboardEvent) {
        if (!open) return;

        // اللوح الأعلى وحده يستجيب.
        if (layer !== openSheets) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            open = false;
            onClose?.();
            return;
        }

        // حصر التركيز داخل اللوح.
        if (e.key !== 'Tab' || !panel) return;

        const items = panel.querySelectorAll<HTMLElement>(
            'button:not([disabled]),input,select,textarea,[href],[tabindex]:not([tabindex="-1"])',
        );

        if (!items.length) return;

        const first = items[0];
        const last = items[items.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    $effect(() => {
        if (open) {
            panel?.querySelector<HTMLElement>('button,input')?.focus();
        }
    });
</script>

<svelte:window on:keydown={onKeydown} />

{#if open}
    <div
        use:portal
        style="z-index: {BASE_LAYER + layer}"
        class="fixed inset-0 flex items-end justify-center md:items-center"
    >
        <button
            type="button"
            class="absolute inset-0 bg-black/45"
            aria-label="إغلاق"
            onclick={() => {
                open = false;
                onClose?.();
            }}
        ></button>

        <div
            bind:this={panel}
            role="dialog"
            aria-modal="true"
            aria-label={title}
            class="relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-card shadow-lg md:max-h-[86dvh] md:max-w-md md:rounded-3xl"
        >
            <!-- مقبض السحب — جوال فقط -->
            <div
                class="mx-auto mt-2 h-1 w-9 shrink-0 rounded-full bg-border md:hidden"
            ></div>

            <!-- الرأس -->
            <header
                class="flex shrink-0 items-start gap-2.5 px-4 pt-3 pb-2.5"
            >
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-[15px] font-semibold">
                        {title}
                    </h2>

                    {#if subtitle}
                        <p
                            class="truncate text-[11px] text-muted-foreground"
                        >
                            {subtitle}
                        </p>
                    {/if}
                </div>

                <button
                    type="button"
                    onclick={close}
                    aria-label={showBack ? 'رجوع' : 'إغلاق'}
                    class="-mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-xl border border-input text-muted-foreground transition-colors hover:bg-secondary"
                >
                    {#if showBack}
                        <ArrowRight class="size-[17px]" />
                    {:else}
                        <X class="size-[17px]" />
                    {/if}
                </button>
            </header>

            {#if steps > 1}
                <div
                    class="flex shrink-0 items-center gap-1.5 px-4 pb-2 text-[11px] text-muted-foreground"
                >
                    {#each Array(steps) as _, i (i)}
                        <span
                            class="h-[3px] w-4 rounded-full {i < currentStep
                                ? 'bg-primary'
                                : 'bg-border'}"
                        ></span>
                    {/each}

                    {#if stepLabel}
                        <span class="ms-1">{stepLabel}</span>
                    {/if}
                </div>
            {/if}

            <!-- الجسم -->
            <div
                class="min-h-0 overflow-y-auto px-4 pb-3"
            >
                {@render children()}
            </div>

            <!-- التذييل -->
            {#if footer}
                <div
                    class="flex shrink-0 items-center gap-2 border-t border-border bg-card px-4 pt-3 pb-[calc(0.85rem+env(safe-area-inset-bottom))] md:pb-3"
                >
                    {@render footer()}
                </div>
            {/if}
        </div>
    </div>
{/if}