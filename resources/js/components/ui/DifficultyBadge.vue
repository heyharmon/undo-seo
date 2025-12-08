<script setup>
import { computed } from 'vue'

const props = defineProps({
    difficulty: {
        type: Number,
        default: null,
    },
    label: {
        type: String,
        default: null,
    },
    showDot: {
        type: Boolean,
        default: false,
    },
})

const difficultyLabel = computed(() => {
    if (props.label) return props.label
    if (props.difficulty === null) return null
    if (props.difficulty < 30) return 'Easy'
    if (props.difficulty < 60) return 'Doable'
    return 'Hard'
})

const badgeClasses = computed(() => {
    const label = difficultyLabel.value
    if (label === 'Easy') return 'bg-emerald-100 text-emerald-700'
    if (label === 'Doable') return 'bg-amber-100 text-amber-700'
    if (label === 'Hard') return 'bg-red-100 text-red-700'
    return 'bg-neutral-100 text-neutral-600'
})

const dotClasses = computed(() => {
    const label = difficultyLabel.value
    if (label === 'Easy') return 'bg-emerald-500'
    if (label === 'Doable') return 'bg-amber-500'
    if (label === 'Hard') return 'bg-red-500'
    return 'bg-neutral-400'
})
</script>

<template>
    <span v-if="showDot" :class="['inline-block h-1.5 w-1.5 rounded-full', dotClasses]"></span>
    <span v-else-if="difficultyLabel" :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', badgeClasses]">
        {{ difficultyLabel }}
    </span>
</template>
