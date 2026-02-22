<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppHeader from '../../Components/AppHeader.vue';
import { useApiAuth } from '../../Composables/auth/useApiAuth';

const props = defineProps({
    playerHomeUrl: {
        type: String,
        required: true,
    },
    profileUrl: {
        type: String,
        required: true,
    },
    webLogoutUrl: {
        type: String,
        required: true,
    },
    canManageMembers: {
        type: Boolean,
        required: true,
    },
});

const API_BASE = window.location.origin;

const {
    bootstrapAuth,
    fetchWithAuthorization,
    clearAuthTokens,
} = useApiAuth();

const profile = ref(null);
const loadError = ref('');
const isLoadingProfile = ref(true);
const isLoggingOut = ref(false);
const isLoadingTeam = ref(false);
const isCreatingMember = ref(false);
const teamError = ref('');
const members = ref([]);
const ownerVideos = ref([]);
const memberEmail = ref('');
const generatedCredentials = ref(null);
const memberModeDrafts = ref({});
const memberVideoDrafts = ref({});
const memberBusyMap = ref({});

function isMemberBusy(memberId) {
    return memberBusyMap.value[memberId] === true;
}

function setMemberBusy(memberId, isBusy) {
    memberBusyMap.value[memberId] = isBusy;
}

function hydrateMemberDrafts(member) {
    memberModeDrafts.value[member.id] = member.access_mode;
    memberVideoDrafts.value[member.id] = Array.isArray(member.granted_video_ids)
        ? [...member.granted_video_ids]
        : [];
}

function mergeMember(member) {
    const existingIndex = members.value.findIndex((currentMember) => currentMember.id === member.id);

    if (existingIndex === -1) {
        members.value.push(member);
    } else {
        members.value[existingIndex] = member;
    }

    hydrateMemberDrafts(member);
}

async function loadTeamData() {
    if (!props.canManageMembers) {
        return;
    }

    isLoadingTeam.value = true;
    teamError.value = '';
    members.value = [];
    ownerVideos.value = [];
    memberModeDrafts.value = {};
    memberVideoDrafts.value = {};

    try {
        const membersResponse = await fetchWithAuthorization(`${API_BASE}/api/v1/members`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });
        const membersPayload = await membersResponse.json().catch(() => {
            return null;
        });

        if (!membersResponse.ok) {
            teamError.value = membersPayload?.message ?? `Unable to load members (${membersResponse.status}).`;
            return;
        }

        const apiMembers = Array.isArray(membersPayload?.data?.members) ? membersPayload.data.members : [];

        members.value = apiMembers;
        members.value.forEach((member) => {
            hydrateMemberDrafts(member);
        });

        const videosResponse = await fetchWithAuthorization(`${API_BASE}/api/v1/videos`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });
        const videosPayload = await videosResponse.json().catch(() => {
            return null;
        });

        if (!videosResponse.ok) {
            teamError.value = videosPayload?.message ?? `Unable to load videos (${videosResponse.status}).`;
            return;
        }

        ownerVideos.value = Array.isArray(videosPayload?.data?.videos) ? videosPayload.data.videos : [];
    } catch (error) {
        teamError.value = error instanceof Error ? error.message : 'Unable to load team data.';
    } finally {
        isLoadingTeam.value = false;
    }
}

