import { reactive, readonly } from 'vue'

const DEFAULT_DURATION = 4000

const state = reactive({ toasts: [] })
let nextId = 1

function dismiss(id) {
    const index = state.toasts.findIndex((toast) => toast.id === id)
    if (index !== -1) {
        state.toasts.splice(index, 1)
    }
}

function show(message, { variant = 'success', duration = DEFAULT_DURATION } = {}) {
    const id = nextId++
    state.toasts.push({ id, message, variant })
    if (duration > 0) {
        setTimeout(() => dismiss(id), duration)
    }
    return id
}

export function useToast() {
    return {
        toasts: readonly(state.toasts),
        success: (message, options) => show(message, { ...options, variant: 'success' }),
        error: (message, options) => show(message, { ...options, variant: 'danger' }),
        dismiss,
    }
}
