<script lang="ts">
    /**
     * الهيكل المتجاوب — شريط سفلي على الجوال، شريط جانبي على الديسكتوب.
     *
     * ══════════════════════════════════════════════════════════════════
     *  الجوال هو التصميم الأساسي، والديسكتوب توسّع له — لا العكس.
     * ══════════════════════════════════════════════════════════════════
     *
     * مكوّن واحد بتخطيطين، تفصل بينهما `md:` — لا JS ولا media query،
     * فما فيه وميض تخطيط عند التحميل.
     *
     *  الجوال (< 768px)
     *    شريط سفلي ثابت: الرئيسية · الميزانية · [+] · الالتزامات · المزيد
     *    زر الإضافة في المنتصف — أقرب نقطة للإبهام، وأهم إجراء يومي.
     *    «المزيد» يفتح لوحاً منزلقاً بالوجهات الأقل تكراراً.
     *    يحترم safe-area-inset-bottom لشريط الآيفون السفلي.
     *
     *  الديسكتوب (≥ 768px)
     *    شريط جانبي 78px بحلقة الميزانية، يتوسّع إلى 252px ببطاقة
     *    «المتبقي لك». حالة التوسّع تُحفظ في كوكي.
     *
     * كل هدف لمس لا يقل عن 44×44px.
     */
    import { Link, page } from '@inertiajs/svelte';
    import ChartNoAxesColumn from 'lucide-svelte/icons/chart-no-axes-column';
    import Ellipsis from 'lucide-svelte/icons/ellipsis';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import PanelRightClose from 'lucide-svelte/icons/panel-right-close';
    import PanelRightOpen from 'lucide-svelte/icons/panel-right-open';
    import Plus from 'lucide-svelte/icons/plus';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import Settings from 'lucide-svelte/icons/settings';
    import Target from 'lucide-svelte/icons/target';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';
    import Vault from 'lucide-svelte/icons/vault';
    import AiAssistantIcon from '@/components/icons/AiAssistantIcon.svelte';
    import { formatAmount, formatPercent } from '@/lib/format';

    interface NavStats {
        remaining: number;
        dailySafe: number;
        daysLeft: number;
        budgetUsedPct: number;
        transactionsCount: number;
        dueCommitments: number;
        savingsPct: number;
        incomeSplit: { key: string; pct: number; color: string }[];
    }

    let {
        stats,
        expanded = $bindable(false),
        onQuickAdd,
    }: {
        stats: NavStats;
        expanded?: boolean;
        onQuickAdd?: () => void;
    } = $props();

    const url = $derived(page.url ?? '');
    const user = $derived(page.props.auth?.user);
    let moreOpen = $state(false);

    /** الوجهات الأربع في الشريط السفلي — الأكثر تكراراً. */
    const PRIMARY = [
        { title: 'الرئيسية', href: '/dashboard', icon: LayoutGrid },
        { title: 'الميزانية', href: '/budgets', icon: Target },
        { title: 'الالتزامات', href: '/commitments', icon: ReceiptText, badge: () => stats.dueCommitments },
    ];

    /** الوجهات الأقل تكراراً — داخل «المزيد» على الجوال، وفي الشريط على الديسكتوب. */
    const SECONDARY = [
        { group: 'فلوسي', items: [
            { title: 'المعاملات', href: '/transactions', icon: ArrowRightLeft, stat: () => String(stats.transactionsCount) },
            { title: 'الادخار', href: '/savings', icon: Vault, stat: () => formatPercent(stats.savingsPct) },
        ]},
        { group: 'أدوات', items: [
            { title: 'التقارير', href: '/reports', icon: ChartNoAxesColumn },
            { title: 'المساعد الذكي', href: '/assistant', icon: AiAssistantIcon },
            { title: 'الإعدادات', href: '/settings/profile', icon: Settings },
        ]},
    ];

    /** ترتيب الشريط الجانبي على الديسكتوب — الستة كاملة. */
    const RAIL = [
        { group: 'نظرة عامة', items: [PRIMARY[0]] },
        { group: 'فلوسي', items: [SECONDARY[0].items[0], PRIMARY[1]] },
        { group: 'التزاماتي', items: [PRIMARY[2], SECONDARY[0].items[1]] },
        { group: 'أدوات', items: [SECONDARY[1].items[0]] },
    ];

    function isActive(href: string): boolean {
        return url === href || url.startsWith(href + '/') || url.startsWith(href + '?');
    }

    function toggle() {
        expanded = !expanded;
        document.cookie = `rail_expanded=${expanded ? '1' : '0'};path=/;max-age=31536000;samesite=lax`;
    }

    const currentMonth = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    const ringDash = $derived(Math.min(100, Math.max(0, stats.budgetUsedPct)));
    const ringColor = $derived(
        stats.budgetUsedPct > 100 ? 'var(--destructive)' : stats.budgetUsedPct >= 70 ? 'var(--warning)' : 'var(--success)',
    );

    function onKeydown(e: KeyboardEvent) {
        if (e.key === 'Escape' && moreOpen) moreOpen = false;
    }
