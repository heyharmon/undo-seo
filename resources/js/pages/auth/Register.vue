<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import auth from '@/services/auth'

const router = useRouter()
const route = useRoute()
const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const error = ref('')
const loading = ref(false)
const token = ref('')

onMounted(() => {
    // Check for token in URL query parameters
    if (route.query.token) {
        token.value = route.query.token
    }
})

const register = async () => {
    loading.value = true
    error.value = ''

    try {
        const registrationData = {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: password_confirmation.value
        }

        // Add token if it exists
        if (token.value) {
            registrationData.token = token.value
        }

        await auth.register(registrationData)

        router.push('/')
    } catch (err) {
        error.value = err.message || 'Registration failed. Please try again.'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50">
        <div class="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
            <h1 class="mb-6 text-2xl font-bold text-neutral-900">Register</h1>

            <form @submit.prevent="register" class="space-y-4">
                <div v-if="error" class="rounded-md bg-red-50 p-4 text-sm text-red-500">
                    {{ error }}
                </div>

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-neutral-700">Name</label>
                    <input
                        id="name"
                        v-model="name"
                        type="text"
                        required
                        class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-neutral-700">Email</label>
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        :disabled="!!token.value"
                        class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-neutral-700">Password</label>
                    <input
                        id="password"
                        v-model="password"
                        type="password"
                        required
                        class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-neutral-700">Confirm Password</label>
                    <input
                        id="password_confirmation"
                        v-model="password_confirmation"
                        type="password"
                        required
                        class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
                    />
                </div>

                <div>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full rounded-md bg-neutral-900 px-4 py-2 text-white hover:bg-neutral-800 focus:outline-none disabled:opacity-70"
                    >
                        {{ loading ? 'Registering...' : 'Register' }}
                    </button>
                </div>

                <div class="text-center text-sm text-neutral-600">
                    Already have an account?
                    <router-link to="/login" class="font-medium text-neutral-900 hover:underline"> Login </router-link>
                </div>
            </form>
        </div>
    </div>
</template>
