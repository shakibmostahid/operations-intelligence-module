<script setup>
import { computed, ref } from 'vue';
import AppHeader from '../components/AppHeader.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    analytics: {
        type: Object,
        required: true,
    },
    timeframe: {
        type: String,
        default: '30',
    },
    dateFrom: {
        type: String,
        default: null,
    },
    dateTo: {
        type: String,
        default: null,
    },
    success: {
        type: String,
        default: null,
    },
});

const dimension = ref('severity');
const selectedTimeframe = ref(props.timeframe);
const palette = ['#DC2626', '#D97706', '#2563EB', '#0891B2', '#7C3AED', '#475569'];
const distribution = computed(() => props.analytics[dimension.value] || []);
const distributionTotal = computed(() => distribution.value.reduce((total, item) => total + item.count, 0));
const pieStyle = computed(() => {
    if (distributionTotal.value === 0) {
        return { background: '#e3e7e9' };
    }

    let start = 0;
    const segments = distribution.value.map((item, index) => {
        const end = start + (item.count / distributionTotal.value) * 100;
        const segment = `${palette[index % palette.length]} ${start}% ${end}%`;
        start = end;

        return segment;
    });

    return { background: `conic-gradient(${segments.join(', ')})` };
});
const maxTagCount = computed(() => Math.max(...props.analytics.tags.map((tag) => tag.count), 1));

const label = (value) => value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="dashboard" />

        <main class="mx-auto max-w-[1440px] px-5 py-8 sm:px-8">
            <div v-if="success" class="mb-6 border border-[#9fc55a] bg-[#f7ffe8] px-4 py-3 text-sm text-[#526d23]">
                {{ success }}
            </div>
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-[#dce1e4] pb-6">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">Operations overview</p>
                    <h1 class="text-2xl font-semibold">Dashboard</h1>
                    <p class="mt-2 text-sm text-[#667079]">Incident distribution and operational workload signals.</p>
                </div>
                <form action="/dashboard" method="GET" class="flex flex-wrap items-end justify-end gap-3">
                    <label class="text-sm">
                        <span class="mb-1.5 block text-xs text-[#667079]">Timeframe</span>
                        <select
                            v-model="selectedTimeframe"
                            name="timeframe"
                            class="h-10 border border-[#cbd1d5] bg-white px-3 outline-none focus:border-[#297069]"
                        >
                            <option value="today">Today</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="all">All time</option>
                            <option value="custom">Custom range</option>
                        </select>
                    </label>

                    <label v-if="selectedTimeframe === 'custom'" class="text-sm">
                        <span class="mb-1.5 block text-xs text-[#667079]">From</span>
                        <input
                            name="from"
                            type="date"
                            :value="dateFrom"
                            :max="dateTo || undefined"
                            required
                            class="h-10 border border-[#cbd1d5] bg-white px-3 outline-none focus:border-[#297069]"
                        >
                    </label>

                    <label v-if="selectedTimeframe === 'custom'" class="text-sm">
                        <span class="mb-1.5 block text-xs text-[#667079]">To</span>
                        <input
                            name="to"
                            type="date"
                            :value="dateTo"
                            :min="dateFrom || undefined"
                            required
                            class="h-10 border border-[#cbd1d5] bg-white px-3 outline-none focus:border-[#297069]"
                        >
                    </label>

                    <button
                        type="submit"
                        class="h-10 bg-[#172027] px-4 text-sm font-semibold text-white hover:bg-[#297069]"
                    >
                        Apply
                    </button>
                </form>
            </div>

            <section class="mb-6 grid gap-px border border-[#dce1e4] bg-[#dce1e4] sm:grid-cols-3">
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-[#667079]">Total incidents</p>
                    <p class="mt-2 text-2xl font-semibold">{{ analytics.total }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-[#667079]">Critical</p>
                    <p class="mt-2 text-2xl font-semibold text-[#b53c36]">
                        {{ analytics.severity.find((item) => item.label === 'critical')?.count || 0 }}
                    </p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-[#667079]">Escalated</p>
                    <p class="mt-2 text-2xl font-semibold text-[#8a5b12]">
                        {{ analytics.status.find((item) => item.label === 'escalated')?.count || 0 }}
                    </p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="min-h-[440px] bg-white p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#e3e7e9] pb-4">
                        <div>
                            <p class="text-sm font-semibold">Incident distribution</p>
                            <p class="mt-1 text-xs text-[#667079]">Share of incidents in the selected timeframe.</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <span class="sr-only">Group incidents by</span>
                            <select
                                v-model="dimension"
                                class="h-9 border border-[#cbd1d5] bg-white px-3 outline-none focus:border-[#297069]"
                            >
                                <option value="severity">Severity</option>
                                <option value="status">Status</option>
                            </select>
                        </label>
                    </div>

                    <div v-if="distributionTotal > 0" class="grid min-h-[340px] items-center gap-8 py-6 sm:grid-cols-[220px_minmax(0,1fr)]">
                        <div class="relative mx-auto size-[220px] shrink-0">
                            <div class="size-full rounded-full" :style="pieStyle"></div>
                            <div class="absolute inset-[54px] grid place-items-center rounded-full bg-white text-center">
                                <span>
                                    <strong class="block text-2xl">{{ distributionTotal }}</strong>
                                    <span class="text-xs text-[#667079]">incidents</span>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(item, index) in distribution"
                                :key="item.label"
                                class="grid grid-cols-[12px_minmax(0,1fr)_auto] items-center gap-3"
                            >
                                <span class="size-3" :style="{ backgroundColor: palette[index % palette.length] }"></span>
                                <span class="text-sm">{{ label(item.label) }}</span>
                                <span class="text-sm font-semibold">{{ item.count }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid min-h-[340px] place-items-center text-sm text-[#667079]">
                        No incidents in this timeframe.
                    </div>
                </section>

                <section class="min-h-[440px] bg-white p-5 sm:p-6">
                    <div class="border-b border-[#e3e7e9] pb-4">
                        <p class="text-sm font-semibold">Incidents by tag</p>
                        <p class="mt-1 text-xs text-[#667079]">Tag frequency across incidents in the selected timeframe.</p>
                    </div>

                    <div v-if="analytics.tags.length > 0" class="space-y-5 py-6">
                        <div v-for="tag in analytics.tags" :key="tag.name">
                            <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="size-3 shrink-0" :style="{ backgroundColor: tag.color }"></span>
                                    <span class="truncate">{{ tag.name }}</span>
                                </span>
                                <strong>{{ tag.count }}</strong>
                            </div>
                            <div class="h-3 bg-[#edf0f1]">
                                <div
                                    class="h-full min-w-[4px]"
                                    :style="{
                                        width: `${(tag.count / maxTagCount) * 100}%`,
                                        backgroundColor: tag.color,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid min-h-[340px] place-items-center text-sm text-[#667079]">
                        No tagged incidents in this timeframe.
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
