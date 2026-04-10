<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

import { api, getApiErrorMessage } from '@/services/api'

interface YandexMarketProduct {
  id: number
  name: string
  offer_id: string
  sku: string | null
  category: string | null
  status: string
  monitoring_enabled: boolean
  campaign_ids: number[]
  created_at: string
}

interface YandexMarketCatalogProduct {
  name: string
  offer_id: string
  sku: string | null
  category: string | null
  vendor: string | null
  status: string
  market_sku: string | null
  campaign_ids: number[]
}

type TableQuickFilter = 'all' | 'enabled' | 'disabled'

const products = ref<YandexMarketProduct[]>([])
const searchResults = ref<YandexMarketCatalogProduct[]>([])
const searchQuery = ref('')
const tableFilterQuery = ref('')
const tableQuickFilter = ref<TableQuickFilter>('all')
const searchContainer = ref<HTMLElement | null>(null)
const isLoading = ref(false)
const isSearching = ref(false)
const showSearchDropdown = ref(false)
const isSubmittingOfferId = ref('')
const isTogglingProductId = ref<number | null>(null)
const isDeletingProductId = ref<number | null>(null)
const selectedProductId = ref<number | null>(null)
const productPendingDeleteId = ref<number | null>(null)
const toast = ref<{
  type: 'success' | 'error'
  message: string
} | null>(null)

const selectedProduct = computed(
  () => products.value.find((item) => item.id === selectedProductId.value) ?? null,
)
const productPendingDelete = computed(
  () => products.value.find((item) => item.id === productPendingDeleteId.value) ?? null,
)
const enabledProductsCount = computed(() => products.value.filter((product) => product.monitoring_enabled).length)
const disabledProductsCount = computed(() => products.value.filter((product) => !product.monitoring_enabled).length)
const hasActiveTableFilter = computed(
  () => tableQuickFilter.value !== 'all' || tableFilterQuery.value.trim().length > 0,
)
const filteredProducts = computed(() => {
  const query = tableFilterQuery.value.trim().toLowerCase()

  const quickFilteredProducts = products.value.filter((product) => {
    if (tableQuickFilter.value === 'enabled') {
      return product.monitoring_enabled
    }

    if (tableQuickFilter.value === 'disabled') {
      return !product.monitoring_enabled
    }

    return true
  })

  if (!query) {
    return quickFilteredProducts
  }

  return quickFilteredProducts.filter((product) => {
    const searchableValues = [
      product.name,
      product.offer_id,
      product.sku ?? '',
      product.category ?? '',
      product.status,
      product.campaign_ids?.join(' ') ?? '',
    ]

    return searchableValues.some((value) => value.toLowerCase().includes(query))
  })
})

let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined
let toastTimer: ReturnType<typeof setTimeout> | undefined
let activeSearchRequest = 0

function showToast(type: 'success' | 'error', message: string) {
  toast.value = { type, message }

  if (toastTimer) {
    clearTimeout(toastTimer)
  }

  toastTimer = setTimeout(() => {
    toast.value = null
  }, 2000)
}

function closeToast() {
  toast.value = null

  if (toastTimer) {
    clearTimeout(toastTimer)
    toastTimer = undefined
  }
}

async function loadProducts() {
  isLoading.value = true

  try {
    const { data } = await api.get('/yandex-market/products')
    products.value = data.products
  } catch (error) {
    showToast('error', getApiErrorMessage(error, 'Не удалось загрузить список товаров.'))
  } finally {
    isLoading.value = false
  }
}

async function searchCatalog(enforceMinLength = false) {
  const query = searchQuery.value.trim()

  if (query.length < 2) {
    searchResults.value = []
    showSearchDropdown.value = query.length > 0

    if (enforceMinLength && query.length > 0) {
      showToast('error', 'Введите минимум 2 символа для поиска.')
    }

    return
  }

  const requestId = ++activeSearchRequest

  isSearching.value = true
  showSearchDropdown.value = true

  try {
    const { data } = await api.get('/yandex-market/catalog/search', {
      params: { query },
    })

    if (requestId !== activeSearchRequest) {
      return
    }

    searchResults.value = (data.products ?? []).slice(0, 8)
  } catch (error) {
    if (requestId !== activeSearchRequest) {
      return
    }

    searchResults.value = []
    showToast('error', getApiErrorMessage(error, 'Не удалось выполнить поиск в каталоге Яндекс Маркета.'))
  } finally {
    if (requestId === activeSearchRequest) {
      isSearching.value = false
    }
  }
}

