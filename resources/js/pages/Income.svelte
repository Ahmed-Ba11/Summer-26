<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'الدخل',
                href: '/income',
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import Button from '@/components/ui/button/button.svelte';
    import { Plus } from 'lucide-svelte';

    const incomes = [
        { id: 1, desc: 'راتب شهري', source: 'وظيفة', amount: 8000, date: '2026-07-01' },
        { id: 2, desc: 'عمل حر', source: 'مستقل', amount: 500, date: '2026-07-08' },
        { id: 3, desc: 'بيع أغراض', source: 'مبيعات', amount: 200, date: '2026-06-15' },
    ];

    const totalIncome = incomes.reduce((sum, i) => sum + i.amount, 0);

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }
</script>

<AppHead title="الدخل" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">الدخل</h1>
            <p class="text-muted-foreground">جميع مصادر دخلك المسجلة</p>
        </div>
        <Button>
            <Plus class="size-4 ms-0 me-2" />
            إضافة دخل
        </Button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">إجمالي الدخل</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{formatCurrency(totalIncome)}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">عدد المصادر</p>
                <p class="text-2xl font-bold">{incomes.length}</p>
            </CardContent>
        </Card>
        <Card>
            <CardContent class="pt-6">
                <p class="text-sm text-muted-foreground">آخر دخل</p>
                <p class="text-2xl font-bold">{formatCurrency(incomes[0].amount)}</p>
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-6 py-3 text-right font-medium">الوصف</th>
                            <th class="px-6 py-3 text-right font-medium">المصدر</th>
                            <th class="px-6 py-3 text-right font-medium">التاريخ</th>
                            <th class="px-6 py-3 text-right font-medium">المبلغ</th>
                            <th class="px-6 py-3 text-right font-medium">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each incomes as inc}
                            <tr class="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                <td class="px-6 py-3">{inc.desc}</td>
                                <td class="px-6 py-3 text-muted-foreground">{inc.source}</td>
                                <td class="px-6 py-3 text-muted-foreground">{inc.date}</td>
                                <td class="px-6 py-3 font-medium text-green-600 dark:text-green-400">{formatCurrency(inc.amount)}</td>
                                <td class="px-6 py-3">
                                    <div class="flex gap-2">
                                        <button class="text-xs text-muted-foreground hover:text-foreground">تعديل</button>
                                        <button class="text-xs text-destructive hover:text-destructive/80">حذف</button>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</div>
