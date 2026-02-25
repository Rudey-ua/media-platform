<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppHeader from '../../Components/AppHeader.vue';
import ApiLoadingState from '../../Components/ApiLoadingState.vue';
import { API_BASE } from '../../Composables/api/apiBase';
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
    profileMembersUrl: {
        type: String,
        required: true,
    },
});

const {
    bootstrapAuth,
    fetchWithAuthorization,
} = useApiAuth();

const isLoadingData = ref(true);
const loadError = ref('');
const members = ref([]);
const ownerVideos = ref([]);
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

async function loadData() {
    isLoadingData.value = true;
    loadError.value = '';
    members.value = [];
    ownerVideos.value = [];
    memberModeDrafts.value = {};
    memberVideoDrafts.value = {};

    try {
        const isAuthenticatedForApi = await bootstrapAuth();

        if (!isAuthenticatedForApi) {
            loadError.value = 'No valid API token found.';
            return;
        }

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
            loadError.value = membersPayload?.message ?? `Unable to load members (${membersResponse.status}).`;
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
            loadError.value = videosPayload?.message ?? `Unable to load videos (${videosResponse.status}).`;
            return;
        }

        ownerVideos.value = Array.isArray(videosPayload?.data?.videos) ? videosPayload.data.videos : [];
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to load access data.';
    } finally {
        isLoadingData.value = false;
    }
}

async function saveMemberAccessMode(memberId) {
    if (isMemberBusy(memberId)) {
        return;
    }

    setMemberBusy(memberId, true);
    loadError.value = '';

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
            loadError.value = payload?.message ?? `Unable to update member access mode (${response.status}).`;
            return;
        }

        const updatedMember = payload?.data?.member;

        if (updatedMember && typeof updatedMember === 'object') {
            mergeMember(updatedMember);
        }
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to update member access mode.';
    } finally {
        setMemberBusy(memberId, false);
    }
}

async function saveMemberVideoAccess(memberId) {
    if (isMemberBusy(memberId)) {
        return;
    }

    setMemberBusy(memberId, true);
    loadError.value = '';

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
            loadError.value = payload?.message ?? `Unable to update member video access (${response.status}).`;
            return;
        }

        const updatedMember = payload?.data?.member;

        if (updatedMember && typeof updatedMember === 'object') {
            mergeMember(updatedMember);
        }
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to update member video access.';
    } finally {
        setMemberBusy(memberId, false);
    }
}

onMounted(() => {
    loadData();
});
</script>

<template>
    <Head title="Video access" />

    <main class="min-h-screen bg-gray-50">
        <AppHeader :profile-url="props.profileUrl" />

        <section class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-gray-900">Video access</h1>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="props.profileShowUrl"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 active:bg-gray-100"
                    >
                        Back to Profile
                    </Link>
                    <Link
                        :href="props.profileMembersUrl"
                        class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59]"
                    >
                        Team members
                    </Link>
                </div>
            </div>

            <div class="space-y-6 rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm sm:p-8">
                <Transition name="fade" mode="out-in">
                    <ApiLoadingState
                        v-if="isLoadingData"
                        key="loading"
                        message="Loading access data..."
                        container-class="flex flex-col items-center justify-center space-y-3 px-5 py-10"
                    />

                    <div v-else-if="loadError !== ''" key="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ loadError }}
                    </div>

                    <div v-else-if="members.length === 0" key="empty" class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                        No members yet.
                    </div>

                    <div v-else key="list" class="space-y-4">
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
                                        class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500"
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
                                        class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500"
                                        :disabled="isMemberBusy(member.id)"
                                        @click="saveMemberVideoAccess(member.id)"
                                    >
                                        {{ isMemberBusy(member.id) ? 'Saving...' : 'Save video access' }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </Transition>
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
