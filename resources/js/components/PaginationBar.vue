<script setup>
defineProps({
    meta: { type: Object, default: null },
})

defineEmits(['change'])
</script>

<template>
    <div v-if="meta && meta.last_page > 1" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
        <span class="text-muted small">Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</span>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: meta.current_page <= 1 }">
                <button type="button" class="page-link" :disabled="meta.current_page <= 1" @click="$emit('change', meta.current_page - 1)">
                    <i class="bi bi-chevron-left"></i> Previous
                </button>
            </li>
            <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }">
                <button
                    type="button"
                    class="page-link"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="$emit('change', meta.current_page + 1)"
                >
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        </ul>
    </div>
</template>