async function loadProfile() {
    isLoadingProfile.value = true;
    loadError.value = '';

    try {
        const isAuthenticatedForApi = await bootstrapAuth();

        if (!isAuthenticatedForApi) {
            loadError.value = 'No valid API token found.';
            return;
        }

        const response = await fetchWithAuthorization(`${API_BASE}/api/v1/profile`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        const payload = await response.json().catch(() => {
            return null;
        });

        if (!response.ok) {
            loadError.value = payload?.message ?? `Unable to load profile (${response.status}).`;
            return;
        }

        if (!payload?.data?.user || typeof payload.data.user !== 'object') {
            loadError.value = 'Profile response is invalid.';
            return;
        }

        profile.value = payload.data.user;

        await loadTeamData();
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to load profile.';
    } finally {
        isLoadingProfile.value = false;
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

        mergeMember(createdMember);
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

async function saveMemberAccessMode(memberId) {
    if (isMemberBusy(memberId)) {
        return;
    }

    setMemberBusy(memberId, true);
    teamError.value = '';

    try {
        const response = await fetchWithAuthorization(`${API_BASE}/api/v1/members/${memberId}/access-mode`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                access_mode: memberModeDrafts.value[memberId],
            }),
        });
        const payload = await response.json().catch(() => {
            return null;
        });

        if (!response.ok) {
            teamError.value = payload?.message ?? `Unable to update member access mode (${response.status}).`;
            return;
        }

        const updatedMember = payload?.data?.member;

        if (updatedMember && typeof updatedMember === 'object') {
            mergeMember(updatedMember);
        }
    } catch (error) {
        teamError.value = error instanceof Error ? error.message : 'Unable to update member access mode.';
    } finally {
        setMemberBusy(memberId, false);
    }
}

async function saveMemberVideoAccess(memberId) {
    if (isMemberBusy(memberId)) {
        return;
    }

    setMemberBusy(memberId, true);
    teamError.value = '';

    try {
        const response = await fetchWithAuthorization(`${API_BASE}/api/v1/members/${memberId}/video-access`, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                video_ids: memberVideoDrafts.value[memberId] ?? [],
            }),
        });
        const payload = await response.json().catch(() => {
            return null;
        });

        if (!response.ok) {
            teamError.value = payload?.message ?? `Unable to update member video access (${response.status}).`;
            return;
        }

        const updatedMember = payload?.data?.member;

        if (updatedMember && typeof updatedMember === 'object') {
            mergeMember(updatedMember);
        }
    } catch (error) {
        teamError.value = error instanceof Error ? error.message : 'Unable to update member video access.';
    } finally {
        setMemberBusy(memberId, false);
    }
}

async function handleLogout() {
    if (isLoggingOut.value) {
        return;
    }

    isLoggingOut.value = true;

    try {
        await fetchWithAuthorization(`${API_BASE}/api/v1/logout`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
            },
        });
    } catch (_error) {
        // Web logout still runs even if API logout fails.
    } finally {
        clearAuthTokens();
        router.post(props.webLogoutUrl, {}, {
            onFinish: () => {
                isLoggingOut.value = false;
            },
        });
    }
}

onMounted(() => {
    loadProfile();
});
</script>

