<script setup>
import AdminNav from '@/components/globals/AdminNav.vue'
</script>

<template>
    <div class="min-h-screen bg-white">
        <AdminNav />

        <main class="max-w-[1563px] mx-auto px-4">
            <slot />
        </main>
    </div>
</template>
