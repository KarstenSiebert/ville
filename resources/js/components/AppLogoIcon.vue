<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" :class="className" v-bind="$attrs">
        <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"
            d="M12 36 24 12 36 36 H28 L24 28 L20 36 H12 Z" />

        <circle cx="24" cy="24" r="22" stroke="currentColor" fill-rule="evenodd" clip-rule="evenodd" stroke-width="2"
            fill="none" />
    </svg>

</template>
