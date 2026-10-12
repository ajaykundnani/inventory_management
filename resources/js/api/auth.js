import http, { ensureCsrfCookie } from './http'

export const authApi = {
    async login(credentials) {
        await ensureCsrfCookie()
        return http.post('/login', credentials)
    },
    logout() {
        return http.post('/logout')
    },
    currentUser() {
        return http.get('/user', { skipUnauthorizedHandler: true })
    },
}
