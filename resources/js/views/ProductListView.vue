<script setup>
import { onMounted } from 'vue'
import { productsApi } from '../api/products'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import RequestState from '../components/RequestState.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { usePaginatedList } from '../composables/usePaginatedList'
import { useAuth } from '../stores/auth'
import { formatCurrency, humanize } from '../utils/format'
import { STOCK_STATUSES } from '../utils/options'

const { isAdmin } = useAuth()
const { filters, items, meta, loading, error, load, setPage } = usePaginatedList(productsApi.list, { search: '', stock_status: '' })

onMounted(load)
</script>

<template>
    <PageHeader title="Products" icon="bi-box-seam">
        <template #actions>
            <RouterLink v-if="isAdmin" :to="{ name: 'products.create' }" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add product
            </RouterLink>
        </template>
    </PageHeader>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input v-model="filters.search" type="search" aria-label="Search products" placeholder="Search by name or SKU" class="form-control" />
                </div>
            </div>
            <div class="col-md-4">
                <select v-model="filters.stock_status" aria-label="Filter by stock status" class="form-select">
                    <option value="">All stock levels</option>
                    <option v-for="status in STOCK_STATUSES" :key="status" :value="status">{{ humanize(status) }}</option>
                </select>
            </div>
        </div>
    </div>

    <RequestState :loading="loading" :error="error" :empty="items.length === 0" empty-message="No products match these filters." @retry="load">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>SKU</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in items" :key="product.id">
                            <td>
                                <RouterLink :to="{ name: 'products.show', params: { id: product.id } }" class="fw-medium text-decoration-none">
                                    {{ product.name }}
                                </RouterLink>
                            </td>
                            <td class="font-monospace small">{{ product.sku }}</td>
                            <td class="text-end">{{ formatCurrency(product.price) }}</td>
                            <td class="text-end text-nowrap">
                                <span class="me-2">{{ product.stock_quantity }}</span>
                                <StatusBadge :status="product.stock_status" />
                            </td>
                            <td><StatusBadge :status="product.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <PaginationBar :meta="meta" @change="setPage" />
    </RequestState>
</template>
