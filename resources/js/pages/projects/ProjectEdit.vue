<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import projects from '@/services/projects'

const router = useRouter()
const route = useRoute()
const name = ref('')
const loading = ref(false)
const deleting = ref(false)
const fetchLoading = ref(true)
const errors = ref({})

const fetchProject = async () => {
    fetchLoading.value = true
    try {
        const project = await projects.get(route.params.id)
        name.value = project.name
    } catch (err) {
        router.push({ name: 'projects.index' })
    } finally {
        fetchLoading.value = false
    }
}

onMounted(() => {
    fetchProject()
})

const submit = async () => {
    loading.value = true
    errors.value = {}

    try {
        await projects.update(route.params.id, { name: name.value })
        router.push({ name: 'projects.show', params: { id: route.params.id } })
    } catch (err) {
        if (err.errors) {
            errors.value = err.errors
        } else {
            errors.value = { name: [err.message || 'Failed to update project.'] }
        }
    } finally {
        loading.value = false
    }
}

const deleteProject = async () => {
    if (!confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
        return
    }

    deleting.value = true
    try {
        await projects.delete(route.params.id)
        router.push({ name: 'projects.index' })
    } catch (err) {
        errors.value = { name: [err.message || 'Failed to delete project.'] }
    } finally {
        deleting.value = false
    }
}

const cancel = () => {
    router.push({ name: 'projects.show', params: { id: route.params.id } })
}
</script>

<template>
    <DefaultLayout>
        <div class="py-8">
            <div class="mx-auto max-w-lg">
                <!-- Loading -->
                <div v-if="fetchLoading" class="py-12 text-center text-sm text-neutral-500">
                    Loading...
                </div>

                <template v-else>
                    <!-- Header -->
                    <div class="mb-6">
                        <h1 class="text-xl font-semibold text-neutral-900">Edit Project</h1>
                        <p class="mt-0.5 text-sm text-neutral-500">Update your project details</p>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="rounded-lg border border-neutral-200 bg-white p-5">
                        <div>
                            <label for="name" class="block text-sm font-medium text-neutral-700">Project Name</label>
                            <Input
                                id="name"
                                v-model="name"
                                type="text"
                                placeholder="e.g., My SaaS Blog"
                                class="mt-1.5"
                                :aria-invalid="!!errors.name"
                            />
                            <p v-if="errors.name" class="mt-1.5 text-sm text-red-600">{{ errors.name[0] }}</p>
                        </div>

                        <div class="mt-5 flex items-center justify-between">
                            <Button
                                type="button"
                                variant="destructive"
                                @click="deleteProject"
                                :disabled="deleting"
                            >
                                {{ deleting ? 'Deleting...' : 'Delete' }}
                            </Button>
                            <div class="flex items-center gap-2">
                                <Button type="button" variant="ghost" @click="cancel">Cancel</Button>
                                <Button type="submit" :disabled="loading">
                                    {{ loading ? 'Saving...' : 'Save Changes' }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </DefaultLayout>
</template>
