<template>
    <section class="space-y-4">
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 xl:col-span-2">
                <v-chart class="h-80" :option="trendOption" autoresize />
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <v-chart class="h-80" :option="levelOption" autoresize />
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="divide-y divide-gray-100">
                <div v-for="item in data?.discrepancies ?? []" :key="item.id" class="grid grid-cols-4 gap-3 py-3 text-sm">
                    <span class="font-medium text-gray-950">{{ item.tenant_name }}</span>
                    <span>{{ item.billing_period }}</span>
                    <span>{{ item.status }}</span>
                    <span class="text-right">¥{{ Number(item.difference_amount).toFixed(2) }}</span>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const data = ref(null);

onMounted(async () => {
    const response = await fetch('/api/internal/platform/risk-recon', { headers: { Accept: 'application/json' } });
    data.value = (await response.json()).data;
});

const trendOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 32, right: 20, top: 32, bottom: 28 },
    xAxis: { type: 'category', data: data.value?.alert_trend.dates ?? [] },
    yAxis: { type: 'value' },
    series: [{ name: '告警', type: 'line', smooth: true, data: data.value?.alert_trend.counts ?? [] }],
}));

const levelOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
        type: 'pie',
        radius: ['45%', '70%'],
        data: Object.entries(data.value?.level_distribution ?? {}).map(([name, value]) => ({ name, value })),
    }],
}));
</script>
