export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** المبلغ بالهللات — يحلّ محلّ `:amount` في النص عند العرض. */
    amount?: number;
    /** إجراء التراجع — يظهر كزر داخل الإشعار لمدة خمس ثوانٍ. */
    undo?: {
        url: string;
        method?: 'delete' | 'post' | 'put';
    };
};