async function addCatalogProduct(product: YandexMarketCatalogProduct) {
  if (products.value.some((item) => item.offer_id === product.offer_id)) {
    showToast('success', 'Этот товар уже есть в таблице мониторинга.')
    showSearchDropdown.value = false
    return
  }

  isSubmittingOfferId.value = product.offer_id

  try {
    const { data } = await api.post('/yandex-market/products', {
      name: product.name,
      offer_id: product.offer_id,
      sku: product.sku ?? '',
      category: product.category ?? '',
      campaign_ids: product.campaign_ids ?? [],
      monitoring_enabled: true,
    })

    products.value = [data.product, ...products.value]
    showToast('success', 'Товар добавлен в таблицу.')
    searchQuery.value = ''
    searchResults.value = []
    showSearchDropdown.value = false
  } catch (error) {
    showToast('error', getApiErrorMessage(error, 'Не удалось добавить товар.'))
  } finally {
    isSubmittingOfferId.value = ''
  }
}

async function toggleMonitoring(product: YandexMarketProduct) {
  const nextValue = !product.monitoring_enabled
  isTogglingProductId.value = product.id

  try {
    const { data } = await api.patch(`/yandex-market/products/${product.id}`, {
      monitoring_enabled: nextValue,
    })

    products.value = products.value.map((item) => (item.id === product.id ? data.product : item))
    showToast(
      'success',
      data.message || (nextValue ? 'Отслеживание товара включено.' : 'Отслеживание товара отключено.'),
    )
  } catch (error) {
    showToast('error', getApiErrorMessage(error, 'Не удалось изменить режим отслеживания.'))
  } finally {
    isTogglingProductId.value = null
  }
}

function openProductDetails(product: YandexMarketProduct) {
  selectedProductId.value = product.id
}

function closeProductDetails() {
  selectedProductId.value = null
}

function requestDeleteProduct(product: YandexMarketProduct) {
  productPendingDeleteId.value = product.id
}

function cancelDeleteProduct() {
  if (isDeletingProductId.value === null) {
    productPendingDeleteId.value = null
  }
}

async function confirmDeleteProduct() {
  const product = productPendingDelete.value

  if (!product) {
    return
  }

  isDeletingProductId.value = product.id

  try {
    const { data } = await api.delete(`/yandex-market/products/${product.id}`)
    products.value = products.value.filter((item) => item.id !== product.id)

    if (selectedProductId.value === product.id) {
      selectedProductId.value = null
    }

    productPendingDeleteId.value = null
    showToast('success', data.message || 'Товар удалён из таблицы мониторинга.')
  } catch (error) {
    showToast('error', getApiErrorMessage(error, 'Не удалось удалить товар.'))
  } finally {
    isDeletingProductId.value = null
  }
}

function handleSearchFocus() {
  if (searchQuery.value.trim().length > 0) {
    showSearchDropdown.value = true
  }
}

function handleWindowClick(event: MouseEvent) {
  const target = event.target as Node | null

  if (searchContainer.value && target && !searchContainer.value.contains(target)) {
    showSearchDropdown.value = false
  }
}

function handleEscapeKey(event: KeyboardEvent) {
  if (event.key !== 'Escape') {
    return
  }

  if (productPendingDelete.value) {
    cancelDeleteProduct()
    return
  }

  if (selectedProduct.value) {
    closeProductDetails()
  }
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

watch(searchQuery, (value) => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  const query = value.trim()

  if (query.length === 0) {
    searchResults.value = []
    showSearchDropdown.value = false
    return
  }

  showSearchDropdown.value = true

  if (query.length < 2) {
    searchResults.value = []
    return
  }

  searchDebounceTimer = setTimeout(() => {
    void searchCatalog(false)
  }, 250)
})

onMounted(() => {
  void loadProducts()
  window.addEventListener('click', handleWindowClick)
  window.addEventListener('keydown', handleEscapeKey)
})

onUnmounted(() => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  if (toastTimer) {
    clearTimeout(toastTimer)
  }

  window.removeEventListener('click', handleWindowClick)
  window.removeEventListener('keydown', handleEscapeKey)
})
</script>

