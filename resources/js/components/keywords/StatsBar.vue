<script setup>
defineProps({
    stats: {
        type: Object,
        default: () => ({
            pillar_count: 0,
            total_keywords: 0,
            combined_volume: 0,
            status_breakdown: { active: 0, draft: 0, planned: 0 }
        })
    }
})

function formatNumber(num) {
    if (!num) return '0'
    return new Intl.NumberFormat().format(num)
}
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pillars -->
        <div class="bg-white border border-neutral-200 rounded-xl p-4">
            <div class="text-2xl font-semibold text-neutral-900">{{ stats?.pillar_count || 0 }}</div>
            <div class="text-sm text-neutral-500">Pillars</div>
        </div>

        <!-- Total Keywords -->
        <div class="bg-white border border-neutral-200 rounded-xl p-4">
            <div class="text-2xl font-semibold text-neutral-900">{{ stats?.total_keywords || 0 }}</div>
            <div class="text-sm text-neutral-500">Total Keywords</div>
        </div>

        <!-- Combined Volume -->
        <div class="bg-white border border-neutral-200 rounded-xl p-4">
            <div class="text-2xl font-semibold text-neutral-900">{{ formatNumber(stats?.combined_volume) }}</div>
            <div class="text-sm text-neutral-500">Combined Volume</div>
        </div>

        <!-- Status Breakdown -->
        <div class="bg-white border border-neutral-200 rounded-xl p-4">
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <span class="font-medium text-neutral-900">{{ stats?.status_breakdown?.active || 0 }}</span>
                    <span class="text-neutral-500">Active</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-neutral-400"></span>
                    <span class="font-medium text-neutral-900">{{ stats?.status_breakdown?.draft || 0 }}</span>
                    <span class="text-neutral-500">Draft</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-blue-500"></span>
                    <span class="font-medium text-neutral-900">{{ stats?.status_breakdown?.planned || 0 }}</span>
                    <span class="text-neutral-500">Planned</span>
                </div>
            </div>
        </div>
    </div>
</template>

