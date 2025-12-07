<script setup>
import { ref, onMounted } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import api from '@/services/api'

const users = ref([])
const loading = ref(false)
const error = ref(null)

const fetchUsers = async () => {
    loading.value = true
    error.value = null
    try {
        users.value = await api.get('/users')
    } catch (err) {
        error.value = err?.message || 'Failed to load users.'
        users.value = []
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchUsers()
})

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}
</script>

<template>
    <DefaultLayout>
        <div class="container mx-auto px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900">Users</h1>
                    <p class="text-sm text-neutral-500">Manage user accounts</p>
                </div>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-200 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-neutral-900">All Users</h2>
                        <div v-if="loading" class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Loading…</div>
                    </div>
                    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
                </div>

                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Role</th>
                                <th class="px-4 py-3 text-left">Created</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="hover:bg-neutral-50/60 cursor-pointer"
                                @click="$router.push({ name: 'users.show', params: { id: user.id } })"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ user.name }}</td>
                                <td class="px-4 py-3 text-sm text-neutral-600">{{ user.email }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                            user.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                        ]"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-neutral-600">{{ formatDate(user.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <Button size="sm" variant="outline" @click.stop="$router.push({ name: 'users.show', params: { id: user.id } })">
                                        View
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="!users.length && !loading">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-500">No users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4 px-4 py-4 md:hidden">
                    <div
                        v-for="user in users"
                        :key="`card-${user.id}`"
                        class="rounded-2xl border border-neutral-200 bg-neutral-50/80 p-4 shadow-sm shadow-neutral-200/40 cursor-pointer"
                        @click="$router.push({ name: 'users.show', params: { id: user.id } })"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-base font-semibold text-neutral-900">{{ user.name }}</div>
                                <div class="mt-1 text-sm text-neutral-600">{{ user.email }}</div>
                            </div>
                            <span
                                :class="[
                                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                    user.role === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800'
                                ]"
                            >
                                {{ user.role }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-xs text-neutral-500">Created {{ formatDate(user.created_at) }}</div>
                            <Button size="sm" variant="outline" @click.stop="$router.push({ name: 'users.show', params: { id: user.id } })"> View </Button>
                        </div>
                    </div>
                    <div
                        v-if="!users.length && !loading"
                        class="rounded-xl border border-dashed border-neutral-300 bg-white/60 p-6 text-center text-sm text-neutral-500"
                    >
                        No users found.
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
