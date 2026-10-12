import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../stores/auth'

const routes = [
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guest: true, layout: 'blank' } },
    { path: '/', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { requiresAuth: true } },
    { path: '/products', name: 'products', component: () => import('../views/ProductListView.vue'), meta: { requiresAuth: true } },
    {
        path: '/products/new',
        name: 'products.create',
        component: () => import('../views/ProductCreateView.vue'),
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/products/:id(\\d+)',
        name: 'products.show',
        component: () => import('../views/ProductDetailView.vue'),
        meta: { requiresAuth: true },
    },
    { path: '/orders', name: 'orders', component: () => import('../views/OrderListView.vue'), meta: { requiresAuth: true } },
    { path: '/orders/new', name: 'orders.create', component: () => import('../views/OrderCreateView.vue'), meta: { requiresAuth: true } },
    { path: '/orders/:id(\\d+)', name: 'orders.show', component: () => import('../views/OrderDetailView.vue'), meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue'), meta: { requiresAuth: true } },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to) => {
    const auth = useAuth()
    await auth.initialize()

    if (to.meta.requiresAuth && !auth.isAuthenticated.value) {
        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
    }

    if (to.meta.guest && auth.isAuthenticated.value) {
        return { name: 'dashboard' }
    }

    if (to.meta.role === 'admin' && !auth.isAdmin.value) {
        return { name: 'dashboard' }
    }

    return true
})

export default router
