<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: 'admin@market-assistant.local',
  password: 'Admin123!',
})

const errorMessage = ref('')

async function submitLogin() {
  errorMessage.value = ''

  try {
    await authStore.login(form)

    const redirectTarget = typeof route.query.redirect === 'string'
      ? route.query.redirect
      : authStore.isAdmin ? '/admin/users' : '/'

    await router.push(redirectTarget)
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Не удалось выполнить вход.'
  }
}
</script>

<template>
  <section class="mx-auto max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="mb-6">
      <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Авторизация</p>
      
    </div>

    <form class="space-y-4" @submit.prevent="submitLogin">
      <label class="block">
        <span class="mb-1 block text-sm font-medium text-slate-700">Email</span>
        <input
          v-model="form.email"
          type="email"
          class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="admin@market-assistant.local"
          required
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-sm font-medium text-slate-700">Пароль</span>
        <input
          v-model="form.password"
          type="password"
          class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="••••••••"
          required
        />
      </label>

      <p v-if="errorMessage" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">
        {{ errorMessage }}
      </p>

      <button
        type="submit"
        class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="authStore.isLoading"
      >
        {{ authStore.isLoading ? 'Входим...' : 'Войти' }}
      </button>
    </form>

    <div class="mt-6 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
      <p class="font-medium text-slate-800">Стартовый админ:</p>
      <p>`admin@market-assistant.local` / `Admin123!`</p>
    </div>
  </section>
</template>
