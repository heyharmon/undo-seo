<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKeywordsStore } from '@/stores/keywords'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import StatsBar from '@/components/keywords/StatsBar.vue'
import FilterBar from '@/components/keywords/FilterBar.vue'
import KeywordTree from '@/components/keywords/KeywordTree.vue'
import KeywordDetailPanel from '@/components/keywords/KeywordDetailPanel.vue'
import AddKeywordModal from '@/components/keywords/AddKeywordModal.vue'

const route = useRoute()
const router = useRouter()
const store = useKeywordsStore()

const showAddModal = ref(false)
const addParentId = ref(null)
const showDetailPanel = ref(false)

const projectId = computed(() => route.params.id)

onMounted(async () => {
    await store.loadProject(projectId.value)
})

onUnmounted(() => {
    store.reset()
})

function handleSelectKeyword(keyword) {
    store.selectKeyword(keyword)
    showDetailPanel.value = true
}

function handleToggle(id) {
    store.toggleExpanded(id)
}

function handleFilterSearch(value) {
    store.setFilter('search', value)
}

function handleFilterIntent(value) {
    store.setFilter('intent', value)
}

function handleFilterStatus(value) {
    store.setFilter('status', value)
}

function openAddModal(parentId = null) {
    addParentId.value = parentId
    showAddModal.value = true
}

async function handleCreateKeyword(data) {
    await store.createKeyword(data)
    showAddModal.value = false
}

async function handleSaveKeyword(data) {
    if (store.selectedKeyword) {
        await store.updateKeyword(store.selectedKeyword.id, data)
    }
}

async function handleDeleteKeyword(id) {
    await store.deleteKeyword(id)
    showDetailPanel.value = false
}

function handleAddChild(parentId) {
    openAddModal(parentId)
}

async function handleDeleteFromRow(id) {
    if (confirm('Delete this keyword? This will also delete all nested keywords.')) {
        await store.deleteKeyword(id)
    }
}

function handleClosePanel() {
    store.clearSelection()
    showDetailPanel.value = false
}

function goBack() {
    router.push({ name: 'projects.index' })
}
</script>

<template>
    <DefaultLayout>
        <div class="py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <button 
                        @click="goBack"
                        class="p-2 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 rounded-lg transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                    </button>
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-neutral-100 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-neutral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m16 6 4 14" />
                                    <path d="M12 6v14" />
                                    <path d="M8 8v12" />
                                    <path d="M4 4v16" />
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold text-neutral-900">Keyword Clusters</h1>
                                <p class="text-sm text-neutral-500">{{ store.project?.name || 'Loading...' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <Button @click="openAddModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    Add Pillar
                </Button>
            </div>

            <!-- Loading -->
            <div v-if="store.loading" class="text-center py-12 text-neutral-500">
                Loading keywords...
            </div>

            <template v-else>
                <!-- Stats Bar -->
                <StatsBar :stats="store.stats" class="mb-6" />

                <!-- Filter Bar -->
                <FilterBar 
                    :filters="store.filters"
                    class="mb-4"
                    @update:search="handleFilterSearch"
                    @update:intent="handleFilterIntent"
                    @update:status="handleFilterStatus"
                    @expand-all="store.expandAll()"
                    @collapse-all="store.collapseAll()"
                />

                <!-- Keywords Table -->
                <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-neutral-50 border-b border-neutral-200">
                            <tr class="text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                <th class="text-left py-3 px-4">Keyword</th>
                                <th class="text-right py-3 px-4 w-32">Volume</th>
                                <th class="text-center py-3 px-4 w-32">Intent</th>
                                <th class="text-left py-3 px-4 w-32">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <KeywordTree 
                                v-if="store.keywords.length"
                                :keywords="store.keywords"
                                :expanded-ids="store.expandedIds"
                                :selected-id="store.selectedKeyword?.id"
                                @select="handleSelectKeyword"
                                @toggle="handleToggle"
                                @add-child="handleAddChild"
                                @delete="handleDeleteFromRow"
                            />
                            <tr v-else>
                                <td colspan="4" class="py-12 text-center text-neutral-500">
                                    <div class="flex flex-col items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-10 text-neutral-300 mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8" />
                                            <path d="m21 21-4.3-4.3" />
                                        </svg>
                                        <p class="font-medium text-neutral-700 mb-1">No keywords yet</p>
                                        <p class="text-sm">Add your first pillar keyword to get started</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Help Text -->
                <p class="text-center text-sm text-neutral-400 mt-4">
                    Click any row to view details
                </p>
            </template>
        </div>

        <!-- Detail Panel -->
        <KeywordDetailPanel 
            v-model="showDetailPanel"
            :keyword="store.selectedKeyword"
            @save="handleSaveKeyword"
            @delete="handleDeleteKeyword"
            @close="handleClosePanel"
        />

        <!-- Add Keyword Modal -->
        <AddKeywordModal 
            v-model="showAddModal"
            :parent-id="addParentId"
            @create="handleCreateKeyword"
        />
    </DefaultLayout>
</template>