<template>
  <div class="grid gap-5">
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="toast"
        class="fixed top-4 right-4 z-50 max-w-sm rounded-2xl border px-4 py-3 shadow-lg"
        :class="toast.type === 'success'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
          : 'border-rose-200 bg-rose-50 text-rose-900'"
      >
        <div class="flex items-start gap-3">
          <div class="flex-1 text-sm font-medium">
            {{ toast.message }}
          </div>

          <button
            type="button"
            class="cursor-pointer rounded-md px-2 py-1 text-xs font-semibold opacity-70 transition hover:opacity-100"
            @click="closeToast"
          >
            ✕
          </button>
        </div>
      </div>
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="selectedProduct"
        class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/45 p-4"
        @click="closeProductDetails"
      >
        <div
          class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl"
          @click.stop
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">Карточка товара</p>
              <h3 class="mt-2 text-xl font-semibold text-slate-900">{{ selectedProduct.name }}</h3>
              <p class="mt-1 text-sm text-slate-500">Offer ID: {{ selectedProduct.offer_id }}</p>
            </div>

            <button
              type="button"
              class="inline-flex cursor-pointer h-10 w-10 min-w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:bg-slate-50 hover:text-slate-700 hover:shadow-md"
              aria-label="Закрыть карточку товара"
              title="Закрыть"
              @click="closeProductDetails"
            >
              <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              </svg>
            </button>
          </div>

          <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">SKU</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">{{ selectedProduct.sku || '—' }}</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Категория</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">{{ selectedProduct.category || '—' }}</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Статус</div>
              <div class="mt-1">
                <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">
                  {{ selectedProduct.status }}
                </span>
              </div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Отслеживание</div>
              <div class="mt-1">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                  :class="selectedProduct.monitoring_enabled
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-slate-200 text-slate-700'"
                >
                  {{ selectedProduct.monitoring_enabled ? 'Включено' : 'Отключено' }}
                </span>
              </div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
              <div class="text-xs font-medium text-slate-500">Активные кампании</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ selectedProduct.campaign_ids?.length ? selectedProduct.campaign_ids.join(', ') : '—' }}
              </div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
              <div class="text-xs font-medium text-slate-500">Добавлен в мониторинг</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">{{ formatDateTime(selectedProduct.created_at) }}</div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="productPendingDelete"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        @click="cancelDeleteProduct"
      >
        <div
          class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl"
          @click.stop
        >
          <div class="flex items-start gap-3">
            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M7.75 8.25v5.5M10 8.25v5.5M12.25 8.25v5.5" stroke="currentColor" stroke-width="1" stroke-linecap="round" />
                <path d="M3.75 5.5h12.5" stroke="currentColor" stroke-width="1" stroke-linecap="round" />
                <path d="M8 3.75h4a.75.75 0 0 1 .72.53l.35 1.22H6.93l.35-1.22A.75.75 0 0 1 8 3.75Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round" />
                <path d="M5.5 5.5l.55 8.64A1.25 1.25 0 0 0 7.3 15.3h5.4a1.25 1.25 0 0 0 1.25-1.16l.55-8.64" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>

            <div class="flex-1">
              <h3 class="text-base font-semibold text-slate-900">Удалить товар?</h3>
              <p class="mt-1 text-sm text-slate-600">
                Товар <span class="font-semibold text-slate-900">«{{ productPendingDelete.name }}»</span>
                будет удалён из таблицы мониторинга.
              </p>
            </div>
          </div>

          <div class="mt-5 flex justify-end gap-2">
            <button
              type="button"
              class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
              :disabled="isDeletingProductId === productPendingDelete.id"
              @click="cancelDeleteProduct"
            >
              Отмена
            </button>
            <button
              type="button"
              class="cursor-pointer rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isDeletingProductId === productPendingDelete.id"
              @click="confirmDeleteProduct"
            >
              {{ isDeletingProductId === productPendingDelete.id ? 'Удаляем...' : 'Удалить' }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
            Управление ценами
          </span>
          
        </div>

        <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
          {{ products.length }} в мониторинге
        </span>
      </div>

      <div class="mt-5 relative" ref="searchContainer">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Найди и добавь товар из нашего каталога в ЯМ для отслеживания</span>
          <input
            v-model="searchQuery"
            type="text"
            class="w-full text-[14px] rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            placeholder="Например, витамин d3, SKU-1001 или offer-id"
            autocomplete="off"
            @focus="handleSearchFocus"
          />
        </label>

        <div
          v-if="showSearchDropdown"
          class="absolute z-20 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg"
        >
          <div v-if="isSearching" class="px-4 py-3 text-sm text-slate-600">
            Выполняем поиск в каталоге...
          </div>

          <div v-else-if="searchQuery.trim().length < 2" class="px-4 py-3 text-sm text-slate-600">
            Введите минимум 2 символа, и список появится прямо здесь.
          </div>

          <div v-else-if="!searchResults.length" class="px-4 py-3 text-sm text-slate-600">
            По этому запросу товары не найдены.
          </div>

          <ul v-else class="max-h-80 overflow-y-auto py-2">
            <li
              v-for="product in searchResults"
              :key="product.offer_id"
              class="border-b border-slate-100 last:border-none"
            >
              <button
                type="button"
                class="group cursor-pointer flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
                :disabled="isSubmittingOfferId === product.offer_id"
                @click="addCatalogProduct(product)"
              >
                <div class="min-w-0 flex-1">
                  <div class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</div>
                  <div class="mt-1 text-xs text-slate-500">
                    Offer ID: {{ product.offer_id }} · SKU: {{ product.sku || '—' }}
                  </div>
                  <div class="mt-1 text-xs text-slate-500">
                    {{ product.category || 'Без категории' }}
                  </div>
                </div>

                <span
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-blue-200 bg-linear-to-b from-blue-50 to-blue-100 text-blue-600 shadow-sm ring-1 ring-white/60 transition duration-150 group-hover:-translate-y-0.5 group-hover:scale-105 group-hover:border-blue-500 group-hover:bg-blue-600 group-hover:from-blue-600 group-hover:to-blue-600 group-hover:text-white group-hover:shadow-md"
                  :class="isSubmittingOfferId === product.offer_id
                    ? 'animate-pulse border-slate-200 bg-slate-100 text-slate-500'
                    : ''"
                  :title="isSubmittingOfferId === product.offer_id ? 'Добавляем...' : 'Добавить товар'"
                  :aria-label="isSubmittingOfferId === product.offer_id ? 'Добавляем...' : 'Добавить товар'"
                >
                  <svg v-if="isSubmittingOfferId !== product.offer_id" class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  </svg>
                  <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  </svg>
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>

    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex-1">
          <label class="block">

            <div class="relative">
              <input
                v-model="tableFilterQuery"
                type="text"
                class="w-full text-[14px] rounded-xl border border-slate-300 bg-white px-3 py-2 pr-11 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="Поиск по названию, Offer ID, SKU или категории"
              />

              <button
                v-if="tableFilterQuery"
                type="button"
                class="absolute top-1/2 right-2 inline-flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                title="Очистить фильтр"
                aria-label="Очистить фильтр"
                @click="tableFilterQuery = ''"
              >
                ✕
              </button>
            </div>
          </label>

          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex cursor-pointer items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition"
              :class="tableQuickFilter === 'all'
                ? 'bg-slate-900 text-white shadow-sm'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
              @click="tableQuickFilter = 'all'"
            >
              <span>Все</span>
              <span
                class="rounded-full px-1.5 py-0.5 text-[11px]"
                :class="tableQuickFilter === 'all' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
              >
                {{ products.length }}
              </span>
            </button>

            <button
              type="button"
              class="inline-flex cursor-pointer items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition"
              :class="tableQuickFilter === 'enabled'
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
              @click="tableQuickFilter = 'enabled'"
            >
              <span>Только отслеживаемые</span>
              <span
                class="rounded-full px-1.5 py-0.5 text-[11px]"
                :class="tableQuickFilter === 'enabled' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
              >
                {{ enabledProductsCount }}
              </span>
            </button>

            <button
              type="button"
              class="inline-flex cursor-pointer items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition"
              :class="tableQuickFilter === 'disabled'
                ? 'bg-amber-500 text-white shadow-sm'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
              @click="tableQuickFilter = 'disabled'"
            >
              <span>Отключённые</span>
              <span
                class="rounded-full px-1.5 py-0.5 text-[11px]"
                :class="tableQuickFilter === 'disabled' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
              >
                {{ disabledProductsCount }}
              </span>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between gap-3 lg:justify-end">
          <span class="text-xs font-medium text-slate-500">
            {{ hasActiveTableFilter ? `${filteredProducts.length} из ${products.length}` : `${products.length} товаров` }}
          </span>

          <button
            type="button"
            class="cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="loadProducts"
          >
            Обновить
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-600">
        Загружаем товары...
      </div>

      <div
        v-else-if="!products.length"
        class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600"
      >
        Пока в таблице нет товаров. Найди первую позицию через строку поиска выше.
      </div>

      <div
        v-else-if="!filteredProducts.length"
        class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600"
      >
        <span v-if="tableFilterQuery.trim()">
          По фильтру <span class="font-semibold text-slate-900">«{{ tableFilterQuery }}»</span> ничего не найдено.
        </span>
        <span v-else>
          В выбранной вкладке пока нет товаров.
        </span>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-200 rounded-2xl">
            <tr class="text-slate-500">
              <th class="px-3 py-3 font-medium rounded-l-2xl">Название</th>
              <th class="px-3 py-3 font-medium w-max">Offer ID</th>
              <th class="px-3 py-3 font-medium">SKU</th>
              <th class="px-3 py-3 font-medium">Категория</th>
              <th class="px-3 py-3 font-medium">Кампании</th>
              <th class="px-3 py-3 font-medium">Статус</th>
              <th class="px-3 py-3 font-medium">Отслеживать</th>
              <th class="px-3 py-3 font-medium">Удалить</th>
              <th class="px-3 py-3 font-medium rounded-r-2xl">Добавлен</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="product in filteredProducts"
              :key="product.id"
              class="cursor-pointer border-b border-slate-100 text-slate-700 transition hover:bg-slate-50 last:border-none"
              tabindex="0"
              @click="openProductDetails(product)"
              @keydown.enter.prevent="openProductDetails(product)"
            >
              <td class="px-3 py-3 font-medium text-slate-900">
                <div>{{ product.name }}</div>
                
              </td>
              <td class="px-3 py-3">{{ product.offer_id }}</td>
              <td class="px-3 py-3">{{ product.sku || '—' }}</td>
              <td class="px-3 py-3">{{ product.category || '—' }}</td>
              <td class="px-3 py-3">
                <span v-if="product.campaign_ids?.length">
                  {{ product.campaign_ids.join(', ') }}
                </span>
                <span v-else>—</span>
              </td>
              <td class="px-3 py-3">
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                  {{ product.status }}
                </span>
              </td>
              <td class="px-3 py-3" @click.stop>
                <label class="inline-flex cursor-pointer items-center gap-2" @click.stop>
                  <input
                    :checked="product.monitoring_enabled"
                    type="checkbox"
                    class="peer sr-only cursor-pointer"
                    :disabled="isTogglingProductId === product.id"
                    @change="toggleMonitoring(product)"
                  />
                  <span class="relative h-6 w-11 rounded-full bg-slate-300 transition after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-all peer-checked:bg-blue-600 peer-checked:after:translate-x-5 peer-disabled:opacity-60"></span>
                  <span class="text-xs font-medium text-slate-600">
                    {{ isTogglingProductId === product.id ? '...' : product.monitoring_enabled ? 'Вкл' : 'Выкл' }}
                  </span>
                </label>
              </td>
              <td class="px-3 py-3" @click.stop>
                <button
                  type="button"
                  class="cursor-pointer group inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-500 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="isDeletingProductId === product.id"
                  title="Удалить товар"
                  aria-label="Удалить товар"
                  @click.stop="requestDeleteProduct(product)"
                >
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M7.75 8.25v5.5M10 8.25v5.5M12.25 8.25v5.5" stroke="currentColor" stroke-width="1" stroke-linecap="round" />
                    <path d="M3.75 5.5h12.5" stroke="currentColor" stroke-width="1" stroke-linecap="round" />
                    <path d="M8 3.75h4a.75.75 0 0 1 .72.53l.35 1.22H6.93l.35-1.22A.75.75 0 0 1 8 3.75Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round" />
                    <path d="M5.5 5.5l.55 8.64A1.25 1.25 0 0 0 7.3 15.3h5.4a1.25 1.25 0 0 0 1.25-1.16l.55-8.64" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
              </td>
              <td class="px-3 py-3">{{ formatDate(product.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
