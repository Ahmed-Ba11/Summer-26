<script lang="ts">
    /**
     * لوح التأكيد الموحّد — كل تأكيد حذف أو إجراء لا رجعة فيه يمرّ من هنا.
     */
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import SheetShell from '@/components/ui/SheetShell.svelte';

    let {
        open = $bindable(false),
        title = 'تأكيد الحذف',
        message = '',
        confirmLabel = 'حذف',
        cancelLabel = 'إلغاء',
        loading = false,
        destructive = true,
        onConfirm,
    }: {
        open?: boolean;
        title?: string;
        message?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        loading?: boolean;
        destructive?: boolean;
        onConfirm?: () => void;
    } = $props();
</script>

<SheetShell bind:open {title}>
    <div class="flex flex-col items-center gap-3 py-2 text-center">
        <div
            class="grid size-10 place-items-center rounded-2xl {destructive
                ? 'bg-destructive/10 text-destructive'
                : 'bg-primary/10 text-primary'}"
        >
            <AlertTriangle class="size-[20px]" />
        </div>
        <p class="text-[13px] text-muted-foreground">{message}</p>
    </div>

    {#snippet footer()}
        <button
            type="button"
            disabled={loading}
            onclick={() => (open = false)}
            class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl border border-input px-4 text-[13px] text-foreground/85 disabled:opacity-45"
        >
            {cancelLabel}
        </button>
        <button
            type="button"
            disabled={loading}
            onclick={() => onConfirm?.()}
            class="inline-flex min-h-12 flex-1 items-center justify-center rounded-2xl text-[14.5px] font-semibold transition-transform active:scale-[.99] disabled:opacity-45 {destructive
                ? 'bg-destructive text-white'
                : 'bg-primary text-primary-foreground'}"
        >
            {loading ? 'جارٍ التنفيذ…' : confirmLabel}
        </button>
    {/snippet}
</SheetShell>
