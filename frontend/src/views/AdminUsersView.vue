<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import AdminNavigation from '@/components/admin/AdminNavigation.vue'
import { api, getApiErrorMessage } from '@/services/api'

interface SystemUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'manager'
  is_active: boolean
  created_at: string
}

const users = ref<SystemUser[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'manager',
  is_active: true,
})

async function loadUsers() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get('/admin/users')
    users.value = data.users
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Не удалось загрузить пользователей.')
  } finally {
    isLoading.value = false
  }
}

async function createUser() {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await api.post('/admin/users', form)
    successMessage.value = 'Менеджер успешно создан.'

    form.name = ''
    form.email = ''
    form.password = ''
    form.password_confirmation = ''
    form.role = 'manager'
    form.is_active = true

    await loadUsers()
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Не удалось создать пользователя.')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(loadUsers)
</script>

<template>
  <section class="space-y-5">
    <AdminNavigation />

    <div class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Админка</p>
          <h2 class="text-xl font-semibold text-slate-900">Пользователи системы</h2>
        </div>
        <button
          type="button"
          class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          @click="loadUsers"
        >
          Обновить
        </button>
      </div>

      <p v-if="errorMessage" class="mb-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">
        {{ errorMessage }}
      </p>
      <p v-if="successMessage" class="mb-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
        {{ successMessage }}
      </p>

      <div v-if="isLoading" class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-600">
        Загружаем пользователей...
      </div>

      <div v-else class="space-y-3">
        <article
          v-for="user in users"
          :key="user.id"
          class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 md:flex-row md:items-center md:justify-between"
        >
          <div>
            <h3 class="text-base font-semibold text-slate-900">{{ user.name }}</h3>
            <p class="text-sm text-slate-600">{{ user.email }}</p>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
            <span
              class="rounded-full px-2.5 py-1"
              :class="user.role === 'admin' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'"
            >
              {{ user.role === 'admin' ? 'Администратор' : 'Менеджер' }}
            </span>
            <span
              class="rounded-full px-2.5 py-1"
              :class="user.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
            >
              {{ user.is_active ? 'Активен' : 'Отключён' }}
            </span>
          </div>
        </article>
      </div>
      </div>

      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Новый пользователь</p>
      <h2 class="mt-1 text-xl font-semibold text-slate-900">Создать менеджера</h2>

      <form class="mt-4 space-y-4" @submit.prevent="createUser">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Имя</span>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            required
          />
        </label>

        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Email</span>
          <input
            v-model="form.email"
            type="email"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            required
          />
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Пароль</span>
            <input
              v-model="form.password"
              type="password"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              minlength="8"
              required
            />
          </label>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Повтор пароля</span>
            <input
              v-model="form.password_confirmation"
              type="password"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              minlength="8"
              required
            />
          </label>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
          Активный пользователь
        </label>

        <button
          type="submit"
          class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="isSubmitting"
        >
          {{ isSubmitting ? 'Создаём...' : 'Создать менеджера' }}
        </button>
      </form>
      </div>
    </div>
  </section>
</template>
