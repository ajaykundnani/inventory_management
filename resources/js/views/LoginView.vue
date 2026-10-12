<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertMessage from '../components/AlertMessage.vue'
import FormField from '../components/FormField.vue'
import PasswordInput from '../components/PasswordInput.vue'
import { useAuth } from '../stores/auth'
import { firstError } from '../utils/format'
import { useToast } from '../stores/toast'

const router = useRouter()
const route = useRoute()
const { login } = useAuth()
const toast = useToast()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const message = ref('')
const submitting = ref(false)

async function submit() {
    submitting.value = true
    errors.value = {}
    message.value = ''

    try {
        const user = await login(form)
        toast.success(`Welcome back, ${user.name}!`)
        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/'
        router.replace(redirect)
    } catch (error) {
        errors.value = error.errors
        message.value = error.status === 422 ? '' : error.message
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="login-page min-vh-100 d-flex align-items-center justify-content-center px-3">
        <div class="card border-0 shadow-lg w-100" style="max-width: 400px">
            <div class="card-body p-4 p-sm-5">
                <div class="text-center mb-4">
                    <i class="bi bi-boxes fs-1 text-primary"></i>
                    <h1 class="h4 mt-2 mb-1">Sign in</h1>
                    <p class="text-muted small mb-0">Order &amp; Inventory Management</p>
                </div>
                <form novalidate @submit.prevent="submit">
                    <AlertMessage :message="message" />
                    <div class="mb-3">
                        <FormField id="email" v-slot="{ inputClass }" label="Email" :error="firstError(errors, 'email')">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input id="email" v-autofocus v-model="form.email" type="email" placeholder="you@example.com" autocomplete="username" required class="form-control" :class="inputClass" />
                            </div>
                        </FormField>
                    </div>
                    <div class="mb-4">
                        <FormField id="password" v-slot="{ invalid }" label="Password" :error="firstError(errors, 'password')">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <PasswordInput
                                    id="password"
                                    v-model="form.password"
                                    :invalid="invalid"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                />
                            </div>
                        </FormField>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" :disabled="submitting">
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                        {{ submitting ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
