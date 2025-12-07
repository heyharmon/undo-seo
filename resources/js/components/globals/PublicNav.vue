<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import auth from '@/services/auth'

const router = useRouter()
const isAuthenticated = computed(() => auth.isAuthenticated())
</script>

<template>
    <nav class="sticky top-0 z-40 border-b border-neutral-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 w-full items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex flex-1 items-center">
                <router-link to="/" class="text-lg font-semibold text-neutral-900">Generator Builder</router-link>
            </div>

            <div class="flex items-center gap-2">
                <router-link
                    to="/login"
                    class="inline-flex items-center rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                >
                    Login
                </router-link>
                <router-link
                    to="/register"
                    class="inline-flex items-center rounded-full border border-neutral-900 bg-neutral-900 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                >
                    Register
                </router-link>
            </div>
        </div>
    </nav>
</template>
