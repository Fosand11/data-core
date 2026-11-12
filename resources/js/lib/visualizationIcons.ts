import type { DashboardVisualizationType } from '@/types';
import { AreaChart, BarChart3, Hash, LineChart, PieChart, Table2, type LucideIcon } from 'lucide-vue-next';

export const visualizationIcons: Record<DashboardVisualizationType, LucideIcon> = {
    kpi: Hash,
    bar: BarChart3,
    line: LineChart,
    area: AreaChart,
    pie: PieChart,
    table: Table2,
};

export const visualizationNames: Record<DashboardVisualizationType, string> = {
    kpi: 'Indicador',
    bar: 'Barras',
    line: 'Línea',
    area: 'Área',
    pie: 'Circular',
    table: 'Tabla',
};
