import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import projectsService from '@/services/projects'

export const useKeywordsStore = defineStore('keywords', () => {
    // State
    const project = ref(null)
    const keywords = ref([])
    const stats = ref(null)
    const selectedKeyword = ref(null)
    const filters = ref({
        search: '',
        intent: '',
        status: ''
    })
    const loading = ref(false)
    const expandedIds = ref(new Set())

    // Getters
    const hasFilters = computed(() => {
        return filters.value.search || filters.value.intent || filters.value.status
    })

    // Actions
    async function loadProject(id) {
        loading.value = true
        try {
            project.value = await projectsService.getProject(id)
            await Promise.all([loadKeywords(), loadStats()])
        } finally {
            loading.value = false
        }
    }

    async function loadKeywords() {
        if (!project.value) return
        
        const filterParams = {}
        if (filters.value.status) filterParams.status = filters.value.status
        if (filters.value.intent) filterParams.intent = filters.value.intent
        if (filters.value.search) filterParams.search = filters.value.search
        
        keywords.value = await projectsService.getKeywords(project.value.id, filterParams)
    }

    async function loadStats() {
        if (!project.value) return
        stats.value = await projectsService.getProjectStats(project.value.id)
    }

    async function createKeyword(data) {
        if (!project.value) return
        const keyword = await projectsService.createKeyword(project.value.id, data)
        await loadKeywords()
        await loadStats()
        return keyword
    }

    async function updateKeyword(id, data) {
        const keyword = await projectsService.updateKeyword(id, data)
        await loadKeywords()
        await loadStats()
        if (selectedKeyword.value?.id === id) {
            selectedKeyword.value = keyword
        }
        return keyword
    }

    async function deleteKeyword(id) {
        await projectsService.deleteKeyword(id)
        if (selectedKeyword.value?.id === id) {
            selectedKeyword.value = null
        }
        await loadKeywords()
        await loadStats()
    }

    async function moveKeyword(id, parentId, position) {
        await projectsService.moveKeyword(id, { parent_id: parentId, position })
        await loadKeywords()
    }

    async function reorderKeywords(keywordsToReorder) {
        await projectsService.reorderKeywords(keywordsToReorder)
        await loadKeywords()
    }

    function selectKeyword(keyword) {
        selectedKeyword.value = keyword
    }

    function clearSelection() {
        selectedKeyword.value = null
    }

    function toggleExpanded(id) {
        if (expandedIds.value.has(id)) {
            expandedIds.value.delete(id)
        } else {
            expandedIds.value.add(id)
        }
    }

    function expandAll() {
        const collectIds = (items) => {
            items.forEach(item => {
                if (item.children?.length) {
                    expandedIds.value.add(item.id)
                    collectIds(item.children)
                }
            })
        }
        collectIds(keywords.value)
    }

    function collapseAll() {
        expandedIds.value.clear()
    }

    function isExpanded(id) {
        return expandedIds.value.has(id)
    }

    function setFilter(key, value) {
        filters.value[key] = value
        loadKeywords()
    }

    function clearFilters() {
        filters.value = { search: '', intent: '', status: '' }
        loadKeywords()
    }

    function reset() {
        project.value = null
        keywords.value = []
        stats.value = null
        selectedKeyword.value = null
        filters.value = { search: '', intent: '', status: '' }
        expandedIds.value.clear()
    }

    return {
        // State
        project,
        keywords,
        stats,
        selectedKeyword,
        filters,
        loading,
        expandedIds,
        // Getters
        hasFilters,
        // Actions
        loadProject,
        loadKeywords,
        loadStats,
        createKeyword,
        updateKeyword,
        deleteKeyword,
        moveKeyword,
        reorderKeywords,
        selectKeyword,
        clearSelection,
        toggleExpanded,
        expandAll,
        collapseAll,
        isExpanded,
        setFilter,
        clearFilters,
        reset
    }
})

