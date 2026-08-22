import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(
            pattern: string,
            options?: { eager?: boolean },
        ) => Record<string, T>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            railExpanded: boolean;
            navStats: {
                remaining: number;
                dailySafe: number;
                daysLeft: number;
                budgetUsedPct: number;
                transactionsCount: number;
                dueCommitments: number;
                savingsPct: number;
                incomeSplit: { key: string; pct: number; color: string }[];
            } | null;
            [key: string]: unknown;
        };
    }
}
