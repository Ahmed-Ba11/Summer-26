<script lang="ts">
    /**
     * زر الإضافة السريعة — ومضيف ألواح الوصول السريع كلّها.
     *
     * المشكلة التي يحلّها: لإضافة مصروف، المستخدم كان لازم ينتقل لصفحة
     * المصاريف ثم يفتح مودالاً — احتكاك في أكثر إجراء يتكرّر يومياً.
     *
     * يُستدعى مرّة واحدة من `AppSidebarLayout` فيظهر في كل الصفحات، ويملك
     * لوحَي الوصول السريع: `QuickAddMenu` (الأربعة إجراءات) و`QuickAddSheet`
     * (المصروف والدخل بقواعد التمويل). شريط التنقّل السفلي على الجوال يفتح
     * نفس اللوحين عبر `menuOpen` و`sheetOpen` — بنسخة واحدة لا نسختين.
     *
     * الزر العائم نفسه للديسكتوب فقط: على الجوال «+» في الشريط السفلي أقرب
     * للإبهام، وزران للإضافة في شاشة واحدة تشتيت.
     */
    import { page } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import QuickAddMenu from '@/components/QuickAddMenu.svelte';
    import QuickAddSheet from '@/components/QuickAddSheet.svelte';
    import { longPress } from '@/lib/long-press';

    let {
        menuOpen = $bindable(false),
        sheetOpen = $bindable(false),
        sheetMode = $bindable<'expense' | 'income'>('expense'),
    }: {
        menuOpen?: boolean;
        sheetOpen?: boolean;
        sheetMode?: 'expense' | 'income';
    } = $props();

    const quickAdd = $derived(page.props.quickAdd ?? null);

    /** الضغطة المطوّلة تقفز للمصروف مباشرة — أكثر الإجراءات تكراراً. */
    function openExpense() {
        menuOpen = false;
        sheetMode = 'expense';
        sheetOpen = true;
    }
</script>

<button
    type="button"
    onclick={() => (menuOpen = true)}
    use:longPress={{ onHold: openExpense }}
    aria-label="إضافة سريعة — اضغط مطوّلاً لتسجيل مصروف"
    class="fixed bottom-6 z-50 hidden size-[58px] place-items-center rounded-full bg-primary text-primary-foreground shadow-xl transition-transform duration-200 select-none active:scale-95 md:grid"
    style="inset-inline-end: 1.5rem"
>
    <Plus class="size-6" stroke-width="2.4" />
</button>

{#if quickAdd}
    <QuickAddMenu
        bind:open={menuOpen}
        savingsGoals={quickAdd.savingsGoals}
        dueCommitments={quickAdd.dueCommitments}
        dueTodayCount={quickAdd.dueTodayCount}
        onPick={(mode) => {
            sheetMode = mode;
            sheetOpen = true;
        }}
    />

    <QuickAddSheet
        bind:open={sheetOpen}
        bind:mode={sheetMode}
        context={quickAdd.context}
        categories={quickAdd.categories}
        lastCategoryId={quickAdd.lastCategoryId}
        learned={quickAdd.learned}
        recurringIncome={quickAdd.recurringIncome}
        fundableGoals={quickAdd.fundableGoals}
    />
{/if}
