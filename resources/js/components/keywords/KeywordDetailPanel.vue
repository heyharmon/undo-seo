<script setup>
import { ref, watch, computed } from 'vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import RightDrawer from '@/components/ui/RightDrawer.vue'

const props = defineProps({
    keyword: {
        type: Object,
        default: null
    },
    modelValue: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'save', 'delete', 'close'])

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const form = ref({
    name: '',
    volume: null,
    status: 'draft',
    intent: 'info',
    keyword_type: 'service',
    content_type: 'article',
    strategic_role: '',
    strategic_opportunity: '',
    competitors: []
})

const saving = ref(false)

// Watch for keyword changes and populate form
watch(() => props.keyword, (keyword) => {
    if (keyword) {
        form.value = {
            name: keyword.name || '',
            volume: keyword.volume || null,
            status: keyword.status || 'draft',
            intent: keyword.intent || 'info',
            keyword_type: keyword.keyword_type || 'service',
            content_type: keyword.content_type || 'article',
            strategic_role: keyword.strategic_role || '',
            strategic_opportunity: keyword.strategic_opportunity || '',
            competitors: keyword.competitors?.map(c => ({ ...c })) || []
        }
    }
}, { immediate: true })

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

function addCompetitor() {
    const nextRank = form.value.competitors.length + 1
    form.value.competitors.push({
        name: '',
        url: '',
        rank: nextRank
    })
}

function removeCompetitor(index) {
    form.value.competitors.splice(index, 1)
    // Re-rank remaining competitors
    form.value.competitors.forEach((c, i) => {
        c.rank = i + 1
    })
}

async function handleSave() {
    saving.value = true
    try {
        emit('save', { ...form.value })
    } finally {
        saving.value = false
    }
}

function handleClose() {
    emit('close')
    isOpen.value = false
}

function handleDelete() {
    if (confirm('Delete this keyword? This will also delete all nested keywords.')) {
        emit('delete', props.keyword.id)
    }
}
</script>

<template>
    <RightDrawer 
        v-model="isOpen" 
        title="Keyword Details"
        width-class="w-full sm:w-[28rem]"
        @close="handleClose"
    >
        <div v-if="keyword" class="p-4 space-y-6">
            <!-- Keyword Name -->
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1.5">Keyword</label>
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

            <hr class="border-neutral-200" />

            <!-- Strategic Role -->
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1.5">Strategic Role</label>
                <textarea 
                    v-model="form.strategic_role"
                    rows="3"
                    placeholder="Describe the strategic role of this keyword..."
                    class="w-full px-3 py-2 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200 resize-none"
                ></textarea>
            </div>

            <!-- Strategic Opportunity -->
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1.5">Strategic Opportunity</label>
                <textarea 
                    v-model="form.strategic_opportunity"
                    rows="3"
                    placeholder="Describe the strategic opportunity..."
                    class="w-full px-3 py-2 rounded-lg border border-neutral-200 bg-white text-sm text-neutral-900 shadow-sm focus:outline-none focus:border-neutral-300 focus:ring-2 focus:ring-neutral-200 resize-none"
                ></textarea>
            </div>

            <hr class="border-neutral-200" />

            <!-- Competitors -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="text-sm font-medium text-neutral-700">Competitors</label>
                    <span class="text-xs text-neutral-500">{{ form.competitors.length }} ranked</span>
                </div>
                
                <div class="space-y-3">
                    <div 
                        v-for="(competitor, index) in form.competitors" 
                        :key="index"
                        class="flex items-start gap-3 p-3 bg-neutral-50 rounded-lg border border-neutral-100"
                    >
                        <div class="flex items-center justify-center size-8 rounded border border-neutral-200 bg-white text-sm font-medium text-neutral-600 shrink-0">
                            {{ competitor.rank }}
                        </div>
                        <div class="flex-1 space-y-2">
                            <Input 
                                v-model="competitor.name" 
                                placeholder="Competitor name"
                                class="h-8 text-sm"
                            />
                            <Input 
                                v-model="competitor.url" 
                                placeholder="https://..."
                                class="h-8 text-sm"
                            />
                        </div>
                        <button 
                            @click="removeCompetitor(index)"
                            class="p-1 text-neutral-400 hover:text-red-500 transition"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button 
                    @click="addCompetitor"
                    class="mt-3 w-full py-2 border border-dashed border-neutral-300 rounded-lg text-sm text-neutral-600 hover:border-neutral-400 hover:text-neutral-900 transition"
                >
                    + Add Competitor
                </button>
            </div>

            <!-- Actions -->
            <div class="pt-4 border-t border-neutral-200 flex items-center gap-3">
                <Button variant="outline" class="flex-1" @click="handleClose">Cancel</Button>
                <Button class="flex-1" @click="handleSave" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>

            <!-- Delete -->
            <button 
                @click="handleDelete"
                class="w-full py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition"
            >
                Delete Keyword
            </button>
        </div>
    </RightDrawer>
</template>

