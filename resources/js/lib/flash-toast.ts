import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import { formatCurrency } from '@/lib/format';
import type { FlashToast } from '@/types/ui';

/**
 * إشعارات الخادم.
 *
 * كان الاستماع على حدث اسمه `flash`، وهو ليس من أحداث Inertia إطلاقاً —
 * فلم يصل المستخدمَ أيُّ `->with('toast', …)` كُتب في المسارات. الحدث
 * الصحيح `success`، وحمولته الصفحة الجديدة بكامل خصائصها.
 *
 * إشعار الإضافة يحمل زر «تراجع» لخمس ثوانٍ — المهلة كافية للانتباه
 * للخطأ، وقصيرة بما لا يبقى الإشعار معلّقاً فوق الشاشة.
 */
export function initializeFlashToast(): void {
    router.on('success', (event) => {
        const props = ((event as CustomEvent).detail?.page?.props ?? {}) as Record<
            string,
            unknown
        >;
        const flash = props.flash as { toast?: FlashToast } | undefined;
        const data = flash?.toast;

        if (!data?.message) {
            return;
        }

        const undo = data.undo;

        // المبلغ يصل بالهللات ويُحوَّل هنا وحده — نفس قاعدة بقية الواجهة.
        const message =
            typeof data.amount === 'number'
                ? data.message.replace(':amount', formatCurrency(data.amount))
                : data.message;

        toast[data.type](message, {
            duration: undo ? 5000 : undefined,
            action: undo
                ? {
                      label: 'تراجع',
                      onClick: () =>
                          router.visit(undo.url, {
                              method: undo.method ?? 'delete',
                              preserveScroll: true,
                          }),
                  }
                : undefined,
        });
    });
}
