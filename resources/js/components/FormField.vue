<script setup>
defineProps({
    label: { type: String, required: true },
    id: { type: String, required: true },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
})
</script>

<template>
    <div>
        <label :for="id" class="form-label fw-medium">
            {{ label }}<span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
            <span v-if="required" class="visually-hidden">(required)</span>
        </label>
        <slot :invalid="Boolean(error)" :input-class="{ 'is-invalid': Boolean(error) }" />
        <div v-if="error" :id="`${id}-error`" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="hint" class="form-text">{{ hint }}</div>
    </div>
</template>
