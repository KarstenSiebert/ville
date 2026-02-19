<script setup lang="ts">
import { ref, watch, onMounted } from "vue"

const props = defineProps<{
    type: "success" | "error"
    message?: string
    duration?: number
}>()

const visible = ref(false)

onMounted(() => {
    if (props.message) {
        show()
    }
})

watch(
    () => props.message,
    (newVal) => {
        if (newVal) {
            show()
        }
    }
)

function show() {
    visible.value = true
    setTimeout(() => {
        visible.value = false
    }, props.duration ?? 4000)
}
</script>

<template>
    <transition enter-active-class="transition ease-out duration-500" enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-500"
        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="visible && message" class="mb-4 px-4 py-2 rounded-lg border shadow" :class="{
            'bg-green-100 border-green-300 text-green-800': type === 'success',
            'bg-red-100 border-red-300 text-red-800': type === 'error',
        }">
            {{ $t(message) }}
        </div>
    </transition>
</template>
