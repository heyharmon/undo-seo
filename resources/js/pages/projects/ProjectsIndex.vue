<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import projectsService from '@/services/projects'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const router = useRouter()
const projects = ref([])
const loading = ref(true)
const showCreateModal = ref(false)
const newProjectName = ref('')
const editingProject = ref(null)
const editName = ref('')

onMounted(async () => {
    await loadProjects()
})

async function loadProjects() {
    loading.value = true
    try {
        projects.value = await projectsService.getProjects()
    } finally {
        loading.value = false
    }
}

async function createProject() {
    if (!newProjectName.value.trim()) return
    
    await projectsService.createProject({ name: newProjectName.value })
    newProjectName.value = ''
    showCreateModal.value = false
    await loadProjects()
}

async function startEdit(project) {
    editingProject.value = project
    editName.value = project.name
}

async function saveEdit() {
    if (!editName.value.trim() || !editingProject.value) return
    
    await projectsService.updateProject(editingProject.value.id, { name: editName.value })
    editingProject.value = null
    editName.value = ''
    await loadProjects()
}

function cancelEdit() {
    editingProject.value = null
    editName.value = ''
}

async function deleteProject(project) {
    if (!confirm(`Delete "${project.name}"? This will also delete all keywords.`)) return
    
    await projectsService.deleteProject(project.id)
    await loadProjects()
}

function openProject(project) {
    router.push({ name: 'projects.keywords', params: { id: project.id } })
}
</script>

<template>
    <DefaultLayout>
        <div class="py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">Projects</h1>
                    <p class="text-sm text-neutral-500 mt-1">Manage your SEO keyword projects</p>
                </div>
                <Button @click="showCreateModal = true">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    New Project
                </Button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-12 text-neutral-500">
                Loading projects...
            </div>

            <!-- Empty State -->
            <div v-else-if="projects.length === 0" class="text-center py-16 border border-dashed border-neutral-200 rounded-xl">
                <div class="text-neutral-400 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-12 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-neutral-900 mb-1">No projects yet</h3>
                <p class="text-neutral-500 mb-6">Create your first project to start organizing keywords</p>
                <Button @click="showCreateModal = true">Create Project</Button>
            </div>

            <!-- Projects Grid -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div 
                    v-for="project in projects" 
                    :key="project.id"
                    class="group relative border border-neutral-200 rounded-xl p-5 hover:border-neutral-300 hover:shadow-sm transition cursor-pointer bg-white"
                    @click="openProject(project)"
                >
                    <div v-if="editingProject?.id === project.id" class="flex items-center gap-2" @click.stop>
                        <Input 
                            v-model="editName" 
                            class="flex-1 h-8"
                            @keyup.enter="saveEdit"
                            @keyup.escape="cancelEdit"
                        />
                        <button 
                            @click="saveEdit" 
                            class="text-neutral-600 hover:text-neutral-900 p-1"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </button>
                        <button 
                            @click="cancelEdit" 
                            class="text-neutral-600 hover:text-neutral-900 p-1"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <template v-else>
                        <h3 class="font-medium text-neutral-900 mb-1">{{ project.name }}</h3>
                        <p class="text-sm text-neutral-500">
                            Created {{ new Date(project.created_at).toLocaleDateString() }}
                        </p>
                        
                        <!-- Actions -->
                        <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition flex items-center gap-1" @click.stop>
                            <button 
                                @click="startEdit(project)" 
                                class="p-1.5 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 rounded"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                                </svg>
                            </button>
                            <button 
                                @click="deleteProject(project)" 
                                class="p-1.5 text-neutral-400 hover:text-red-600 hover:bg-red-50 rounded"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" @click="showCreateModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-semibold text-neutral-900 mb-4">Create New Project</h2>
                <Input 
                    v-model="newProjectName" 
                    placeholder="Project name"
                    class="mb-4"
                    @keyup.enter="createProject"
                />
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="showCreateModal = false">Cancel</Button>
                    <Button @click="createProject" :disabled="!newProjectName.trim()">Create</Button>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
