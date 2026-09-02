import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type FontScale = 'sm' | 'md' | 'lg';
export type UiLocale = 'ar' | 'en';

export type ThemeState = {
    appearance: {
        value: Appearance;
    };
    resolvedAppearance: () => ResolvedAppearance;
    updateAppearance: (value: Appearance) => void;
};

const appearance = $state<{ value: Appearance }>({ value: 'light' });

let themeChangeMediaQuery: MediaQueryList | null = null;

/** 15 · 16 · 17 بكسل — الفرق محسوس بلا كسر أي تخطيط. */
const FONT_SIZES: Record<FontScale, string> = {
    sm: '15px',
    md: '16px',
    lg: '17px',
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const isDarkMode = (value: Appearance): boolean => {
    return value === 'dark' || (value === 'system' && prefersDark());
};

const getResolvedAppearance = (): ResolvedAppearance => {
    return isDarkMode(appearance.value) ? 'dark' : 'light';
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyTheme = (value: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(value);
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const stored = localStorage.getItem('appearance');

    return stored === 'light' || stored === 'dark' || stored === 'system'
        ? stored
        : 'light';
};

const handleSystemThemeChange = (): void => {
    // `system` يتبع تفضيل الجهاز فعلاً — التبديل في إعدادات النظام يظهر فوراً
    // بلا إعادة تحميل، وإلا صار الخيار اسماً بلا معنى.
    if (appearance.value === 'system') {
        applyTheme('system');
    }
};

const detachThemeChangeListener = (): void => {
    if (!themeChangeMediaQuery) {
        return;
    }

    themeChangeMediaQuery.removeEventListener(
        'change',
        handleSystemThemeChange,
    );
    themeChangeMediaQuery = null;
};

/** تفضيل الحساب — إن وُجد — يسبق ما هو محفوظ على الجهاز. */
const serverAppearance = (): Appearance | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const raw = document.querySelector('script[data-page]')?.textContent;

    if (!raw) {
        return null;
    }

    try {
        const theme = JSON.parse(raw)?.props?.auth?.user?.theme;

        return theme === 'light' || theme === 'dark' || theme === 'system'
            ? theme
            : null;
    } catch {
        return null;
    }
};

export function initializeTheme(): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    appearance.value = serverAppearance() ?? getStoredAppearance();

    localStorage.setItem('appearance', appearance.value);
    setCookie('appearance', appearance.value);
    applyTheme(appearance.value);

    detachThemeChangeListener();

    themeChangeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    themeChangeMediaQuery.addEventListener('change', handleSystemThemeChange);

    return detachThemeChangeListener;
}

export function updateAppearance(value: Appearance): void {
    appearance.value = value;

    if (typeof window !== 'undefined') {
        localStorage.setItem('appearance', value);
    }

    setCookie('appearance', value);
    applyTheme(value);
}

/** الدورة: فاتح ← داكن ← تلقائي — لزر الاختصار في رأس كل صفحة. */
export function cycleAppearance(): Appearance {
    const order: Appearance[] = ['light', 'dark', 'system'];
    const next = order[(order.indexOf(appearance.value) + 1) % order.length];

    updateAppearance(next);

    return next;
}

/** حجم الخط يضبط `html { font-size }` فتتبعه كل وحدات rem في الواجهة. */
export function applyFontScale(scale: FontScale): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.style.fontSize =
        FONT_SIZES[scale] ?? FONT_SIZES.md;

    setCookie('font_scale', scale);
}

/** تغيير اللغة يقلب `dir` كاملاً — التنسيقات المنطقية تجعل ذلك يعمل بلا CSS إضافي. */
export function applyLocale(locale: UiLocale): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'en' ? 'ltr' : 'rtl';

    setCookie('ui_locale', locale);
}

export function themeState(): ThemeState {
    return {
        appearance,
        resolvedAppearance: getResolvedAppearance,
        updateAppearance,
    };
}