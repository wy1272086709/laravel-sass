import { createApp } from 'vue';
import ECharts from 'vue-echarts';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    TooltipComponent,
} from 'echarts/components';

use([
    CanvasRenderer,
    BarChart,
    LineChart,
    PieChart,
    GridComponent,
    LegendComponent,
    TooltipComponent,
]);

export function mountPanel(selector, component) {
    const element = document.querySelector(selector);

    if (!element) {
        return;
    }

    createApp(component)
        .component('VChart', ECharts)
        .mount(element);
}
