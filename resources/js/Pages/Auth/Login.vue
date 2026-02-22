<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    loginStoreUrl: {
        type: String,
        required: true,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const topErrorMessage = computed(() => {
    if (form.errors.email) {
        return form.errors.email;
    }

    if (form.errors.password) {
        return form.errors.password;
    }

    return '';
});

function submitLogin() {
    form.post(props.loginStoreUrl, {
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <Head title="Login" />

    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
        <section class="w-full rounded-2xl bg-white p-8 shadow-xl shadow-slate-200">
            <h1 class="text-center text-2xl font-semibold text-slate-900">Login</h1>

            <div
                v-if="topErrorMessage"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                v-text="topErrorMessage"
            ></div>

            <form class="mt-4 space-y-4" @submit.prevent="submitLogin">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        name="password"
                        type="password"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-slate-300 text-slate-700 focus:ring-slate-400"
                    >
                    Remember me
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                >
                    {{ form.processing ? 'Signing in...' : 'Sign in' }}
                </button>
            </form>
        </section>
    </main>
</template>
