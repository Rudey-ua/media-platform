<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    profileUrl: {
        type: String,
        required: true,
    },
});

const page = usePage();

const isCurrentProfilePage = computed(() => {
    const currentUrl = typeof page.url === 'string' ? page.url : '';

    return currentUrl === props.profileUrl || currentUrl.startsWith(`${props.profileUrl}?`);
});
</script>

<template>
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex h-14 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link href="/" class="flex items-center gap-2 rounded-md focus:outline focus:outline-2 focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-600">
                    <span class="text-sm font-semibold text-white">C</span>
                </div>
                <span class="font-semibold text-gray-900">Converto</span>
            </Link>

            <Link
                v-if="!isCurrentProfilePage"
                :href="profileUrl"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition-colors hover:border-teal-300 hover:text-teal-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-300 focus-visible:ring-inset"
            >
                <span class="sr-only">Open profile</span>
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    aria-hidden="true"
                >
                    <path d="M20 21a8 8 0 1 0-16 0" />
                    <circle cx="12" cy="8" r="4" />
                </svg>
            </Link>

            <span
                v-else
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-teal-300 bg-teal-50 text-teal-700"
                aria-current="page"
            >
                <span class="sr-only">Current page: profile</span>
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    aria-hidden="true"
                >
                    <path d="M20 21a8 8 0 1 0-16 0" />
                    <circle cx="12" cy="8" r="4" />
                </svg>
            </span>
        </div>
    </header>
</template>
