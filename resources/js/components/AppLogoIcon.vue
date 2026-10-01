<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- public/logo.svg と同じ図形。色はテーマに合わせず固定 -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 100 100"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <g
            transform="rotate(-45 50 50)"
            stroke="#EC8FE3"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <rect x="19" y="19" width="62" height="62" rx="8" />
            <polyline points="32,68 32,32 50,59.9 68,32 68,68" />
        </g>
    </svg>
</template>
