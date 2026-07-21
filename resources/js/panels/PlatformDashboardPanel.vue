<template>
    <section class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div v-for="item in cards" :key="item.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">{{ item.label }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950">{{ item.value }}</div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 xl:col-span-2">
                <v-chart class="ops-chart" :option="gmvOption" autoresize />
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <v-chart class="ops-chart" :option="packageOption" autoresize />
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const data = ref(null);

const fetchData = async () => {
    const response = await fetch('/api/internal/platform/dashboard', { headers: { Accept: 'application/json' } });
    data.value = (await response.json()).data;
};

onMounted(fetchData);

const cards = computed(() => [
    { label: '商户数', value: data.value?.summary.tenant_count ?? 0 },
    { label: '订单数', value: data.value?.summary.order_count ?? 0 },
    { label: 'GMV', value: `¥${Number(data.value?.summary.gmv ?? 0).toFixed(2)}` },
    { label: '待结算账单', value: data.value?.summary.pending_bills ?? 0 },
]);

const gmvOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: { data: ['GMV', '订单数'] },
    grid: { left: 32, right: 20, top: 48, bottom: 28 },
    xAxis: { type: 'category', data: data.value?.gmv_trend.dates ?? [] },
    yAxis: [{ type: 'value' }, { type: 'value' }],
    series: [
        { name: 'GMV', type: 'line', smooth: true, data: data.value?.gmv_trend.amounts ?? [] },
        { name: '订单数', type: 'bar', yAxisIndex: 1, data: data.value?.gmv_trend.order_counts ?? [] },
    ],
}));

const packageOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
        type: 'pie',
        radius: ['45%', '70%'],
        data: (data.value?.package_distribution ?? []).map((item) => ({
            name: item.name,
            value: item.tenant_count,
        })),
    }],
}));
</script>

<style scoped>
.ops-chart {
    width: 100%;
    height: 20rem;
    min-height: 20rem;
}
</style>
