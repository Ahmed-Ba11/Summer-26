<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'الفواتير', href: '/bills' },
        ],
    };
</script>

<script lang="ts">
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Calendar from 'lucide-svelte/icons/calendar';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import Clock from 'lucide-svelte/icons/clock';
    import FileText from 'lucide-svelte/icons/file-text';
    import Hash from 'lucide-svelte/icons/hash';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Receipt from 'lucide-svelte/icons/receipt';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

    interface Bill {
        id: number;
        icon: string;
        name: string;
        amount: number;
        dueDate: string;
        status: string;
        accountNumber: string | null;
    }

    const mockBills: Bill[] = [
        { id: 1, icon: '⚡', name: 'فاتورة كهرباء', amount: 350000, dueDate: '2026-08-15', status: 'upcoming', accountNumber: '62012345' },
        { id: 2, icon: '📱', name: 'فاتورة جوال', amount: 150000, dueDate: '2026-08-10', status: 'upcoming', accountNumber: null },
        { id: 3, icon: '🌐', name: 'فاتورة إنترنت', amount: 200000, dueDate: '2026-08-05', status: 'upcoming', accountNumber: 'NET998877' },
        { id: 4, icon: '💧', name: 'فاتورة ماء', amount: 120000, dueDate: '2026-07-20', status: 'paid', accountNumber: null },
        { id: 5, icon: '⚡', name: 'فاتورة كهرباء', amount: 320000, dueDate: '2026-07-15', status: 'paid', accountNumber: '62012345' },
    ];

    let bills = $state<Bill[]>([...mockBills]);

    function displayAmount(halalas: number): string {
        return (halalas / 100).toLocaleString('ar-SA') + ' ر.س';
    }

    function formatDate(dateStr: string): string {
        return new Date(dateStr).toLocaleDateString('ar-SA');
    }

    function daysUntil(dueDateStr: string): number {
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const due = new Date(dueDateStr);
        due.setHours(0, 0, 0, 0);

        return Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    }

    function isPastDue(dueDateStr: string): boolean {
        return daysUntil(dueDateStr) < 0;
    }

    function dueLabel(item: Bill): { text: string; class: string } {
        if (item.status === 'paid') {
            return { text: 'مدفوعة', class: 'text-green-600 dark:text-green-400' };
        }

        const days = daysUntil(item.dueDate);

        if (days < 0) {
            return { text: `متأخرة بـ ${Math.abs(days)} أيام`, class: 'text-red-600 dark:text-red-400' };
        }

        if (days === 0) {
            return { text: 'اليوم', class: 'text-orange-600 dark:text-orange-400' };
        }

        if (days <= 3) {
            return { text: `بعد ${days} أيام`, class: 'text-orange-600 dark:text-orange-400' };
        }

        return { text: `بعد ${days} أيام`, class: 'text-muted-foreground' };
    }

    function statusBadge(item: Bill): { text: string; light: string; dark: string; icon: typeof CircleCheck } {
        if (item.status === 'paid') {
            return { text: 'مدفوعة', light: 'bg-green-100 text-green-700', dark: 'dark:bg-green-900/30 dark:text-green-400', icon: CircleCheck };
        }

        if (isPastDue(item.dueDate)) {
            return { text: 'متأخرة', light: 'bg-red-100 text-red-700', dark: 'dark:bg-red-900/30 dark:text-red-400', icon: AlertCircle };
        }

        return { text: 'مستحقة', light: 'bg-blue-100 text-blue-700', dark: 'dark:bg-blue-900/30 dark:text-blue-400', icon: Clock };
    }

    let activeTab = $state<'upcoming' | 'paid' | 'all'>('upcoming');

    const filteredBills = $derived.by(() => {
        if (activeTab === 'all') {
return bills;
}

        return bills.filter((b) => b.status === activeTab);
    });

    const upcomingCount = $derived(bills.filter((b) => b.status === 'upcoming').length);
    const paidCount = $derived(bills.filter((b) => b.status === 'paid').length);
    const totalUpcomingAmount = $derived(
        bills.filter((b) => b.status === 'upcoming').reduce((s, b) => s + b.amount, 0),
    );

    // Details modal
    let selectedBill = $state<Bill | null>(null);
    let showDetailsModal = $state(false);

    function openDetailsModal(item: Bill) {
        selectedBill = item;
        showDetailsModal = true;
    }

    function closeDetailsModal() {
        showDetailsModal = false;
        selectedBill = null;
    }

    function markAsPaid() {
        if (!selectedBill) {
return;
}

        bills = bills.map((b) =>
            b.id === selectedBill!.id ? { ...b, status: 'paid' } : b,
        );
        selectedBill = { ...selectedBill, status: 'paid' };
    }

    function deleteBill() {
        if (!selectedBill) {
return;
}

        bills = bills.filter((b) => b.id !== selectedBill!.id);
        closeDetailsModal();
    }

    // Add modal
    let showFormModal = $state(false);
    let editingId = $state<number | null>(null);
    let formName = $state('');
    let formAmount = $state('');
    let formDueDate = $state('');
    let formAccountNumber = $state('');
    let formErrors = $state<Record<string, string>>({});

    function openAddModal() {
        editingId = null;
        formName = '';
        formAmount = '';
        formDueDate = '';
        formAccountNumber = '';
        formErrors = {};
        showFormModal = true;
    }

    function openEditModal(item: Bill) {
        editingId = item.id;
        formName = item.name;
        formAmount = String(item.amount / 100);
        formDueDate = item.dueDate;
        formAccountNumber = item.accountNumber ?? '';
        formErrors = {};
        showFormModal = true;
    }

    function closeFormModal() {
        showFormModal = false;
        editingId = null;
        formErrors = {};
    }

    function submitForm() {
        formErrors = {};
        const amountSar = parseFloat(formAmount);

        if (!formName.trim()) {
            formErrors.name = 'اسم الفاتورة مطلوب';

            return;
        }

        if (!amountSar || amountSar <= 0) {
            formErrors.amount = 'المبلغ مطلوب';

            return;
        }

        if (!formDueDate) {
            formErrors.dueDate = 'تاريخ الاستحقاق مطلوب';

            return;
        }

        if (editingId) {
            bills = bills.map((b) =>
                b.id === editingId
                    ? {
                          ...b,
                          name: formName.trim(),
                          amount: Math.round(amountSar * 100),
                          dueDate: formDueDate,
                          accountNumber: formAccountNumber.trim() || null,
                      }
                    : b,
            );
        } else {
            const newItem: Bill = {
                id: Math.max(0, ...bills.map((b) => b.id)) + 1,
                icon: '📄',
                name: formName.trim(),
                amount: Math.round(amountSar * 100),
                dueDate: formDueDate,
                status: 'upcoming',
                accountNumber: formAccountNumber.trim() || null,
            };
            bills = [...bills, newItem];
        }

        closeFormModal();
    }

    // Delete from list
    let deleteId = $state<number | null>(null);

    function confirmDelete(id: number) {
        deleteId = id;
    }

    function cancelDelete() {
        deleteId = null;
    }

    function executeDelete() {
        if (!deleteId) {
return;
}

        bills = bills.filter((b) => b.id !== deleteId);

        if (showDetailsModal && selectedBill?.id === deleteId) {
            closeDetailsModal();
        }

        deleteId = null;
    }

    const tabs = [
        { key: 'upcoming' as const, label: 'القادمة', count: upcomingCount },
        { key: 'paid' as const, label: 'المدفوعة', count: paidCount },
        { key: 'all' as const, label: 'الكل', count: bills.length },
    ];
