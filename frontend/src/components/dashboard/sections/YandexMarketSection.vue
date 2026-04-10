<script setup lang="ts">
import { computed, ref, type Component } from 'vue'

import OperationsPanel from '@/components/dashboard/sections/yandex-market/OperationsPanel.vue'
import PriceManagementPanel from '@/components/dashboard/sections/yandex-market/PriceManagementPanel.vue'
import PublicationStatusPanel from '@/components/dashboard/sections/yandex-market/PublicationStatusPanel.vue'

type YandexMarketSubviewKey = 'pricing' | 'statuses' | 'operations'

interface YandexMarketSubview {
  key: YandexMarketSubviewKey
  title: string
  text: string
}

const subviews: YandexMarketSubview[] = [
  {
    key: 'pricing',
    title: 'Управление ценами',
    text: 'Расчёт цен, стратегии обновления и правила публикации на Маркете.',
  },
  {
    key: 'statuses',
    title: 'Статусы публикации',
    text: 'Контроль ошибок выгрузки, модерации и состояния карточек товаров по API Яндекс Маркета.',
  },
  {
    key: 'operations',
    title: 'Операционные действия',
    text: 'Быстрые сценарии для массовых обновлений, фильтров и ручного запуска синхронизации.',
  },
]

const activeSubview = ref<YandexMarketSubviewKey>('pricing')

const subviewComponents: Record<YandexMarketSubviewKey, Component> = {
  pricing: PriceManagementPanel,
  statuses: PublicationStatusPanel,
  operations: OperationsPanel,
}

const activeSubviewComponent = computed<Component>(() => {
  return subviewComponents[activeSubview.value]
})
</script>

<template>
  <div class="grid gap-5">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="rounded-xl border border-gray-300 px-3 py-1 text-xs font-medium text-gray-900">
            Яндекс Маркет
          </p>
        </div>

        <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
          API-зона в подготовке
        </span>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <button
          v-for="subview in subviews"
          :key="subview.key"
          type="button"
          class="rounded-xl border px-4 py-4 text-left transition"
          :class="activeSubview === subview.key
            ? 'border-slate-900 bg-slate-900 text-white'
            : 'border-dashed cursor-pointer border-slate-200 bg-slate-50 text-slate-900 hover:bg-white'"
          @click="activeSubview = subview.key"
        >
          <h3 class="mb-2 text-sm font-semibold">{{ subview.title }}</h3>
          <p
            class="text-sm leading-6"
            :class="activeSubview === subview.key ? 'text-slate-100' : 'text-slate-600'"
          >
            {{ subview.text }}
          </p>
        </button>
      </div>
    </div>

    <KeepAlive>
      <component :is="activeSubviewComponent" />
    </KeepAlive>
  </div>
</template>
