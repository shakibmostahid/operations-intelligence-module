<script setup>
import { computed } from 'vue';
import AppHeader from '../components/AppHeader.vue';

const props = defineProps({
    user: { type: Object, required: true },
    incidents: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    severities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    success: { type: String, default: null },
});

const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const visiblePages = computed(() => {
    const start = Math.max(1, props.incidents.current_page - 2);
    const end = Math.min(props.incidents.last_page, start + 4);
    const adjustedStart = Math.max(1, end - 4);
    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});
const pageUrl = (page) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    return `${url.pathname}${url.search}`;
};
const severityClass = {
    critical: 'bg-[#feeceb] text-[#9e2f2a]',
    high: 'bg-[#fff3dd] text-[#8a5b12]',
    medium: 'bg-[#e8f3fb] text-[#246183]',
    low: 'bg-[#eef2f3] text-[#566169]',
};
const slaClass = {
    breached: 'bg-[#feeceb] text-[#9e2f2a]',
    at_risk: 'bg-[#fff3dd] text-[#8a5b12]',
    healthy: 'bg-[#eef8e1] text-[#526d23]',
    resolved: 'bg-[#e8f3fb] text-[#246183]',
    not_set: 'bg-[#eef2f3] text-[#667079]',
};
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="incidents" />

        <main class="mx-auto max-w-[1440px] px-5 py-8 sm:px-8">
            <div v-if="success" class="mb-6 border border-[#9fc55a] bg-[#f7ffe8] px-4 py-3 text-sm text-[#526d23]">{{ success }}</div>

            <div class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-[#dce1e4] pb-5">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">Incident operations</p>
                    <h1 class="text-2xl font-semibold">Incidents</h1>
                    <p class="mt-2 text-sm text-[#667079]">Filter, review, and act on operational issues.</p>
                </div>
                <a href="/incidents/create" class="h-10 bg-[#172027] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#297069]">Create incident</a>
            </div>

            <form action="/incidents" method="GET" class="mb-5 grid gap-3 bg-white p-4 md:grid-cols-3 xl:grid-cols-7">
                <input v-if="filters.per_page" type="hidden" name="per_page" :value="filters.per_page">
                <input name="search" type="search" :value="filters.search" placeholder="Search incidents" class="h-10 border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] md:col-span-2" onchange="this.form.submit()">
                <select name="severity" class="h-10 border border-[#cbd1d5] bg-white px-3 text-sm" onchange="this.form.submit()">
                    <option value="">All severities</option>
                    <option v-for="severity in severities" :key="severity" :value="severity" :selected="filters.severity === severity">{{ label(severity) }}</option>
                </select>
                <select name="status" class="h-10 border border-[#cbd1d5] bg-white px-3 text-sm" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option v-for="status in statuses" :key="status" :value="status" :selected="filters.status === status">{{ label(status) }}</option>
                </select>
                <select name="assigned_to" class="h-10 border border-[#cbd1d5] bg-white px-3 text-sm" onchange="this.form.submit()">
                    <option value="">All owners</option>
                    <option v-for="owner in users" :key="owner.id" :value="owner.id" :selected="String(filters.assigned_to || '') === String(owner.id)">{{ owner.name }}</option>
                </select>
                <select name="tag_id" class="h-10 border border-[#cbd1d5] bg-white px-3 text-sm" onchange="this.form.submit()">
                    <option value="">All tags</option>
                    <option v-for="tag in tags" :key="tag.id" :value="tag.id" :selected="String(filters.tag_id || '') === String(tag.id)">{{ tag.name }}</option>
                </select>
                <select name="sla" class="h-10 border border-[#cbd1d5] bg-white px-3 text-sm" onchange="this.form.submit()">
                    <option value="">All SLA states</option>
                    <option v-for="state in ['healthy', 'at_risk', 'breached', 'resolved', 'not_set']" :key="state" :value="state" :selected="filters.sla === state">{{ label(state) }}</option>
                </select>
                <div class="flex justify-end md:col-span-3 xl:col-span-7">
                    <a href="/incidents" class="h-10 border border-[#cbd1d5] px-4 py-2.5 text-sm">Clear</a>
                </div>
            </form>

            <div class="mb-3 flex items-center justify-between text-sm text-[#667079]">
                <span>Showing {{ incidents.from || 0 }} to {{ incidents.to || 0 }} of {{ incidents.total }}</span>
                <form action="/incidents" method="GET">
                    <input v-for="(value, key) in filters" v-show="key !== 'per_page'" :key="key" type="hidden" :name="key" :value="value">
                    <select name="per_page" :value="filters.per_page || 10" class="h-9 border border-[#cbd1d5] bg-white px-2" onchange="this.form.submit()">
                        <option value="10">10 rows</option>
                        <option value="25">25 rows</option>
                        <option value="50">50 rows</option>
                    </select>
                </form>
            </div>

            <div class="overflow-x-auto border border-[#dce1e4] bg-white">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead class="border-b border-[#dce1e4] bg-[#f7f8f9] text-xs uppercase text-[#667079]">
                        <tr>
                            <th class="px-5 py-3">Incident</th><th class="px-5 py-3">Severity</th><th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Owner</th><th class="px-5 py-3">SLA</th><th class="px-5 py-3">Tags</th><th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e7e9]">
                        <tr v-for="incident in incidents.data" :key="incident.id" class="hover:bg-[#fafbfb]">
                            <td class="max-w-[360px] px-5 py-4">
                                <a :href="`/incidents/${incident.id}`" class="font-medium hover:text-[#297069]">{{ incident.title }}</a>
                                <p class="mt-1 truncate text-xs text-[#788188]">{{ incident.description }}</p>
                            </td>
                            <td class="px-5 py-4"><span class="px-2 py-1 text-xs font-semibold" :class="severityClass[incident.severity]">{{ label(incident.severity) }}</span></td>
                            <td class="px-5 py-4">{{ label(incident.status) }}</td>
                            <td class="px-5 py-4 text-[#667079]">{{ incident.assignee || 'Unassigned' }}</td>
                            <td class="px-5 py-4"><span class="px-2 py-1 text-xs font-semibold" :class="slaClass[incident.sla_state]">{{ label(incident.sla_state) }}</span></td>
                            <td class="px-5 py-4">
                                <div class="flex max-w-[220px] flex-wrap gap-1">
                                    <span v-for="tag in incident.tags" :key="tag.id" class="border px-2 py-1 text-xs" :style="{ borderColor: tag.color, color: tag.color }">{{ tag.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right"><a :href="`/incidents/${incident.id}`" class="font-medium text-[#297069]">View</a></td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="incidents.data.length === 0" class="px-5 py-14 text-center text-sm text-[#667079]">No incidents match these filters.</div>
            </div>

            <nav v-if="incidents.last_page > 1" class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
                <a v-if="incidents.prev_page_url" :href="incidents.prev_page_url" class="border border-[#cbd1d5] bg-white px-4 py-2">Previous</a>
                <span v-else class="border border-[#dce1e4] px-4 py-2 text-[#a2a9ad]">Previous</span>
                <div class="flex">
                    <a v-for="page in visiblePages" :key="page" :href="pageUrl(page)" class="grid size-9 place-items-center border-y border-r border-[#cbd1d5] first:border-l" :class="page === incidents.current_page ? 'bg-[#172027] text-white' : 'bg-white'">{{ page }}</a>
                </div>
                <a v-if="incidents.next_page_url" :href="incidents.next_page_url" class="border border-[#cbd1d5] bg-white px-4 py-2">Next</a>
                <span v-else class="border border-[#dce1e4] px-4 py-2 text-[#a2a9ad]">Next</span>
            </nav>
        </main>
    </div>
</template>
