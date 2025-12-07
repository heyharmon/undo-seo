<script setup>
import AdminNav from '@/components/globals/AdminNav.vue'

defineProps({
    sidebarWidth: {
        type: String,
        default: 'w-72 xl:w-90'
    }
})
</script>

<template>
    <div class="flex min-h-screen flex-col bg-neutral-50">
        <AdminNav />

        <div class="mx-auto flex w-full flex-1 min-h-0 flex-col overflow-hidden lg:flex-row">
            <aside
                v-if="$slots.sidebar"
                :class="['hidden h-[(100vh - 1rem)] min-h-0 flex-shrink-0 border-r border-neutral-200  pl-8 pr-4 py-6 lg:block', sidebarWidth]"
            >
                <slot name="sidebar" />
            </aside>

            <main class="flex-1 min-h-0 overflow-y-auto bg-white">
                <slot />
            </main>
        </div>
    </div>
</template>
