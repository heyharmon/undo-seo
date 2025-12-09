<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import DifficultyBadge from '@/components/ui/DifficultyBadge.vue'
import projects from '@/services/projects'
import keywords from '@/services/keywords'

const router = useRouter()
const route = useRoute()

// Project state
const project = ref(null)
const loading = ref(true)
const error = ref(null)

// Search form state
const searchQuery = ref('')
const searchSource = ref('suggestions')
const searching = ref(false)
const searchError = ref(null)

// Results state
const results = ref([])
const selectedKeywords = ref(new Set())

// Sorting state
const sortKey = ref('search_volume')
const sortDir = ref('desc')

// Adding state
const adding = ref(false)

// Computed: sorted results
const sortedResults = computed(() => {
    if (!results.value.length) return []

    return [...results.value].sort((a, b) => {
        let aVal = a[sortKey.value]
        let bVal = b[sortKey.value]

        // String comparison for keyword
        if (sortKey.value === 'keyword') {
            aVal = (aVal || '').toLowerCase()
            bVal = (bVal || '').toLowerCase()
            if (sortDir.value === 'asc') {
                return aVal.localeCompare(bVal)
            }
            return bVal.localeCompare(aVal)
        }

        // Numeric comparison for volume/difficulty
        if (aVal === null) aVal = 0
        if (bVal === null) bVal = 0

        if (sortDir.value === 'asc') {
            return aVal > bVal ? 1 : -1
        }
        return aVal < bVal ? 1 : -1
    })
})

// Computed: all selected?
const allSelected = computed(() => {
    if (!results.value.length) return false
    const selectable = results.value.filter(k => !k.in_project)
    return selectable.length > 0 && selectable.every(k => selectedKeywords.value.has(k.keyword))
})

// Computed: some selected?
const someSelected = computed(() => {
    return selectedKeywords.value.size > 0
})

// Computed: count of selectable (not in project)
const selectableCount = computed(() => {
    return results.value.filter(k => !k.in_project).length
})

// Dynamic placeholder based on source
const inputPlaceholder = computed(() => {
    return searchSource.value === 'ideas'
        ? 'e.g., keto diet, weight loss, healthy eating'
        : 'e.g., keto diet'
})

// Fetch project on mount
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

// Search keywords
const search = async () => {
    if (!searchQuery.value.trim()) {
        searchError.value = 'Please enter a search query'
        return
    }

    searching.value = true
    searchError.value = null
    selectedKeywords.value = new Set()

    try {
        const data = await keywords.explore(route.params.id, searchQuery.value.trim(), searchSource.value)
        results.value = data.keywords || []
    } catch (err) {
        searchError.value = err?.message || 'Failed to search keywords'
        results.value = []
    } finally {
        searching.value = false
    }
}

// Toggle sort
const toggleSort = (key) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
    } else {
        sortKey.value = key
        sortDir.value = key === 'keyword' ? 'asc' : 'desc'
    }
}

const getSortIcon = (key) => {
    if (sortKey.value !== key) return ''
    return sortDir.value === 'asc' ? '↑' : '↓'
}

// Toggle single keyword selection
const toggleKeyword = (keyword) => {
    if (selectedKeywords.value.has(keyword)) {
        selectedKeywords.value.delete(keyword)
    } else {
        selectedKeywords.value.add(keyword)
    }
    // Trigger reactivity
    selectedKeywords.value = new Set(selectedKeywords.value)
}

// Toggle all selection
const toggleAll = () => {
    if (allSelected.value) {
        selectedKeywords.value = new Set()
    } else {
        const selectable = results.value.filter(k => !k.in_project).map(k => k.keyword)
        selectedKeywords.value = new Set(selectable)
    }
}

// Add selected keywords
const addSelected = async () => {
    if (!selectedKeywords.value.size) return

    adding.value = true
    try {
        const keywordsToAdd = results.value
            .filter(k => selectedKeywords.value.has(k.keyword))
            .map(k => ({
                keyword: k.keyword,
                search_volume: k.search_volume,
                difficulty: k.difficulty,
            }))

        await keywords.addKeywords(route.params.id, keywordsToAdd)

        // Mark added keywords as in_project
        results.value = results.value.map(k => ({
            ...k,
            in_project: k.in_project || selectedKeywords.value.has(k.keyword),
        }))
        selectedKeywords.value = new Set()
    } catch (err) {
        searchError.value = err?.message || 'Failed to add keywords'
    } finally {
        adding.value = false
    }
}

// Add all keywords (not in project)
const addAll = async () => {
    if (!selectableCount.value) return

    adding.value = true
    try {
        const keywordsToAdd = results.value
            .filter(k => !k.in_project)
            .map(k => ({
                keyword: k.keyword,
                search_volume: k.search_volume,
                difficulty: k.difficulty,
            }))

        await keywords.addKeywords(route.params.id, keywordsToAdd)

        // Mark all as in_project
        results.value = results.value.map(k => ({ ...k, in_project: true }))
        selectedKeywords.value = new Set()
    } catch (err) {
        searchError.value = err?.message || 'Failed to add keywords'
    } finally {
        adding.value = false
    }
}

