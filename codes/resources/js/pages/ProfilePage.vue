<script setup>
import AppHeader from '../components/AppHeader.vue';

defineProps({
    user: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    success: {
        type: String,
        default: null,
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <AppHeader :user="user" current="profile" />

        <main class="mx-auto max-w-[900px] px-5 py-8 sm:px-8">
            <div v-if="success" class="mb-6 border border-[#9fc55a] bg-[#f7ffe8] px-4 py-3 text-sm text-[#526d23]">
                {{ success }}
            </div>

            <div class="mb-7 border-b border-[#dce1e4] pb-5">
                <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">Account settings</p>
                <h1 class="text-2xl font-semibold">Edit profile</h1>
                <p class="mt-2 text-sm text-[#667079]">Update your display name or replace your password.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <form action="/profile" method="POST" class="bg-white p-6">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PATCH">

                    <h2 class="font-semibold">Profile details</h2>
                    <p class="mt-1 text-sm text-[#667079]">Your email address cannot be changed here.</p>

                    <div class="mt-6">
                        <label for="name" class="mb-2 block text-sm font-medium">Full name</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            :value="user.name"
                            required
                            class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                        >
                        <p v-if="errors.name" class="mt-2 text-sm text-[#b53c36]">{{ errors.name[0] }}</p>
                    </div>

                    <div class="mt-5">
                        <label for="email" class="mb-2 block text-sm font-medium">Email address</label>
                        <input
                            id="email"
                            type="email"
                            :value="user.email"
                            disabled
                            class="h-11 w-full border border-[#dce1e4] bg-[#f3f5f7] px-3 text-sm text-[#667079]"
                        >
                    </div>

                    <button type="submit" class="mt-6 h-11 bg-[#172027] px-5 text-sm font-semibold text-white hover:bg-[#297069]">
                        Save name
                    </button>
                </form>

                <form action="/profile/password" method="POST" class="bg-white p-6">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PUT">

                    <h2 class="font-semibold">Change password</h2>
                    <p class="mt-1 text-sm text-[#667079]">Use a password different from your current one.</p>

                    <div class="mt-6">
                        <label for="current_password" class="mb-2 block text-sm font-medium">Current password</label>
                        <input
                            id="current_password"
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                        >
                        <p v-if="errors.current_password" class="mt-2 text-sm text-[#b53c36]">
                            {{ errors.current_password[0] }}
                        </p>
                    </div>

                    <div class="mt-5">
                        <label for="password" class="mb-2 block text-sm font-medium">New password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                        >
                        <p v-if="errors.password" class="mt-2 text-sm text-[#b53c36]">{{ errors.password[0] }}</p>
                    </div>

                    <div class="mt-5">
                        <label for="password_confirmation" class="mb-2 block text-sm font-medium">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                        >
                    </div>

                    <button type="submit" class="mt-6 h-11 bg-[#172027] px-5 text-sm font-semibold text-white hover:bg-[#297069]">
                        Update password
                    </button>
                </form>
            </div>
        </main>
    </div>
</template>
