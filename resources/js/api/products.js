import http from './http'

export const productsApi = {
    list(params = {}) {
        return http.get('/products', { params })
    },
    get(id) {
        return http.get(`/products/${id}`)
    },
    create(payload) {
        return http.post('/products', payload)
    },
}
