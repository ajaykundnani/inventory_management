import http from './http'

export const ordersApi = {
    list(params = {}) {
        return http.get('/orders', { params })
    },
    get(id) {
        return http.get(`/orders/${id}`)
    },
    create(payload) {
        return http.post('/orders', payload)
    },
    updateStatus(id, status) {
        return http.patch(`/orders/${id}/status`, { status })
    },
}
