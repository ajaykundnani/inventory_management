import { computed, reactive, readonly } from 'vue'
import { authApi } from '../api/auth'

const state = reactive({
    user: null,
    initialized: false,
})

let pendingInitialization = null

async function initialize() {
    if (state.initialized) {
        return
    }

    pendingInitialization ??= authApi
        .currentUser()
        .then((response) => {
            state.user = response.data
        })
        .catch(() => {
            state.user = null
        })
        .finally(() => {
            state.initialized = true
            pendingInitialization = null
        })

    await pendingInitialization
}

async function login(credentials) {
    const response = await authApi.login(credentials)
    state.user = response.data
    state.initialized = true
    return response.data
}

async function logout() {
    try {
        await authApi.logout()
    } finally {
        clear()
    }
}

function clear() {
    state.user = null
}

const user = computed(() => state.user)
const isAuthenticated = computed(() => state.user !== null)
const isAdmin = computed(() => state.user?.role === 'admin')

export function useAuth() {
    return {
        state: readonly(state),
        user,
        isAuthenticated,
        isAdmin,
        initialize,
        login,
        logout,
        clear,
    }
}
