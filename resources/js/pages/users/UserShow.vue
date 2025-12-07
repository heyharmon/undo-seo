<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

const user = ref(null)
const loading = ref(false)
const error = ref(null)

const fetchUser = async () => {
    loading.value = true
    error.value = null
    try {
        user.value = await api.get(`/users/${route.params.id}`)
    } catch (err) {
        error.value = err?.message || 'Failed to load user.'
        user.value = null
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchUser()
})

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const goBack = () => {
    router.push({ name: 'users.index' })
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto px-4 py-8">
            <div class="mb-6">
                <Button variant="link" @click="goBack" class="mb-6"> ← Back to Users </Button>
                <h1 class="text-2xl font-bold text-neutral-900">User Profile</h1>
            </div>

            <div v-if="loading" class="text-center py-12">
                <div class="text-neutral-500">Loading user...</div>
            </div>

            <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                {{ error }}
            </div>

            <div v-else-if="user" class="space-y-6">
                <!-- User Information Card -->
                <div class="rounded-lg border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-neutral-900">User Information</h2>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Name</dt>
                                <dd class="mt-1 text-sm text-neutral-900">{{ user.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Email</dt>
                                <dd class="mt-1 text-sm text-neutral-900">{{ user.email }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Role</dt>
                                <dd class="mt-1">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            user.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                        ]"
                                    >
                                        {{ user.role }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Member Since</dt>
                                <dd class="mt-1 text-sm text-neutral-900">{{ formatDate(user.created_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
