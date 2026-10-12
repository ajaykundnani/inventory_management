<script setup>
import { computed } from 'vue'
import { humanize } from '../utils/format'

const props = defineProps({
    status: { type: String, required: true },
})

const palette = {
    pending: 'text-bg-warning',
    processing: 'text-bg-primary',
    completed: 'text-bg-success',
    cancelled: 'text-bg-secondary',
    active: 'text-bg-success',
    inactive: 'text-bg-secondary',
    in_stock: 'text-bg-success',
    low_stock: 'text-bg-warning',
    out_of_stock: 'text-bg-danger',
}

const classes = computed(() => palette[props.status] ?? 'text-bg-light')
</script>

<template>
    <span class="badge rounded-pill" :class="classes">{{ humanize(status) }}</span>
</template>
