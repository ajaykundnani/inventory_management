<script setup>
import { computed } from 'vue'

const props = defineProps({
    variant: { type: String, default: 'error' },
    message: { type: String, default: '' },
})

const config = computed(
    () =>
        ({
            error: { className: 'alert-danger', icon: 'bi-exclamation-triangle-fill' },
            success: { className: 'alert-success', icon: 'bi-check-circle-fill' },
            info: { className: 'alert-info', icon: 'bi-info-circle-fill' },
        })[props.variant],
)
</script>

<template>
    <div v-if="message || $slots.default" class="alert d-flex align-items-start gap-2 mb-3" :class="config.className" role="alert">
        <i class="bi flex-shrink-0" :class="config.icon"></i>
        <div><slot>{{ message }}</slot></div>
    </div>
</template>
