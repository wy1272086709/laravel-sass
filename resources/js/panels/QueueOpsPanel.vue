<template>
    <section class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <v-chart class="h-80" :option="statusOption" autoresize />
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 xl:col-span-2">
            <div class="divide-y divide-gray-100">
                <div v-for="job in data?.recent_jobs ?? []" :key="job.id" class="grid grid-cols-4 gap-3 py-3 text-sm">
                    <span class="font-medium text-gray-950">{{ job.name }}</span>
                    <span>{{ job.queue }}</span>
                    <span>{{ job.status }}</span>
                    <span class="text-right text-gray-500">{{ job.finished_at ?? '-' }}</span>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const data = ref(null);

onMounted(async () => {
    const response = await fetch('/api/internal/platform/queue-ops', { headers: { Accept: 'application/json' } });
    data.value = (await response.json()).data;
});

const statusOption = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [{
        type: 'pie',
        radius: ['45%', '70%'],
        data: Object.entries(data.value?.status_counts ?? {}).map(([name, value]) => ({ name, value })),
    }],
}));
</script>
