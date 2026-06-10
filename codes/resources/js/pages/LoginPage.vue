<script setup>
defineProps({
    email: {
        type: String,
        default: '',
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
</script>

<template>
    <main class="min-h-screen bg-[#f3f5f7] text-[#172027] lg:grid lg:grid-cols-[minmax(360px,0.9fr)_minmax(520px,1.1fr)]">
        <section class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-10">
            <div class="w-full max-w-[420px]">
                <a href="/" class="mb-14 inline-flex items-center gap-3 text-[#172027]">
                    <span class="grid size-10 place-items-center bg-[#c8f169] text-sm font-bold">FGL</span>
                    <span>
                        <strong class="block text-lg leading-5">FGL Incident &amp; Operations Tracking</strong>
                        <span class="text-xs text-[#667079]">Operations intelligence</span>
                    </span>
                </a>

                <div class="mb-8">
                    <p class="mb-2 text-sm font-semibold text-[#297069]">Operations workspace</p>
                    <h1 class="text-3xl font-semibold">Welcome back</h1>
                    <p class="mt-3 text-sm leading-6 text-[#667079]">
                        Sign in to review incidents, SLA risk, and operational activity.
                    </p>
                </div>

                <form action="/login" method="POST" class="space-y-5">
                    <input type="hidden" name="_token" :value="csrfToken">

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium">Email address</label>
                        <input
                            id="email"
                            :value="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            autofocus
                            class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm outline-none transition focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                            placeholder="operator@example.com"
                        >
                        <p v-if="errors.email" class="mt-2 text-sm text-[#b53c36]">{{ errors.email[0] }}</p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="text-sm font-medium">Password</label>
                            <span class="text-xs text-[#788188]">Assigned credentials</span>
                        </div>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="h-11 w-full border border-[#cbd1d5] bg-white px-3 text-sm outline-none transition focus:border-[#297069] focus:ring-2 focus:ring-[#297069]/15"
                            placeholder="Enter your password"
                        >
                        <p v-if="errors.password" class="mt-2 text-sm text-[#b53c36]">{{ errors.password[0] }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-[#566169]">
                        <input name="remember" type="checkbox" value="1" class="size-4 accent-[#297069]">
                        Keep me signed in
                    </label>

                    <button
                        type="submit"
                        class="h-11 w-full bg-[#172027] px-4 text-sm font-semibold text-white transition hover:bg-[#297069] focus:outline-none focus:ring-2 focus:ring-[#297069] focus:ring-offset-2"
                    >
                        Sign in
                    </button>
                </form>
            </div>
        </section>

        <aside class="relative hidden overflow-hidden bg-[#172027] p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-x-0 top-0 h-1 bg-[#c8f169]"></div>
            <div class="flex items-center justify-between text-xs uppercase text-[#a9b2b7]">
                <span>Operational control</span>
                <span>Live workspace</span>
            </div>

            <div class="max-w-xl">
                <p class="mb-5 text-sm font-semibold text-[#c8f169]">Clarity under pressure</p>
                <h2 class="text-4xl font-semibold leading-tight">
                    Keep assignments, urgency, and SLA risk visible.
                </h2>
                <div class="mt-10 grid grid-cols-3 border-y border-white/15 py-6">
                    <div>
                        <span class="block text-2xl font-semibold">01</span>
                        <span class="mt-1 block text-xs text-[#a9b2b7]">Shared queue</span>
                    </div>
                    <div class="border-x border-white/15 px-6">
                        <span class="block text-2xl font-semibold">02</span>
                        <span class="mt-1 block text-xs text-[#a9b2b7]">SLA signals</span>
                    </div>
                    <div class="pl-6">
                        <span class="block text-2xl font-semibold">03</span>
                        <span class="mt-1 block text-xs text-[#a9b2b7]">Clear history</span>
                    </div>
                </div>
            </div>

            <p class="text-xs text-[#7f8a91]">FGL Incident &amp; Operations Tracking</p>
        </aside>
    </main>
</template>
