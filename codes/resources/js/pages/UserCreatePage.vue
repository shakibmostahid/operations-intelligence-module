<script setup>
import AppHeader from '../components/AppHeader.vue';

defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    old: {
        type: Object,
        default: () => ({}),
    },
    createdUser: {
        type: Object,
        default: null,
    },
    temporaryPassword: {
        type: String,
        default: null,
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

const roleLabel = (role) => role.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="users" />

        <main class="mx-auto grid max-w-[1100px] gap-8 px-5 py-8 md:grid-cols-[minmax(0,1fr)_320px] sm:px-8">
            <section>
                <div class="mb-7 border-b border-[#dce1e4] pb-5">
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">User administration</p>
                    <h1 class="text-2xl font-semibold">Create user</h1>
                    <p class="mt-2 text-sm text-[#667079]">
                        A temporary password will be generated and must be changed on first sign-in.
                    </p>
                </div>

                <form action="/users" method="POST" class="space-y-5 bg-white p-6 sm:p-8">
                    <input type="hidden" name="_token" :value="csrfToken">

                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium">Full name</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            :value="old.name"
                            required
                            class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                        >
                        <p v-if="errors.name" class="mt-2 text-sm text-[#b53c36]">{{ errors.name[0] }}</p>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            :value="old.email"
                            required
                            class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                        >
                        <p v-if="errors.email" class="mt-2 text-sm text-[#b53c36]">{{ errors.email[0] }}</p>
                    </div>

                    <div>
                        <label for="role_id" class="mb-2 block text-sm font-medium">Role</label>
                        <select
                            id="role_id"
                            name="role_id"
                            required
                            class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm outline-none focus:border-[#297069]"
                        >
                            <option value="">Select role</option>
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.id"
                                :selected="String(old.role_id || '') === String(role.id)"
                            >
                                {{ roleLabel(role.role) }}
                            </option>
                        </select>
                        <p v-if="errors.role_id" class="mt-2 text-sm text-[#b53c36]">{{ errors.role_id[0] }}</p>
                    </div>

                    <div class="flex justify-end border-t border-[#e3e7e9] pt-5">
                        <button type="submit" class="h-11 bg-[#172027] px-5 text-sm font-semibold text-white hover:bg-[#297069]">
                            Create user
                        </button>
                    </div>
                </form>
            </section>

            <aside>
                <div v-if="createdUser && temporaryPassword" class="border border-[#9fc55a] bg-[#f7ffe8] p-5">
                    <p class="text-xs font-semibold uppercase text-[#526d23]">User created</p>
                    <h2 class="mt-2 font-semibold">{{ createdUser.name }}</h2>
                    <p class="mt-1 text-sm text-[#667079]">{{ createdUser.email }}</p>
                    <div class="mt-5 border border-[#cfe4a5] bg-white p-4">
                        <p class="text-xs text-[#667079]">Temporary password</p>
                        <code class="mt-2 block break-all text-base font-semibold">{{ temporaryPassword }}</code>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-[#667079]">
                        Share this password securely. It is displayed only once.
                    </p>
                </div>

                <div v-else class="border border-[#dce1e4] bg-white p-5">
                    <p class="text-sm font-semibold">Created by</p>
                    <p class="mt-2 text-sm text-[#667079]">{{ user.name }}</p>
                    <p class="mt-1 text-xs text-[#899298]">{{ user.email }}</p>
                </div>
            </aside>
        </main>
    </div>
</template>
