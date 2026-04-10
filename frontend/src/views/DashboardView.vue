<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import { useRoute } from 'vue-router'

import {
  defaultDashboardSection,
  isDashboardSectionKey,
  type DashboardSectionKey,
} from '@/constants/dashboardSections'

const route = useRoute()

const sectionComponents: Record<DashboardSectionKey, Component> = {
  dashboard: defineAsyncComponent(() => import('@/components/dashboard/sections/DashboardHomeSection.vue')),
  'yandex-market': defineAsyncComponent(() => import('@/components/dashboard/sections/YandexMarketSection.vue')),
  moysklad: defineAsyncComponent(() => import('@/components/dashboard/sections/MoySkladSection.vue')),
  analytics: defineAsyncComponent(() => import('@/components/dashboard/sections/AnalyticsSection.vue')),
}

const activeSection = computed<DashboardSectionKey>(() => {
  const section = route.query.section

  if (typeof section === 'string' && isDashboardSectionKey(section)) {
    return section
  }

  return defaultDashboardSection
})

const activeSectionComponent = computed<Component>(() => {
  return sectionComponents[activeSection.value]
})
</script>

<template>
  <section>
    <component :is="activeSectionComponent" />
  </section>
</template>
