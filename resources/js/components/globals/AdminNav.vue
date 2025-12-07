<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import auth from '@/services/auth'

const router = useRouter()
const route = useRoute()
const isAuthenticated = computed(() => auth.isAuthenticated())
const user = computed(() => auth.getUser())
const isAdmin = computed(() => auth.isAdmin())
const navLinkClasses = 'rounded-full px-3 py-1 transition hover:bg-neutral-100 hover:text-neutral-900'
const activeNavClasses = 'bg-neutral-900 text-white hover:bg-neutral-900 hover:text-white'
const mobileMenuOpen = ref(false)
const adminDropdownOpen = ref(false)
const mobileAdminOpen = ref(false)

const logout = async () => {
    await auth.logout()
    mobileMenuOpen.value = false
    router.push('/login')
}

const isRouteActive = (target) => {
    if (!target) {
        return false
    }

    if (typeof target === 'string') {
        if (target.startsWith('/')) {
            return route.path === target
        }

        return route.name === target
    }

    if (typeof target === 'object') {
        if (target.name) {
            return route.name === target.name
        }

        if (target.path) {
            return route.path === target.path
        }
    }

    return false
}

watch(
    () => route.fullPath,
    () => {
        mobileMenuOpen.value = false
    }
)

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value
}

const closeMobileMenu = () => {
    mobileMenuOpen.value = false
}

const toggleAdminDropdown = () => {
    adminDropdownOpen.value = !adminDropdownOpen.value
}

const closeAdminDropdown = () => {
    adminDropdownOpen.value = false
}

const toggleMobileAdmin = () => {
    mobileAdminOpen.value = !mobileAdminOpen.value
}
</script>

<template>
    <nav class="sticky top-0 z-40 border-b border-neutral-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 w-full items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex flex-1 items-center gap-6">
                <router-link to="/" class="text-lg font-semibold text-neutral-900">Generator Builder</router-link>

                <div v-if="isAuthenticated" class="hidden items-center gap-1 text-sm font-medium text-neutral-500 md:flex">
                    <router-link v-if="isAdmin" :to="{ name: 'dashboard' }" :class="[navLinkClasses, { [activeNavClasses]: isRouteActive('dashboard') }]">
                        Dashboard
                    </router-link>

                </div>
                <button
                    v-if="isAuthenticated"
                    type="button"
                    class="ml-auto inline-flex items-center justify-center rounded-full border border-transparent p-2 text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 focus-visible:outline-offset-2 focus-visible:outline-neutral-400 md:hidden"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="app-nav-mobile"
                    @click="toggleMobileMenu"
                >
                    <span class="sr-only">Toggle navigation</span>
                    <svg v-if="!mobileMenuOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="1.8" d="M6 6l12 12M6 18L18 6" />
                    </svg>
                </button>
            </div>

            <div v-if="!isAuthenticated" class="flex items-center gap-2 md:hidden">
                <router-link
                    to="/login"
                    class="inline-flex items-center rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                >
                    Login
                </router-link>
                <router-link
                    to="/register"
                    class="inline-flex items-center rounded-full border border-neutral-900 bg-neutral-900 px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                >
                    Register
                </router-link>
            </div>

            <div class="hidden items-center gap-2 md:flex">
                <template v-if="isAuthenticated">
                    <div v-if="isAdmin" class="relative" @keydown.escape="closeAdminDropdown">
                        <button
                            type="button"
                            @click="toggleAdminDropdown"
                            :aria-expanded="adminDropdownOpen"
                            :class="[navLinkClasses, 'inline-flex items-center gap-1 text-sm font-medium text-neutral-500']"
                        >
                            Admin
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9l6 6 6-6" />
                            </svg>
                        </button>
                        <div v-if="adminDropdownOpen" class="absolute right-0 top-full mt-2 w-48 rounded-lg border border-neutral-200 bg-white py-1 shadow-lg">
                            <router-link
                                :to="{ name: 'users.index' }"
                                @click="closeAdminDropdown"
                                class="block px-4 py-2 text-sm text-neutral-700 transition hover:bg-neutral-100"
                            >
                                Users
                            </router-link>
                        </div>
                    </div>

                    <button
                        @click="logout"
                        class="inline-flex items-center rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-100"
                    >
                        Logout
                    </button>
                </template>
                <template v-else>
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
                </template>
            </div>
        </div>

        <transition name="fade">
            <div v-if="mobileMenuOpen && isAuthenticated" id="app-nav-mobile" class="md:hidden">
                <div class="border-t border-neutral-200 bg-white/95 pb-6 pt-4 shadow-sm">
                    <div class="mx-auto flex w-full max-w-[1563px] flex-col gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="space-y-2 text-sm font-medium text-neutral-600">
                            <router-link
                                v-if="isAdmin"
                                :to="{ name: 'dashboard' }"
                                :class="[
                                    'block rounded-xl px-3 py-2 transition hover:bg-neutral-100 hover:text-neutral-900',
                                    { 'bg-neutral-900 text-white hover:bg-neutral-900 hover:text-white': isRouteActive('dashboard') }
                                ]"
                                @click="closeMobileMenu"
                            >
                                Dashboard
                            </router-link>
                        </div>

                        <div v-if="isAdmin" class="border-t border-neutral-200"></div>

                        <div v-if="isAdmin" class="space-y-2">
                            <button
                                type="button"
                                @click="toggleMobileAdmin"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900"
                            >
                                <span>Admin</span>
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 transition-transform"
                                    :class="{ 'rotate-180': mobileAdminOpen }"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9l6 6 6-6" />
                                </svg>
                            </button>
                            <div v-if="mobileAdminOpen" class="space-y-2 pl-3">
                                <router-link
                                    :to="{ name: 'users.index' }"
                                    :class="[
                                        'block rounded-xl px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900',
                                        { 'bg-neutral-900 text-white hover:bg-neutral-900 hover:text-white': isRouteActive('users.index') }
                                    ]"
                                    @click="closeMobileMenu"
                                >
                                    Users
                                </router-link>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-neutral-200 bg-neutral-50/80 p-4">
                            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-400">Signed in as</div>
                            <div class="text-sm font-medium text-neutral-800">{{ user?.name }}</div>
                            <button
                                type="button"
                                class="mt-4 inline-flex w-full items-center justify-center rounded-full border border-neutral-900 bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-neutral-800"
                                @click="logout"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </nav>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
