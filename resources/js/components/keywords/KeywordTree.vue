<script setup>
import KeywordRow from './KeywordRow.vue'

const props = defineProps({
    keywords: {
        type: Array,
        default: () => []
    },
    depth: {
        type: Number,
        default: 0
    },
    expandedIds: {
        type: Object,
        default: () => new Set()
    },
    selectedId: {
        type: [Number, String],
        default: null
    }
})

const emit = defineEmits(['select', 'toggle', 'add-child', 'delete'])

function isExpanded(id) {
    return props.expandedIds.has(id)
}

function isSelected(id) {
    return props.selectedId === id
}
</script>

<template>
    <template v-for="keyword in keywords" :key="keyword.id">
        <KeywordRow 
            :keyword="keyword"
            :depth="depth"
            :is-expanded="isExpanded(keyword.id)"
            :is-selected="isSelected(keyword.id)"
            @select="emit('select', $event)"
            @toggle="emit('toggle', $event)"
            @add-child="emit('add-child', $event)"
            @delete="emit('delete', $event)"
        />
        
        <!-- Recursively render children if expanded -->
        <KeywordTree 
            v-if="keyword.children?.length && isExpanded(keyword.id)"
            :keywords="keyword.children"
            :depth="depth + 1"
            :expanded-ids="expandedIds"
            :selected-id="selectedId"
            @select="emit('select', $event)"
            @toggle="emit('toggle', $event)"
            @add-child="emit('add-child', $event)"
            @delete="emit('delete', $event)"
        />
    </template>
</template>

