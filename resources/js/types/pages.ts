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

/** رسالة كما تُرسَل للسيرفر ضمن `history` — نصّ فقط، بلا بطاقات أدوات. */
export interface AssistantMessage {
    role: 'user' | 'assistant';
    content: string;
}

/** استدعاء أداة واحد كما يُعرض في البطاقة. */
export interface ToolInvocation {
    kind: 'tool';
    id: string;
    name: string;
    arguments: Record<string, unknown>;
    status: 'running' | 'done' | 'failed';
    summary: string;
    data: Record<string, unknown> | null;
}

/**
 * كتلة نصّ داخل دور المساعد.
 *
 * `raw` هو النصّ المتراكم و`html` ناتجه المعقَّم. يُحفظان معاً لأن
 * Markdown لا يُحلَّل تدريجياً: كل إعادة رندرة تعمل على `raw` كاملاً.
 */
export interface AssistantText {
    kind: 'text';
    raw: string;
    html: string;
}

export type AssistantPart = AssistantText | ToolInvocation;

/** دور واحد في المحادثة كما تعرضه الصفحة. */
export type ChatTurn =
    | { role: 'user'; content: string }
    | { role: 'assistant'; parts: AssistantPart[]; failed?: boolean };

/** إطار SSE واحد من `POST /assistant/stream`. */
export type StreamFrame =
    | { type: 'text'; delta: string }
    | {
          type: 'tool_call';
          id: string;
          name: string;
          arguments: Record<string, unknown>;
      }
    | {
          type: 'tool_result';
          id: string;
          name: string;
          ok: boolean;
          summary: string;
          data: Record<string, unknown> | null;
      }
    | { type: 'error'; message: string }
    | { type: 'done' };

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
