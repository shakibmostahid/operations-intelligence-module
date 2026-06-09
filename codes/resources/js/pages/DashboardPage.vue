<script setup>
defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
</script>

<template>
    <div class="min-h-screen bg-[#f3f5f7] text-[#172027]">
        <header class="border-b border-[#dce1e4] bg-white">
            <div class="mx-auto flex h-16 max-w-[1440px] items-center justify-between px-5 sm:px-8">
                <div class="flex items-center gap-8">
                    <a href="/dashboard" class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center bg-[#c8f169] text-xs font-bold">OP</span>
                        <strong class="text-base">FGL Incident &amp; Operations Tracking</strong>
                    </a>
                    <nav class="hidden items-center gap-6 text-sm md:flex">
                        <a href="/dashboard" class="font-semibold">Overview</a>
                        <span class="text-[#899298]">Incidents</span>
                    </nav>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium">{{ user.name }}</p>
                        <p class="text-xs text-[#788188]">{{ user.email }}</p>
                    </div>
                    <form action="/logout" method="POST">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <button
                            type="submit"
                            class="border border-[#cbd1d5] bg-white px-3 py-2 text-sm font-medium transition hover:border-[#899298] hover:bg-[#f7f8f9]"
                        >
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1440px] px-5 py-8 sm:px-8">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-[#dce1e4] pb-6">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-[#297069]">Operations overview</p>
                    <h1 class="text-2xl font-semibold">Dashboard</h1>
                    <p class="mt-2 text-sm text-[#667079]">Your operational workspace is ready.</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-[#667079]">
                    <span class="size-2 bg-[#58a66c]"></span>
                    Systems connected
                </div>
            </div>

            <section class="min-h-[420px] border border-dashed border-[#cbd1d5] bg-white">
                <div class="flex min-h-[420px] flex-col items-center justify-center px-6 text-center">
                    <div class="mb-5 grid size-12 place-items-center border border-[#dce1e4] bg-[#f7f8f9] text-lg font-semibold text-[#297069]">
                        +
                    </div>
                    <h2 class="text-lg font-semibold">No operational data yet</h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-[#667079]">
                        Incident metrics, SLA status, and current activity will appear here as the module is built.
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