</script>

<AppHead title="الفواتير" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">الفواتير</h1>
            <p class="text-muted-foreground">فواتيرك الدورية</p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة فاتورة جديدة
        </Button>
    </div>

    {#if upcomingCount > 0}
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">الفواتير القادمة</p>
                        <FileText class="size-4 text-blue-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{upcomingCount}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">إجمالي المستحق</p>
                        <Receipt class="size-4 text-orange-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold text-destructive">{displayAmount(totalUpcomingAmount)}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">المدفوعة</p>
                        <CircleCheck class="size-4 text-green-500" />
                    </div>
                    <p class="mt-2 text-xl font-bold">{paidCount}</p>
                </CardContent>
            </Card>
        </div>
    {/if}

    <!-- Tabs -->
    <div class="flex gap-1 rounded-lg bg-muted p-1">
        {#each tabs as tab}
            <button
                class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium transition-all {activeTab === tab.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
                onclick={() => (activeTab = tab.key)}
            >
                {tab.label}
                <span class="mr-1 text-xs text-muted-foreground">({tab.count})</span>
            </button>
        {/each}
    </div>

    <!-- Cards -->
    {#if filteredBills.length === 0}
        <Card>
            <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                <Receipt class="size-12 text-muted-foreground" />
                <p class="mt-3 font-medium">لا توجد فواتير</p>
                <p class="text-sm text-muted-foreground">
                    {activeTab === 'upcoming' ? 'لا توجد فواتير قادمة' : activeTab === 'paid' ? 'لا توجد فواتير مدفوعة' : 'أضف فاتورة جديدة للبدء'}
                </p>
                {#if activeTab === 'all'}
                    <Button size="sm" class="mt-4 gap-1.5" onclick={openAddModal}>
                        <Plus class="size-3.5" />
                        إضافة فاتورة جديدة
                    </Button>
                {/if}
            </CardContent>
        </Card>
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each filteredBills as item}
                {@const due = dueLabel(item)}
                {@const badge = statusBadge(item)}
                <Card class="overflow-hidden {isPastDue(item.dueDate) && item.status !== 'paid' ? 'border-destructive/50' : ''}">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-lg bg-muted text-xl">
                                    {item.icon}
                                </span>
                                <div>
                                    <CardTitle class="text-base">{item.name}</CardTitle>
                                    <CardDescription>{formatDate(item.dueDate)}</CardDescription>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full {badge.light} {badge.dark} px-2 py-0.5 text-[10px] font-medium">
                                <badge.icon class="size-2.5" />
                                {badge.text}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">المبلغ</span>
                                <span class="text-sm font-bold tabular-nums">{displayAmount(item.amount)}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">تاريخ الاستحقاق</span>
                                <span class="text-sm tabular-nums {due.class}">
                                    {#if item.status === 'paid'}
                                        <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                            <CircleCheck class="size-3" />
                                            مدفوعة
                                        </span>
                                    {:else}
                                        {due.text}
                                    {/if}
                                </span>
                            </div>
                            {#if item.accountNumber}
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">رقم الحساب</span>
                                    <span class="text-sm tabular-nums">{item.accountNumber}</span>
                                </div>
                            {/if}
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="flex-1 text-xs gap-1"
                                onclick={() => openDetailsModal(item)}
                            >
                                تفاصيل
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="تعديل"
                                onclick={() => openEditModal(item)}
                            >
                                <Pencil class="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label="حذف"
                                class="text-destructive hover:text-destructive"
                                onclick={() => confirmDelete(item.id)}
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<!-- Details Modal -->
{#if showDetailsModal && selectedBill}
    {@const item = selectedBill}
    {@const due = dueLabel(item)}
    {@const badge = statusBadge(item)}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center"
        onclick={(e) => {
 if (e.target === e.currentTarget) {
closeDetailsModal();
} 
}}
        onkeydown={(e) => {
 if (e.key === 'Escape') {
closeDetailsModal();
} 
}}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeDetailsModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-lg rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <span class="text-xl">{item.icon}</span>
                    {item.name}
                </h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeDetailsModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div class="rounded-lg bg-muted/50 p-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">المبلغ</span>
                            <p class="text-lg font-bold tabular-nums">{displayAmount(item.amount)}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">الحالة</span>
                            <p>
                                <span class="inline-flex items-center gap-1 rounded-full {badge.light} {badge.dark} px-2 py-0.5 text-xs font-medium">
                                    <badge.icon class="size-3" />
                                    {badge.text}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">تاريخ الاستحقاق</span>
                            <p class="font-medium tabular-nums">{formatDate(item.dueDate)}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">الموعد</span>
                            <p class="font-medium {due.class}">{due.text}</p>
                        </div>
                        {#if item.accountNumber}
                            <div>
                                <span class="flex items-center gap-1 text-muted-foreground">
                                    <Hash class="size-3" />
                                    رقم الحساب
                                </span>
                                <p class="font-medium tabular-nums">{item.accountNumber}</p>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" class="text-destructive hover:text-destructive" onclick={deleteBill}>
                    <Trash2 class="size-4" />
                    حذف
                </Button>
                {#if item.status === 'upcoming'}
                    <Button onclick={markAsPaid} class="gap-1.5">
                        <CircleCheck class="size-4" />
                        تم الدفع
                    </Button>
                {:else}
                    <Button variant="outline" onclick={closeDetailsModal}>إغلاق</Button>
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- Add / Edit Modal -->
{#if showFormModal}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto pt-[10vh]"
        onclick={(e) => {
 if (e.target === e.currentTarget) {
closeFormModal();
} 
}}
        onkeydown={(e) => {
 if (e.key === 'Escape') {
closeFormModal();
} 
}}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={closeFormModal}></div>
        <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border bg-card p-0 shadow-lg">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold">
                    {editingId ? 'تعديل فاتورة' : 'إضافة فاتورة جديدة'}
                </h2>
                <button class="text-muted-foreground hover:text-foreground cursor-pointer" onclick={closeFormModal}>
                    <X class="size-5" />
                </button>
            </div>
            <div class="space-y-4 px-6 py-4">
                <div>
                    <label for="bill-name" class="mb-1.5 block text-sm font-medium">اسم الفاتورة</label>
                    <input
                        id="bill-name"
                        type="text"
                        placeholder="مثال: فاتورة كهرباء"
                        bind:value={formName}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.name}
                        <p class="mt-1 text-xs text-destructive">{formErrors.name}</p>
                    {/if}
                </div>
                <div>
                    <label for="bill-amount" class="mb-1.5 block text-sm font-medium">المبلغ (ر.س)</label>
                    <input
                        id="bill-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        bind:value={formAmount}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring text-left direction-ltr"
                    />
                    {#if formErrors.amount}
                        <p class="mt-1 text-xs text-destructive">{formErrors.amount}</p>
                    {/if}
                </div>
                <div>
                    <label for="bill-date" class="mb-1.5 block text-sm font-medium">تاريخ الاستحقاق</label>
                    <input
                        id="bill-date"
                        type="date"
                        bind:value={formDueDate}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {#if formErrors.dueDate}
                        <p class="mt-1 text-xs text-destructive">{formErrors.dueDate}</p>
                    {/if}
                </div>
                <div>
                    <label for="bill-account" class="mb-1.5 block text-sm font-medium">رقم الحساب (اختياري)</label>
                    <input
                        id="bill-account"
                        type="text"
                        placeholder="مثال: 62012345"
                        bind:value={formAccountNumber}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline" onclick={closeFormModal}>إلغاء</Button>
                <Button onclick={submitForm}>
                    {editingId ? 'حفظ التعديلات' : 'إضافة'}
                </Button>
            </div>
        </div>
    </div>
{/if}

<!-- Delete confirmation -->
{#if deleteId !== null}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center"
        onclick={(e) => {
 if (e.target === e.currentTarget) {
cancelDelete();
} 
}}
        onkeydown={(e) => {
 if (e.key === 'Escape') {
cancelDelete();
} 
}}
    >
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 bg-black/50" onclick={cancelDelete}></div>
        <div class="relative z-10 mx-4 w-full max-w-sm rounded-xl border bg-card p-6 shadow-lg">
            <h2 class="text-lg font-semibold">تأكيد الحذف</h2>
            <p class="mt-2 text-sm text-muted-foreground">هل أنت متأكد من حذف هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.</p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" onclick={cancelDelete}>إلغاء</Button>
                <Button variant="destructive" onclick={executeDelete}>حذف</Button>
            </div>
        </div>
    </div>
{/if}
