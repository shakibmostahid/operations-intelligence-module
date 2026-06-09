<script setup>
import AppHeader from '../components/AppHeader.vue';

defineProps({
    user: {
        type: Object,
        required: true,
    },
    success: {
        type: String,
        default: null,
    },
});
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
