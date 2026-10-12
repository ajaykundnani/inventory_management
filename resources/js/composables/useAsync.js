import { ref, shallowRef } from 'vue'

export function useAsync(task) {
    const data = shallowRef(null)
    const loading = ref(false)
    const error = shallowRef(null)

    async function run(...args) {
        loading.value = true
        error.value = null

        try {
            data.value = await task(...args)
            return data.value
        } catch (caught) {
            error.value = caught
            return null
        } finally {
            loading.value = false
        }
    }

    return { data, loading, error, run }
}
