<script setup>
import { computed } from 'vue'

const props = defineProps({
    keyword: {
        type: Object,
        required: true
    },
    depth: {
        type: Number,
        default: 0
    },
    isExpanded: {
        type: Boolean,
        default: false
    },
    isSelected: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['select', 'toggle', 'add-child', 'delete'])

const hasChildren = computed(() => props.keyword.children?.length > 0)

const indentStyle = computed(() => ({
    paddingLeft: `${props.depth * 24 + 16}px`
}))

function handleAddChild(e) {
    e.stopPropagation()
    emit('add-child', props.keyword.id)
}

function handleDelete(e) {
    e.stopPropagation()
    emit('delete', props.keyword.id)
}

const statusColors = {
    active: 'bg-emerald-500',
    draft: 'bg-neutral-400',
    planned: 'bg-blue-500'
}

const intentBadgeClasses = {
    info: 'bg-neutral-100 text-neutral-700',
    commercial: 'bg-amber-50 text-amber-700',
    transactional: 'bg-purple-50 text-purple-700',
    navigational: 'bg-sky-50 text-sky-700'
}

function formatVolume(volume) {
    if (!volume) return '-'
    return new Intl.NumberFormat().format(volume)
}

function formatIntent(intent) {
    if (!intent) return '-'
    return intent.charAt(0).toUpperCase() + intent.slice(1)
}

function formatStatus(status) {
    if (!status) return '-'
    return status.charAt(0).toUpperCase() + status.slice(1)
}
</script>

<template>
    <tr 
        class="group border-b border-neutral-100 hover:bg-neutral-50 cursor-pointer transition-colors"
        :class="{ 'bg-neutral-50': isSelected }"
        @click="emit('select', keyword)"
    >
        <!-- Keyword Name -->
        <td class="py-3" :style="indentStyle">
            <div class="flex items-center gap-2">
                <!-- Expand/Collapse Toggle -->
                <button 
                    v-if="hasChildren"
                    @click.stop="emit('toggle', keyword.id)"
                    class="p-0.5 text-neutral-400 hover:text-neutral-600 transition"
                >
                    <!-- Down chevron when expanded -->
                    <svg 
                        v-if="isExpanded"
                        xmlns="http://www.w3.org/2000/svg" 
                        class="size-4"
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                    <!-- Right chevron when collapsed -->
                    <svg 
                        v-else
                        xmlns="http://www.w3.org/2000/svg" 
                        class="size-4"
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </button>
                <span v-else class="w-5"></span>
                
                <span class="font-medium text-neutral-900">{{ keyword.name }}</span>
            </div>
        </td>

        <!-- Volume -->
        <td class="py-3 text-right pr-4">
            <div class="flex items-center justify-end gap-1.5 text-sm text-neutral-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-neutral-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3v18h18" />
                    <path d="m19 9-5 5-4-4-3 3" />
                </svg>
                {{ formatVolume(keyword.volume) }}
            </div>
        </td>

        <!-- Intent -->
        <td class="py-3 text-center">
            <span 
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="intentBadgeClasses[keyword.intent] || intentBadgeClasses.info"
            >
                {{ formatIntent(keyword.intent) }}
            </span>
        </td>

        <!-- Status + Actions -->
        <td class="py-3 pr-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span 
                        class="size-2 rounded-full"
                        :class="statusColors[keyword.status] || statusColors.draft"
                    ></span>
                    <span class="text-sm text-neutral-600">{{ formatStatus(keyword.status) }}</span>
                </div>
                
                <!-- Hover Actions -->
                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <!-- Add Child -->
                    <button 
                        @click="handleAddChild"
                        class="p-1 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 rounded transition"
                        title="Add child keyword"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </button>
                    <!-- Delete -->
                    <button 
                        @click="handleDelete"
                        class="p-1 text-neutral-400 hover:text-red-600 hover:bg-red-50 rounded transition"
                        title="Delete keyword"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                        </svg>
                    </button>
                </div>
            </div>
        </td>
    </tr>
</template>

