<script setup>
import { ref, watch } from 'vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    parentId: {
        type: [Number, String],
        default: null
    }
})

const emit = defineEmits(['update:modelValue', 'create'])

const form = ref({
    name: '',
    volume: null,
    status: 'draft',
    intent: 'info',
    keyword_type: 'service',
    content_type: 'article',
    strategic_role: '',
    strategic_opportunity: ''
})

const saving = ref(false)

// Reset form when modal opens
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        form.value = {
            name: '',
            volume: null,
            status: 'draft',
            intent: 'info',
            keyword_type: 'service',
            content_type: 'article',
            strategic_role: '',
            strategic_opportunity: ''
        }
    }
})

const intents = [
    { value: 'info', label: 'Info' },
    { value: 'commercial', label: 'Commercial' },
    { value: 'transactional', label: 'Transactional' },
    { value: 'navigational', label: 'Navigational' }
]

const statuses = [
    { value: 'active', label: 'Active' },
    { value: 'draft', label: 'Draft' },
    { value: 'planned', label: 'Planned' }
]

const keywordTypes = [
    { value: 'product', label: 'Product' },
    { value: 'service', label: 'Service' },
    { value: 'benefit', label: 'Benefit' },
    { value: 'price', label: 'Price' },
    { value: 'competitor', label: 'Competitor' }
]

const contentTypes = [
    { value: 'pillar_page', label: 'Pillar Page' },
    { value: 'article', label: 'Article' },
    { value: 'tutorial', label: 'Tutorial' },
    { value: 'comparison', label: 'Comparison' },
    { value: 'landing_page', label: 'Landing Page' }
]

async function handleCreate() {
    if (!form.value.name.trim()) return
    
    saving.value = true
    try {
        emit('create', {
            ...form.value,
            parent_id: props.parentId
        })
        emit('update:modelValue', false)
    } finally {
        saving.value = false
    }
}

function close() {
    emit('update:modelValue', false)
}
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="close"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900">
                    {{ parentId ? 'Add Nested Keyword' : 'Add Pillar Keyword' }}
                </h2>
                <button @click="close" class="text-neutral-400 hover:text-neutral-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <div class="p-6 space-y-5">
                <!-- Keyword Name -->
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">Keyword *</label>
                    <Input v-model="form.name" placeholder="Enter keyword" />
                </div>

                <!-- Volume & Status -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Volume</label>
                        <Input v-model.number="form.volume" type="number" placeholder="0" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Status</label>
                        <select 
                            v-model="form.status"
                            class="w-full h-10 px-3 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200"
                        >
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Intent & Keyword Type -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Intent</label>
                        <select 
                            v-model="form.intent"
                            class="w-full h-10 px-3 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200"
                        >
                            <option v-for="i in intents" :key="i.value" :value="i.value">{{ i.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Keyword Type</label>
                        <select 
                            v-model="form.keyword_type"
                            class="w-full h-10 px-3 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200"
                        >
                            <option v-for="kt in keywordTypes" :key="kt.value" :value="kt.value">{{ kt.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Content Type -->
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">Content Type</label>
                    <select 
                        v-model="form.content_type"
                        class="w-full h-10 px-3 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200"
                    >
                        <option v-for="ct in contentTypes" :key="ct.value" :value="ct.value">{{ ct.label }}</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
                <Button variant="outline" @click="close">Cancel</Button>
                <Button @click="handleCreate" :disabled="!form.name.trim() || saving">
                    {{ saving ? 'Creating...' : 'Create Keyword' }}
                </Button>
            </div>
        </div>
    </div>
</template>

