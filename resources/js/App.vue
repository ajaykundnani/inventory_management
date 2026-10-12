<script setup>
import { onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { onUnauthorized } from './api/http'
import AppLayout from './components/AppLayout.vue'
import ToastContainer from './components/ToastContainer.vue'
import { useAuth } from './stores/auth'

const route = useRoute()
const router = useRouter()
const { clear } = useAuth()

const stopListening = onUnauthorized(() => {
    clear()
    if (route.meta.requiresAuth) {
        router.replace({ name: 'login', query: { redirect: route.fullPath } })
    }
})

onBeforeUnmount(stopListening)
</script>

<template>
    <RouterView v-if="route.meta.layout === 'blank'" />
    <AppLayout v-else>
        <RouterView :key="route.path" />
    </AppLayout>
    <ToastContainer />
</template>
