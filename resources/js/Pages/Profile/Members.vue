<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppHeader from '../../Components/AppHeader.vue';
import { useApiAuth } from '../../Composables/auth/useApiAuth';

const props = defineProps({
    profileUrl: {
        type: String,
        required: true,
    },
    profileShowUrl: {
        type: String,
        required: true,
    },
    profileVideoAccessUrl: {
        type: String,
        required: true,
    },
});

const API_BASE = window.location.origin;

const {
    bootstrapAuth,
    fetchWithAuthorization,
} = useApiAuth();

const isLoadingMembers = ref(true);
const isCreatingMember = ref(false);
const loadError = ref('');
const teamError = ref('');
const members = ref([]);
const memberEmail = ref('');
const generatedCredentials = ref(null);

async function loadMembers() {
    isLoadingMembers.value = true;
    loadError.value = '';
    teamError.value = '';

    try {
        const isAuthenticatedForApi = await bootstrapAuth();

        if (!isAuthenticatedForApi) {
            loadError.value = 'No valid API token found.';
            return;
        }

        const response = await fetchWithAuthorization(`${API_BASE}/api/v1/members`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = await response.json().catch(() => {
            return null;
        });

        if (!response.ok) {
            loadError.value = payload?.message ?? `Unable to load members (${response.status}).`;
            return;
        }

        const apiMembers = Array.isArray(payload?.data?.members) ? payload.data.members : [];
        members.value = apiMembers;
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to load members.';
    } finally {
        isLoadingMembers.value = false;
    }
}

async function createMember() {
    if (isCreatingMember.value) {
        return;
    }

    const normalizedEmail = memberEmail.value.trim().toLowerCase();

    if (normalizedEmail === '') {
        teamError.value = 'Email is required.';
        return;
    }

    isCreatingMember.value = true;
    teamError.value = '';
    generatedCredentials.value = null;

    try {
        const response = await fetchWithAuthorization(`${API_BASE}/api/v1/members`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: normalizedEmail,
            }),
        });
        const payload = await response.json().catch(() => {
            return null;
        });

        if (!response.ok) {
            teamError.value = payload?.message ?? `Unable to create member (${response.status}).`;
            return;
        }

        const createdMember = payload?.data?.member;
        const generatedPassword = payload?.data?.generated_password;

        if (!createdMember || typeof createdMember !== 'object' || typeof generatedPassword !== 'string') {
            teamError.value = 'Member was created, but response format is invalid.';
            return;
        }

        members.value = [createdMember, ...members.value.filter((member) => member.id !== createdMember.id)];
        memberEmail.value = '';
        generatedCredentials.value = {
            email: createdMember.email,
            password: generatedPassword,
        };
    } catch (error) {
        teamError.value = error instanceof Error ? error.message : 'Unable to create member.';
    } finally {
        isCreatingMember.value = false;
    }
}

onMounted(() => {
    loadMembers();
});
</script>

<template>
    <Head title="Team members" />

    <main class="min-h-screen bg-gray-50">
        <AppHeader :profile-url="props.profileUrl" />

        <section class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">Team members</h1>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="props.profileShowUrl"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 active:bg-gray-100"
                    >
                        Back to Profile
                    </Link>
                    <Link
                        :href="props.profileVideoAccessUrl"
                        class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59]"
                    >
                        Video access
                    </Link>
                </div>
            </div>

            <div class="space-y-6 rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div class="space-y-2">
                    <h2 class="text-base font-semibold text-gray-900">Add</h2>
                    <p class="text-sm text-gray-500">Enter email, then copy generated credentials.</p>
                </div>

                <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:flex-row">
                    <input
                        v-model="memberEmail"
                        type="email"
                        placeholder="member@example.com"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        :disabled="isCreatingMember"
                    >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500"
                        :disabled="isCreatingMember"
                        @click="createMember"
                    >
                        {{ isCreatingMember ? 'Creating...' : 'Add' }}
                    </button>
                </div>

                <div v-if="generatedCredentials" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">Member credentials</p>
                    <p class="mt-1">Email: {{ generatedCredentials.email }}</p>
                    <p>Password: {{ generatedCredentials.password }}</p>
                </div>

                <div v-if="teamError !== ''" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ teamError }}
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h2 class="text-base font-semibold text-gray-900">Members</h2>

                    <div v-if="isLoadingMembers" class="mt-4 flex flex-col items-center justify-center space-y-3 px-5 py-8">
                        <svg class="h-8 w-8 animate-spin text-[#0D9488]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm text-gray-500">Loading members...</span>
                    </div>

                    <div v-else-if="loadError !== ''" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ loadError }}
                    </div>

                    <div v-else-if="members.length === 0" class="mt-4 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                        No members yet.
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <article
                            v-for="member in members"
                            :key="member.id"
                            class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3"
                        >
                            <p class="truncate font-semibold text-gray-900">{{ member.name }}</p>
                            <p class="truncate text-sm text-gray-500">{{ member.email }}</p>
                            <p class="mt-1 text-xs uppercase tracking-wide text-gray-500">Mode: {{ member.access_mode }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
