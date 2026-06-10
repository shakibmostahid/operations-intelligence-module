<script setup>
import AppHeader from '../components/AppHeader.vue';

const props = defineProps({
    user: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    severities: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    old: { type: Object, default: () => ({}) },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const oldTagIds = Array.isArray(props.old.tag_ids) ? props.old.tag_ids.map(String) : [];
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="incidents" />

        <main class="mx-auto max-w-[1000px] px-5 py-8 sm:px-8">
            <div class="mb-7 flex items-end justify-between gap-4 border-b border-[#dce1e4] pb-5">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">Incident operations</p>
                    <h1 class="text-2xl font-semibold">Create incident</h1>
                    <p class="mt-2 text-sm text-[#667079]">Record a new operational issue for investigation.</p>
                </div>
                <a href="/incidents" class="text-sm font-medium text-[#667079] hover:text-[#172027]">Back to incidents</a>
            </div>

            <form action="/incidents" method="POST" class="bg-white p-6 sm:p-8">
                <input type="hidden" name="_token" :value="csrfToken">

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="title" class="mb-2 block text-sm font-medium">Title</label>
                        <input id="title" name="title" type="text" :value="old.title" required autofocus class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069]">
                        <p v-if="errors.title" class="mt-2 text-sm text-[#b53c36]">{{ errors.title[0] }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="description" class="mb-2 block text-sm font-medium">Description</label>
                        <textarea id="description" name="description" rows="5" required class="w-full border border-[#cbd1d5] px-3 py-3 text-sm outline-none focus:border-[#297069]">{{ old.description }}</textarea>
                        <p v-if="errors.description" class="mt-2 text-sm text-[#b53c36]">{{ errors.description[0] }}</p>
                    </div>

                    <div>
                        <label for="severity" class="mb-2 block text-sm font-medium">Severity</label>
                        <select id="severity" name="severity" required class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm outline-none focus:border-[#297069]">
                            <option value="">Select severity</option>
                            <option v-for="severity in severities" :key="severity" :value="severity" :selected="old.severity === severity">{{ label(severity) }}</option>
                        </select>
                        <p v-if="errors.severity" class="mt-2 text-sm text-[#b53c36]">{{ errors.severity[0] }}</p>
                    </div>

                    <div>
                        <label for="assigned_to" class="mb-2 block text-sm font-medium">Assigned user</label>
                        <select id="assigned_to" name="assigned_to" class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm outline-none focus:border-[#297069]">
                            <option value="">Unassigned</option>
                            <option v-for="assignedUser in users" :key="assignedUser.id" :value="assignedUser.id" :selected="String(old.assigned_to || '') === String(assignedUser.id)">{{ assignedUser.name }}</option>
                        </select>
                        <p v-if="errors.assigned_to" class="mt-2 text-sm text-[#b53c36]">{{ errors.assigned_to[0] }}</p>
                    </div>

                    <div>
                        <label for="sla_deadline" class="mb-2 block text-sm font-medium">SLA deadline</label>
                        <input id="sla_deadline" name="sla_deadline" type="datetime-local" :value="old.sla_deadline" class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069]">
                        <p v-if="errors.sla_deadline" class="mt-2 text-sm text-[#b53c36]">{{ errors.sla_deadline[0] }}</p>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium">Initial status</p>
                        <div class="flex h-11 items-center border border-[#dce1e4] bg-[#f7f8f9] px-3 text-sm text-[#667079]">Open</div>
                    </div>

                    <fieldset class="md:col-span-2">
                        <legend class="mb-3 text-sm font-medium">Tags</legend>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="tag in tags" :key="tag.id" class="flex items-center gap-2 border border-[#dce1e4] px-3 py-2 text-sm">
                                <input name="tag_ids[]" type="checkbox" :value="tag.id" :checked="oldTagIds.includes(String(tag.id))" class="size-4 accent-[#297069]">
                                <span class="size-3" :style="{ backgroundColor: tag.color }"></span>
                                {{ tag.name }}
                            </label>
                        </div>
                    </fieldset>

                    <div class="md:col-span-2">
                        <label for="rca_note" class="mb-2 block text-sm font-medium">RCA note <span class="font-normal text-[#899298]">(optional)</span></label>
                        <textarea id="rca_note" name="rca_note" rows="3" class="w-full border border-[#cbd1d5] px-3 py-3 text-sm outline-none focus:border-[#297069]">{{ old.rca_note }}</textarea>
                        <p v-if="errors.rca_note" class="mt-2 text-sm text-[#b53c36]">{{ errors.rca_note[0] }}</p>
                    </div>
                </div>

                <div class="mt-7 flex justify-end border-t border-[#e3e7e9] pt-5">
                    <button type="submit" class="h-11 bg-[#172027] px-5 text-sm font-semibold text-white hover:bg-[#297069]">Create incident</button>
                </div>
            </form>
        </main>
    </div>
</template>
