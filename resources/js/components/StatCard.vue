<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: '—' },
    to: { type: Object, default: null },
    icon: { type: String, default: 'bi-bar-chart' },
    variant: { type: String, default: 'primary' },
})
</script>

<template>
    <component :is="to ? 'RouterLink' : 'div'" :to="to" class="card stat-card h-100 border-0 shadow-sm text-decoration-none text-body">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" :class="`bg-${variant}-subtle text-${variant}`">
                <i class="bi" :class="icon"></i>
            </div>
            <div class="min-w-0">
                <div class="text-muted small">{{ label }}</div>
                <div class="fs-3 fw-semibold lh-sm">{{ value }}</div>
            </div>
        </div>
    </component>
</template>
