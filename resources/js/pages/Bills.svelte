<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'الفواتير', href: '/bills' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import Hash from 'lucide-svelte/icons/hash';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import CategoryIcon from '@/components/CategoryIcon.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import Button from '@/components/ui/button/Button.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import {
        formatCurrency,
        formatDate,
        formatRelativeDays,
        toRiyals,
    } from '@/lib/format';
    import {
        destroy as destroyBill,
        pay as payBill,
        store as storeBill,
        unpay as unpayBill,
    } from '@/routes/bills';

    interface Bill {
        id: number;
        name: string;
        icon: string | null;
        amount: number | null;
        due_date: string;
        account_number: string | null;
        is_paid: boolean;
    }

    interface BillStats {
        upcoming_count: number;
        total_due: number;
        paid_count: number;
    }

    interface PageProps {
        bills?: Bill[];
        stats?: BillStats;
        error?: string | null;
    }

    let {
        bills,
        stats = { upcoming_count: 0, total_due: 0, paid_count: 0 },
        error = null,
    }: PageProps = $props();

    const billItems = $derived(bills ?? []);
    const isLoading = $derived(bills === undefined);
    let activeTab = $state<'upcoming' | 'paid' | 'all'>('upcoming');

    const filteredBills = $derived.by(() => {
        if (activeTab === 'all') {
            return billItems;
        }

        return billItems.filter((bill) =>
            activeTab === 'paid' ? bill.is_paid : !bill.is_paid,
        );
    });

    const upcomingCount = $derived(
        billItems.filter((bill) => !bill.is_paid).length,
    );
    const paidCount = $derived(billItems.filter((bill) => bill.is_paid).length);
    const totalUpcomingAmount = $derived(
        billItems
            .filter((bill) => !bill.is_paid)
            .reduce((total, bill) => total + (bill.amount ?? 0), 0),
    );

    function isPastDue(due_date: string): boolean {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const due = new Date(due_date);
        due.setHours(0, 0, 0, 0);

        return due < today;
    }

    function statusLabel(bill: Bill): string {
        if (bill.is_paid) {
            return 'مدفوعة';
        }

        return isPastDue(bill.due_date) ? 'متأخرة' : 'مستحقة';
    }

    function statusClass(bill: Bill): string {
        if (bill.is_paid) {
            return 'bg-success/10 text-success-text';
        }

        return isPastDue(bill.due_date)
            ? 'bg-destructive/10 text-destructive'
            : 'bg-warning/15 text-warning-text';
    }

    function openStatusIcon(bill: Bill) {
        if (bill.is_paid) {
            return CheckCircle2;
        }

        return isPastDue(bill.due_date) ? AlertCircle : Clock3;
    }

    function dueText(bill: Bill): string {
        return bill.is_paid ? 'مدفوعة' : formatRelativeDays(bill.due_date);
    }

    let selectedBill = $state<Bill | null>(null);
    let detailsOpen = $state(false);
    let formOpen = $state(false);
    let deleteOpen = $state(false);
    let editingId = $state<number | null>(null);
    let deleteId = $state<number | null>(null);
    let submitting = $state(false);
    let formErrors = $state<Record<string, string>>({});

    let form = $state({
        name: '',
        icon: 'zap',
        amount: '',
        due_date: '',
        account_number: '',
    });

    function openAddModal(): void {
        submitting = false;
        editingId = null;
        form = {
            name: '',
            icon: 'zap',
            amount: '',
            due_date: new Date().toISOString().slice(0, 10),
            account_number: '',
        };
        formErrors = {};
        formOpen = true;
    }

    function openEditModal(bill: Bill): void {
        editingId = bill.id;
        form = {
            name: bill.name,
            icon: bill.icon ?? 'ellipsis',
            amount:
                bill.amount === null ? '' : toRiyals(bill.amount).toFixed(2),
            due_date: bill.due_date,
            account_number: bill.account_number ?? '',
        };
        formErrors = {};
        formOpen = true;
    }

    function closeForm(): void {
        formOpen = false;
        editingId = null;
        formErrors = {};
    }

    function submitForm(): void {
        formErrors = {};
        const amount = form.amount === '' ? null : Number(form.amount);

        if (!form.name.trim()) {
            formErrors.name = 'اسم الفاتورة مطلوب';
        }

        if (amount !== null && (!Number.isFinite(amount) || amount < 0)) {
            formErrors.amount = 'المبلغ غير صحيح';
        }

        if (!form.due_date) {
            formErrors.due_date = 'تاريخ الاستحقاق مطلوب';
        }

        if (Object.keys(formErrors).length > 0) {
            return;
        }

        submitting = true;
        const payload = {
            name: form.name.trim(),
            icon: form.icon,
            amount,
            due_date: form.due_date,
            account_number: form.account_number.trim() || null,
        };

        if (editingId !== null) {
            // The backend currently exposes create/pay/unpay/delete, but not PUT /bills/{bill}.
            router.put(`/bills/${editingId}`, payload, {
                preserveScroll: true,
                onSuccess: closeForm,
                onError: (errors) => {
                    formErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    submitting = false;
                },
            });
        } else {
            router.post(storeBill(), payload, {
                preserveScroll: true,
                onSuccess: closeForm,
                onError: (errors) => {
                    formErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    submitting = false;
                },
            });
        }
    }

    function openDetails(bill: Bill): void {
        selectedBill = bill;
        detailsOpen = true;
    }

    function closeDetails(): void {
        detailsOpen = false;
        selectedBill = null;
    }

    function togglePaid(bill: Bill): void {
        const action = bill.is_paid ? unpayBill(bill.id) : payBill(bill.id);

        router.put(
            action,
            {},
            { preserveScroll: true, onSuccess: closeDetails },
        );
    }

    function askDelete(id: number): void {
        deleteId = id;
        deleteOpen = true;
    }

    function cancelDelete(): void {
        deleteOpen = false;
        deleteId = null;
    }

    function deleteBill(): void {
        if (deleteId === null) {
            return;
        }

        const id = deleteId;
        router.delete(destroyBill(id), {
            preserveScroll: true,
            onSuccess: () => {
                deleteOpen = false;
                deleteId = null;
                if (selectedBill?.id === id) {
                    closeDetails();
                }
            },
        });
    }

    onMount(() => {
        if (new URLSearchParams(window.location.search).get('new') === '1') {
            openAddModal();
            window.history.replaceState({}, '', window.location.pathname);
        }
    });

    const tabs = $derived([
        { key: 'upcoming' as const, label: 'المستحقة', count: upcomingCount },
        { key: 'paid' as const, label: 'المدفوعة', count: paidCount },
        { key: 'all' as const, label: 'الكل', count: billItems.length },
    ]);
