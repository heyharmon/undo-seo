<script setup>
import { computed } from 'vue'
import { useVModel } from '@vueuse/core'

const props = defineProps({
    defaultValue: [String, Number],
    modelValue: [String, Number],
    class: [String, Object, Array]
})

const emits = defineEmits(['update:modelValue'])

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue
})

const baseClasses =
    'flex h-10 w-full min-w-0 rounded-lg border border-neutral-200 bg-white px-4 text-sm text-neutral-900 placeholder:text-neutral-400 shadow-sm transition focus-visible:outline-none focus-visible:border-neutral-300 focus-visible:ring-2 focus-visible:ring-neutral-200 disabled:cursor-not-allowed disabled:opacity-60 file:inline-flex file:h-7 file:rounded-full file:border-0 file:bg-neutral-100 file:px-3 file:text-xs file:font-semibold file:text-neutral-700 aria-invalid:border-red-300 aria-invalid:ring-2 aria-invalid:ring-red-200'

const inputClasses = computed(() => [baseClasses, props.class])
</script>

<template>
    <input v-model="modelValue" data-slot="input" :class="inputClasses" />
</template>
