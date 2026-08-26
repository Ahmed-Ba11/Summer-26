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
     * ثلاث طبقات ثابتة:
     *   رأس ثابت  →  جسم يتمرّر  →  تذييل ثابت
     *
     * لماذا مكوّن واحد بدل تكرار البنية في كل صفحة:
     *
     *   • **زر الإغلاق كان يتداخل مع العنوان** في عدّة صفحات. هنا العنوان
     *     داخل حاوية `min-w-0` تقصّ نصّها، والزر `shrink-0` بمسافة ثابتة —
     *     التداخل مستحيل هندسياً، ويُصلَح مرة واحدة للجميع.
     *
     *   • **زر الحفظ كان يختفي** أسفل نموذج طويل. هنا التذييل خارج منطقة
     *     التمرير، فيبقى تحت الإبهام مهما طال المحتوى.
     *
     *   • لوح سفلي على الجوال، ومركزي على الديسكتوب — بنفس المكوّن.
     */
    import X from 'lucide-svelte/icons/x';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import type { Snippet } from 'svelte';

    let {
        open = $bindable(false),
        title,
        subtitle = '',
        /** يعرض سهم رجوع بدل زر الإغلاق — للألواح متعدّدة الخطوات */
        showBack = false,
        /** مثال: «الخطوة 2 من 2» */
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

    /** ترتيب هذا اللوح في مكدّس الألواح المفتوحة — 1 للأول، 2 للمتداخل فيه… */
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
     * ينقل اللوح إلى `document.body`.
     *
     * `position: fixed` ينسب نفسه لأقرب سلف فيه `transform` أو `filter` أو
     * `contain` لا للنافذة — وهذا يجعل اللوح المفتوح من داخل صفحة أو لوح آخر
     * يُقصّ داخل حاوية التمرير الخاصة بها. النقل إلى جذر المستند يقطع هذا
     * الاحتمال من أصله بدل مطاردته صفحةً صفحة.
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
        if (showBack) onBack?.();
        else {
            open = false;
            onClose?.();
        }
    }

    function onKeydown(e: KeyboardEvent) {
        if (!open) return;
        // اللوح الأعلى وحده يستجيب — وإلا أغلق Escape الأب والابن معاً.
        if (layer !== openSheets) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            open = false;
            onClose?.();
            return;
        }
        // حصر التركيز داخل اللوح
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
        if (open) panel?.querySelector<HTMLElement>('button,input')?.focus();
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
            class="relative flex max-h-[92vh] max-h-[92dvh] w-full flex-col rounded-t-3xl bg-card shadow-lg md:max-h-[86vh] md:max-h-[86dvh] md:max-w-md md:rounded-3xl"
        >
            <!-- مقبض السحب — جوال فقط -->
            <div class="mx-auto mt-2 h-1 w-9 shrink-0 rounded-full bg-border md:hidden"></div>

            <!-- الرأس الثابت -->
            <header class="flex shrink-0 items-start gap-2.5 px-4 pt-3 pb-2.5">
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-[15px] font-semibold">{title}</h2>
                    {#if subtitle}
                        <p class="truncate text-[11px] text-muted-foreground">{subtitle}</p>
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
                <div class="flex shrink-0 items-center gap-1.5 px-4 pb-2 text-[11px] text-muted-foreground">
                    {#each Array(steps) as _, i (i)}
                        <span class="h-[3px] w-4 rounded-full {i < currentStep ? 'bg-primary' : 'bg-border'}"></span>
                    {/each}
                    {#if stepLabel}<span class="ms-1">{stepLabel}</span>{/if}
                </div>
            {/if}

            <!-- الجسم القابل للتمرير -->
            <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-3">
                {@render children()}
            </div>

            <!-- التذييل الثابت -->
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
