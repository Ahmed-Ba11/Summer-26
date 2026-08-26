<script lang="ts">
    /**
     * لوح المبلغ الموحّد — **كل** مبلغ في التطبيق يمرّ من هنا:
     * مصروف · دخل · إيداع ادخار · ميزانية فئة · مبلغ فاتورة · قيمة قسط.
     *
     * لوحة أرقام لا حقل نص: لوحة مفاتيح الجوال تفتح رموزاً وحروفاً لا يحتاجها
     * إدخال المبلغ، وتغطّي نصف الشاشة، وتسمح بإدخال غير رقمي يحتاج تنظيفاً.
     *
     * القيمة تُدار **بالهللات** في الخارج وتُعرض بالريالات في الداخل — نفس
     * قاعدة `AmountInput`، فلا يتكرّر خطأ الضرب في 100.
     */
    import Check from 'lucide-svelte/icons/check';
    import Delete from 'lucide-svelte/icons/delete';
    import Wand from 'lucide-svelte/icons/wand-sparkles';
    import SheetShell from '@/components/ui/SheetShell.svelte';
    import { formatAmount } from '@/lib/format';

    let {
        open = $bindable(false),
        /** القيمة بالهللات */
        value = $bindable(0),
        title,
        subtitle = '',
        hint = '',
        /** رقائق إضافة سريعة بالريالات، مثل [50, 100] */
        quickAdd = [] as number[],
        /** رقاقة «المتوسّط» — بالهللات */
        averageAmount = 0,
        saveLabel = 'حفظ',
        onSave,
    }: {
        open?: boolean;
        value?: number;
        title: string;
        subtitle?: string;
        hint?: string;
        quickAdd?: number[];
        averageAmount?: number;
        saveLabel?: string;
        onSave?: (halalas: number) => void;
    } = $props();

    /** النص المعروض بالريالات — منفصل عن القيمة الحقيقية بالهللات. */
    let text = $state('');

    $effect(() => {
        if (open) text = value ? (value / 100).toString() : '';
    });

    const halalas = $derived(Math.round((parseFloat(text || '0') || 0) * 100));

    /**
     * كاشف الخطأ الشائع: 20004 بدل 200.04 — رقم من خمس خانات فأكثر بلا فاصلة
     * غالباً نسيان النقطة. نقترح التصحيح ولا نفرضه.
     */
    const typoSuggestion = $derived.by(() => {
        if (text.includes('.') || text.length < 5) return null;
        const n = parseFloat(text);
        if (!n || n < 10_000) return null;
        return Math.round(n) / 10;
    });

    function press(k: string) {
        if (k === 'del') {
            text = text.slice(0, -1);
            return;
        }
        if (k === '.') {
            if (!text.includes('.')) text = (text || '0') + '.';
            return;
        }
        // منزلتان عشريتان كحدّ أقصى
        if (text.includes('.') && text.split('.')[1].length >= 2) return;
        if (text === '0') text = k;
        else text = text + k;
    }

    function addRiyals(n: number) {
        text = (Math.round((parseFloat(text || '0') || 0) + n) * 1).toString();
    }

    function applySuggestion() {
        if (typoSuggestion !== null) text = typoSuggestion.toString();
    }

    function save() {
        value = halalas;
        onSave?.(halalas);
        open = false;
    }

    const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'del'];
</script>

<SheetShell bind:open {title} {subtitle}>
    <!-- الرقم -->
    <div class="pt-1 pb-2.5 text-center">
        <p class="text-[42px] leading-none font-semibold tracking-[-0.04em] tabular-nums">
            {text || '0'}<span class="ms-1.5 text-[15px] font-medium text-muted-foreground">ر.س</span>
        </p>

        {#if typoSuggestion !== null}
            <button
                type="button"
                onclick={applySuggestion}
                class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-full border border-primary/25 bg-primary/8 px-3 text-[11.5px] font-semibold text-primary"
            >
                <Wand class="size-3.5" /> تقصد {typoSuggestion} ر.س؟
            </button>
        {:else if hint}
            <p class="mt-1.5 text-[11.5px] text-muted-foreground">{hint}</p>
        {/if}
    </div>

    <!-- رقائق سريعة -->
    {#if quickAdd.length || averageAmount > 0}
        <div class="mb-3 flex flex-wrap justify-center gap-1.5">
            {#each quickAdd as q (q)}
                <button
                    type="button"
                    onclick={() => addRiyals(q)}
                    class="inline-flex min-h-11 min-w-12 items-center justify-center rounded-xl border border-input px-3 text-[13px] font-medium text-foreground/85"
                >
                    +{q}
                </button>
            {/each}
            {#if averageAmount > 0}
                <button
                    type="button"
                    onclick={() => (text = (averageAmount / 100).toString())}
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-input px-3 text-[13px] font-medium text-foreground/85"
                >
                    المتوسّط {formatAmount(averageAmount)}
                </button>
            {/if}
        </div>
    {/if}

    <!-- لوحة الأرقام -->
    <div class="grid grid-cols-3 gap-2">
        {#each KEYS as k (k)}
            <button
                type="button"
                onclick={() => press(k)}
                aria-label={k === 'del' ? 'حذف' : k}
                class="grid min-h-[50px] place-items-center rounded-2xl border border-border bg-secondary text-[21px] font-medium transition-transform active:scale-[.97]"
            >
                {#if k === 'del'}
                    <Delete class="size-[21px]" />
                {:else}
                    {k}
                {/if}
            </button>
        {/each}
    </div>

    {#snippet footer()}
        <button
            type="button"
            onclick={() => (open = false)}
            class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85"
        >
            إلغاء
        </button>
        <button
            type="button"
            disabled={halalas <= 0}
            onclick={save}
            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary text-[14.5px] font-semibold text-primary-foreground transition-transform active:scale-[.99] disabled:opacity-45"
        >
            <Check class="size-[18px]" />
            {saveLabel}
            {#if halalas > 0}{formatAmount(halalas)} ر.س{/if}
        </button>
    {/snippet}
</SheetShell>
