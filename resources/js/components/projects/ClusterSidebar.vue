<script setup>
import { ref, computed, watch } from 'vue'
import DifficultyBadge from '@/components/ui/DifficultyBadge.vue'

const props = defineProps({
    clusters: {
        type: Array,
        default: () => [],
    },
    selectedClusterId: {
        type: Number,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['select'])

const searchQuery = ref('')
const expandedClusters = ref(new Set())

// Filter clusters by search query
const filteredClusters = computed(() => {
    if (!searchQuery.value.trim()) return props.clusters

    const query = searchQuery.value.toLowerCase()
    return props.clusters.filter((cluster) => cluster.keyword.toLowerCase().includes(query))
})

const toggleExpand = (clusterId, event) => {
    event.stopPropagation()
    if (expandedClusters.value.has(clusterId)) {
        expandedClusters.value.delete(clusterId)
    } else {
        expandedClusters.value.add(clusterId)
    }
}

const selectCluster = (cluster) => {
    emit('select', cluster)
}

const isSelected = (clusterId) => {
    return props.selectedClusterId === clusterId
}

const isExpanded = (clusterId) => {
    return expandedClusters.value.has(clusterId)
}
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Search -->
        <div class="p-2">
            <div class="relative">
                <svg
                    class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search clusters..."
                    class="w-full rounded-md border border-neutral-200 bg-white py-1.5 pl-8 pr-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-neutral-300 focus:outline-none focus:ring-1 focus:ring-neutral-200"
                />
            </div>
        </div>

        <!-- Cluster List -->
        <div class="flex-1 overflow-y-auto p-0.5">
            <div v-if="loading" class="p-4 text-center text-sm text-neutral-500">Loading clusters...</div>

            <div v-else-if="!filteredClusters.length" class="p-4 text-center text-sm text-neutral-500">
                {{ searchQuery ? 'No clusters match your search' : 'No clusters yet' }}
            </div>

            <div v-else class="space-y-0.5">
                <div
                    v-for="cluster in filteredClusters"
                    :key="cluster.id"
                    @click="selectCluster(cluster)"
                    :class="[
                        'group flex cursor-pointer items-center gap-1 rounded-md px-2 py-1.5 transition',
                        isSelected(cluster.id) ? 'bg-neutral-900 text-white' : 'hover:bg-neutral-100',
                    ]"
                >
                    <!-- Expand button (only if has children) -->
                    <button
                        v-if="cluster.children_count > 0"
                        @click="toggleExpand(cluster.id, $event)"
                        :class="['flex h-5 w-5 flex-shrink-0 items-center justify-center rounded transition', isSelected(cluster.id) ? 'hover:bg-white/10' : 'hover:bg-neutral-200']"
                    >
                        <svg
                            :class="['h-3.5 w-3.5 transition-transform', isExpanded(cluster.id) ? 'rotate-90' : '']"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div v-else class="w-5 flex-shrink-0"></div>

                    <!-- Cluster name and count -->
                    <span :class="['flex-1 truncate text-sm', isSelected(cluster.id) ? 'font-medium' : '']">
                        {{ cluster.keyword }}
                    </span>
                    <span :class="['text-xs', isSelected(cluster.id) ? 'text-white/70' : 'text-neutral-400']"> ({{ cluster.children_count }}) </span>

                    <!-- Difficulty dot -->
                    <DifficultyBadge :label="cluster.difficulty_label" show-dot />
                </div>
            </div>
        </div>
    </div>
</template>
