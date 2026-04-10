<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { defaultDashboardSection, dashboardSections, isDashboardSectionKey } from '@/constants/dashboardSections'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'

const appStore = useAppStore()
const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const isProfileMenuOpen = ref(false)

const sectionNavigationItems = computed(() => {
  if (!authStore.isAuthenticated) {
    return []
  }

  return dashboardSections.map((section) => ({
    key: section.key,
    label: section.label,
    to: {
      name: 'dashboard',
      query: { section: section.key },
    },
  }))
})

const activeHeaderSection = computed(() => {
  const section = route.query.section

  if (typeof section === 'string' && isDashboardSectionKey(section)) {
    return section
  }

  return defaultDashboardSection
})

const userInitials = computed(() => {
  if (!authStore.user?.name) {
    return 'U'
  }

  return authStore.user.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})

function openProfileMenu() {
  isProfileMenuOpen.value = true
}

function closeProfileMenu() {
  isProfileMenuOpen.value = false
}

function toggleProfileMenu() {
  isProfileMenuOpen.value = !isProfileMenuOpen.value
}

function handleWindowClick() {
  closeProfileMenu()
}

onMounted(() => {
  if (!authStore.initialized) {
    void authStore.fetchMe()
  }

  window.addEventListener('click', handleWindowClick)
})

onUnmounted(() => {
  window.removeEventListener('click', handleWindowClick)
})

async function logout() {
  closeProfileMenu()
  await authStore.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-center gap-3">
            <RouterLink
              to="/"
              class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white shadow-sm"
            >
              VM
            </RouterLink>

            <div>
              <p class="text-[28px] leading-[1.2] font-medium text-gray-900">
                {{ appStore.projectTitle }}
              </p>
            </div>
          </div>

          <nav
            v-if="sectionNavigationItems.length"
            class="flex flex-1 flex-wrap items-center justify-center gap-2 lg:px-4"
          >
            <RouterLink
              v-for="item in sectionNavigationItems"
              :key="item.key"
              :to="item.to"
              class="rounded-full border px-3 py-1.5 text-sm transition"
              :class="route.name === 'dashboard' && activeHeaderSection === item.key
                ? 'border-slate-900 bg-slate-900 text-white'
                : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
            >
              {{ item.label }}
            </RouterLink>
          </nav>

          <div class="flex items-center justify-end gap-3">
            <div v-if="authStore.isAuthenticated && authStore.user" class="relative">
              <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700"
                :title="authStore.user.name"
                @mouseenter="openProfileMenu"
                @click.stop="toggleProfileMenu"
              >
                {{ userInitials }}
              </button>

              <div
                v-if="isProfileMenuOpen"
                class="absolute right-0 z-20 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg"
                @mouseenter="openProfileMenu"
                @mouseleave="closeProfileMenu"
                @click.stop
              >
                <div class="border-b border-slate-100 pb-3">
                  <p class="text-sm font-semibold text-slate-900">{{ authStore.user.name }}</p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ authStore.user.role === 'admin' ? 'Администратор' : 'Менеджер' }}
                  </p>
                </div>

                <div v-if="authStore.isAdmin" class="mt-3 space-y-2 border-b border-slate-100 pb-3">
                  <RouterLink
                    :to="{ name: 'dashboard', query: { section: 'dashboard' } }"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="closeProfileMenu"
                  >
                    Главная
                  </RouterLink>

                  <RouterLink
                    to="/admin/users"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="closeProfileMenu"
                  >
                    Пользователи системы
                  </RouterLink>

                  <RouterLink
                    to="/admin/integrations"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="closeProfileMenu"
                  >
                    Интеграции
                  </RouterLink>
                </div>

                <button
                  type="button"
                  class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                  @click="logout"
                >
                  Выйти
                </button>
              </div>
            </div>

            <RouterLink
              v-else
              to="/login"
              class="rounded-full bg-slate-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-800"
            >
              Войти
            </RouterLink>
          </div>
        </div>
      </header>

      <RouterView />
    </div>
  </div>
</template>
