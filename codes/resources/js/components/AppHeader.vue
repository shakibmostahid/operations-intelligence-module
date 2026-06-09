<script setup>
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    current: {
        type: String,
        default: '',
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const canManageUsers = computed(() => ['super_admin', 'admin'].includes(props.user.role));
const initials = computed(() => props.user.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase());
</script>

<template>
    <header class="border-b border-[#dce1e4] bg-white">
        <div class="mx-auto flex min-h-16 max-w-[1440px] items-center justify-between gap-4 px-5 py-3 sm:px-8">
            <div class="flex min-w-0 items-center gap-7">
                <a href="/dashboard" class="flex min-w-0 items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center bg-[#c8f169] text-xs font-bold">FGL</span>
                    <strong class="hidden truncate text-sm lg:block">Incident &amp; Operations Tracking</strong>
                </a>

                <nav class="flex items-center gap-5 text-sm">
                    <a
                        href="/dashboard"
                        :class="current === 'dashboard' ? 'font-semibold' : 'text-[#667079] hover:text-[#172027]'"
                    >
                        Overview
                    </a>
                    <a
                        v-if="canManageUsers"
                        href="/users"
                        :class="current === 'users' ? 'font-semibold' : 'text-[#667079] hover:text-[#172027]'"
                    >
                        Users
                    </a>
                </nav>
            </div>

            <details class="group relative shrink-0">
                <summary
                    class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden"
                    aria-label="Open profile menu"
                >
                    <span class="hidden text-right sm:block">
                        <span class="block text-sm font-medium">{{ user.name }}</span>
                        <span class="block text-xs text-[#788188]">{{ user.email }}</span>
                    </span>
                    <span class="grid size-10 place-items-center border border-[#cbd1d5] bg-[#f7f8f9] text-xs font-bold">
                        {{ initials }}
                    </span>
                </summary>

                <div class="absolute right-0 z-20 mt-2 w-52 border border-[#cbd1d5] bg-white py-2 shadow-lg">
                    <div class="border-b border-[#e3e7e9] px-4 pb-3 pt-1 sm:hidden">
                        <p class="truncate text-sm font-medium">{{ user.name }}</p>
                        <p class="mt-1 truncate text-xs text-[#788188]">{{ user.email }}</p>
                    </div>
                    <a href="/profile" class="block px-4 py-2.5 text-sm hover:bg-[#f3f5f7]">
                        Edit profile
                    </a>
                    <form action="/logout" method="POST" class="border-t border-[#e3e7e9] pt-1">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-[#b53c36] hover:bg-[#fff5f4]">
                            Sign out
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </header>
</template>
