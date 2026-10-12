import { reactive, ref, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function usePaginatedList(fetchPage, defaults = {}, { debounceKeys = ['search'] } = {}) {
    const route = useRoute()
    const router = useRouter()

    const filters = reactive({ ...defaults })
    Object.keys(defaults).forEach((key) => {
        if (typeof route.query[key] === 'string') {
            filters[key] = route.query[key]
        }
    })

    const page = ref(Number(route.query.page) || 1)
    const items = shallowRef([])
    const meta = shallowRef(null)
    const loading = ref(false)
    const error = shallowRef(null)

    let requestId = 0
    let debounceTimer = null

    function activeParams() {
        const params = { page: page.value }
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                params[key] = value
            }
        })
        return params
    }

    async function load() {
        const currentRequest = ++requestId
        const params = activeParams()
        loading.value = true
        error.value = null

        router.replace({ query: Object.fromEntries(Object.entries(params).filter(([key, value]) => !(key === 'page' && value === 1))) })

        try {
            const response = await fetchPage(params)
            if (currentRequest !== requestId) {
                return
            }
            items.value = response.data
            meta.value = response.meta
        } catch (caught) {
            if (currentRequest === requestId) {
                error.value = caught
            }
        } finally {
            if (currentRequest === requestId) {
                loading.value = false
            }
        }
    }

    function setPage(value) {
        page.value = value
        load()
    }

    Object.keys(defaults).forEach((key) => {
        watch(
            () => filters[key],
            () => {
                page.value = 1
                clearTimeout(debounceTimer)
                if (debounceKeys.includes(key)) {
                    debounceTimer = setTimeout(load, 300)
                } else {
                    load()
                }
            },
        )
    })

    return { filters, page, items, meta, loading, error, load, setPage }
}