</script>

<svelte:window onkeydown={onKeydown} />

<!-- ══════════════════════════════════════════════════════════════════
     الديسكتوب — شريط جانبي
     ══════════════════════════════════════════════════════════════════ -->
<aside
    class="hidden shrink-0 flex-col border-s border-sidebar-border bg-sidebar py-3.5 transition-[width] duration-200 ease-out md:flex {expanded
        ? 'w-[252px] px-3'
        : 'w-[78px] items-center px-0'}"
>
    <div class="mb-3 flex w-full items-center gap-2.5 {expanded ? 'px-2' : 'justify-center'}">
        <Link href="/dashboard" class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary text-[15px] font-bold text-primary-foreground no-underline">
            م
        </Link>
        {#if expanded}
            <div class="min-w-0 flex-1">
                <b class="block truncate text-[14.5px] font-semibold">ميزانيتي</b>
                <span class="block text-[10.5px] text-muted-foreground">{currentMonth}</span>
            </div>
            <button type="button" onclick={toggle} aria-label="طيّ الشريط" aria-expanded="true"
                class="grid size-9 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-secondary hover:text-foreground">
                <PanelRightOpen class="size-4" />
            </button>
        {/if}
    </div>

    {#if expanded}
        <div class="mb-3 rounded-2xl border border-sidebar-border bg-linear-to-b from-accent to-transparent px-4 py-3.5">
            <p class="text-[11px] text-muted-foreground">المتبقي لك للصرف</p>
            <p class="mt-0.5 text-[25px] leading-tight font-semibold tracking-tighter {stats.remaining < 0 ? 'text-destructive' : ''}">
                {formatAmount(stats.remaining)}<span class="ms-1 text-xs font-medium text-foreground/75">ر.س</span>
            </p>
            <div class="mt-2.5 flex h-[5px] gap-[1.5px] overflow-hidden rounded-full border border-sidebar-border bg-secondary">
                {#each stats.incomeSplit as s (s.key)}
                    <i class="block h-full" style="width:{s.pct}%;background-color:{s.color}"></i>
                {/each}
            </div>
            <div class="mt-1.5 flex justify-between text-[10.5px] text-muted-foreground">
                <span class="tabular-nums">{formatAmount(stats.dailySafe)} ر.س يومياً</span>
                <span class="tabular-nums">{stats.daysLeft} يوم للراتب</span>
            </div>
        </div>
    {:else}
        <button type="button" onclick={toggle} aria-label="توسيع الشريط" aria-expanded="false"
            class="relative mb-1.5 size-[52px] rounded-full transition-transform hover:scale-105"
            title="استُهلك {formatPercent(stats.budgetUsedPct)} من ميزانيتك — اضغط للتوسيع">
            <svg viewBox="0 0 36 36" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--secondary)" stroke-width="3.5" />
                <circle cx="18" cy="18" r="15.5" fill="none" stroke={ringColor} stroke-width="3.5" stroke-linecap="round" stroke-dasharray="{ringDash} 100" />
            </svg>
            <span class="absolute inset-0 grid place-content-center text-center">
                <b class="block text-[13px] leading-none font-semibold tracking-tight tabular-nums">{formatPercent(stats.budgetUsedPct)}</b>
                <span class="mt-px block text-[8px] text-muted-foreground">مصروف</span>
            </span>
        </button>
        <div class="my-2 h-px w-6 bg-sidebar-border"></div>
    {/if}

    <nav class="flex w-full flex-col gap-0.5 {expanded ? '' : 'items-center'}">
        {#each RAIL as g (g.group)}
            {#if expanded}
                <p class="px-3 pt-3 pb-1 text-[10.5px] text-muted-foreground">{g.group}</p>
            {/if}
            {#each g.items as item (item.href)}
                {@const active = isActive(item.href)}
                {@const badge = item.badge?.() ?? 0}
                <Link href={item.href} aria-current={active ? 'page' : undefined}
                    class="group relative flex items-center no-underline transition-colors {expanded
                        ? 'gap-2.5 rounded-xl px-3 py-2.5 text-[13px]'
                        : 'size-[46px] justify-center rounded-2xl'} {active
                        ? 'bg-accent font-semibold text-accent-foreground'
                        : 'text-sidebar-foreground hover:bg-secondary hover:text-foreground'}">
                    {#if active}
                        <span class="absolute top-2.5 bottom-2.5 w-[3px] rounded-full bg-primary" style="inset-inline-end:0"></span>
                    {/if}
                    <item.icon class={expanded ? 'size-[17px] shrink-0' : 'size-5'} />
                    {#if expanded}
                        <span class="min-w-0 flex-1 truncate">{item.title}</span>
                        {#if badge > 0}
                            <span class="grid h-[18px] min-w-[18px] place-items-center rounded-full bg-destructive px-1.5 text-[10px] font-bold text-white tabular-nums">{badge}</span>
                        {:else if item.stat}
                            <span class="text-[10.5px] text-muted-foreground tabular-nums">{item.stat()}</span>
                        {/if}
                    {:else}
                        {#if badge > 0}
                            <span class="absolute top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-destructive px-1 text-[9.5px] font-bold text-white tabular-nums" style="inset-inline-start:6px">{badge}</span>
                        {/if}
                        <span class="pointer-events-none absolute top-1/2 z-30 -translate-y-1/2 rounded-lg bg-foreground px-2.5 py-1 text-[11.5px] whitespace-nowrap text-background opacity-0 transition-opacity group-hover:opacity-100" style="inset-inline-end:calc(100% + 8px)">
                            {item.title}
                        </span>
                    {/if}
                </Link>
            {/each}
        {/each}
    </nav>

    <div class="flex-1"></div>

    <Link href="/assistant"
        class="mb-2.5 flex items-center gap-2.5 no-underline transition-transform hover:scale-[1.02] {expanded
            ? 'w-full rounded-2xl border border-sidebar-border p-2.5'
            : 'justify-center'}"
        style={expanded ? 'background:linear-gradient(135deg,color-mix(in srgb,var(--chart-3) 9%,transparent),transparent)' : ''}
        aria-label="المساعد الذكي">
        <span class="grid shrink-0 place-items-center text-white {expanded ? 'size-9 rounded-xl' : 'size-[50px] rounded-[17px]'}"
            style="background:linear-gradient(145deg,#2c4a6e,#1baf7a);box-shadow:0 4px 14px rgba(27,175,122,.28)">
            <AiAssistantIcon class={expanded ? 'size-[18px]' : 'size-6'} />
        </span>
        {#if expanded}
            <span class="min-w-0">
                <b class="block text-[12.5px] font-semibold">المساعد الذكي</b>
                <span class="block text-[10.5px] text-muted-foreground">اسألني عن ميزانيتك</span>
            </span>
        {/if}
    </Link>

    <div class="w-full {expanded ? 'border-t border-sidebar-border pt-2.5' : 'flex justify-center'}">
        <Link href="/settings/profile" class="flex items-center gap-2.5 rounded-xl no-underline {expanded ? 'w-full px-2 py-1.5 hover:bg-secondary' : ''}">
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-sidebar-border bg-secondary text-[12.5px] font-semibold text-foreground/75">
                {user?.name?.[0] ?? 'م'}
            </span>
            {#if expanded}
                <span class="min-w-0">
                    <b class="block truncate text-[12.5px] font-medium">{user?.name ?? 'حسابي'}</b>
                    <span class="block text-[10px] text-muted-foreground">الإعدادات</span>
                </span>
            {/if}
        </Link>
    </div>
</aside>

<!-- ══════════════════════════════════════════════════════════════════
     الجوال — لوح «المزيد»
     ══════════════════════════════════════════════════════════════════ -->
{#if moreOpen}
    <div class="fixed inset-0 z-50 flex flex-col justify-end md:hidden">
        <button type="button" class="absolute inset-0 cursor-default bg-black/45" onclick={() => (moreOpen = false)} aria-label="إغلاق"></button>
        <div class="relative rounded-t-3xl border-t border-border bg-card px-3 pt-2 shadow-2xl"
            style="padding-bottom: calc(76px + env(safe-area-inset-bottom))"
            role="dialog" aria-modal="true" aria-label="وجهات إضافية">
            <div class="mx-auto mt-1.5 mb-3 h-1 w-9 rounded-full bg-input"></div>
            {#each SECONDARY as g (g.group)}
                <p class="px-3 pt-2 pb-1 text-[10px] text-muted-foreground">{g.group}</p>
                {#each g.items as item (item.href)}
                    {@const active = isActive(item.href)}
                    <Link href={item.href} onclick={() => (moreOpen = false)}
                        class="flex min-h-[48px] items-center gap-3 rounded-2xl px-3 text-[13.5px] no-underline transition-colors {active
                            ? 'bg-accent font-semibold text-accent-foreground'
                            : 'text-foreground/85 hover:bg-secondary'}">
                        <item.icon class="size-[18px] shrink-0 {item.title === 'المساعد الذكي' ? 'text-[color:var(--chart-3)]' : ''}" />
                        <span class="flex-1">{item.title}</span>
                        {#if item.stat}
                            <span class="text-[11px] text-muted-foreground tabular-nums">{item.stat()}</span>
                        {/if}
                    </Link>
                {/each}
            {/each}
        </div>
    </div>
{/if}

<!-- ══════════════════════════════════════════════════════════════════
     الجوال — الشريط السفلي
     ══════════════════════════════════════════════════════════════════ -->
<nav
    class="fixed inset-x-0 bottom-0 z-[55] flex items-center justify-around border-t border-border bg-card shadow-[0_-2px_14px_rgba(22,23,26,.05)] md:hidden"
    style="padding-bottom: env(safe-area-inset-bottom)"
    aria-label="التنقّل الرئيسي"
>
    {#each PRIMARY.slice(0, 2) as item (item.href)}
        {@const active = isActive(item.href)}
        <Link href={item.href} aria-current={active ? 'page' : undefined}
            class="relative flex min-h-[56px] flex-1 flex-col items-center justify-center gap-[3px] py-1.5 text-[9.5px] no-underline {active ? 'text-primary' : 'text-muted-foreground'}">
            {#if active}<span class="absolute top-0 h-[2.5px] w-6 rounded-b-[3px] bg-primary"></span>{/if}
            <item.icon class="size-[21px]" />
            {item.title}
        </Link>
    {/each}

    <!-- زر الإضافة — أقرب نقطة للإبهام -->
    <div class="grid w-[62px] shrink-0 place-items-center">
        <button type="button" onclick={() => onQuickAdd?.()} aria-label="إضافة سريعة"
            class="-mt-5 grid size-[52px] place-items-center rounded-full border-[3.5px] border-card bg-primary text-primary-foreground shadow-[0_5px_16px_rgba(44,74,110,.32)] transition-transform active:scale-95">
            <Plus class="size-6" stroke-width="2.2" />
        </button>
    </div>

    {#each PRIMARY.slice(2) as item (item.href)}
        {@const active = isActive(item.href)}
        {@const badge = item.badge?.() ?? 0}
        <Link href={item.href} aria-current={active ? 'page' : undefined}
            class="relative flex min-h-[56px] flex-1 flex-col items-center justify-center gap-[3px] py-1.5 text-[9.5px] no-underline {active ? 'text-primary' : 'text-muted-foreground'}">
            {#if active}<span class="absolute top-0 h-[2.5px] w-6 rounded-b-[3px] bg-primary"></span>{/if}
            {#if badge > 0}
                <span class="absolute top-1 grid h-[15px] min-w-[15px] place-items-center rounded-full bg-destructive px-1 text-[9px] font-bold text-white tabular-nums" style="inset-inline-start:calc(50% + 7px)">{badge}</span>
            {/if}
            <item.icon class="size-[21px]" />
            {item.title}
        </Link>
    {/each}

    <button type="button" onclick={() => (moreOpen = !moreOpen)} aria-expanded={moreOpen}
        class="relative flex min-h-[56px] flex-1 flex-col items-center justify-center gap-[3px] py-1.5 text-[9.5px] {moreOpen ? 'text-primary' : 'text-muted-foreground'}">
        {#if moreOpen}<span class="absolute top-0 h-[2.5px] w-6 rounded-b-[3px] bg-primary"></span>{/if}
        <Ellipsis class="size-[21px]" />
        المزيد
    </button>
</nav>
