<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'
import { useToast } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const { user, isAdmin, logout } = useAuth()
const toast = useToast()

const menuOpen = ref(false)

watch(
    () => route.fullPath,
    () => {
        menuOpen.value = false
    },
)

const links = computed(() => [
    { to: { name: 'dashboard' }, label: 'Dashboard', icon: 'bi-speedometer2' },
    { to: { name: 'products' }, label: 'Products', icon: 'bi-box-seam' },
    { to: { name: 'orders' }, label: 'Orders', icon: 'bi-receipt' },
    { to: { name: 'orders.create' }, label: 'New order', icon: 'bi-cart-plus' },
    ...(isAdmin.value ? [{ to: { name: 'products.create' }, label: 'Add product', icon: 'bi-plus-square' }] : []),
])

async function handleLogout() {
    await logout()
    toast.success('You have been logged out.')
    router.push({ name: 'login' })
}
</script>

<template>
    <div class="min-vh-100 d-flex flex-column">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
            <div class="container">
                <RouterLink :to="{ name: 'dashboard' }" class="navbar-brand fw-semibold">
                    <i class="bi bi-boxes me-2"></i>Inventory
                </RouterLink>
                <button
                    type="button"
                    class="navbar-toggler"
                    aria-controls="main-nav"
                    :aria-expanded="menuOpen"
                    aria-label="Toggle navigation"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div id="main-nav" class="collapse navbar-collapse" :class="{ show: menuOpen }">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li v-for="link in links" :key="link.label" class="nav-item">
                            <RouterLink :to="link.to" class="nav-link" exact-active-class="active">
                                <i class="bi me-1" :class="link.icon"></i>{{ link.label }}
                            </RouterLink>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center gap-2 pb-2 pb-lg-0">
                        <span class="text-white-50 small"><i class="bi bi-person-circle me-1"></i>{{ user?.name }}</span>
                        <span class="badge text-uppercase" :class="isAdmin ? 'text-bg-warning' : 'text-bg-info'">{{ user?.role }}</span>
                        <button type="button" class="btn btn-outline-light btn-sm" @click="handleLogout">
                            <i class="bi bi-box-arrow-right me-1"></i>Log out
                        </button>
                    </div>
                </div>
            </div>
        </nav>
        <main class="container py-4 flex-grow-1">
            <slot />
        </main>
    </div>
</template>
