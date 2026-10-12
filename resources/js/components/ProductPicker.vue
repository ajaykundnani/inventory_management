<script setup>
import { onMounted, ref, watch } from 'vue'
import { productsApi } from '../api/products'
import { useAsync } from '../composables/useAsync'
import { formatCurrency } from '../utils/format'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
    isSelected: { type: Function, required: true },
})

const emit = defineEmits(['select'])

const search = ref('')
const { data, loading, error, run } = useAsync((term) => productsApi.list({ search: term, status: 'active', per_page: 8 }))

let timer = null
watch(search, (term) => {
    clearTimeout(timer)
    timer = setTimeout(() => run(term), 300)
})

onMounted(() => run(''))

function canSelect(product) {
    return product.stock_quantity > 0 && !props.isSelected(product.id)
}
</script>

<template>
    <div>
        <label for="product-search" class="form-label fw-medium">Find products</label>
        <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="product-search" v-model="search" type="search" placeholder="Search by name or SKU" class="form-control" />
        </div>
        <div v-if="error" class="text-danger small">{{ error.message }}</div>
        <div v-else-if="loading && !data" class="text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Loading products…</div>
        <div v-else-if="data" class="list-group">
            <div v-if="data.data.length === 0" class="list-group-item text-muted small">No active products match.</div>
            <div v-for="product in data.data" :key="product.id" class="list-group-item d-flex align-items-center justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="fw-medium text-truncate">{{ product.name }}</div>
                    <div class="small text-muted">
                        {{ product.sku }} · {{ formatCurrency(product.price) }} · {{ product.stock_quantity }} in stock
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <StatusBadge v-if="product.stock_status !== 'in_stock'" :status="product.stock_status" />
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="isSelected(product.id) ? 'btn-success' : 'btn-outline-primary'"
                        :disabled="!canSelect(product)"
                        @click="emit('select', product)"
                    >
                        <i class="bi" :class="isSelected(product.id) ? 'bi-check-lg' : 'bi-plus-lg'"></i>
                        {{ isSelected(product.id) ? 'Added' : 'Add' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
