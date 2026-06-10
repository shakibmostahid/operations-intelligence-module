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
    alerts: {
        type: Object,
        required: true,
    },
    systemHealth: {
        type: Object,
        required: true,
    },
    selfAssignedIncidents: {
        type: Object,
        required: true,
    },
    slaBreaches: {
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
const hoveredSegment = ref(null);
const selectedTimeframe = ref(props.timeframe);
const trendTimeframe = ref(['today', '7', '30', '90', 'all'].includes(props.timeframe) ? props.timeframe : '30');
const trendResults = ref(props.analytics.trend);
const focusedTrendIndex = ref(null);
const trendLoading = ref(false);
const trendError = ref('');
const slaResults = ref(props.slaBreaches);
const slaLoading = ref(false);
const slaError = ref('');
const palette = ['#DC2626', '#D97706', '#2563EB', '#0891B2', '#7C3AED', '#475569'];
const breachSeverityClass = {
    critical: 'bg-[#feeceb] text-[#9e2f2a]',
    high: 'bg-[#fff3dd] text-[#8a5b12]',
    medium: 'bg-[#e8f3fb] text-[#246183]',
    low: 'bg-[#eef2f3] text-[#566169]',
};
const durationClass = {
    resolved: 'bg-[#eef8e1] text-[#526d23]',
    running: 'bg-[#e8f3fb] text-[#246183]',
    breached: 'bg-[#feeceb] text-[#9e2f2a]',
};
const distribution = computed(() => props.analytics[dimension.value] || []);
const distributionTotal = computed(() => distribution.value.reduce((total, item) => total + item.count, 0));
const distributionPercentage = (count) => `${((count / distributionTotal.value) * 100).toFixed(1)}%`;
const pieSegments = computed(() => {
    let start = 0;
    return distribution.value.map((item, index) => {
        const percentage = (item.count / distributionTotal.value) * 100;
        const segment = {
            ...item,
            color: palette[index % palette.length],
            percentage,
            offset: -start,
        };
        start += percentage;

        return segment;
    });
});
const activePieSegment = computed(() => (
    hoveredSegment.value === null ? null : pieSegments.value[hoveredSegment.value]
));
const maxTagCount = computed(() => Math.max(...props.analytics.tags.map((tag) => tag.count), 1));
const trendMax = computed(() => Math.max(
    ...trendResults.value.flatMap((item) => [item.created, item.resolved]),
    1,
));
const trendTotals = computed(() => trendResults.value.reduce(
    (totals, item) => ({
        created: totals.created + item.created,
        resolved: totals.resolved + item.resolved,
    }),
    { created: 0, resolved: 0 },
));
const activeTrendPoint = computed(() => (
    focusedTrendIndex.value === null ? null : trendResults.value[focusedTrendIndex.value]
));
const trendX = (index) => {
    const denominator = Math.max(trendResults.value.length - 1, 1);
    return trendResults.value.length === 1 ? 50 : (index / denominator) * 100;
};
const trendY = (item, key) => 36 - (item[key] / trendMax.value) * 30;
const trendPoints = (key) => trendResults.value
    .map((item, index) => `${trendX(index)},${trendY(item, key)}`)
    .join(' ');
const trendLabels = computed(() => {
    const trend = trendResults.value;
    if (trend.length <= 3) {
        return trend;
    }

    return [trend[0], trend[Math.floor((trend.length - 1) / 2)], trend[trend.length - 1]];
});
const uptimeTrendPoints = computed(() => props.systemHealth.trend
    .map((item, index) => {
        const denominator = Math.max(props.systemHealth.trend.length - 1, 1);
        const x = props.systemHealth.trend.length === 1 ? 50 : (index / denominator) * 100;
        const y = 36 - (item.uptime / 100) * 30;
        return `${x},${y}`;
    })
    .join(' '));
const uptimeClass = (uptime) => {
    if (uptime >= 99) {
        return 'text-[#526d23]';
    }

    if (uptime >= 95) {
        return 'text-[#8a5b12]';
    }

    return 'text-[#9e2f2a]';
};
const slaPages = computed(() => {
    const current = slaResults.value.current_page;
    const last = slaResults.value.last_page;

    if (last <= 5) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = [1];

    for (let page = Math.max(2, current - 1); page <= Math.min(last - 1, current + 1); page += 1) {
        pages.push(page);
    }

    pages.push(last);

    return [...new Set(pages)]
        .sort((first, second) => first - second)
        .flatMap((page, index, sortedPages) => {
            if (index > 0 && page - sortedPages[index - 1] > 1) {
                return [`ellipsis-${page}`, page];
            }

            return [page];
        });
});

const label = (value) => value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const loadSlaPage = async (page) => {
    if (slaLoading.value || page === slaResults.value.current_page) {
        return;
    }

    slaLoading.value = true;
    slaError.value = '';

    try {
        const response = await fetch(`/dashboard/sla-breaches?sla_page=${page}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load SLA breaches.');
        }

        slaResults.value = await response.json();
    } catch (error) {
        slaError.value = error.message;
    } finally {
        slaLoading.value = false;
    }
};

const loadTrend = async () => {
    trendLoading.value = true;
    trendError.value = '';

    try {
        const response = await fetch(`/dashboard/trend?timeframe=${trendTimeframe.value}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load incident trend.');
        }

        const payload = await response.json();
        trendResults.value = payload.trend;
        focusedTrendIndex.value = null;
    } catch (error) {
        trendError.value = error.message;
    } finally {
        trendLoading.value = false;
    }
};

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
                    <a href="/incidents" class="group mt-2 inline-flex items-center gap-2 text-2xl font-semibold hover:text-[#297069]">
                        {{ analytics.total }}
                        <span class="text-base text-[#899298] transition-transform group-hover:translate-x-1">→</span>
                    </a>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-[#667079]">Critical</p>
                    <a href="/incidents?severity=critical" class="group mt-2 inline-flex items-center gap-2 text-2xl font-semibold text-[#b53c36] hover:text-[#7f2824]">
                        {{ analytics.severity.find((item) => item.label === 'critical')?.count || 0 }}
                        <span class="text-base text-[#c56a65] transition-transform group-hover:translate-x-1">→</span>
                    </a>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-semibold uppercase text-[#667079]">Escalated</p>
                    <a href="/incidents?status=escalated" class="group mt-2 inline-flex items-center gap-2 text-2xl font-semibold text-[#8a5b12] hover:text-[#6d460c]">
                        {{ analytics.status.find((item) => item.label === 'escalated')?.count || 0 }}
                        <span class="text-base text-[#b9883c] transition-transform group-hover:translate-x-1">→</span>
                    </a>
                </div>
            </section>

            <section class="mb-6 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#e3e7e9] px-5 py-4">
                    <div>
                        <p class="text-sm font-semibold">System availability</p>
                        <p class="mt-1 text-xs text-[#667079]">Simulated health checks across the last {{ systemHealth.period_days }} days.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase text-[#667079]">Overall uptime</p>
                        <p class="mt-1 text-2xl font-semibold" :class="uptimeClass(systemHealth.overall_uptime)">{{ systemHealth.overall_uptime }}%</p>
                    </div>
                </div>

                <div class="grid gap-px bg-[#e3e7e9] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                    <div class="space-y-5 bg-white p-5">
                        <div v-for="system in systemHealth.systems" :key="system.name">
                            <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                <span>
                                    <strong>{{ system.name }}</strong>
                                    <span class="ml-2 text-xs text-[#899298]">{{ system.average_response_ms }} ms</span>
                                </span>
                                <strong :class="uptimeClass(system.uptime)">{{ system.uptime }}%</strong>
                            </div>
                            <div class="h-3 bg-[#edf0f1]">
                                <div
                                    class="h-full"
                                    :class="system.uptime >= 99 ? 'bg-[#78a83c]' : system.uptime >= 95 ? 'bg-[#d99a2b]' : 'bg-[#c84b45]'"
                                    :style="{ width: `${system.uptime}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-semibold">Daily uptime trend</p>
                            <span class="text-xs text-[#667079]">Target 99%</span>
                        </div>
                        <div class="h-[190px]">
                            <svg class="size-full overflow-visible" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Daily system uptime trend">
                                <line x1="0" y1="6.3" x2="100" y2="6.3" stroke="#d99a2b" stroke-width="0.5" stroke-dasharray="2 2" vector-effect="non-scaling-stroke"></line>
                                <line v-for="line in [6, 16, 26, 36]" :key="line" x1="0" :y1="line" x2="100" :y2="line" stroke="#e3e7e9" stroke-width="0.25"></line>
                                <polyline :points="uptimeTrendPoints" fill="none" stroke="#297069" stroke-width="1.4" vector-effect="non-scaling-stroke"></polyline>
                            </svg>
                        </div>
                        <div class="mt-2 flex justify-between text-xs text-[#667079]">
                            <span v-for="point in systemHealth.trend" :key="point.date">{{ point.label }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mb-6 grid gap-6 lg:grid-cols-2">
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
                                @change="hoveredSegment = null"
                                class="h-9 border border-[#cbd1d5] bg-white px-3 outline-none focus:border-[#297069]"
                            >
                                <option value="severity">Severity</option>
                                <option value="status">Status</option>
                            </select>
                        </label>
                    </div>

                    <div v-if="distributionTotal > 0" class="grid min-h-[340px] items-center gap-8 py-6 sm:grid-cols-[220px_minmax(0,1fr)]">
                        <div class="relative mx-auto size-[220px] shrink-0">
                            <svg class="size-full -rotate-90 overflow-visible" viewBox="0 0 220 220" role="img" aria-label="Incident distribution chart">
                                <circle cx="110" cy="110" r="82" fill="none" stroke="#edf0f1" stroke-width="36"></circle>
                                <circle
                                    v-for="(segment, index) in pieSegments"
                                    :key="segment.label"
                                    cx="110"
                                    cy="110"
                                    r="82"
                                    fill="none"
                                    :stroke="segment.color"
                                    :stroke-width="hoveredSegment === index ? 44 : 36"
                                    pathLength="100"
                                    :stroke-dasharray="`${segment.percentage} ${100 - segment.percentage}`"
                                    :stroke-dashoffset="segment.offset"
                                    class="cursor-pointer transition-all duration-150 outline-none"
                                    :class="hoveredSegment !== null && hoveredSegment !== index ? 'opacity-35' : 'opacity-100'"
                                    tabindex="0"
                                    @mouseenter="hoveredSegment = index"
                                    @mouseleave="hoveredSegment = null"
                                    @focus="hoveredSegment = index"
                                    @blur="hoveredSegment = null"
                                ></circle>
                            </svg>
                            <div class="pointer-events-none absolute inset-[54px] grid place-items-center rounded-full bg-white text-center">
                                <span>
                                    <strong class="block text-2xl">{{ activePieSegment?.count ?? distributionTotal }}</strong>
                                    <span class="block text-xs text-[#667079]">
                                        {{ activePieSegment ? label(activePieSegment.label) : 'incidents' }}
                                    </span>
                                    <span v-if="activePieSegment" class="mt-1 block text-xs font-semibold" :style="{ color: activePieSegment.color }">
                                        {{ distributionPercentage(activePieSegment.count) }}
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <button
                                v-for="(item, index) in distribution"
                                :key="item.label"
                                type="button"
                                class="grid w-full grid-cols-[12px_minmax(0,1fr)_auto] items-center gap-3 px-2 py-1.5 text-left transition-colors hover:bg-[#f3f5f7]"
                                :class="hoveredSegment === index ? 'bg-[#f3f5f7]' : ''"
                                @mouseenter="hoveredSegment = index"
                                @mouseleave="hoveredSegment = null"
                                @focus="hoveredSegment = index"
                                @blur="hoveredSegment = null"
                            >
                                <span class="size-3" :style="{ backgroundColor: palette[index % palette.length] }"></span>
                                <span class="text-sm">{{ label(item.label) }}</span>
                                <span class="text-right text-sm">
                                    <strong>{{ item.count }}</strong>
                                    <span class="ml-2 text-xs text-[#667079]">{{ distributionPercentage(item.count) }}</span>
                                </span>
                            </button>
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

            <section class="mb-6 bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#e3e7e9] pb-4">
                    <div>
                        <p class="text-sm font-semibold">Incident trend</p>
                        <p class="mt-1 text-xs text-[#667079]">Incidents created and resolved in the selected timeframe.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <span class="flex items-center gap-2">
                            <span class="size-3 bg-[#2563eb]"></span>
                            Created <strong>{{ trendTotals.created }}</strong>
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="size-3 bg-[#297069]"></span>
                            Resolved <strong>{{ trendTotals.resolved }}</strong>
                        </span>
                        <select
                            v-model="trendTimeframe"
                            class="h-9 border border-[#cbd1d5] bg-white px-3 text-sm outline-none focus:border-[#297069]"
                            :disabled="trendLoading"
                            @change="loadTrend"
                        >
                            <option value="today">Today</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="all">All time</option>
                        </select>
                    </div>
                </div>

                <div v-if="trendResults.length > 0" class="relative pt-5">
                    <div v-if="trendLoading" class="absolute inset-0 z-10 grid place-items-center bg-white/75 text-sm font-medium text-[#667079]">
                        Loading trend...
                    </div>
                    <div class="mb-3 flex min-h-10 items-center justify-center text-sm">
                        <div v-if="activeTrendPoint" class="flex flex-wrap items-center justify-center gap-4 border border-[#dce1e4] bg-[#f7f8f9] px-4 py-2">
                            <strong>{{ activeTrendPoint.label }}</strong>
                            <span class="text-[#2563eb]">Created: <strong>{{ activeTrendPoint.created }}</strong></span>
                            <span class="text-[#297069]">Resolved: <strong>{{ activeTrendPoint.resolved }}</strong></span>
                        </div>
                        <span v-else class="text-xs text-[#899298]">Hover or focus a date to inspect its counts.</span>
                    </div>
                    <div class="h-[240px] w-full">
                        <svg class="size-full overflow-visible" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Incident creation and resolution trend">
                            <line v-for="line in [6, 16, 26, 36]" :key="line" x1="0" :y1="line" x2="100" :y2="line" stroke="#e3e7e9" stroke-width="0.25"></line>
                            <polyline :points="trendPoints('created')" fill="none" stroke="#2563eb" stroke-width="1.2" vector-effect="non-scaling-stroke"></polyline>
                            <polyline :points="trendPoints('resolved')" fill="none" stroke="#297069" stroke-width="1.2" vector-effect="non-scaling-stroke"></polyline>
                            <line
                                v-if="focusedTrendIndex !== null"
                                :x1="trendX(focusedTrendIndex)"
                                y1="4"
                                :x2="trendX(focusedTrendIndex)"
                                y2="36"
                                stroke="#899298"
                                stroke-width="0.6"
                                stroke-dasharray="2 2"
                                vector-effect="non-scaling-stroke"
                            ></line>
                            <template v-for="(point, index) in trendResults" :key="point.date">
                                <circle
                                    :cx="trendX(index)"
                                    :cy="trendY(point, 'created')"
                                    :r="focusedTrendIndex === index ? 1.1 : 0.65"
                                    fill="#2563eb"
                                    stroke="white"
                                    stroke-width="0.5"
                                    vector-effect="non-scaling-stroke"
                                ></circle>
                                <circle
                                    :cx="trendX(index)"
                                    :cy="trendY(point, 'resolved')"
                                    :r="focusedTrendIndex === index ? 1.1 : 0.65"
                                    fill="#297069"
                                    stroke="white"
                                    stroke-width="0.5"
                                    vector-effect="non-scaling-stroke"
                                ></circle>
                                <rect
                                    :x="Math.max(0, trendX(index) - (50 / trendResults.length))"
                                    y="0"
                                    :width="100 / trendResults.length"
                                    height="40"
                                    fill="transparent"
                                    class="cursor-crosshair outline-none"
                                    tabindex="0"
                                    :aria-label="`${point.label}: ${point.created} created, ${point.resolved} resolved`"
                                    @mouseenter="focusedTrendIndex = index"
                                    @mouseleave="focusedTrendIndex = null"
                                    @focus="focusedTrendIndex = index"
                                    @blur="focusedTrendIndex = null"
                                ></rect>
                            </template>
                        </svg>
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-[#667079]">
                        <span v-for="point in trendLabels" :key="point.date">{{ point.label }}</span>
                    </div>
                </div>

                <div v-else class="grid h-[240px] place-items-center text-sm text-[#667079]">
                    No trend data in this timeframe.
                </div>

                <p v-if="trendError" class="mt-3 text-sm text-[#b53c36]">{{ trendError }}</p>
            </section>

            <section class="mb-6 border border-[#b8d8d2] border-l-4 border-l-[#297069] bg-white">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#d5e8e4] bg-[#f1f9f7] px-5 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-[#1f625b]">My unresolved assigned incidents</p>
                            <span class="bg-[#297069] px-2 py-0.5 text-xs font-semibold text-white">{{ selfAssignedIncidents.total }}</span>
                        </div>
                        <p class="mt-1 text-xs text-[#527a75]">Unresolved incidents currently assigned to you.</p>
                    </div>
                    <a href="/incidents?assigned_to=self" class="border border-[#83b9b1] bg-white px-3 py-2 text-sm font-semibold text-[#1f625b] hover:bg-[#e8f5f2]">
                        View my incidents
                    </a>
                </div>

                <div v-if="selfAssignedIncidents.items.length > 0" class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-[#e3e7e9] bg-[#f7f8f9] text-xs uppercase text-[#667079]">
                            <tr>
                                <th class="px-5 py-3">ID</th>
                                <th class="px-5 py-3">Incident</th>
                                <th class="px-5 py-3">Severity</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Duration</th>
                                <th class="px-5 py-3">SLA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e3e7e9]">
                            <tr v-for="incident in selfAssignedIncidents.items" :key="incident.id" class="hover:bg-[#f7fbfa]">
                                <td class="px-5 py-4 font-mono text-xs font-semibold text-[#667079]">#{{ incident.id }}</td>
                                <td class="px-5 py-4">
                                    <a :href="`/incidents/${incident.id}`" class="font-semibold hover:text-[#297069]">{{ incident.title }}</a>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold" :class="breachSeverityClass[incident.severity]">
                                        {{ label(incident.severity) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">{{ label(incident.status) }}</td>
                                <td class="px-5 py-4">
                                    <span class="whitespace-nowrap px-2 py-1 text-xs font-semibold" :class="durationClass[incident.duration_state]">
                                        Running {{ incident.duration }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-[#667079]">{{ incident.sla_deadline || 'Not set' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="px-5 py-10 text-center text-sm text-[#527a75]">
                    No unresolved incidents are assigned to you.
                </div>
            </section>

            <section class="mb-6 border border-[#cfd7dc] bg-white">
                <div class="flex items-center justify-between border-b border-[#e3e7e9] px-5 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold">Routed alerts</p>
                            <span class="bg-[#172027] px-2 py-0.5 text-xs font-semibold text-white">{{ alerts.unread }} unread</span>
                        </div>
                        <p class="mt-1 text-xs text-[#667079]">Alerts routed by severity, escalation, assignment, and SLA state.</p>
                    </div>
                </div>
                <div v-if="alerts.items.length > 0" class="divide-y divide-[#e3e7e9]">
                    <a
                        v-for="alert in alerts.items"
                        :key="alert.id"
                        :href="`/incidents/${alert.incident.id}`"
                        class="grid gap-2 px-5 py-4 hover:bg-[#f7f8f9] sm:grid-cols-[minmax(0,1fr)_auto]"
                        :class="alert.read ? '' : 'border-l-4 border-l-[#d99a2b]'"
                    >
                        <span>
                            <span class="block text-sm font-semibold">{{ alert.message }}</span>
                            <span class="mt-1 block text-xs text-[#667079]">#{{ alert.incident.id }} · {{ label(alert.incident.status) }}</span>
                        </span>
                        <span class="text-xs text-[#899298]">{{ alert.created_at }}</span>
                    </a>
                </div>
                <div v-else class="px-5 py-10 text-center text-sm text-[#667079]">No routed alerts.</div>
            </section>

            <section class="border border-[#e7aaa6] border-l-4 border-l-[#b53c36] bg-white">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#f0c8c5] bg-[#fff5f4] px-5 py-4">
                    <div class="flex items-center gap-4">
                        <span class="grid size-10 shrink-0 place-items-center bg-[#b53c36] text-lg font-bold text-white">!</span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-[#7f2824]">Unresolved SLA breaches</p>
                                <span class="bg-[#b53c36] px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ slaResults.total }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-[#8f5551]">These incidents are overdue and require immediate operational attention.</p>
                        </div>
                    </div>
                    <a href="/incidents?sla=breached" class="border border-[#d98d88] bg-white px-3 py-2 text-sm font-semibold text-[#9e2f2a] hover:bg-[#feeceb]">
                        View all breaches
                    </a>
                </div>

                <div v-if="slaResults.data.length > 0" class="relative overflow-x-auto">
                    <div v-if="slaLoading" class="absolute inset-0 z-10 grid place-items-center bg-white/75 text-sm font-medium text-[#667079]">
                        Loading incidents...
                    </div>
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-[#e3e7e9] bg-[#f7f8f9] text-xs uppercase text-[#667079]">
                            <tr>
                                <th class="px-5 py-3">Incident</th>
                                <th class="px-5 py-3">Severity</th>
                                <th class="px-5 py-3">Assigned user</th>
                                <th class="px-5 py-3">Deadline</th>
                                <th class="px-5 py-3">Duration</th>
                                <th class="px-5 py-3">Overdue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e3e7e9]">
                            <tr v-for="incident in slaResults.data" :key="incident.id" class="hover:bg-[#fffafa]">
                                <td class="px-5 py-4">
                                    <a :href="`/incidents/${incident.id}`" class="font-semibold hover:text-[#9e2f2a]">{{ incident.title }}</a>
                                    <p class="mt-1 text-xs text-[#788188]">{{ label(incident.status) }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold" :class="breachSeverityClass[incident.severity]">
                                        {{ label(incident.severity) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-[#667079]">{{ incident.assignee || 'Unassigned' }}</td>
                                <td class="px-5 py-4 text-[#667079]">{{ incident.sla_deadline }}</td>
                                <td class="px-5 py-4">
                                    <span class="whitespace-nowrap bg-[#feeceb] px-2 py-1 text-xs font-semibold text-[#9e2f2a]">
                                        Running {{ incident.duration }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-block bg-[#feeceb] px-2 py-1 font-semibold text-[#9e2f2a]">
                                        {{ incident.overdue_for }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="px-5 py-10 text-center text-sm text-[#526d23]">
                    No unresolved SLA breaches.
                </div>

                <p v-if="slaError" class="border-t border-[#e3e7e9] px-5 py-3 text-sm text-[#b53c36]">
                    {{ slaError }}
                </p>

                <div
                    v-if="slaResults.last_page > 1"
                    class="flex items-center justify-between gap-4 border-t border-[#e3e7e9] px-5 py-3 text-sm"
                >
                    <span class="text-[#667079]">Showing {{ slaResults.from }}-{{ slaResults.to }} of {{ slaResults.total }}</span>
                    <nav class="flex items-center" aria-label="SLA breach pagination">
                        <template v-for="page in slaPages" :key="page">
                            <span
                                v-if="String(page).startsWith('ellipsis-')"
                                class="grid size-9 place-items-center text-[#899298]"
                                aria-hidden="true"
                            >
                                ...
                            </span>
                            <button
                                v-else
                                type="button"
                                class="grid size-9 place-items-center border-y border-r border-[#cbd1d5] first:border-l disabled:cursor-wait"
                                :class="page === slaResults.current_page ? 'bg-[#b53c36] font-semibold text-white' : 'bg-white hover:bg-[#fff5f4]'"
                                :disabled="slaLoading"
                                :aria-current="page === slaResults.current_page ? 'page' : undefined"
                                @click="loadSlaPage(page)"
                            >
                                {{ page }}
                            </button>
                        </template>
                    </nav>
                </div>
            </section>
        </main>
    </div>
</template>
