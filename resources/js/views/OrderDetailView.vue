<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ordersApi } from '../api/orders'
import AlertMessage from '../components/AlertMessage.vue'
import OrderItemsTable from '../components/OrderItemsTable.vue'
import OrderStatusControl from '../components/OrderStatusControl.vue'
import PageHeader from '../components/PageHeader.vue'
import RequestState from '../components/RequestState.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAsync } from '../composables/useAsync'
import { useAuth } from '../stores/auth'
import { useToast } from '../stores/toast'
import { formatDateTime, humanize } from '../utils/format'

const route = useRoute()
const { isAdmin } = useAuth()
const { data: order, loading, error, run } = useAsync((id) => ordersApi.get(id).then((response) => response.data))

const updating = ref(false)
const statusError = ref('')
const toast = useToast()

const load = () => run(route.params.id)

async function changeStatus(status) {
    updating.value = true
    statusError.value = ''

    try {
        const response = await ordersApi.updateStatus(order.value.id, status)
        order.value = response.data
        toast.success(`Order ${response.data.order_number} marked as ${humanize(response.data.status).toLowerCase()}.`)
    } catch (caught) {
        statusError.value = caught.errors?.status?.[0] ?? caught.message
        if (caught.status === 422) {
            load()
        }
    } finally {
        updating.value = false
    }
}

onMounted(load)
watch(() => route.params.id, load)
</script>

<template>
    <RequestState :loading="loading" :error="error" :empty="!order" @retry="load">
        <template v-if="order">
            <PageHeader :title="order.order_number" :subtitle="`Customer: ${order.customer_name}`" icon="bi-receipt">
                <template #actions>
                    <RouterLink :to="{ name: 'orders' }" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to orders</RouterLink>
                </template>
            </PageHeader>

            <AlertMessage :message="statusError" />

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Status</div>
                            <StatusBadge :status="order.status" />
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Created by</div>
                            <div class="fw-medium">{{ order.created_by?.name }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Created</div>
                            <div class="fw-medium small">{{ formatDateTime(order.created_at) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">{{ order.cancelled_at ? 'Cancelled' : 'Last updated' }}</div>
                            <div class="fw-medium small">{{ formatDateTime(order.cancelled_at || order.updated_at) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="order.notes" class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="text-muted small">Notes</div>
                    <div style="white-space: pre-line">{{ order.notes }}</div>
                </div>
            </div>

            <OrderItemsTable :items="order.items" :total="order.total" />

            <div v-if="isAdmin" class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-repeat me-2"></i>Manage status</div>
                <div class="card-body">
                    <OrderStatusControl :allowed-transitions="order.allowed_transitions" :busy="updating" @change="changeStatus" />
                </div>
            </div>
        </template>
    </RequestState>
</template>
