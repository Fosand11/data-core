import type { DashboardVisualizationType } from '@/types';
import {
    AreaChart,
    BarChart3,
    BarChartHorizontal,
    Blocks,
    Filter,
    Gauge,
    Hash,
    LineChart,
    PieChart,
    Radar,
    ScatterChart,
    Table2,
    type LucideIcon,
} from 'lucide-vue-next';

export const visualizationIcons: Record<DashboardVisualizationType, LucideIcon> = {
    kpi: Hash,
    bar: BarChart3,
    hbar: BarChartHorizontal,
    line: LineChart,
    area: AreaChart,
    pie: PieChart,
    funnel: Filter,
    treemap: Blocks,
    radar: Radar,
    scatter: ScatterChart,
    gauge: Gauge,
    table: Table2,
};

export const visualizationNames: Record<DashboardVisualizationType, string> = {
    kpi: 'Indicador',
    bar: 'Barras',
    hbar: 'Barras horizontales',
    line: 'Línea',
    area: 'Área',
    pie: 'Circular',
    funnel: 'Embudo',
    treemap: 'Mapa de árbol',
    radar: 'Radar',
    scatter: 'Dispersión',
    gauge: 'Medidor',
    table: 'Tabla',
};
