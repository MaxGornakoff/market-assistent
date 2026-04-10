<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import { dashboardSections } from '@/constants/dashboardSections'

const overviewCards = computed(() => {
  return dashboardSections.filter((section) => section.key !== 'dashboard')
})
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-100 pb-4">
      <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Dashboard</p>
      <h2 class="mt-2 text-xl font-semibold text-slate-900">Рабочие направления</h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
        Здесь находится стартовая зона приложения. Выбирай направление ниже, чтобы открыть отдельный
        контейнер с собственной логикой, таблицами, API-интеграциями и рабочими инструментами.
      </p>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <RouterLink
        v-for="card in overviewCards"
        :key="card.key"
        :to="{ name: 'dashboard', query: { section: card.key } }"
        class="rounded-2xl bg-slate-50 p-5 text-left ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-md"
      >
        <h3 class="mb-2 text-lg font-semibold text-slate-900">{{ card.title }}</h3>
        <p class="text-sm leading-6 text-slate-600">{{ card.description }}</p>
      </RouterLink>
    </div>
  </div>
</template>
