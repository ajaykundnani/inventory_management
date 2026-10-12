<script setup>
import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { productsApi } from '../api/products'
import PageHeader from '../components/PageHeader.vue'
import RequestState from '../components/RequestState.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAsync } from '../composables/useAsync'
import { formatCurrency, formatDateTime } from '../utils/format'

const route = useRoute()
const { data: product, loading, error, run } = useAsync((id) => productsApi.get(id).then((response) => response.data))

const load = () => run(route.params.id)

onMounted(load)
watch(() => route.params.id, load)
</script>

<template>
    <RequestState :loading="loading" :error="error" :empty="!product" @retry="load">
        <template v-if="product">
            <PageHeader :title="product.name" :subtitle="product.sku" icon="bi-box-seam">
                <template #actions>
                    <RouterLink :to="{ name: 'products' }" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to products</RouterLink>
                </template>
            </PageHeader>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Price</div>
                            <div class="fs-4 fw-semibold">{{ formatCurrency(product.price) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Available stock</div>
                            <div class="fs-4 fw-semibold d-flex align-items-center gap-2">
                                {{ product.stock_quantity }}
                                <StatusBadge :status="product.stock_status" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Low-stock threshold</div>
                            <div class="fs-4 fw-semibold">{{ product.low_stock_threshold }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Status</div>
                            <StatusBadge :status="product.status" />
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3 text-muted fw-normal">Description</dt>
                                <dd class="col-sm-9" style="white-space: pre-line">{{ product.description || '—' }}</dd>
                                <dt class="col-sm-3 text-muted fw-normal">Created</dt>
                                <dd class="col-sm-9 mb-0">{{ formatDateTime(product.created_at) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </RequestState>
</template>
