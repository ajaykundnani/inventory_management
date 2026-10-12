<script setup>
import { onMounted } from 'vue'
import { ordersApi } from '../api/orders'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import RequestState from '../components/RequestState.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { usePaginatedList } from '../composables/usePaginatedList'
import { useAuth } from '../stores/auth'
import { formatCurrency, formatDateTime, humanize } from '../utils/format'
import { ORDER_STATUSES } from '../utils/options'

const { isAdmin } = useAuth()
const { filters, items, meta, loading, error, load, setPage } = usePaginatedList(ordersApi.list, { search: '', status: '' })

onMounted(load)
</script>

<template>
    <PageHeader title="Orders" icon="bi-receipt" :subtitle="isAdmin ? '' : 'Showing orders you created'">
        <template #actions>
            <RouterLink :to="{ name: 'orders.create' }" class="btn btn-primary"><i class="bi bi-cart-plus me-1"></i>New order</RouterLink>
        </template>
    </PageHeader>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input
                        v-model="filters.search"
                        type="search"
                        aria-label="Search orders"
                        placeholder="Search by order number or customer"
                        class="form-control"
                    />
                </div>
            </div>
            <div class="col-md-4">
                <select v-model="filters.status" aria-label="Filter by status" class="form-select">
                    <option value="">All statuses</option>
                    <option v-for="status in ORDER_STATUSES" :key="status" :value="status">{{ humanize(status) }}</option>
                </select>
            </div>
        </div>
    </div>

    <RequestState :loading="loading" :error="error" :empty="items.length === 0" empty-message="No orders match these filters." @retry="load">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Total</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in items" :key="order.id">
                            <td>
                                <RouterLink :to="{ name: 'orders.show', params: { id: order.id } }" class="font-monospace small fw-semibold text-decoration-none">
                                    {{ order.order_number }}
                                </RouterLink>
                                <div v-if="isAdmin" class="small text-muted">by {{ order.created_by?.name }}</div>
                            </td>
                            <td>{{ order.customer_name }}</td>
                            <td><StatusBadge :status="order.status" /></td>
                            <td class="text-end">{{ order.items_count }}</td>
                            <td class="text-end fw-medium">{{ formatCurrency(order.total) }}</td>
                            <td class="text-nowrap text-muted small">{{ formatDateTime(order.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <PaginationBar :meta="meta" @change="setPage" />
    </RequestState>
</template>