<template>
    <Head title="Profile" />

    <main class="min-h-screen bg-gray-50">
        <AppHeader :profile-url="props.profileUrl" />

        <section class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold text-gray-900">Profile</h1>
                <Link
                    :href="props.playerHomeUrl"
                    class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] focus:outline focus:outline-[3px] focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2"
                >
                    Back to Player
                </Link>
            </div>

            <div class="space-y-6 rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm sm:p-8">
                <Transition name="fade" mode="out-in">
                    <div v-if="isLoadingProfile" key="loading" class="flex flex-col items-center justify-center space-y-3 px-5 py-12">
                        <svg class="h-8 w-8 animate-spin text-[#0D9488]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm text-gray-500">Loading profile...</span>
                    </div>

                    <div v-else-if="loadError !== ''" key="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ loadError }}
                    </div>

                    <div v-else-if="profile" key="profile" class="space-y-6">
                        <div class="flex items-center gap-4">
                            <img
                                v-if="profile.profile_image"
                                :src="profile.profile_image"
                                alt="Profile avatar"
                                class="h-14 w-14 rounded-full object-cover ring-1 ring-gray-200"
                            >
                            <div
                                v-else
                                class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-100 text-lg font-semibold text-teal-700 ring-1 ring-teal-200"
                            >
                                {{ String(profile.name || 'U').slice(0, 1).toUpperCase() }}
                            </div>

                            <div class="min-w-0">
                                <p class="truncate text-lg font-semibold text-gray-900">{{ profile.name }}</p>
                                <p class="truncate text-sm text-gray-500">{{ profile.email }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Name</p>
                                <p class="mt-1 text-sm text-gray-900">{{ profile.name }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Email</p>
                                <p class="mt-1 text-sm text-gray-900">{{ profile.email }}</p>
                            </div>
                        </div>

                        <div v-if="props.canManageMembers" class="space-y-4 border-t border-gray-200 pt-6">
                            <div class="space-y-1">
                                <h2 class="text-base font-semibold text-gray-900">Team members</h2>
                                <p class="text-sm text-gray-500">Create members, choose access mode, and assign custom video access.</p>
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
                                    class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] disabled:cursor-not-allowed disabled:bg-teal-300"
                                    :disabled="isCreatingMember"
                                    @click="createMember"
                                >
                                    {{ isCreatingMember ? 'Creating...' : 'Create member' }}
                                </button>
                            </div>

                            <div v-if="generatedCredentials" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                <p class="font-semibold">Member credentials</p>
                                <p class="mt-1">Email: {{ generatedCredentials.email }}</p>
                                <p>Password: {{ generatedCredentials.password }}</p>
                            </div>

                            <div v-if="isLoadingTeam" class="flex flex-col items-center justify-center space-y-3 px-5 py-8">
                                <svg class="h-8 w-8 animate-spin text-[#0D9488]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm text-gray-500">Loading team...</span>
                            </div>

                            <div v-else-if="teamError !== ''" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ teamError }}
                            </div>

                            <div v-else-if="members.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                                No members yet.
                            </div>

                            <div v-else class="space-y-4">
                                <article
                                    v-for="member in members"
                                    :key="member.id"
                                    class="rounded-xl border border-gray-200 bg-white p-4"
                                >
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-gray-900">{{ member.name }}</p>
                                            <p class="truncate text-sm text-gray-500">{{ member.email }}</p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <select
                                                v-model="memberModeDrafts[member.id]"
                                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                :disabled="isMemberBusy(member.id)"
                                            >
                                                <option value="all">All owner videos</option>
                                                <option value="custom">Custom access</option>
                                            </select>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-400"
                                                :disabled="isMemberBusy(member.id)"
                                                @click="saveMemberAccessMode(member.id)"
                                            >
                                                {{ isMemberBusy(member.id) ? 'Saving...' : 'Save mode' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="memberModeDrafts[member.id] === 'custom'" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                                        <p class="text-sm font-medium text-gray-700">Custom video access</p>

                                        <div v-if="ownerVideos.length === 0" class="text-sm text-gray-500">
                                            No videos available for assignment.
                                        </div>

                                        <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <label
                                                v-for="video in ownerVideos"
                                                :key="video.id"
                                                class="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
                                            >
                                                <input
                                                    v-model="memberVideoDrafts[member.id]"
                                                    type="checkbox"
                                                    :value="video.id"
                                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                                                    :disabled="isMemberBusy(member.id)"
                                                >
                                                <span class="min-w-0 text-sm text-gray-700">
                                                    <span class="block truncate font-medium text-gray-900">{{ video.title || 'Untitled video' }}</span>
                                                    <span class="block truncate text-xs text-gray-500">{{ video.id }}</span>
                                                </span>
                                            </label>
                                        </div>

                                        <div>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-400"
                                                :disabled="isMemberBusy(member.id)"
                                                @click="saveMemberVideoAccess(member.id)"
                                            >
                                                {{ isMemberBusy(member.id) ? 'Saving...' : 'Save video access' }}
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </Transition>

                <div class="border-t border-gray-200 pt-6">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 active:bg-red-800 disabled:cursor-not-allowed disabled:bg-red-300"
                        :disabled="isLoggingOut"
                        @click="handleLogout"
                    >
                        {{ isLoggingOut ? 'Logging out...' : 'Logout' }}
                    </button>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
