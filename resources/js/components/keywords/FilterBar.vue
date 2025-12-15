<script setup>
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import Input from '@/components/ui/Input.vue'

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ search: '', intent: '', status: '' })
    }
})

const emit = defineEmits(['update:search', 'update:intent', 'update:status', 'expand-all', 'collapse-all'])

const searchInput = ref(props.filters.search)

const debouncedSearch = useDebounceFn((value) => {
    emit('update:search', value)
}, 300)

watch(searchInput, (value) => {
    debouncedSearch(value)
})

watch(() => props.filters.search, (value) => {
    searchInput.value = value
})

const intents = [
    { value: '', label: 'All Intents' },
    { value: 'info', label: 'Info' },
    { value: 'commercial', label: 'Commercial' },
    { value: 'transactional', label: 'Transactional' },
    { value: 'navigational', label: 'Navigational' }
]

const statuses = [
    { value: '', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'draft', label: 'Draft' },
    { value: 'planned', label: 'Planned' }
]
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px] max-w-md">
            <svg 
                xmlns="http://www.w3.org/2000/svg" 
                class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-neutral-400"
                viewBox="0 0 24 24" 
                fill="none" 
                stroke="currentColor" 
                stroke-width="2" 
                stroke-linecap="round" 
                stroke-linejoin="round"
            >
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
            </svg>
            <Input 
                v-model="searchInput"
                placeholder="Search keywords..."
                class="pl-9"
            />
        </div>

        <!-- Intent Filter -->
        <select 
            :value="filters.intent"
            @change="emit('update:intent', $event.target.value)"
            class="h-10 px-3 pr-8 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200"
        >
            <option v-for="intent in intents" :key="intent.value" :value="intent.value">
                {{ intent.label }}
            </option>
        </select>

        <!-- Status Filter -->
        <select 
            :value="filters.status"
            @change="emit('update:status', $event.target.value)"
            class="h-10 px-3 pr-8 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200"
        >
            <option v-for="status in statuses" :key="status.value" :value="status.value">
                {{ status.label }}
            </option>
        </select>

        <!-- Expand/Collapse -->
        <div class="flex items-center gap-1 ml-auto">
            <button 
                @click="emit('expand-all')"
                class="px-3 py-2 text-sm text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition"
            >
                Expand
            </button>
            <button 
                @click="emit('collapse-all')"
                class="px-3 py-2 text-sm text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition"
            >
                Collapse
            </button>
        </div>
    </div>
</template>

