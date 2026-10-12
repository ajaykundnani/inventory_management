<script setup>
import { onMounted } from 'vue'
import { dashboardApi } from '../api/dashboard'
import PageHeader from '../components/PageHeader.vue'
import RequestState from '../components/RequestState.vue'
import StatCard from '../components/StatCard.vue'
import { useAsync } from '../composables/useAsync'
import { useAuth } from '../stores/auth'

const { isAdmin } = useAuth()
const { data, loading, error, run } = useAsync(() => dashboardApi.summary().then((response) => response.data))

onMounted(run)
</script>

<template>
    <PageHeader
        title="Dashboard"
        icon="bi-speedometer2"
        :subtitle="isAdmin ? 'All orders across the team' : 'Order figures include only orders you created'"
    />
    <RequestState :loading="loading" :error="error" :empty="!data" @retry="run">
        <template v-if="data">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <StatCard label="Total products" :value="data.total_products" icon="bi-box-seam" variant="primary" :to="{ name: 'products' }" />
                </div>
                <div class="col-6 col-lg-3">
                    <StatCard label="Total orders" :value="data.total_orders" icon="bi-receipt" variant="info" :to="{ name: 'orders' }" />
                </div>
                <div class="col-6 col-lg-3">
                    <StatCard
                        label="Pending orders"
                        :value="data.pending_orders"
                        icon="bi-hourglass-split"
                        variant="secondary"
                        :to="{ name: 'orders', query: { status: 'pending' } }"
                    />
                </div>
                <div class="col-6 col-lg-3">
                    <StatCard
                        label="Low-stock products"
                        :value="data.low_stock_products"
                        icon="bi-exclamation-triangle"
                        variant="warning"
                        :to="{ name: 'products', query: { stock_status: 'low_stock' } }"
                    />
                </div>
            </div>
            <div v-if="data.out_of_stock_products" class="alert alert-danger d-flex align-items-center gap-2 mt-4 mb-0">
                <i class="bi bi-x-octagon-fill"></i>
                <span>
                    {{ data.out_of_stock_products }} product(s) are out of stock.
                    <RouterLink :to="{ name: 'products', query: { stock_status: 'out_of_stock' } }" class="alert-link">View them</RouterLink>
                </span>
            </div>
        </template>
    </RequestState>
</template>
