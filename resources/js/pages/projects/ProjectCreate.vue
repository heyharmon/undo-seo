<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import projects from '@/services/projects'

const router = useRouter()
const name = ref('')
const loading = ref(false)
const errors = ref({})

const submit = async () => {
    loading.value = true
    errors.value = {}

    try {
        const project = await projects.create({ name: name.value })
        router.push({ name: 'projects.show', params: { id: project.id } })
    } catch (err) {
        if (err.errors) {
            errors.value = err.errors
        } else {
            errors.value = { name: [err.message || 'Failed to create project.'] }
        }
    } finally {
        loading.value = false
    }
}

const cancel = () => {
    router.push({ name: 'projects.index' })
}
</script>

<template>
    <DefaultLayout>
        <div class="py-8">
            <div class="mx-auto max-w-lg">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-xl font-semibold text-neutral-900">New Project</h1>
                    <p class="mt-0.5 text-sm text-neutral-500">Create a new keyword research project</p>
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

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <Button type="button" variant="ghost" @click="cancel">Cancel</Button>
                        <Button type="submit" :disabled="loading">
                            {{ loading ? 'Creating...' : 'Create Project' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </DefaultLayout>
</template>
