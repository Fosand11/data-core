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
    author: { name: string } | null;
}

export type DashboardStatus = 'queued' | 'generating' | 'ready' | 'failed' | 'archived';
export type DashboardGenerationStatus = 'queued' | 'processing' | 'completed' | 'failed';
export type DashboardVisualizationType = 'kpi' | 'bar' | 'line' | 'table';

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
    version: '1.0';
    title: string;
    description: string;
    dataset: string;
    global_filters: DashboardFilter[];
    visualizations: DashboardVisualization[];
    layout: { columns: 12 };
}

export interface DashboardDetail extends DashboardSummary {
    description: string | null;
    original_prompt: string | null;
    provider: string | null;
    spec_version: string | null;
    dashboard_spec: DashboardSpec | null;
    error_message: string | null;
    generation_started_at: string | null;
    generation_completed_at: string | null;
    generation: {
        id: string;
        status: DashboardGenerationStatus;
        provider: string;
        started_at: string | null;
        completed_at: string | null;
        error_message: string | null;
    } | null;
}

export interface SharedData extends Record<string, unknown> {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
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
