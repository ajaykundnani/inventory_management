<script setup>
import AlertMessage from './AlertMessage.vue'

defineProps({
    loading: { type: Boolean, default: false },
    error: { type: Object, default: null },
    empty: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'Nothing to show yet.' },
})

defineEmits(['retry'])
</script>

<template>
    <div v-if="error">
        <AlertMessage :message="error.message" />
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="$emit('retry')">
            <i class="bi bi-arrow-clockwise me-1"></i>Try again
        </button>
    </div>
    <div v-else-if="loading && empty" class="text-center text-muted py-5" role="status">
        <div class="spinner-border spinner-border-sm me-2"></div>Loading…
    </div>
    <div v-else-if="empty" class="card border-dashed text-center text-muted py-5">
        <div><i class="bi bi-inbox fs-2 d-block mb-2"></i>{{ emptyMessage }}</div>
    </div>
    <div v-else class="position-relative" :class="{ 'opacity-50': loading }">
        <slot />
    </div>
</template>
