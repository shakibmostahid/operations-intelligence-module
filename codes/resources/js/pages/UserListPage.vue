<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppHeader from '../components/AppHeader.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    users: {
        type: Object,
        required: true,
    },
    success: {
        type: String,
        default: null,
    },
    perPage: {
        type: Number,
        default: 10,
    },
    search: {
        type: String,
        default: '',
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const searchTerm = ref(props.search);
let searchTimer;

watch(searchTerm, (value) => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        const url = new URL(window.location.href);
        const normalizedSearch = value.trim();

        if (normalizedSearch) {
            url.searchParams.set('search', normalizedSearch);
        } else {
            url.searchParams.delete('search');
        }

        url.searchParams.delete('page');
        window.location.assign(`${url.pathname}${url.search}`);
    }, 350);
});

onBeforeUnmount(() => window.clearTimeout(searchTimer));

const roleLabel = (role) => role.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const visiblePages = computed(() => {
    const start = Math.max(1, props.users.current_page - 2);
    const end = Math.min(props.users.last_page, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});
const pageUrl = (page) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);

    return `${url.pathname}${url.search}`;
};
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="users" />

        <main class="mx-auto max-w-[1440px] px-5 py-8 sm:px-8">
            <div v-if="success" class="mb-6 border border-[#9fc55a] bg-[#f7ffe8] px-4 py-3 text-sm text-[#526d23]">
                {{ success }}
            </div>

            <div class="mb-7 flex flex-wrap items-end justify-between gap-4 border-b border-[#dce1e4] pb-5">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">User administration</p>
                    <h1 class="text-2xl font-semibold">Users</h1>
                    <p class="mt-2 text-sm text-[#667079]">Review access, roles, and account status.</p>
                </div>
                <a href="/users/create" class="h-10 bg-[#172027] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#297069]">
                    Create user
                </a>
            </div>

            <div class="mb-3 flex flex-wrap items-end justify-between gap-4">
                <p class="text-sm text-[#667079]">
                    Showing {{ users.from || 0 }} to {{ users.to || 0 }} of {{ users.total }} users
                </p>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-[#667079]">Search by name</span>
                        <input
                            v-model="searchTerm"
                            type="search"
                            placeholder="Enter user name"
                            class="h-9 w-64 border border-[#cbd1d5] bg-white px-3 text-sm outline-none focus:border-[#297069]"
                        >
                    </label>
                    <form action="/users" method="GET" class="flex items-center gap-2 text-sm">
                        <input v-if="search" type="hidden" name="search" :value="search">
                        <label for="per_page" class="text-[#667079]">Rows</label>
                        <select
                            id="per_page"
                            name="per_page"
                            :value="perPage"
                            class="h-9 border border-[#cbd1d5] bg-white px-2 outline-none focus:border-[#297069]"
                            onchange="this.form.submit()"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto border border-[#dce1e4] bg-white">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b border-[#dce1e4] bg-[#f7f8f9] text-xs uppercase text-[#667079]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">User</th>
                            <th class="px-5 py-3 font-semibold">Role</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Created by</th>
                            <th class="px-5 py-3 font-semibold">Created</th>
                            <th class="w-20 px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e7e9]">
                        <tr v-for="listedUser in users.data" :key="listedUser.id" class="hover:bg-[#fafbfb]">
                            <td class="px-5 py-4">
                                <p class="font-medium">{{ listedUser.name }}</p>
                                <p class="mt-1 text-xs text-[#788188]">{{ listedUser.email }}</p>
                            </td>
                            <td class="px-5 py-4">{{ roleLabel(listedUser.role) }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex px-2 py-1 text-xs font-semibold"
                                    :class="listedUser.status === 'active'
                                        ? 'bg-[#eef8e1] text-[#526d23]'
                                        : 'bg-[#f0f2f3] text-[#667079]'"
                                >
                                    {{ roleLabel(listedUser.status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-[#667079]">{{ listedUser.created_by || 'System' }}</td>
                            <td class="px-5 py-4 text-[#667079]">{{ listedUser.created_at }}</td>
                            <td class="px-5 py-4 text-right">
                                <details
                                    v-if="listedUser.can_deactivate || listedUser.can_reactivate"
                                    class="relative inline-block text-left"
                                >
                                    <summary
                                        class="grid size-9 cursor-pointer list-none place-items-center border border-[#cbd1d5] text-lg leading-none hover:bg-[#f3f5f7] [&::-webkit-details-marker]:hidden"
                                        :aria-label="`Actions for ${listedUser.name}`"
                                    >
                                        &#8942;
                                    </summary>
                                    <div class="absolute right-0 z-10 mt-1 w-40 border border-[#cbd1d5] bg-white py-1 shadow-lg">
                                        <form
                                            v-if="listedUser.can_deactivate"
                                            :action="`/users/${listedUser.id}/deactivate`"
                                            method="POST"
                                        >
                                            <input type="hidden" name="_token" :value="csrfToken">
                                            <input type="hidden" name="_method" value="PATCH">
                                            <button
                                                type="submit"
                                                class="w-full px-4 py-2.5 text-left text-sm text-[#b53c36] hover:bg-[#fff5f4]"
                                                onclick="return confirm('Deactivate this user and end their active sessions?')"
                                            >
                                                Deactivate
                                            </button>
                                        </form>
                                        <form
                                            v-if="listedUser.can_reactivate"
                                            :action="`/users/${listedUser.id}/reactivate`"
                                            method="POST"
                                        >
                                            <input type="hidden" name="_token" :value="csrfToken">
                                            <input type="hidden" name="_method" value="PATCH">
                                            <button
                                                type="submit"
                                                class="w-full px-4 py-2.5 text-left text-sm text-[#297069] hover:bg-[#f1faf8]"
                                                onclick="return confirm('Reactivate this user account?')"
                                            >
                                                Reactivate
                                            </button>
                                        </form>
                                    </div>
                                </details>
                                <span v-else class="text-[#a2a9ad]">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="users.data.length === 0" class="px-5 py-14 text-center text-sm text-[#667079]">
                    No users found.
                </div>
            </div>

            <nav v-if="users.last_page > 1" class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
                <a
                    v-if="users.prev_page_url"
                    :href="users.prev_page_url"
                    class="border border-[#cbd1d5] bg-white px-4 py-2 hover:bg-[#f7f8f9]"
                >
                    Previous
                </a>
                <span v-else class="border border-[#dce1e4] px-4 py-2 text-[#a2a9ad]">Previous</span>

                <div class="flex items-center">
                    <a
                        v-for="page in visiblePages"
                        :key="page"
                        :href="pageUrl(page)"
                        class="grid size-9 place-items-center border-y border-r border-[#cbd1d5] first:border-l"
                        :class="page === users.current_page
                            ? 'bg-[#172027] font-semibold text-white'
                            : 'bg-white hover:bg-[#f7f8f9]'"
                        :aria-current="page === users.current_page ? 'page' : undefined"
                    >
                        {{ page }}
                    </a>
                </div>

                <a
                    v-if="users.next_page_url"
                    :href="users.next_page_url"
                    class="border border-[#cbd1d5] bg-white px-4 py-2 hover:bg-[#f7f8f9]"
                >
                    Next
                </a>
                <span v-else class="border border-[#dce1e4] px-4 py-2 text-[#a2a9ad]">Next</span>
            </nav>
        </main>
    </div>
</template>
