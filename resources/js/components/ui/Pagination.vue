<script setup>
import Button from '@/components/ui/Button.vue'

const props = defineProps({
    pagination: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['page-change', 'per-page-change'])

const handlePageChange = (page) => {
    emit('page-change', page)
}

const handlePerPageChange = (event) => {
    const newPerPage = parseInt(event.target.value)
    emit('per-page-change', newPerPage)
}

const perPageOptions = [25, 50, 100, 200]
</script>

<template>
    <div v-if="pagination.last_page > 1" class="py-3 flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden gap-1.5">
            <Button @click="handlePageChange(pagination.current_page - 1)" :disabled="pagination.current_page === 1" variant="outline"> Previous </Button>
            <Button @click="handlePageChange(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" variant="outline">
                Next
            </Button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <p class="text-sm text-neutral-700">
                    Showing
                    <span class="font-medium">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span>
                    to
                    <span class="font-medium">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                    of
                    <span class="font-medium">{{ pagination.total }}</span>
                    results
                </p>
                <div class="flex items-center gap-2">
                    <label for="per-page-select" class="text-sm text-neutral-600">Per page:</label>
                    <select
                        id="per-page-select"
                        :value="pagination.per_page"
                        @change="handlePerPageChange"
                        class="rounded-md border border-neutral-200 bg-white px-2 py-1 text-sm text-neutral-700 shadow-sm transition hover:border-neutral-300 focus:border-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                    >
                        <option v-for="option in perPageOptions" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                </div>
            </div>
            <div>
                <nav class="relative z-0 inline-flex gap-1.5">
                    <Button @click="handlePageChange(pagination.current_page - 1)" :disabled="pagination.current_page === 1" variant="outline" size="sm">
                        Previous
                    </Button>
                    <Button
                        v-for="page in Math.min(5, pagination.last_page)"
                        :key="page"
                        @click="handlePageChange(page)"
                        :variant="page === pagination.current_page ? 'default' : 'outline'"
                        size="sm"
                    >
                        {{ page }}
                    </Button>
                    <Button
                        @click="handlePageChange(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        variant="outline"
                        size="sm"
                    >
                        Next
                    </Button>
                </nav>
            </div>
        </div>
    </div>
</template>
