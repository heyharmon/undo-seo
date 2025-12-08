<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import projects from '@/services/projects'

const router = useRouter()
const projectList = ref([])
const loading = ref(false)
const error = ref(null)

const fetchProjects = async () => {
    loading.value = true
    error.value = null
    try {
        projectList.value = await projects.getAll()
    } catch (err) {
        error.value = err?.message || 'Failed to load projects.'
        projectList.value = []
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchProjects()
})

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

const goToProject = (id) => {
    router.push({ name: 'projects.show', params: { id } })
}

const goToCreate = () => {
    router.push({ name: 'projects.create' })
}
</script>

<template>
    <DefaultLayout>
        <div class="py-8">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-neutral-900">Projects</h1>
                    <p class="mt-0.5 text-sm text-neutral-500">Manage your keyword research projects</p>
                </div>
                <Button @click="goToCreate">New Project</Button>
            </div>

            <!-- Error State -->
            <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-600">
                {{ error }}
            </div>

            <!-- Loading State -->
            <div v-else-if="loading" class="py-12 text-center text-sm text-neutral-500">
                Loading projects...
            </div>

            <!-- Empty State -->
            <div v-else-if="!projectList.length" class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50/50 py-16 text-center">
                <div class="mx-auto max-w-sm">
                    <svg class="mx-auto h-10 w-10 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-neutral-900">No projects yet</h3>
                    <p class="mt-1 text-sm text-neutral-500">Get started by creating your first project.</p>
                    <div class="mt-4">
                        <Button @click="goToCreate">Create Project</Button>
                    </div>
                </div>
            </div>

            <!-- Projects Grid -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="project in projectList"
                    :key="project.id"
                    @click="goToProject(project.id)"
                    class="group cursor-pointer rounded-lg border border-neutral-200 bg-white p-4 transition hover:border-neutral-300 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-medium text-neutral-900 group-hover:text-black">
                                {{ project.name }}
                            </h3>
                            <p class="mt-1 text-xs text-neutral-500">
                                Created {{ formatDate(project.created_at) }}
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">
                                0 keywords
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
