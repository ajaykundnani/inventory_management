<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: '' },
})
</script>

<template>
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div class="min-w-0">
            <h1 class="h3 mb-1 text-break">
                <i v-if="icon" class="bi me-2 text-secondary" :class="icon"></i>{{ title }}
            </h1>
            <p v-if="subtitle" class="text-muted small mb-0">{{ subtitle }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
