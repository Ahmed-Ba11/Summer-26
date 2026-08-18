<script lang="ts">
    /**
     * زر الإضافة السريعة العائم.
     *
     * المشكلة التي يحلّها: لإضافة مصروف، المستخدم كان لازم ينتقل لصفحة
     * المصاريف ثم يفتح مودال — احتكاك في أكثر إجراء يتكرّر يومياً.
     *
     * يُستدعى مرّة واحدة من AppSidebarLayout فيظهر في كل الصفحات.
     * موضعه في يسار الشاشة (inset-inline-end في RTL) حتى لا يغطي بداية
     * صفوف الجداول.
     */
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import ShoppingCart from 'lucide-svelte/icons/shopping-cart';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';

    let open = $state(false);

    const actions = [
        { label: 'مصروف', icon: ShoppingCart, color: 'var(--chart-1)', href: '/expenses?new=1' },
        { label: 'دخل', icon: TrendingUp, color: 'var(--success)', href: '/income?new=1' },
        { label: 'فاتورة', icon: ReceiptText, color: 'var(--chart-7)', href: '/bills?new=1' },
        { label: 'ادخار', icon: PiggyBank, color: 'var(--chart-3)', href: '/savings?new=1' },
    ];

    function go(href: string) {
        open = false;
        router.visit(href);
    }

    function onKeydown(e: KeyboardEvent) {
        if (e.key === 'Escape' && open) {
            open = false;
        }
    }
</script>

<svelte:window onkeydown={onKeydown} />

{#if open}
    <button
        type="button"
        class="fixed inset-0 z-40 cursor-default bg-transparent"
        onclick={() => (open = false)}
        aria-label="إغلاق قائمة الإضافة"
    ></button>
{/if}

<div class="fixed bottom-6 z-50 flex flex-col items-start gap-2.5" style="inset-inline-end: 1.5rem">
    {#if open}
        <div class="flex flex-col gap-1.5">
            {#each actions as a, i (a.label)}
                {@const Icon = a.icon}
                <button
                    type="button"
                    onclick={() => go(a.href)}
                    class="flex items-center gap-2.5 rounded-full border border-border bg-card py-2 ps-2.5 pe-4 text-[13px] shadow-lg transition-transform hover:scale-[1.03]"
                    style="animation: fab-in 180ms ease-out both; animation-delay: {i * 30}ms"
                >
                    <span class="grid size-[22px] place-items-center rounded-full" style="background-color: {a.color}">
                        <Icon class="size-[13px] text-white" />
                    </span>
                    {a.label}
                </button>
            {/each}
        </div>
    {/if}

    <button
        type="button"
        onclick={() => (open = !open)}
        aria-expanded={open}
        aria-label={open ? 'إغلاق الإضافة السريعة' : 'إضافة سريعة'}
        class="grid size-14 place-items-center rounded-full bg-primary text-primary-foreground shadow-xl transition-transform duration-200 {open
            ? 'rotate-45'
            : ''}"
    >
        <Plus class="size-6" />
    </button>
</div>

<style>
    @keyframes fab-in {
        from {
            opacity: 0;
            transform: translateY(8px) scale(0.96);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }
</style>
