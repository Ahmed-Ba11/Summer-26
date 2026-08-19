export type ValidationErrors = Record<string, string | string[] | undefined>;

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page?: number;
    from?: number | null;
    to?: number | null;
}

export interface ListFilters {
    search?: string;
    category?: string;
    source?: string;
    recurring?: boolean | number | string;
    sort?: 'date' | 'amount' | string;
    direction?: 'asc' | 'desc' | string;
}

export interface OnboardingCategory {
    id: number;
    name: string;
    icon: string;
    color: string;
}

export interface AssistantMessage {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
}

export interface ReportSummary {
    total_income?: number;
    total_expenses?: number;
    net_savings?: number;
    net?: number;
    savings_rate?: number;
}

export interface ReportCategory {
    id?: number;
    name: string;
    icon?: string | null;
    color?: string | null;
    amount: number;
    budget: number;
    difference?: number;
    percentage: number;
}

export interface ReportMonthlyPoint {
    month: string;
    income: number;
    expenses: number;
}

export interface ReportTopExpense {
    id: number;
    description: string | null;
    category?: string | null;
    icon?: string | null;
    amount: number;
    date: string;
}

export interface SavingsStats {
    total_saved: number;
    monthly_income: number;
    monthly_deposits: number;
    savings_rate?: number;
}
