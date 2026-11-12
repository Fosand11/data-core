import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    disabled?: boolean;
    badge?: string;
}

export type TenantRole = 'owner' | 'admin' | 'member';

export interface TenantOption {
    name: string;
    slug: string;
    role: TenantRole;
}

export interface CurrentTenant {
    name: string;
    slug: string;
}

export interface DashboardSummary {
    id: string;
    title: string;
    status: DashboardStatus;
    created_at: string;
    description: string | null;
    is_pinned: boolean;
    visualization_types: DashboardVisualizationType[];
    author: { name: string } | null;
}

export type DashboardStatus = 'queued' | 'generating' | 'ready' | 'failed' | 'archived';
export type DashboardGenerationStatus = 'queued' | 'processing' | 'completed' | 'failed';
export type DashboardVisualizationType = 'kpi' | 'bar' | 'line' | 'area' | 'pie' | 'table';

export interface DashboardMetric {
    field: string;
    aggregation: 'sum' | 'avg' | 'count' | 'min' | 'max';
    alias: string;
    format?: 'currency' | 'number' | 'percent' | 'integer';
}

export interface DashboardDimension {
    field: string;
}

export interface DashboardFilter {
    field: string;
    operator: string;
    value: unknown;
}

export interface DashboardOrder {
    field: string;
    direction: 'asc' | 'desc';
}

export interface DashboardVisualization {
    key: string;
    title: string;
    type: DashboardVisualizationType;
    query: {
        metrics: DashboardMetric[];
        dimensions: DashboardDimension[];
        filters: DashboardFilter[];
        order_by: DashboardOrder[];
        limit: number;
    };
    display: Record<string, boolean | string | string[]>;
    layout: {
        x: number;
        y: number;
        width: number;
        height: number;
    };
}

export interface DashboardSpec {
    version: '1.0' | '1.1';
    title: string;
    description: string;
    dataset: string;
    global_filters: DashboardFilter[];
    visualizations: DashboardVisualization[];
    layout: { columns: 12 };
}

export interface VisualizationColumn {
    key: string;
    role: 'dimension' | 'metric';
    type: 'string' | 'numeric' | 'month' | 'number';
    format: DashboardMetric['format'] | null;
}

export interface VisualizationResult {
    columns: VisualizationColumn[];
    rows: Array<Record<string, string | number | null>>;
    /** The month still in progress ("YYYY-MM"); its bucket is incomplete. */
    meta?: { current_month: string };
}

export interface DashboardPeriodInfo {
    value: string;
    label: string;
    overrides_spec: boolean;
    date_field: string;
    /** Inclusive dates ("YYYY-MM-DD") of the active period, or null when it is not a fixed range. */
    range: { from: string; to: string } | null;
    options: Array<{ value: string; label: string }>;
}

/** Query results keyed by visualization key. */
export type VisualizationData = Record<string, VisualizationResult>;

export interface DashboardDetail extends DashboardSummary {
    description: string | null;
    original_prompt: string | null;
    provider: string | null;
    spec_version: string | null;
    dashboard_spec: DashboardSpec | null;
    can: { update: boolean; duplicate: boolean; delete: boolean; retry: boolean; refine: boolean };
    error_message: string | null;
    generation_started_at: string | null;
    generation_completed_at: string | null;
    generation: {
        id: string;
        status: DashboardGenerationStatus;
        provider: string;
        model: string | null;
        started_at: string | null;
        completed_at: string | null;
        error_message: string | null;
    } | null;
}

export interface RecentDashboard {
    id: string;
    title: string;
    status: DashboardStatus;
}

/** A proposed change to a ready dashboard; nothing is applied until the user accepts it. */
export interface RefinementState {
    id: string;
    status: DashboardGenerationStatus;
    instruction: string;
    error_message: string | null;
    proposed_spec: DashboardSpec | null;
    /** The dashboard changed since this proposal was made, so it can no longer be applied. */
    is_stale: boolean;
}

export interface DashboardVersionEntry {
    number: number;
    source: 'generated' | 'refined' | 'restored';
    instruction: string | null;
    author: string | null;
    created_at: string;
    is_current: boolean;
}

/** What an exported file says about where it comes from. */
export interface ExportContext {
    title: string;
    description: string | null;
    /** The period the numbers cover, e.g. "Trimestre anterior" or "Fecha: trimestre anterior". */
    periodLabel: string;
}

export interface SharedData extends Record<string, unknown> {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    recentDashboards: RecentDashboard[];
    pinnedDashboards: RecentDashboard[];
    flash: { status: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
