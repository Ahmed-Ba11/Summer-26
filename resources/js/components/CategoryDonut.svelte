<script lang="ts">
    /**
     * رسم دائري مجوّف لتوزيع المصاريف على الفئات.
     *
     * مبني بـ stroke-dasharray (لا مسارات SVG يدوية) — أدق، وأبسط، ويدعم
     * الفواصل بين القطع.
     *
     * القائمة المجاورة **إلزامية** وليست خياراً: ثلاثة من ألوان البالتة تحت
     * 3:1 تباين على السطح الفاتح، والتسمية المباشرة هي المعالجة المطلوبة.
     *
     * حدّ أقصى ٧ فئات معروضة؛ ما زاد ينطوي تحت «أخرى».
     */
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import { formatAmount, formatPercent } from '@/lib/format';

    interface Cat {
        id: number | string;
        name: string;
        icon: string;
        color: string;
        amount: number;
    }

    let { categories = [], max = 7 }: { categories?: Cat[]; max?: number } = $props();

    const sorted = $derived([...categories].filter((c) => c.amount > 0).sort((a, b) => b.amount - a.amount));

    const shown = $derived.by(() => {
        if (sorted.length <= max) return sorted;
        const head = sorted.slice(0, max - 1);
        const tail = sorted.slice(max - 1);
        return [
            ...head,
            {
                id: '__other',
                name: 'أخرى',
                icon: 'ellipsis',
                color: '#8a8b90',
                amount: tail.reduce((s, c) => s + c.amount, 0),
            },
        ];
    });

    const total = $derived(shown.reduce((s, c) => s + c.amount, 0));

    // نصف القطر مختار بحيث محيط الدائرة = 100، فتصير النِسب مباشرة أطوالاً
    const R = 15.915;
    const GAP = 0.8; // فاصل بصري بين القطع

    const segments = $derived.by(() => {
        let offset = 0;
        return shown.map((c) => {
            const p = total > 0 ? (c.amount / total) * 100 : 0;
            const seg = { ...c, pct: p, dash: Math.max(0, p - GAP), offset: -offset };
            offset += p;
            return seg;
        });
    });

    let hovered = $state<string | number | null>(null);
</script>

<div class="flex flex-col items-center gap-6 sm:flex-row">
    <div class="relative size-[132px] shrink-0">
        <svg viewBox="0 0 42 42" class="size-full -rotate-90" aria-hidden="true">
            <circle cx="21" cy="21" r={R} fill="none" stroke="var(--secondary)" stroke-width="5" />
            {#each segments as seg (seg.id)}
                <circle
                    cx="21"
                    cy="21"
                    r={R}
                    fill="none"
                    stroke={seg.color}
                    stroke-width={hovered === seg.id ? 6.5 : 5}
                    stroke-dasharray="{seg.dash} {100 - seg.dash}"
                    stroke-dashoffset={seg.offset}
                    class="transition-[stroke-width] duration-150"
                />
            {/each}
        </svg>
        <div class="absolute inset-0 grid place-content-center text-center">
            <p class="text-base font-semibold tracking-tight tabular-nums">{formatAmount(total)}</p>
            <p class="text-[11px] text-muted-foreground">ر.س</p>
        </div>
    </div>

    <!-- التسمية المباشرة — إلزامية -->
    <ul class="min-w-0 flex-1 space-y-px">
        {#each segments as seg (seg.id)}
            <li
                class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-secondary"
                onmouseenter={() => (hovered = seg.id)}
                onmouseleave={() => (hovered = null)}
            >
                <CategoryIcon icon={seg.icon} color={seg.color} size="sm" />
                <span class="min-w-0 flex-1 truncate text-[13px]">{seg.name}</span>
                <span class="text-[12.5px] font-semibold tabular-nums">{formatAmount(seg.amount)}</span>
                <span class="w-11 text-end text-[11.5px] text-muted-foreground tabular-nums">
                    {formatPercent(seg.pct)}
                </span>
            </li>
        {/each}
    </ul>
</div>
