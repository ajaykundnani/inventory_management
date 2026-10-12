import { computed, ref } from 'vue'
import { toCents } from '../utils/format'

export function useOrderDraft() {
    const lines = ref([])

    const previewTotalCents = computed(() =>
        lines.value.reduce((sum, line) => sum + toCents(line.product.price) * (Number(line.quantity) || 0), 0),
    )

    const isEmpty = computed(() => lines.value.length === 0)

    function hasProduct(productId) {
        return lines.value.some((line) => line.product.id === productId)
    }

    function addProduct(product) {
        if (!hasProduct(product.id)) {
            lines.value.push({ product, quantity: 1 })
        }
    }

    function removeLine(index) {
        lines.value.splice(index, 1)
    }

    function reset() {
        lines.value = []
    }

    function toPayload() {
        return lines.value.map((line) => ({
            product_id: line.product.id,
            quantity: Number(line.quantity),
        }))
    }

    return { lines, previewTotalCents, isEmpty, hasProduct, addProduct, removeLine, reset, toPayload }
}
