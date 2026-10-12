<script setup>
import { ref } from 'vue'

defineOptions({ inheritAttrs: false })

defineProps({
    id: { type: String, required: true },
    invalid: { type: Boolean, default: false },
})

const model = defineModel({ type: String, default: '' })
const visible = ref(false)
</script>

<template>
    <input
        :id="id"
        v-model="model"
        v-bind="$attrs"
        :type="visible ? 'text' : 'password'"
        class="form-control"
        :class="{ 'is-invalid': invalid }"
    />
    <button
        type="button"
        class="btn btn-outline-secondary"
        :aria-label="visible ? 'Hide password' : 'Show password'"
        :aria-pressed="visible"
        :aria-controls="id"
        :title="visible ? 'Hide password' : 'Show password'"
        @click="visible = !visible"
    >
        <i class="bi" :class="visible ? 'bi-eye-slash' : 'bi-eye'"></i>
    </button>
</template>
