<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { productsApi } from '../api/products'
import AlertMessage from '../components/AlertMessage.vue'
import FormField from '../components/FormField.vue'
import PageHeader from '../components/PageHeader.vue'
import { firstError, humanize } from '../utils/format'
import { PRODUCT_STATUSES } from '../utils/options'
import { useToast } from '../stores/toast'

const router = useRouter()
const toast = useToast()

const form = reactive({
    name: '',
    sku: '',
    description: '',
    price: '',
    stock_quantity: '',
    low_stock_threshold: '',
    status: 'active',
})
const errors = ref({})
const message = ref('')
const submitting = ref(false)

async function submit() {
    submitting.value = true
    errors.value = {}
    message.value = ''

    try {
        const payload = { ...form, description: form.description || null }
        if (payload.low_stock_threshold === '') {
            delete payload.low_stock_threshold
        }
        const response = await productsApi.create(payload)
        toast.success(`Product "${response.data.name}" created successfully.`)
        router.push({ name: 'products.show', params: { id: response.data.id } })
    } catch (error) {
        errors.value = error.errors
        message.value = error.message
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <PageHeader title="Add product" icon="bi-plus-square">
        <template #actions>
            <RouterLink :to="{ name: 'products' }" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</RouterLink>
        </template>
    </PageHeader>
    <div class="card border-0 shadow-sm" style="max-width: 760px">
        <form class="card-body p-4" novalidate @submit.prevent="submit">
            <AlertMessage :message="message" />
            <p class="text-muted small mb-3">Fields marked with <span class="text-danger">*</span> are required.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <FormField id="name" v-slot="{ inputClass }" required label="Name" :error="firstError(errors, 'name')">
                        <input id="name" v-autofocus required v-model="form.name" type="text" placeholder="e.g. Wireless Mouse" maxlength="255" class="form-control" :class="inputClass" />
                    </FormField>
                </div>
                <div class="col-md-6">
                    <FormField id="sku" v-slot="{ inputClass }" required label="SKU" :error="firstError(errors, 'sku')" hint="Stored in upper case; must be unique.">
                        <input id="sku" required v-model="form.sku" type="text" placeholder="e.g. WM-1001" maxlength="64" class="form-control font-monospace" :class="inputClass" />
                    </FormField>
                </div>
                <div class="col-md-6">
                    <FormField id="price" v-slot="{ inputClass }" required label="Price" :error="firstError(errors, 'price')">
                        <div class="input-group has-validation">
                            <span class="input-group-text">$</span>
                            <input id="price" required v-model="form.price" type="number" placeholder="0.00" min="0" step="0.01" inputmode="decimal" class="form-control" :class="inputClass" />
                        </div>
                    </FormField>
                </div>
                <div class="col-md-6">
                    <FormField id="stock_quantity" v-slot="{ inputClass }" required label="Initial stock" :error="firstError(errors, 'stock_quantity')">
                        <input id="stock_quantity" required v-model.number="form.stock_quantity" type="number" placeholder="e.g. 50" min="0" step="1" class="form-control" :class="inputClass" />
                    </FormField>
                </div>
                <div class="col-md-6">
                    <FormField
                        id="low_stock_threshold"
                        v-slot="{ inputClass }"
                        label="Low-stock threshold"
                        :error="firstError(errors, 'low_stock_threshold')"
                    >
                        <input
                            id="low_stock_threshold"
                            v-model.number="form.low_stock_threshold"
                            placeholder="Defaults to 10"
                            type="number"
                            min="0"
                            step="1"
                            class="form-control"
                            :class="inputClass"
                        />
                    </FormField>
                </div>
                <div class="col-md-6">
                    <FormField id="status" v-slot="{ inputClass }" label="Status" :error="firstError(errors, 'status')">
                        <select id="status" v-model="form.status" class="form-select" :class="inputClass">
                            <option v-for="status in PRODUCT_STATUSES" :key="status" :value="status">{{ humanize(status) }}</option>
                        </select>
                    </FormField>
                </div>
                <div class="col-12">
                    <FormField id="description" v-slot="{ inputClass }" label="Description" :error="firstError(errors, 'description')">
                        <textarea id="description" v-model="form.description" placeholder="Short description of the product (optional)" rows="3" maxlength="2000" class="form-control" :class="inputClass" />
                    </FormField>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" :disabled="submitting">
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-check-lg me-1"></i>{{ submitting ? 'Saving…' : 'Create product' }}
                </button>
                <RouterLink :to="{ name: 'products' }" class="btn btn-outline-secondary">Cancel</RouterLink>
            </div>
        </form>
    </div>
</template>
