import http from './http'

export const dashboardApi = {
    summary() {
        return http.get('/dashboard')
    },
}
