<script setup>
import { computed, watch, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  widthClass: { type: String, default: 'w-full sm:w-[32rem]' },
  closeOnEsc: { type: Boolean, default: true },
  closeOnBackdrop: { type: Boolean, default: true }
})

const emit = defineEmits(['update:modelValue', 'close'])

const isOpen = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const onKeydown = (e) => {
  if (props.closeOnEsc && e.key === 'Escape' && isOpen.value) {
    close()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
})

const close = () => {
  isOpen.value = false
  emit('close')
}
</script>

<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-40">
      <!-- Backdrop -->
      <div
        class="absolute inset-0 bg-black/30"
        @click="closeOnBackdrop ? close() : null"
      ></div>

      <!-- Panel -->
      <div class="absolute inset-y-0 right-0 flex max-w-full">
        <transition name="slide">
          <div
            v-if="isOpen"
            class="h-full bg-white shadow-xl border-l border-neutral-200 flex flex-col"
            :class="widthClass"
          >
            <div class="px-4 py-3 border-b border-neutral-200 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-neutral-900 truncate">{{ title }}</h3>
              <button class="text-neutral-500 hover:text-neutral-700" @click="close" aria-label="Close panel">✕</button>
            </div>
            <div class="flex-1 overflow-y-auto">
              <slot />
            </div>
          </div>
        </transition>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 150ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform 200ms ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>

