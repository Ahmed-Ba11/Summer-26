<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'المصاريف',
                href: '/expenses',
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import Button from '@/components/ui/button/button.svelte';
    import { Plus } from 'lucide-svelte';

    const expenses = [
        { id: 1, desc: 'مطعم', cat: 'طعام', amount: 150, date: '2026-07-14' },
        { id: 2, desc: 'تاكسي', cat: 'مواصلات', amount: 45, date: '2026-07-13' },
        { id: 3, desc: 'فاتورة كهرباء', cat: 'فواتير', amount: 320, date: '2026-07-12' },
        { id: 4, desc: 'سينما', cat: 'ترفيه', amount: 120, date: '2026-07-10' },
        { id: 5, desc: 'دواء', cat: 'صحة', amount: 85, date: '2026-07-09' },
        { id: 6, desc: 'اشتراك نت', cat: 'فواتير', amount: 199, date: '2026-07-05' },
        { id: 7, desc: 'بقالة', cat: 'طعام', amount: 340, date: '2026-07-04' },
        { id: 8, desc: 'بنزين', cat: 'مواصلات', amount: 200, date: '2026-07-03' },
    ];

    function formatCurrency(amount: number): string {
        return amount.toLocaleString('ar-SA') + ' ر.س';
    }
</script>

<AppHead title="المصاريف" />

<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">المصاريف</h1>
            <p class="text-muted-foreground">جميع مصاريفك المسجلة</p>
        </div>
        <Button>
            <Plus class="size-4 ms-0 me-2" />
            إضافة مصروف
        </Button>
    </div>

    <Card>
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="px-6 py-3 text-right font-medium">الوصف</th>
                            <th class="px-6 py-3 text-right font-medium">الفئة</th>
                            <th class="px-6 py-3 text-right font-medium">التاريخ</th>
                            <th class="px-6 py-3 text-right font-medium">المبلغ</th>
                            <th class="px-6 py-3 text-right font-medium">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each expenses as e}
                            <tr class="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                <td class="px-6 py-3">{e.desc}</td>
                                <td class="px-6 py-3 text-muted-foreground">{e.cat}</td>
                                <td class="px-6 py-3 text-muted-foreground">{e.date}</td>
                                <td class="px-6 py-3 font-medium text-destructive">{formatCurrency(e.amount)}</td>
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
