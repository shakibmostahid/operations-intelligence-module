<script setup>
defineProps({
    user: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-[#f3f5f7] px-5 py-10 text-[#172027]">
        <section class="w-full max-w-[520px] border border-[#dce1e4] bg-white p-6 sm:p-9">
            <div class="mb-8 flex items-start justify-between gap-4 border-b border-[#e3e7e9] pb-6">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">Account security</p>
                    <h1 class="text-2xl font-semibold">Change your password</h1>
                    <p class="mt-2 text-sm leading-6 text-[#667079]">
                        Replace your temporary password before accessing the workspace.
                    </p>
                </div>
                <span class="grid size-10 shrink-0 place-items-center bg-[#c8f169] text-xs font-bold">IO</span>
            </div>

            <div class="mb-6 bg-[#f7f8f9] px-4 py-3 text-sm">
                <p class="font-medium">{{ user.name }}</p>
                <p class="mt-1 text-xs text-[#667079]">{{ user.email }}</p>
            </div>

            <form action="/change-password" method="POST" class="space-y-5">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="_method" value="PUT">

                <div>
                    <label for="current_password" class="mb-2 block text-sm font-medium">Temporary password</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                        class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                    >
                    <p v-if="errors.current_password" class="mt-2 text-sm text-[#b53c36]">
                        {{ errors.current_password[0] }}
                    </p>
                </div>

                <div>
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
                    <p class="mt-2 text-xs leading-5 text-[#788188]">
                        It must differ from your current password and use at least 8 characters with upper and lowercase letters, a number, and a symbol.
                    </p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium">Confirm new password</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="h-11 w-full border border-[#cbd1d5] px-3 text-sm outline-none focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                    >
                </div>

                <button type="submit" class="h-11 w-full bg-[#172027] text-sm font-semibold text-white hover:bg-[#297069]">
                    Update password
                </button>
            </form>

            <form action="/logout" method="POST" class="mt-4 text-center">
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit" class="text-sm font-medium text-[#667079] hover:text-[#172027]">
                    Sign out
                </button>
            </form>
        </section>
    </main>
</template>
