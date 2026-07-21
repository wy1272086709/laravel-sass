<template>
    <section class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div v-for="item in cards" :key="item.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">{{ item.label }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950">{{ item.value }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <v-chart class="ops-chart" :option="trendOption" autoresize />
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const data = ref(null);

onMounted(async () => {
    const response = await fetch('/api/internal/platform/api-monitor', { headers: { Accept: 'application/json' } });
    data.value = (await response.json()).data;
});

const cards = computed(() => [
    { label: '今日调用', value: data.value?.summary.calls_today ?? 0 },
    { label: '今日错误', value: data.value?.summary.errors_today ?? 0 },
    { label: '平均耗时', value: `${data.value?.summary.avg_duration_ms ?? 0} ms` },
]);

const trendOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: { data: ['调用', '错误'] },
    grid: { left: 32, right: 20, top: 48, bottom: 28 },
    xAxis: { type: 'category', data: data.value?.hourly_trend.hours ?? [] },
    yAxis: { type: 'value' },
    series: [
        { name: '调用', type: 'line', smooth: true, data: data.value?.hourly_trend.counts ?? [] },
        { name: '错误', type: 'bar', data: data.value?.hourly_trend.errors ?? [] },
    ],
}));
</script>

<style scoped>
.ops-chart {
    width: 100%;
    height: 20rem;
    min-height: 20rem;
}
</style>
