<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
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
});

const API_BASE = window.location.origin;

const {
    bootstrapAuth,
    fetchWithAuthorization,
} = useApiAuth();

const profile = ref(null);
const profileNameDraft = ref('');
const isLoadingProfile = ref(true);
const isSavingProfileName = ref(false);
const loadError = ref('');
const profileNameError = ref('');
const profileNameSuccess = ref('');

const canSaveProfileName = computed(() => {
    if (isSavingProfileName.value || !profile.value) {
        return false;
    }

    const normalizedName = profileNameDraft.value.trim();

    return normalizedName !== '' && normalizedName !== profile.value.name;
});

async function loadProfile() {
    isLoadingProfile.value = true;
    loadError.value = '';
    profileNameError.value = '';
    profileNameSuccess.value = '';

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
        profileNameDraft.value = String(payload.data.user.name ?? '');
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'Unable to load profile.';
    } finally {
        isLoadingProfile.value = false;
    }
}

async function updateProfileName() {
    if (!canSaveProfileName.value || !profile.value) {
        return;
    }

    isSavingProfileName.value = true;
    profileNameError.value = '';
    profileNameSuccess.value = '';

    try {
        const response = await fetchWithAuthorization(`${API_BASE}/api/v1/profile`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: profileNameDraft.value.trim(),
            }),
        });
        const payload = await response.json().catch(() => {
            return null;
        });

        if (!response.ok) {
            profileNameError.value = payload?.message ?? `Unable to update name (${response.status}).`;
            return;
        }

        const updatedUser = payload?.data?.user;

        if (!updatedUser || typeof updatedUser !== 'object') {
            profileNameError.value = 'Profile was updated, but response format is invalid.';
            return;
        }

        profile.value = updatedUser;
        profileNameDraft.value = String(updatedUser.name ?? '');
        profileNameSuccess.value = 'Name updated.';
    } catch (error) {
        profileNameError.value = error instanceof Error ? error.message : 'Unable to update name.';
    } finally {
        isSavingProfileName.value = false;
    }
}

onMounted(() => {
    loadProfile();
});
</script>

<template>
    <Head title="Edit Profile" />

    <main class="min-h-screen bg-gray-50">
        <AppHeader :profile-url="props.profileUrl" />

        <section class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold text-gray-900">Edit profile</h1>
                <Link
                    :href="props.profileShowUrl"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 active:bg-gray-100 focus:outline focus:outline-[3px] focus:outline-[rgba(107,114,128,0.25)] focus:outline-offset-2"
                >
                    Back to Profile
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

                    <div v-else-if="profile" key="form" class="space-y-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Email</p>
                            <p class="mt-1 text-sm text-gray-900">{{ profile.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <label for="profile-name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input
                                id="profile-name"
                                v-model="profileNameDraft"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder="Your name"
                                :disabled="isSavingProfileName"
                            >
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-[#0D9488] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0F766E] active:bg-[#115E59] disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500"
                            :disabled="!canSaveProfileName"
                            @click="updateProfileName"
                        >
                            {{ isSavingProfileName ? 'Saving...' : 'Save' }}
                        </button>

                        <div v-if="profileNameError !== ''" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ profileNameError }}
                        </div>
                        <div v-else-if="profileNameSuccess !== ''" class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-700">
                            {{ profileNameSuccess }}
                        </div>
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
