<script setup>
import { ref, watch } from 'vue'
import { humanize } from '../utils/format'

const props = defineProps({
    allowedTransitions: { type: Array, required: true },
    busy: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])

const selected = ref('')

watch(
    () => props.allowedTransitions,
    () => {
        selected.value = ''
    },
)

function submit() {
    if (!selected.value) {
        return
    }
    if (selected.value === 'cancelled' && !window.confirm('Cancel this order? Reserved stock will be returned to inventory.')) {
        return
    }
    emit('change', selected.value)
}
</script>

<template>
    <p v-if="allowedTransitions.length === 0" class="text-muted small mb-0">
        <i class="bi bi-lock me-1"></i>This order is in a final status.
    </p>
    <form v-else class="row g-2 align-items-end" @submit.prevent="submit">
        <div class="col-sm-auto">
            <label for="order-status" class="form-label fw-medium">Change status</label>
            <select id="order-status" v-model="selected" class="form-select" :disabled="busy">
                <option value="" disabled>Select status</option>
                <option v-for="status in allowedTransitions" :key="status" :value="status">{{ humanize(status) }}</option>
            </select>
        </div>
        <div class="col-sm-auto">
            <button type="submit" class="btn" :class="selected === 'cancelled' ? 'btn-danger' : 'btn-primary'" :disabled="busy || !selected">
                <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>
                {{ busy ? 'Updating…' : 'Update status' }}
            </button>
        </div>
    </form>
</template>
