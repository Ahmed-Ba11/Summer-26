<script lang="ts">
    /**
     * الشريط الجانبي — مُعاد تنظيمه في أربع مجموعات.
     *
     * ما تغيّر عن النسخة السابقة:
     *  1. كانت قائمة مسطّحة بثمانية عناصر، والترتيب غير منطقي (لوحة التحكم
     *     في المركز السابع). صارت مجموعات مسمّاة، ولوحة التحكم أولاً.
     *  2. عنصر «المساعد الذكي» كان href="#" — رابط ميت في التنقّل يضرّ الثقة.
     *     صار مساراً فعلياً /assistant.
     *  3. أُضيفت شارة عدد الفواتير المستحقة، وصفحة التقارير.
     */
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import FileBarChart from 'lucide-svelte/icons/file-bar-chart';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import Target from 'lucide-svelte/icons/target';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let { children }: { children?: Snippet } = $props();

    // يأتي من HandleInertiaRequests كمشاركة عامة
    const dueBills = $derived(page.props.dueBillsCount ?? 0);

    const currentMonth = new Intl.DateTimeFormat('ar-SA-u-ca-gregory-nu-latn', {
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    const overview: NavItem[] = [{ title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid }];

    const money: NavItem[] = [
        { title: 'الدخل', href: '/income', icon: TrendingUp },
        { title: 'الميزانية', href: '/budgets', icon: Target },
        { title: 'المصاريف', href: '/expenses', icon: ArrowRightLeft },
    ];

    const commitments: NavItem[] = [
        { title: 'الفواتير', href: '/bills', icon: ReceiptText, badge: dueBills },
        { title: 'الأقساط', href: '/installments', icon: CreditCard },
        { title: 'الادخار', href: '/savings', icon: PiggyBank },
    ];

    const tools: NavItem[] = [
        { title: 'التقارير', href: '/reports', icon: FileBarChart },
        { title: 'المساعد الذكي', href: '/assistant', icon: Sparkles, tag: 'تجريبي' },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link {...(props || {})} href={toUrl('/dashboard')} class="flex w-full items-center gap-3">
                            <AppLogo />
                            <span class="text-[11px] text-muted-foreground group-data-[collapsible=icon]:hidden">
                                {currentMonth}
                            </span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={overview} label="نظرة عامة" />
        <NavMain items={money} label="فلوسي" />
        <NavMain items={commitments} label="التزاماتي" />
        <NavMain items={tools} label="أدوات" />
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>

{@render children?.()}
