import axios from 'axios'

export class ApiError extends Error {
    constructor(message, status = 0, errors = {}) {
        super(message)
        this.name = 'ApiError'
        this.status = status
        this.errors = errors
    }
}

const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

const unauthorizedListeners = new Set()

export function onUnauthorized(listener) {
    unauthorizedListeners.add(listener)
    return () => unauthorizedListeners.delete(listener)
}

export function ensureCsrfCookie() {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

function toApiError(error) {
    if (!error.response) {
        return new ApiError('Unable to reach the server. Check your connection and try again.')
    }

    const { status, data } = error.response
    const message = data?.message || 'Something went wrong. Please try again.'

    return new ApiError(message, status, data?.errors || {})
}

http.interceptors.response.use(
    (response) => response.data,
    async (error) => {
        const config = error.config

        if (error.response?.status === 419 && config && !config.csrfRetried) {
            config.csrfRetried = true
            await ensureCsrfCookie()
            return http(config)
        }

        const apiError = toApiError(error)

        if (apiError.status === 401 && !config?.skipUnauthorizedHandler) {
            unauthorizedListeners.forEach((listener) => listener())
        }

        return Promise.reject(apiError)
    },
)

export default http