const formatNumber = (num) => {
    if (num === null || num === undefined) return '—'
    return num.toLocaleString()
}

const getDifficultyLabel = (difficulty) => {
    if (difficulty === null || difficulty === undefined) return null
    if (difficulty < 30) return 'Easy'
    if (difficulty < 60) return 'Doable'
    return 'Hard'
}

const goToProject = () => {
    router.push({ name: 'projects.show', params: { id: route.params.id } })
}

const goBack = () => {
    router.push({ name: 'projects.index' })
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

    <!-- Explorer View -->
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
                <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600">Explorer</span>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 rounded-lg bg-neutral-100 p-0.5">
                <button @click="goToProject" class="rounded-md px-3 py-1 text-xs font-medium text-neutral-600 hover:text-neutral-900">
                    Topical Map
                </button>
                <button class="rounded-md bg-white px-3 py-1 text-xs font-medium text-neutral-900 shadow-sm">
                    Explorer
                </button>
            </div>

            <div class="w-24"></div>
        </header>

        <!-- Search Bar -->
        <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3">
            <form @submit.prevent="search" class="flex items-center gap-3">
                <div class="flex-1">
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="inputPlaceholder"
                        class="w-full"
                    />
                </div>
                <select
                    v-model="searchSource"
                    class="h-9 rounded-md border border-neutral-200 bg-white px-3 text-sm text-neutral-900 focus:border-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                >
                    <option value="suggestions">Suggestions</option>
                    <option value="ideas">Ideas</option>
                </select>
                <Button type="submit" :disabled="searching">
                    {{ searching ? 'Searching...' : 'Search' }}
                </Button>
            </form>
            <div v-if="searchError" class="mt-2 text-sm text-red-600">{{ searchError }}</div>
        </div>

        <!-- Results Area -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Empty State -->
            <div v-if="!results.length && !searching" class="flex flex-1 flex-col items-center justify-center text-neutral-500">
                <svg class="h-12 w-12 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <p class="mt-3 text-sm font-medium text-neutral-900">Search for keywords</p>
                <p class="mt-1 text-sm">Enter a query above to explore keywords</p>
            </div>

            <!-- Results Table -->
            <div v-else class="flex-1 overflow-auto">
                <table class="w-full">
                    <thead class="sticky top-0 bg-neutral-50 text-xs font-medium uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="w-10 px-4 py-2">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    :indeterminate="someSelected && !allSelected"
                                    @change="toggleAll"
                                    class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500"
                                    :disabled="!selectableCount"
                                />
                            </th>
                            <th @click="toggleSort('keyword')" class="cursor-pointer px-4 py-2 text-left hover:text-neutral-700">
                                Keyword {{ getSortIcon('keyword') }}
                            </th>
                            <th @click="toggleSort('search_volume')" class="w-28 cursor-pointer px-4 py-2 text-right hover:text-neutral-700">
                                Volume {{ getSortIcon('search_volume') }}
                            </th>
                            <th @click="toggleSort('difficulty')" class="w-28 cursor-pointer px-4 py-2 text-center hover:text-neutral-700">
                                Difficulty {{ getSortIcon('difficulty') }}
                            </th>
                            <th class="w-24 px-4 py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="kw in sortedResults" :key="kw.keyword" class="hover:bg-neutral-50/50" :class="{ 'opacity-50': kw.in_project }">
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="selectedKeywords.has(kw.keyword)"
                                    @change="toggleKeyword(kw.keyword)"
                                    :disabled="kw.in_project"
                                    class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-500 disabled:opacity-50"
                                />
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ kw.keyword }}</td>
                            <td class="px-4 py-3 text-right text-sm text-neutral-500">{{ formatNumber(kw.search_volume) }}</td>
                            <td class="px-4 py-3 text-center">
                                <DifficultyBadge :label="getDifficultyLabel(kw.difficulty)" />
                            </td>
                            <td class="px-4 py-3 text-center text-xs">
                                <span v-if="kw.in_project" class="text-emerald-600">Added</span>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Actions -->
            <div v-if="results.length" class="border-t border-neutral-200 bg-neutral-50 px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-neutral-500">
                        {{ results.length }} results
                        <span v-if="selectedKeywords.size"> · {{ selectedKeywords.size }} selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="!selectableCount || adding"
                            @click="addAll"
                        >
                            {{ adding ? 'Adding...' : `Add All (${selectableCount})` }}
                        </Button>
                        <Button
                            size="sm"
                            :disabled="!selectedKeywords.size || adding"
                            @click="addSelected"
                        >
                            {{ adding ? 'Adding...' : `Add Selected (${selectedKeywords.size})` }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
