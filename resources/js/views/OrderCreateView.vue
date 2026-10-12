<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ordersApi } from '../api/orders'
import AlertMessage from '../components/AlertMessage.vue'
import FormField from '../components/FormField.vue'
import PageHeader from '../components/PageHeader.vue'
import ProductPicker from '../components/ProductPicker.vue'
import { useOrderDraft } from '../composables/useOrderDraft'
import { useToast } from '../stores/toast'
import { firstError, formatCents, formatCurrency, toCents } from '../utils/format'

const router = useRouter()
const toast = useToast()
const { lines, previewTotalCents, isEmpty, hasProduct, addProduct, removeLine, toPayload } = useOrderDraft()

const customerName = ref('')
const notes = ref('')
const errors = ref({})
const message = ref('')
const submitting = ref(false)

const generalItemErrors = computed(() => errors.value.items ?? [])

async function submit() {
    submitting.value = true
    errors.value = {}
    message.value = ''

    try {
        const response = await ordersApi.create({
            customer_name: customerName.value,
            notes: notes.value || null,
            items: toPayload(),
        })
        toast.success(`Order ${response.data.order_number} placed successfully.`)
        router.push({ name: 'orders.show', params: { id: response.data.id } })
    } catch (error) {
        errors.value = error.errors
        message.value = error.message
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <PageHeader title="New order" icon="bi-cart-plus" subtitle="Prices and totals are confirmed by the server when the order is placed." />

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <ProductPicker :is-selected="hasProduct" @select="addProduct" />
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <form class="card border-0 shadow-sm" novalidate @submit.prevent="submit">
                <div class="card-body">
                    <AlertMessage :message="message" />

                    <div class="mb-3">
                        <FormField id="customer_name" v-slot="{ inputClass }" required label="Customer name" :error="firstError(errors, 'customer_name')">
                            <input id="customer_name" v-autofocus required v-model="customerName" type="text" placeholder="e.g. Acme Corp" maxlength="255" class="form-control" :class="inputClass" />
                        </FormField>
                    </div>

                    <h2 class="h6 fw-semibold">Items</h2>
                    <div v-if="isEmpty" class="border border-dashed rounded text-center text-muted small py-4 mb-3">
                        <i class="bi bi-cart fs-3 d-block mb-1"></i>Add products from the list to start the order.
                    </div>
                    <ul v-else class="list-group mb-3">
                        <li v-for="(line, index) in lines" :key="line.product.id" class="list-group-item">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-sm min-w-0">
                                    <div class="fw-medium text-truncate">{{ line.product.name }}</div>
                                    <div class="small text-muted">{{ formatCurrency(line.product.price) }} each · {{ line.product.stock_quantity }} available</div>
                                    <div v-if="firstError(errors, `items.${index}.product_id`)" class="small text-danger">
                                        {{ firstError(errors, `items.${index}.product_id`) }}
                                    </div>
                                    <div v-if="firstError(errors, `items.${index}.quantity`)" class="small text-danger">
                                        {{ firstError(errors, `items.${index}.quantity`) }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <input
                                        v-model.number="line.quantity"
                                        placeholder="Qty"
                                        type="number"
                                        min="1"
                                        :max="line.product.stock_quantity"
                                        step="1"
                                        :aria-label="`Quantity for ${line.product.name}`"
                                        class="form-control form-control-sm text-end"
                                        :class="{ 'is-invalid': firstError(errors, `items.${index}.quantity`) }"
                                        style="width: 5.5rem"
                                    />
                                </div>
                                <div class="col text-end col-sm-auto fw-medium" style="min-width: 6rem">
                                    {{ formatCents(toCents(line.product.price) * (Number(line.quantity) || 0)) }}
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger" :aria-label="`Remove ${line.product.name}`" @click="removeLine(index)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div v-for="itemError in generalItemErrors" :key="itemError" class="small text-danger mb-2">{{ itemError }}</div>

                    <FormField id="notes" v-slot="{ inputClass }" label="Notes (optional)" :error="firstError(errors, 'notes')">
                        <textarea id="notes" v-model="notes" placeholder="Delivery instructions or other notes (optional)" rows="2" maxlength="1000" class="form-control" :class="inputClass" />
                    </FormField>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                    <div>
                        <div class="text-muted small">Estimated total</div>
                        <div class="fs-3 fw-semibold">{{ formatCents(previewTotalCents) }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" :disabled="submitting || isEmpty">
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-check2-circle me-1"></i>{{ submitting ? 'Placing order…' : 'Place order' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
