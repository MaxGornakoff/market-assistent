import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { api, ensureCsrfCookie, getApiErrorMessage } from '@/services/api'

export type UserRole = 'admin' | 'manager'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  is_active: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)
  const isLoading = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value))
  const isAdmin = computed(() => user.value?.role === 'admin')

  function clearSession() {
    user.value = null
  }

  async function login(payload: { email: string; password: string }) {
    isLoading.value = true

    try {
      await ensureCsrfCookie()

      const { data } = await api.post('/login', payload)

      user.value = data.user
      initialized.value = true
    } catch (error) {
      clearSession()
      throw new Error(getApiErrorMessage(error, 'Не удалось войти в систему.'))
    } finally {
      isLoading.value = false
    }
  }

  async function fetchMe() {
    try {
      const { data } = await api.get('/me')
      user.value = data.user

      return user.value
    } catch {
      clearSession()
      return null
    } finally {
      initialized.value = true
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      clearSession()
      initialized.value = true
    }
  }

  return {
    fetchMe,
    initialized,
    isAdmin,
    isAuthenticated,
    isLoading,
    login,
    logout,
    user,
  }
})
