import type { ChartTheme } from '@/composables/useChartTheme';
import { buildChartOption } from '@/lib/chartOptions';
import type { DashboardVisualization, VisualizationResult } from '@/types';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TitleComponent, TooltipComponent } from 'echarts/components';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

// Only the modules the supported chart types need; keeps the bundle small. Imported by the live chart and by exports.
echarts.use([BarChart, LineChart, PieChart, GridComponent, LegendComponent, TitleComponent, TooltipComponent, CanvasRenderer]);

export interface ChartImageOptions {
    width: number;
    height: number;
    theme: ChartTheme;
    /** Device pixel ratio; 2 gives a sharp image for slides and printing. */
    pixelRatio?: number;
}

/**
 * Draws a chart off-screen and returns it as a PNG data URL. Independent of the
 * visible page, so an export never depends on dark mode or on layout size.
 */
export function renderChartImage(visualization: DashboardVisualization, result: VisualizationResult, options: ChartImageOptions): string {
    const container = document.createElement('div');
    container.style.cssText = `position:fixed;left:-10000px;top:0;width:${options.width}px;height:${options.height}px;`;
    document.body.appendChild(container);

    const chart = echarts.init(container, null, { renderer: 'canvas', width: options.width, height: options.height });

    try {
        chart.setOption({ ...buildChartOption(visualization, result, options.theme), animation: false });

        return chart.getDataURL({ type: 'png', pixelRatio: options.pixelRatio ?? 2, backgroundColor: options.theme.surface });
    } finally {
        chart.dispose();
        container.remove();
    }
}
