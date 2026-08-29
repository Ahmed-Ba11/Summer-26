<script lang="ts">
    /**
     * «يوم الانتهاء» — تاريخ انتهاء آخر قسط، وكم يرجع لك شهرياً بعده.
     * رقم محفّز غائب تماماً في التطبيق الحالي: يحوّل الأقساط من عبء غامض
     * إلى عدّاد تنازلي له نهاية معروفة.
     */
    import Flag from 'lucide-svelte/icons/flag';
    import { formatAmount } from '@/lib/format';

    let { label = '', monthly = 0 }: { label?: string; monthly?: number } = $props();
</script>

{#if label}
    <section class="rounded-2xl border border-border bg-card p-3.5 shadow-xs">
        <p class="flex items-center gap-2 text-[12.5px] font-semibold">
            <Flag class="size-4 text-success-text" />
            يوم الانتهاء
        </p>
        <p class="mt-1 text-[20px] font-semibold tracking-tight">{label}</p>
        <p class="mt-0.5 text-[11px] text-muted-foreground">
            بعدها يرجع لك <b class="font-semibold text-foreground tabular-nums">{formatAmount(monthly)} ر.س</b> شهرياً
        </p>
    </section>
{/if}
