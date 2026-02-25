<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppHeader from '../../Components/AppHeader.vue';
import ApiLoadingState from '../../Components/ApiLoadingState.vue';
import { API_BASE } from '../../Composables/api/apiBase';
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
    profileEditUrl: {
        type: String,
        required: true,
    },
    profileMembersUrl: {
        type: String,
        required: true,
    },
    profileVideoAccessUrl: {
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

const {
    bootstrapAuth,
    fetchWithAuthorization,
    clearAuthTokens,
} = useApiAuth();

const profile = ref(null);
const loadError = ref('');
const isLoadingProfile = ref(true);
const isLoggingOut = ref(false);

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
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to load profile.';
    } finally {
        isLoadingProfile.value = false;
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
                    <ApiLoadingState
                        v-if="isLoadingProfile"
                        key="loading"
                        message="Loading profile..."
                    />

                    <div v-else-if="loadError !== ''" key="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ loadError }}
                    </div>

                    <div v-else-if="profile" key="profile" class="space-y-6">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-4">
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

                            <Link
                                :href="props.profileEditUrl"
                                class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] focus:outline focus:outline-[3px] focus:outline-[rgba(13,148,136,0.35)] focus:outline-offset-2"
                            >
                                Edit
                            </Link>
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

                        <div v-if="props.canManageMembers" class="space-y-3 border-t border-gray-200 pt-6">
                            <h2 class="text-base font-semibold text-gray-900">Management</h2>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <Link
                                    :href="props.profileMembersUrl"
                                    class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59]"
                                >
                                    Team members
                                </Link>
                                <Link
                                    :href="props.profileVideoAccessUrl"
                                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 active:bg-gray-100"
                                >
                                    Video access
                                </Link>
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