</script>

<AppHead title="الفواتير" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold">الفواتير</h1>
            <p class="text-muted-foreground">
                التزاماتك الدورية ومواعيد استحقاقها
            </p>
        </div>
        <Button class="gap-1.5" onclick={openAddModal}>
            <Plus class="size-4" />
            إضافة فاتورة
        </Button>
    </div>

    {#if error}
        <Card class="border-destructive/40">
            <CardContent class="flex items-center gap-3 py-5 text-destructive">
                <AlertCircle class="size-5 shrink-0" />
                <p>{error}</p>
            </CardContent>
        </Card>
    {:else if isLoading}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each Array(3) as _}
                <Card>
                    <CardContent class="space-y-4 py-6">
                        <div
                            class="h-5 w-2/3 animate-pulse rounded bg-muted"
                        ></div>
                        <div
                            class="h-4 w-1/2 animate-pulse rounded bg-muted"
                        ></div>
                        <div class="h-16 animate-pulse rounded bg-muted"></div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {:else}
        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        الفواتير المستحقة
                    </p>
                    <p class="mt-2 text-xl font-bold tabular-nums">
                        {stats.upcoming_count || upcomingCount}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">إجمالي المستحق</p>
                    <p
                        class="mt-2 text-xl font-bold tabular-nums text-destructive"
                    >
                        {formatCurrency(stats.total_due || totalUpcomingAmount)}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        الفواتير المدفوعة
                    </p>
                    <p class="mt-2 text-xl font-bold tabular-nums">
                        {stats.paid_count || paidCount}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="flex gap-1 rounded-lg bg-muted p-1">
            {#each tabs as tab}
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors {activeTab ===
                    tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => (activeTab = tab.key)}
                >
                    {tab.label}
                    <span class="ms-1 text-xs text-muted-foreground"
                        >({tab.count})</span
                    >
                </button>
            {/each}
        </div>

        {#if filteredBills.length === 0}
            <Card>
                <EmptyState
                    icon={ReceiptText as any}
                    title="لا توجد فواتير"
                    description={activeTab === 'all'
                        ? 'أضف فاتورتك الدورية الأولى للبدء.'
                        : 'لا توجد فواتير في هذا التصنيف.'}
                    actionLabel={activeTab === 'all' ? 'إضافة فاتورة' : ''}
                    onaction={openAddModal}
                />
            </Card>
        {:else}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {#each filteredBills as bill (bill.id)}
                    {@const StatusIcon = openStatusIcon(bill)}
                    <Card class="overflow-hidden">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <CategoryIcon
                                        icon={bill.icon ?? 'ellipsis'}
                                        color="var(--chart-7)"
                                        size="md"
                                    />
                                    <div class="min-w-0">
                                        <CardTitle class="truncate text-base"
                                            >{bill.name}</CardTitle
                                        >
                                        <p
                                            class="text-sm text-muted-foreground tabular-nums"
                                        >
                                            {formatDate(bill.due_date)}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {statusClass(
                                        bill,
                                    )}"
                                >
                                    <StatusIcon class="size-3" />
                                    {statusLabel(bill)}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >المبلغ</span
                                    >
                                    <span class="font-bold tabular-nums"
                                        >{formatCurrency(
                                            bill.amount ?? 0,
                                        )}</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground"
                                        >الموعد</span
                                    >
                                    <span
                                        class="text-sm tabular-nums {bill.is_paid
                                            ? 'text-success-text'
                                            : isPastDue(bill.due_date)
                                              ? 'text-destructive'
                                              : 'text-warning-text'}"
                                    >
                                        {dueText(bill)}
                                    </span>
                                </div>
                                {#if bill.account_number}
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <span
                                            class="flex items-center gap-1 text-sm text-muted-foreground"
                                        >
                                            <Hash class="size-3" />
                                            رقم الحساب
                                        </span>
                                        <span
                                            class="truncate text-sm tabular-nums"
                                            >{bill.account_number}</span
                                        >
                                    </div>
                                {/if}
                            </div>
                            <div class="mt-4 flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="flex-1 text-xs"
                                    onclick={() => openDetails(bill)}
                                >
                                    تفاصيل
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="تعديل"
                                    onclick={() => openEditModal(bill)}
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="حذف"
                                    class="text-destructive hover:text-destructive"
                                    onclick={() => askDelete(bill.id)}
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                {/each}
            </div>
        {/if}
    {/if}
</div>

<Dialog bind:open={detailsOpen}>
    <DialogContent class="max-w-lg">
        {#if selectedBill}
            {@const StatusIcon = openStatusIcon(selectedBill)}
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <CategoryIcon
                        icon={selectedBill.icon ?? 'ellipsis'}
                        color="var(--chart-7)"
                        size="sm"
                    />
                    {selectedBill.name}
                </DialogTitle>
                <DialogDescription
                    >تفاصيل الفاتورة وحالتها الحالية.</DialogDescription
                >
            </DialogHeader>
            <div
                class="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-4 text-sm"
            >
                <div>
                    <p class="text-muted-foreground">المبلغ</p>
                    <p class="mt-1 font-bold tabular-nums">
                        {formatCurrency(selectedBill.amount ?? 0)}
                    </p>
                </div>
                <div>
                    <p class="text-muted-foreground">الحالة</p>
                    <p class="mt-1 inline-flex items-center gap-1 font-medium">
                        <StatusIcon class="size-3" />
                        {statusLabel(selectedBill)}
                    </p>
                </div>
                <div>
                    <p class="text-muted-foreground">تاريخ الاستحقاق</p>
                    <p class="mt-1 font-medium tabular-nums">
                        {formatDate(selectedBill.due_date)}
                    </p>
                </div>
                <div>
                    <p class="text-muted-foreground">الموعد</p>
                    <p class="mt-1 font-medium tabular-nums">
                        {dueText(selectedBill)}
                    </p>
                </div>
                {#if selectedBill.account_number}
                    <div class="col-span-2">
                        <p
                            class="flex items-center gap-1 text-muted-foreground"
                        >
                            <Hash class="size-3" /> رقم الحساب
                        </p>
                        <p class="mt-1 font-medium tabular-nums">
                            {selectedBill.account_number}
                        </p>
                    </div>
                {/if}
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    onclick={() => askDelete(selectedBill!.id)}
                >
                    <Trash2 class="size-4" />
                    حذف
                </Button>
                <Button
                    onclick={() => togglePaid(selectedBill!)}
                    class="gap-1.5"
                >
                    <StatusIcon class="size-4" />
                    {selectedBill.is_paid ? 'إلغاء الدفع' : 'تم الدفع'}
                </Button>
            </DialogFooter>
        {/if}
    </DialogContent>
</Dialog>

<Dialog bind:open={formOpen}>
    <DialogContent class="max-h-[90vh] overflow-y-auto">
        <DialogHeader>
            <DialogTitle
                >{editingId === null
                    ? 'إضافة فاتورة جديدة'
                    : 'تعديل الفاتورة'}</DialogTitle
            >
            <DialogDescription
                >أدخل بيانات الفاتورة كما تظهر في حسابك.</DialogDescription
            >
        </DialogHeader>
        <form
            class="flex flex-col gap-4"
            onsubmit={(event) => {
                event.preventDefault();
                submitForm();
            }}
        >
            <div class="flex flex-col gap-1.5">
                <label for="bill-name" class="text-sm font-medium"
                    >اسم الفاتورة</label
                >
                <input
                    id="bill-name"
                    type="text"
                    bind:value={form.name}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                {#if formErrors.name}<p class="text-xs text-destructive">
                        {formErrors.name}
                    </p>{/if}
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="bill-amount" class="text-sm font-medium"
                    >المبلغ (ر.س)</label
                >
                <input
                    id="bill-amount"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    bind:value={form.amount}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm tabular-nums focus:outline-none focus:ring-2 focus:ring-ring"
                />
                {#if formErrors.amount}<p class="text-xs text-destructive">
                        {formErrors.amount}
                    </p>{/if}
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="bill-due-date" class="text-sm font-medium"
                    >تاريخ الاستحقاق</label
                >
                <input
                    id="bill-due-date"
                    type="date"
                    bind:value={form.due_date}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                {#if formErrors.due_date}<p class="text-xs text-destructive">
                        {formErrors.due_date}
                    </p>{/if}
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="bill-account-number" class="text-sm font-medium"
                    >رقم الحساب (اختياري)</label
                >
                <input
                    id="bill-account-number"
                    type="text"
                    bind:value={form.account_number}
                    class="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                {#if formErrors.account_number}<p
                        class="text-xs text-destructive"
                    >
                        {formErrors.account_number}
                    </p>{/if}
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onclick={closeForm}
                    disabled={submitting}>إلغاء</Button
                >
                <Button type="submit" disabled={submitting}
                    >{submitting ? 'جاري الحفظ...' : 'حفظ'}</Button
                >
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

<Dialog bind:open={deleteOpen}>
    <DialogContent class="max-w-sm">
        <DialogHeader>
            <DialogTitle>تأكيد الحذف</DialogTitle>
            <DialogDescription
                >هل أنت متأكد من حذف هذه الفاتورة؟ لا يمكن التراجع عن هذا
                الإجراء.</DialogDescription
            >
        </DialogHeader>
        <DialogFooter>
            <Button variant="outline" onclick={cancelDelete}>إلغاء</Button>
            <Button variant="destructive" onclick={deleteBill}>حذف</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
