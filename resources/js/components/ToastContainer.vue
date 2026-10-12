<script setup>
import { useToast } from '../stores/toast'

const { toasts, dismiss } = useToast()

const icons = {
    success: 'bi-check-circle-fill',
    danger: 'bi-exclamation-triangle-fill',
}
</script>

<template>
    <div class="toast-container position-fixed top-0 end-0 p-3" aria-live="polite" aria-atomic="false">
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="toast show align-items-center border-0 shadow"
                :class="`text-bg-${toast.variant}`"
                :role="toast.variant === 'danger' ? 'alert' : 'status'"
            >
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi fs-5" :class="icons[toast.variant]"></i>
                        <span>{{ toast.message }}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close" @click="dismiss(toast.id)"></button>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>
