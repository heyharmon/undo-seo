<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import ClusterSidebar from '@/components/projects/ClusterSidebar.vue'
import KeywordsTable from '@/components/projects/KeywordsTable.vue'
import projects from '@/services/projects'
import keywords from '@/services/keywords'

const router = useRouter()
const route = useRoute()

// Project state
const project = ref(null)
const loading = ref(true)
const error = ref(null)

// Topical map state
const mapData = ref(null)
const mapLoading = ref(false)
const selectedCluster = ref(null)
const clusterKeywords = ref([])
const clusterLoading = ref(false)

// Generation form state
const seedKeyword = ref('')
const includeSuggestions = ref(false)
const generating = ref(false)
const generateError = ref(null)

// Suggestions state
const suggestionsLoading = ref(false)

const hasTopicalMap = computed(() => {
    return mapData.value && mapData.value.clusters && mapData.value.clusters.length > 0
})

const fetchProject = async () => {
    loading.value = true
    error.value = null
    try {
        project.value = await projects.get(route.params.id)
        await fetchClusters()
    } catch (err) {
        error.value = err?.message || 'Failed to load project.'
    } finally {
        loading.value = false
    }
}

const fetchClusters = async () => {
    mapLoading.value = true
    try {
        mapData.value = await keywords.getClusters(route.params.id)
    } catch (err) {
        // No map yet is fine
        mapData.value = null
    } finally {
        mapLoading.value = false
    }
}

const generateMap = async () => {
    if (!seedKeyword.value.trim()) {
        generateError.value = 'Please enter a seed keyword'
        return
    }

    generating.value = true
    generateError.value = null

    try {
        await keywords.generateMap(route.params.id, seedKeyword.value.trim(), includeSuggestions.value)
        await fetchClusters()
        seedKeyword.value = ''
    } catch (err) {
        generateError.value = err?.message || 'Failed to generate topical map. Please try again.'
    } finally {
        generating.value = false
    }
}

const regenerateMap = async () => {
    if (!mapData.value?.seed) return
    if (!confirm('This will replace your current topical map. Continue?')) return

    seedKeyword.value = mapData.value.seed
    await generateMap()
}

const selectCluster = async (cluster) => {
    selectedCluster.value = cluster
    clusterLoading.value = true

    try {
        const data = await keywords.getCluster(route.params.id, cluster.id)
        clusterKeywords.value = data.children || []
    } catch (err) {
        clusterKeywords.value = []
    } finally {
        clusterLoading.value = false
    }
}

const getSuggestions = async () => {
    suggestionsLoading.value = true
    try {
        await keywords.getSuggestions(route.params.id)
        await fetchClusters()
    } catch (err) {
        // Handle error silently or show notification
    } finally {
        suggestionsLoading.value = false
    }
}

const goBack = () => {
    router.push({ name: 'projects.index' })
}

const goToEdit = () => {
    router.push({ name: 'projects.edit', params: { id: route.params.id } })
}

onMounted(() => {
    fetchProject()
})
</script>

<template>
    <!-- Loading State -->
    <div v-if="loading" class="flex h-screen items-center justify-center bg-white">
        <p class="text-sm text-neutral-500">Loading...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex h-screen items-center justify-center bg-white">
        <div class="text-center">
            <p class="text-sm text-red-600">{{ error }}</p>
            <Button class="mt-4" variant="outline" @click="goBack">Go Back</Button>
        </div>
    </div>

    <!-- No Topical Map Yet - Show Generation Form -->
    <div v-else-if="!hasTopicalMap && !mapLoading" class="flex min-h-screen flex-col bg-white">
        <!-- Simple Header -->
        <header class="flex h-12 flex-shrink-0 items-center border-b border-neutral-200 px-4">
            <button @click="goBack" class="mr-3 flex h-7 w-7 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <span class="text-sm font-medium text-neutral-900">{{ project?.name }}</span>
        </header>

        <!-- Generation Form -->
        <div class="flex flex-1 items-center justify-center p-8">
            <div class="w-full max-w-md">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                    <h2 class="mt-4 text-lg font-semibold text-neutral-900">Generate Your Topical Map</h2>
                    <p class="mt-1 text-sm text-neutral-500">Enter a seed keyword to discover related topics and keywords</p>
                </div>

                <form @submit.prevent="generateMap" class="mt-6 space-y-4">
                    <div v-if="generateError" class="rounded-md bg-red-50 p-3 text-sm text-red-600">
                        {{ generateError }}
                    </div>

                    <div>
                        <label for="seed" class="block text-sm font-medium text-neutral-700">Seed Keyword</label>
                        <Input id="seed" v-model="seedKeyword" type="text" placeholder="e.g., keto diet, dog training" class="mt-1.5" />
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-neutral-200 p-3 transition hover:border-neutral-300">
                        <input v-model="includeSuggestions" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500" />
                        <div>
                            <span class="text-sm font-medium text-neutral-900">Include keyword suggestions</span>
                            <p class="mt-0.5 text-xs text-neutral-500">Adds long-tail variations but takes longer to generate</p>
                        </div>
                    </label>

                    <Button type="submit" class="w-full" :disabled="generating">
                        {{ generating ? 'Generating...' : 'Generate Topical Map' }}
                    </Button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-xs text-neutral-400">This may take 10-30 seconds</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Topical Map View -->
    <div v-else class="flex h-screen flex-col bg-white">
        <!-- Header -->
        <header class="flex h-12 flex-shrink-0 items-center justify-between border-b border-neutral-200 px-4">
            <div class="flex items-center gap-3">
                <button @click="goBack" class="flex h-7 w-7 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="h-4 w-px bg-neutral-200"></div>
                <span class="text-sm font-medium text-neutral-900">{{ project?.name }}</span>
                <span v-if="mapData?.seed" class="rounded bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600">{{ mapData.seed }}</span>
            </div>

            <div class="flex items-center gap-4 text-xs text-neutral-500">
                <span>{{ mapData?.cluster_count || 0 }} clusters • {{ mapData?.total_keywords || 0 }} keywords</span>
            </div>

            <div class="flex items-center gap-2">
                <Button size="sm" variant="ghost" @click="getSuggestions" :disabled="suggestionsLoading">
                    {{ suggestionsLoading ? 'Loading...' : 'Get Suggestions' }}
                </Button>
                <Button size="sm" variant="ghost" @click="regenerateMap" :disabled="generating">
                    <svg class="mr-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Regenerate
                </Button>
                <Button size="sm" variant="outline" @click="goToEdit">Edit</Button>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-80 flex-shrink-0 border-r border-neutral-200">
                <ClusterSidebar :clusters="mapData?.clusters || []" :selected-cluster-id="selectedCluster?.id" :loading="mapLoading" @select="selectCluster" />
            </aside>

            <!-- Keywords Table -->
            <main class="flex-1 overflow-hidden">
                <KeywordsTable :cluster="selectedCluster" :keywords="clusterKeywords" :loading="clusterLoading" />
            </main>
        </div>
    </div>
</template>
