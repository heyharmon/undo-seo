<script setup>
import { ref, computed } from 'vue'
import DifficultyBadge from '@/components/ui/DifficultyBadge.vue'

const props = defineProps({
    cluster: {
        type: Object,
        default: null,
    },
    keywords: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
})

const sortKey = ref('search_volume')
const sortDir = ref('desc')

const sortedKeywords = computed(() => {
    if (!props.keywords.length) return []

    return [...props.keywords].sort((a, b) => {
        let aVal = a[sortKey.value]
        let bVal = b[sortKey.value]

        // Handle nulls
        if (aVal === null) aVal = 0
        if (bVal === null) bVal = 0

        if (sortDir.value === 'asc') {
            return aVal > bVal ? 1 : -1
        }
        return aVal < bVal ? 1 : -1
    })
})

const toggleSort = (key) => {
    if (sortKey.value === key) {
        // Cycle through: desc -> asc -> desc
        sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
    } else {
        sortKey.value = key
        sortDir.value = 'desc'
    }
}

const getSortIcon = (key) => {
    if (sortKey.value !== key) return null
    return sortDir.value === 'asc' ? '↑' : '↓'
}

const formatNumber = (num) => {
    if (num === null || num === undefined) return '—'
    return num.toLocaleString()
}
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Loading State -->
        <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-neutral-500">Loading keywords...</div>

        <!-- Empty State -->
        <div v-else-if="!cluster" class="flex flex-1 flex-col items-center justify-center text-neutral-500">
            <svg class="h-10 w-10 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
            </svg>
            <p class="mt-3 text-sm font-medium text-neutral-900">Select a cluster</p>
            <p class="mt-1 text-sm">Choose a cluster from the sidebar to view its keywords</p>
        </div>

        <!-- No Keywords State -->
        <div v-else-if="!keywords.length" class="flex flex-1 flex-col items-center justify-center text-neutral-500">
            <svg class="h-10 w-10 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="mt-3 text-sm font-medium text-neutral-900">No keywords yet</p>
            <p class="mt-1 text-sm">This cluster has no child keywords</p>
        </div>

        <!-- Table -->
        <div v-else class="flex-1 overflow-auto">
            <table class="w-full">
                <thead class="sticky top-0 bg-neutral-50 text-xs font-medium uppercase tracking-wider text-neutral-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Keyword</th>
                        <th @click="toggleSort('search_volume')" class="w-28 cursor-pointer px-4 py-2 text-right hover:text-neutral-700">
                            Volume {{ getSortIcon('search_volume') }}
                        </th>
                        <th @click="toggleSort('difficulty')" class="w-28 cursor-pointer px-4 py-2 text-center hover:text-neutral-700">
                            Difficulty {{ getSortIcon('difficulty') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="keyword in sortedKeywords" :key="keyword.id" class="hover:bg-neutral-50/50">
                        <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ keyword.keyword }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-500">{{ formatNumber(keyword.search_volume) }}</td>
                        <td class="px-4 py-3 text-center">
                            <DifficultyBadge :label="keyword.difficulty_label" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer with cluster parent info -->
        <div v-if="cluster" class="border-t border-neutral-200 bg-neutral-50 px-4 py-2">
            <div class="flex items-center justify-between text-xs text-neutral-500">
                <span>
                    <span class="font-medium text-neutral-700">{{ cluster.keyword }}</span>
                    · {{ formatNumber(cluster.search_volume) }} volume
                </span>
                <span>{{ keywords.length }} keywords</span>
            </div>
        </div>
    </div>
</template>
