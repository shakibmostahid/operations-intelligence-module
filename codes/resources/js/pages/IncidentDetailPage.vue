<script setup>
import { ref } from 'vue';
import AppHeader from '../components/AppHeader.vue';

const props = defineProps({
    user: { type: Object, required: true },
    incident: { type: Object, required: true },
    canEditIncident: { type: Boolean, default: false },
    canChangeStatus: { type: Boolean, default: false },
    canComment: { type: Boolean, default: false },
    tags: { type: Array, default: () => [] },
    severities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const selectedStatus = ref(props.incident.status);
const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const selectedTags = props.incident.tags.map((tag) => String(tag.id));
const activityTitle = (type) => type === 'comment' ? 'Comment' : label(type);
const durationClass = {
    resolved: 'bg-[#eef8e1] text-[#526d23]',
    running: 'bg-[#e8f3fb] text-[#246183]',
    breached: 'bg-[#feeceb] text-[#9e2f2a]',
};
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="incidents" />

        <main class="mx-auto max-w-[1300px] px-5 py-8 sm:px-8">
            <div v-if="success" class="mb-6 border border-[#9fc55a] bg-[#f7ffe8] px-4 py-3 text-sm text-[#526d23]">{{ success }}</div>
            <div v-if="Object.keys(errors).length > 0" class="mb-6 border border-[#e7aaa6] bg-[#fff5f4] px-4 py-3 text-sm text-[#9e2f2a]">
                {{ errors.incident?.[0] || 'Please review the highlighted incident fields and try again.' }}
            </div>
            <div class="mb-7 flex flex-wrap items-start justify-between gap-4 border-b border-[#dce1e4] pb-5">
                <div>
                    <a href="/incidents" class="mb-3 inline-block text-sm text-[#667079] hover:text-[#172027]">Back to incidents</a>
                    <h1 class="max-w-4xl text-2xl font-semibold">{{ incident.title }}</h1>
                    <p class="mt-2 text-sm text-[#667079]">Created by {{ incident.creator }} on {{ incident.created_at }}</p>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <a :href="`/incidents/${incident.id}/export.pdf`" class="border border-[#297069] bg-white px-3 py-2 text-xs font-semibold text-[#297069] hover:bg-[#e8f5f2]">Export PDF</a>
                    <span class="bg-[#feeceb] px-3 py-2 text-xs font-semibold uppercase">{{ label(incident.severity) }}</span>
                    <span class="bg-[#eef2f3] px-3 py-2 text-xs font-semibold uppercase">{{ label(incident.status) }}</span>
                    <span class="px-3 py-2 text-xs font-semibold" :class="durationClass[incident.duration_state]">
                        {{ incident.duration_state === 'resolved' ? 'Resolved in' : 'Running' }} {{ incident.duration }}
                    </span>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                <div class="space-y-6">
                    <section class="bg-white p-6">
                        <h2 class="text-sm font-semibold">Description</h2>
                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-[#566169]">{{ incident.description }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span v-for="tag in incident.tags" :key="tag.id" class="border px-2 py-1 text-xs" :style="{ borderColor: tag.color, color: tag.color }">{{ tag.name }}</span>
                        </div>
                    </section>

                    <section v-if="canComment" class="bg-white p-6">
                        <h2 class="text-sm font-semibold">Add comment</h2>
                        <form :action="`/incidents/${incident.id}/comments`" method="POST" class="mt-4">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <textarea name="content" rows="3" required placeholder="Add an operational note..." class="w-full border border-[#cbd1d5] px-3 py-3 text-sm outline-none focus:border-[#297069]"></textarea>
                            <p v-if="errors.content" class="mt-2 text-sm text-[#b53c36]">{{ errors.content[0] }}</p>
                            <div class="mt-3 flex justify-end"><button class="h-10 bg-[#172027] px-4 text-sm font-semibold text-white">Post comment</button></div>
                        </form>
                    </section>

                    <section class="bg-white p-6">
                        <h2 class="text-sm font-semibold">Activity timeline</h2>
                        <div class="mt-5 divide-y divide-[#e3e7e9]">
                            <article v-for="activity in incident.activities" :key="activity.id" class="grid grid-cols-[12px_minmax(0,1fr)] gap-4 py-4">
                                <span class="mt-1.5 size-3 bg-[#297069]"></span>
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold">{{ activityTitle(activity.type) }}</p>
                                        <time class="text-xs text-[#899298]">{{ activity.created_at }}</time>
                                    </div>
                                    <p class="mt-1 text-sm leading-6 text-[#566169]">{{ activity.content }}</p>
                                    <p class="mt-2 text-xs text-[#899298]">{{ activity.user }}</p>
                                </div>
                            </article>
                            <p v-if="incident.activities.length === 0" class="py-8 text-center text-sm text-[#667079]">No activity recorded.</p>
                        </div>
                    </section>
                </div>

                <aside>
                    <form v-if="canEditIncident" :action="`/incidents/${incident.id}`" method="POST" class="bg-white p-6">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="_method" value="PATCH">
                        <h2 class="text-sm font-semibold">Incident controls</h2>

                        <div class="mt-5 space-y-5">
                            <div>
                                <label for="title" class="mb-2 block text-sm font-medium">Title</label>
                                <input id="title" name="title" type="text" :value="incident.title" required class="h-11 w-full border border-[#cbd1d5] px-3 text-sm">
                                <p v-if="errors.title" class="mt-2 text-xs text-[#b53c36]">{{ errors.title[0] }}</p>
                            </div>
                            <div>
                                <label for="description" class="mb-2 block text-sm font-medium">Description</label>
                                <textarea id="description" name="description" rows="4" required class="w-full border border-[#cbd1d5] px-3 py-3 text-sm">{{ incident.description }}</textarea>
                                <p v-if="errors.description" class="mt-2 text-xs text-[#b53c36]">{{ errors.description[0] }}</p>
                            </div>
                            <div>
                                <label for="status" class="mb-2 block text-sm font-medium">Status</label>
                                <select
                                    v-if="canChangeStatus"
                                    id="status"
                                    v-model="selectedStatus"
                                    name="status"
                                    class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm"
                                >
                                    <option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option>
                                </select>
                                <div v-else class="flex h-11 items-center border border-[#dce1e4] bg-[#f7f8f9] px-3 text-sm text-[#667079]">
                                    {{ label(incident.status) }}
                                </div>
                                <input v-if="!canChangeStatus" type="hidden" name="status" :value="incident.status">
                                <p v-if="errors.status" class="mt-2 text-xs text-[#b53c36]">{{ errors.status[0] }}</p>
                            </div>
                            <div v-if="canChangeStatus && selectedStatus === 'escalated' && incident.status !== 'escalated'">
                                <label for="escalation_reason" class="mb-2 block text-sm font-medium">Escalation reason</label>
                                <textarea
                                    id="escalation_reason"
                                    name="escalation_reason"
                                    rows="3"
                                    required
                                    placeholder="Why does this incident need escalation?"
                                    class="w-full border border-[#cbd1d5] px-3 py-3 text-sm"
                                ></textarea>
                                <p v-if="errors.escalation_reason" class="mt-2 text-xs text-[#b53c36]">{{ errors.escalation_reason[0] }}</p>
                            </div>
                            <div>
                                <label for="severity" class="mb-2 block text-sm font-medium">Severity</label>
                                <select id="severity" name="severity" class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm">
                                    <option v-for="severity in severities" :key="severity" :value="severity" :selected="incident.severity === severity">{{ label(severity) }}</option>
                                </select>
                                <p v-if="errors.severity" class="mt-2 text-xs text-[#b53c36]">{{ errors.severity[0] }}</p>
                            </div>
                            <div>
                                <p class="mb-2 text-sm font-medium">Assigned user</p>
                                <div class="flex h-11 items-center border border-[#dce1e4] bg-[#f7f8f9] px-3 text-sm text-[#667079]">
                                    {{ incident.assignee || 'Unassigned' }}
                                </div>
                            </div>
                            <div>
                                <label for="sla_deadline" class="mb-2 block text-sm font-medium">SLA deadline</label>
                                <input id="sla_deadline" name="sla_deadline" type="datetime-local" :value="incident.sla_deadline_input" class="h-11 w-full border border-[#cbd1d5] px-3 text-sm">
                                <p class="mt-2 text-xs text-[#667079]">Current state: {{ label(incident.sla_state) }}</p>
                                <p v-if="errors.sla_deadline" class="mt-2 text-xs text-[#b53c36]">{{ errors.sla_deadline[0] }}</p>
                            </div>
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium">Tags</legend>
                                <div class="space-y-2">
                                    <label v-for="tag in tags" :key="tag.id" class="flex items-center gap-2 text-sm">
                                        <input name="tag_ids[]" type="checkbox" :value="tag.id" :checked="selectedTags.includes(String(tag.id))" class="size-4 accent-[#297069]">
                                        <span class="size-3" :style="{ backgroundColor: tag.color }"></span>{{ tag.name }}
                                    </label>
                                </div>
                            </fieldset>
                            <div>
                                <label for="rca_note" class="mb-2 block text-sm font-medium">RCA note</label>
                                <textarea id="rca_note" name="rca_note" rows="4" class="w-full border border-[#cbd1d5] px-3 py-3 text-sm">{{ incident.rca_note }}</textarea>
                                <p v-if="errors.rca_note" class="mt-2 text-xs text-[#b53c36]">{{ errors.rca_note[0] }}</p>
                            </div>
                        </div>
                        <button type="submit" class="mt-6 h-11 w-full bg-[#172027] text-sm font-semibold text-white hover:bg-[#297069]">Save changes</button>
                    </form>

                    <section v-else class="bg-white p-6">
                        <h2 class="text-sm font-semibold">Incident details</h2>
                        <p v-if="incident.status === 'resolved'" class="mt-3 border border-[#9fc55a] bg-[#f7ffe8] px-3 py-2 text-xs text-[#526d23]">
                            This incident is resolved. Details are locked, but comments remain available.
                        </p>
                        <dl class="mt-5 space-y-4 text-sm">
                            <div><dt class="text-xs text-[#899298]">Assigned user</dt><dd class="mt-1">{{ incident.assignee || 'Unassigned' }}</dd></div>
                            <div><dt class="text-xs text-[#899298]">SLA deadline</dt><dd class="mt-1">{{ incident.sla_deadline || 'Not set' }}</dd></div>
                            <div><dt class="text-xs text-[#899298]">SLA state</dt><dd class="mt-1">{{ label(incident.sla_state) }}</dd></div>
                            <div><dt class="text-xs text-[#899298]">Duration</dt><dd class="mt-1">{{ incident.duration_state === 'resolved' ? 'Resolved in' : 'Running' }} {{ incident.duration }}</dd></div>
                            <div><dt class="text-xs text-[#899298]">Resolved</dt><dd class="mt-1">{{ incident.resolved_at || 'No' }}</dd></div>
                            <div v-if="incident.rca_note"><dt class="text-xs text-[#899298]">RCA note</dt><dd class="mt-1 whitespace-pre-line leading-6">{{ incident.rca_note }}</dd></div>
                        </dl>
                    </section>
                </aside>
            </div>
        </main>
    </div>
</template>
