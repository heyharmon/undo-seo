<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import projects from '@/services/projects'

const router = useRouter()
const route = useRoute()
const project = ref(null)
const loading = ref(true)
const error = ref(null)

const fetchProject = async () => {
    loading.value = true
    error.value = null
    try {
        project.value = await projects.get(route.params.id)
    } catch (err) {
        error.value = err?.message || 'Failed to load project.'
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchProject()
})

const goToEdit = () => {
    router.push({ name: 'projects.edit', params: { id: route.params.id } })
}

const goBack = () => {
    router.push({ name: 'projects.index' })
}
</script>

<template>
    <DefaultLayout>
        <div class="py-8">
            <!-- Loading -->
            <div v-if="loading" class="py-12 text-center text-sm text-neutral-500">
                Loading...
            </div>

            <!-- Error -->
            <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-600">
                {{ error }}
            </div>

            <template v-else-if="project">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button
                            @click="goBack"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-700"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <h1 class="text-xl font-semibold text-neutral-900">{{ project.name }}</h1>
                    </div>
                    <Button variant="outline" @click="goToEdit">Edit</Button>
                </div>

                <!-- Topical Map Placeholder -->
                <div class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50/50 py-20 text-center">
                    <div class="mx-auto max-w-sm">
                        <svg class="mx-auto h-10 w-10 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        <h3 class="mt-3 text-sm font-medium text-neutral-900">Your topical map will appear here</h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            Enter a seed keyword to generate your keyword clusters and topical map.
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </DefaultLayout>
</template>
