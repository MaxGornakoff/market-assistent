<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import AdminNavigation from '@/components/admin/AdminNavigation.vue'
import { api, getApiErrorMessage } from '@/services/api'

interface YandexMarketSettings {
  api_url: string
  business_id: number | null
  campaign_ids: number[]
  campaign_id: number | null
  has_token: boolean
  token_masked: string | null
}

interface YandexMarketConnectionCampaign {
  id: number | null
  name: string
  placement_type: string | null
  api_availability: string | null
  is_active?: boolean
}

interface YandexMarketConnection {
  connected: boolean
  business_id: number | null
  business_name: string | null
  campaigns: YandexMarketConnectionCampaign[]
}

const isLoading = ref(false)
const isSubmitting = ref(false)
const isChecking = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const currentSettings = ref<YandexMarketSettings | null>(null)
const connectionResult = ref<YandexMarketConnection | null>(null)

const form = reactive({
  api_url: 'https://api.partner.market.yandex.ru',
  business_id: '',
  campaign_ids_text: '',
  token: '',
  clear_token: false,
})

function applySettings(settings: YandexMarketSettings) {
  currentSettings.value = settings
  form.api_url = settings.api_url || 'https://api.partner.market.yandex.ru'
  form.business_id = settings.business_id ? String(settings.business_id) : ''
  form.campaign_ids_text = settings.campaign_ids?.join(', ') || ''
  form.token = ''
  form.clear_token = false
}

async function loadSettings() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get('/admin/integrations/yandex-market')
    applySettings(data.settings)
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Не удалось загрузить настройки интеграции.')
  } finally {
    isLoading.value = false
  }
}

async function saveSettings() {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const campaignIds = form.campaign_ids_text
      .split(',')
      .map((item) => Number(item.trim()))
      .filter((item) => Number.isInteger(item) && item > 0)

    const payload: {
      api_url: string
      business_id: number | null
      campaign_ids: number[]
      token?: string
      clear_token: boolean
    } = {
      api_url: form.api_url.trim(),
      business_id: form.business_id ? Number(form.business_id) : null,
      campaign_ids: campaignIds,
      clear_token: form.clear_token,
    }

    if (form.token.trim()) {
      payload.token = form.token.trim()
    }

    const { data } = await api.put('/admin/integrations/yandex-market', payload)
    applySettings(data.settings)
    connectionResult.value = null
    successMessage.value = data.message || 'Настройки интеграции сохранены.'
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Не удалось сохранить настройки интеграции.')
  } finally {
    isSubmitting.value = false
  }
}

async function checkConnection() {
  isChecking.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const { data } = await api.post('/admin/integrations/yandex-market/check')
    connectionResult.value = data.connection
    successMessage.value = data.message || 'Подключение к Яндекс Маркету подтверждено.'
  } catch (error) {
    connectionResult.value = null
    errorMessage.value = getApiErrorMessage(error, 'Не удалось проверить подключение к Яндекс Маркету.')
  } finally {
    isChecking.value = false
  }
}

onMounted(loadSettings)
</script>

<template>
  <section class="space-y-5">
    <AdminNavigation />

    <div class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Админка</p>
        <h2 class="mt-1 text-xl font-semibold text-slate-900">Интеграция Яндекс Маркета</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">
          Токен хранится на бэкенде и не раскрывается в браузере. Здесь можно обновить API-ключ и ID кабинета.
        </p>

        <p v-if="errorMessage" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {{ errorMessage }}
        </p>
        <p v-if="successMessage" class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
          {{ successMessage }}
        </p>

        <div v-if="isLoading" class="mt-4 rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-600">
          Загружаем настройки интеграции...
        </div>

        <form v-else class="mt-4 space-y-4" @submit.prevent="saveSettings">
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">API URL</span>
            <input
              v-model="form.api_url"
              type="url"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              required
            />
          </label>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="mb-1 block text-sm font-medium text-slate-700">Business ID</span>
              <input
                v-model="form.business_id"
                type="number"
                min="1"
                class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="77283184"
              />
            </label>

            <label class="block">
              <span class="mb-1 block text-sm font-medium text-slate-700">Активные campaignId</span>
              <input
                v-model="form.campaign_ids_text"
                type="text"
                class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="68023107, 100655646"
              />
            </label>
          </div>

          <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">Новый API-токен</span>
            <input
              v-model="form.token"
              type="password"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              placeholder="Оставь пустым, чтобы не менять"
              autocomplete="new-password"
            />
          </label>

          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input v-model="form.clear_token" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
            Очистить сохранённый токен
          </label>

          <div class="flex flex-wrap gap-3">
            <button
              type="submit"
              class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isSubmitting"
            >
              {{ isSubmitting ? 'Сохраняем...' : 'Сохранить настройки' }}
            </button>

            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isChecking"
              @click="checkConnection"
            >
              {{ isChecking ? 'Проверяем...' : 'Проверить подключение' }}
            </button>
          </div>
        </form>
      </div>

      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Текущее состояние</p>
        <h3 class="mt-1 text-lg font-semibold text-slate-900">Статус конфигурации</h3>

        <div class="mt-4 space-y-3 text-sm text-slate-700">
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs text-slate-500">API URL</div>
            <div class="mt-1 font-medium text-slate-900">{{ currentSettings?.api_url || '—' }}</div>
          </div>

          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs text-slate-500">Business ID</div>
            <div class="mt-1 font-medium text-slate-900">{{ currentSettings?.business_id || '—' }}</div>
          </div>

          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs text-slate-500">Активные campaignId</div>
            <div class="mt-1 font-medium text-slate-900">
              {{ currentSettings?.campaign_ids?.length ? currentSettings.campaign_ids.join(', ') : '—' }}
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs text-slate-500">Токен</div>
            <div class="mt-1 font-medium text-slate-900">
              {{ currentSettings?.has_token ? currentSettings?.token_masked : 'Не задан' }}
            </div>
          </div>
        </div>

        <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
          Поиск товаров в разделе <strong>Управление ценами</strong> использует эти настройки автоматически.
        </div>

        <div v-if="connectionResult" class="mt-4 rounded-xl border px-4 py-3 text-sm"
          :class="connectionResult.connected ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900'"
        >
          <div class="font-semibold">
            {{ connectionResult.connected ? 'Подключение активно' : 'Подключение не подтверждено' }}
          </div>
          <div class="mt-1">
            Кабинет: {{ connectionResult.business_name || '—' }}
            <span v-if="connectionResult.business_id">(#{{ connectionResult.business_id }})</span>
          </div>
          <div class="mt-2 text-xs">
            Найдено кампаний: {{ connectionResult.campaigns.length }}
          </div>

          <ul v-if="connectionResult.campaigns.length" class="mt-3 space-y-2 text-xs">
            <li
              v-for="campaign in connectionResult.campaigns"
              :key="`${campaign.id}-${campaign.name}`"
              class="rounded-lg bg-white/70 px-3 py-2"
            >
              <strong>{{ campaign.name }}</strong>
              <span v-if="campaign.id"> · ID {{ campaign.id }}</span>
              <span v-if="campaign.placement_type"> · {{ campaign.placement_type }}</span>
              <span v-if="campaign.api_availability"> · {{ campaign.api_availability }}</span>
              <span v-if="campaign.is_active" class="font-semibold text-emerald-700"> · используется</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>
