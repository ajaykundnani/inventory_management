<script setup>
import { formatCurrency } from '../utils/format'

defineProps({
    items: { type: Array, required: true },
    total: { type: [String, Number], required: true },
})
</script>

<template>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-2"></i>Items</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Unit price</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td>
                            <RouterLink :to="{ name: 'products.show', params: { id: item.product_id } }" class="fw-medium text-decoration-none">
                                {{ item.product_name }}
                            </RouterLink>
                            <div class="small text-muted font-monospace">{{ item.product_sku }}</div>
                        </td>
                        <td class="text-end">{{ formatCurrency(item.unit_price) }}</td>
                        <td class="text-end">{{ item.quantity }}</td>
                        <td class="text-end">{{ formatCurrency(item.line_total) }}</td>
                    </tr>
                </tbody>
                <tfoot class="table-group-divider">
                    <tr class="fw-semibold">
                        <td colspan="3">Total</td>
                        <td class="text-end fs-5">{{ formatCurrency(total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
