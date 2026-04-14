<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

import { api, getApiErrorMessage } from '@/services/api'

interface MarketServiceCostItem {
  type: string
  amount: number
  currency: string
}

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
  initial_price: number | null
  initial_price_currency: string | null
  market_price: number | null
  market_price_currency: string | null
  market_price_updated_at: string | null
  market_service_cost: number | null
  market_service_cost_currency: string | null
  market_service_cost_breakdown: MarketServiceCostItem[]
  market_service_cost_note: string | null
  recommended_market_price: number | null
  recommended_market_price_currency: string | null
  recommended_market_net_payout: number | null
  recommended_market_price_note: string | null
  market_category_id: number | null
  market_sku: number | null
  market_url: string | null
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
type ColumnKey =
  | 'name'
  | 'initial_price'
  | 'market_price'
  | 'market_service_cost'
  | 'recommended_market_price'
  | 'offer_id'
  | 'sku'
  | 'category'
  | 'campaigns'
  | 'status'
  | 'monitoring_enabled'
  | 'delete'
  | 'created_at'

interface TableColumnDefinition {
  key: ColumnKey
  label: string
  hideable?: boolean
}

const defaultColumnOrder: ColumnKey[] = [
  'name',
  'initial_price',
  'market_service_cost',
  'recommended_market_price',
  'market_price',
  'offer_id',
  'sku',
  'category',
  'campaigns',
  'status',
  'monitoring_enabled',
  'delete',
  'created_at',
]

const columnDefinitions: TableColumnDefinition[] = [
  { key: 'name', label: 'Название', hideable: false },
  { key: 'initial_price', label: 'Начальная цена' },
  { key: 'market_service_cost', label: 'Расходы Маркета' },
  { key: 'recommended_market_price', label: 'Рекомендованная цена на ЯМ' },
  { key: 'market_price', label: 'Наша цена на ЯМ' },
  { key: 'offer_id', label: 'SKU' },
  { key: 'sku', label: 'Артикул Маркета' },
  { key: 'category', label: 'Категория' },
  { key: 'campaigns', label: 'Кампании' },
  { key: 'status', label: 'Статус' },
  { key: 'monitoring_enabled', label: 'Отслеживать' },
  { key: 'delete', label: 'Удалить' },
  { key: 'created_at', label: 'Добавлен' },
]

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
  name: true,
  initial_price: true,
  market_price: true,
  market_service_cost: true,
  recommended_market_price: true,
  offer_id: true,
  sku: true,
  category: true,
  campaigns: true,
  status: true,
  monitoring_enabled: true,
  delete: true,
  created_at: true,
}

const columnSettingsStorageKey = 'ym-price-management-columns-v1'

function normalizeColumnOrder(value: unknown): ColumnKey[] {
  if (!Array.isArray(value)) {
    return [...defaultColumnOrder]
  }

  const validKeys = value.filter((item): item is ColumnKey =>
    typeof item === 'string' && defaultColumnOrder.includes(item as ColumnKey),
  )

  const uniqueKeys = Array.from(new Set(validKeys))

  return [...uniqueKeys, ...defaultColumnOrder.filter((key) => !uniqueKeys.includes(key))]
}

function normalizeColumnVisibility(value: unknown): Record<ColumnKey, boolean> {
  if (!value || typeof value !== 'object') {
    return { ...defaultColumnVisibility }
  }

  const typedValue = value as Partial<Record<ColumnKey, boolean>>

  return defaultColumnOrder.reduce(
    (result, key) => {
      result[key] = typeof typedValue[key] === 'boolean' ? typedValue[key] as boolean : defaultColumnVisibility[key]
      return result
    },
    {} as Record<ColumnKey, boolean>,
  )
}

function restoreColumnSettings() {
  if (typeof window === 'undefined') {
    return
  }

  try {
    const rawSettings = window.localStorage.getItem(columnSettingsStorageKey)

    if (!rawSettings) {
      return
    }

    const parsedSettings = JSON.parse(rawSettings) as {
      order?: ColumnKey[]
      visibility?: Partial<Record<ColumnKey, boolean>>
    }

    columnOrder.value = normalizeColumnOrder(parsedSettings.order)
    columnVisibility.value = normalizeColumnVisibility(parsedSettings.visibility)
  } catch {
    columnOrder.value = [...defaultColumnOrder]
    columnVisibility.value = { ...defaultColumnVisibility }
  }
}

function persistColumnSettings() {
  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.setItem(
    columnSettingsStorageKey,
    JSON.stringify({
      order: columnOrder.value,
      visibility: columnVisibility.value,
    }),
  )
}

const products = ref<YandexMarketProduct[]>([])
const searchResults = ref<YandexMarketCatalogProduct[]>([])
const searchQuery = ref('')
const tableFilterQuery = ref('')
const tableQuickFilter = ref<TableQuickFilter>('all')
const columnOrder = ref<ColumnKey[]>([...defaultColumnOrder])
const columnVisibility = ref<Record<ColumnKey, boolean>>({ ...defaultColumnVisibility })
const isColumnSettingsOpen = ref(false)
const draggedColumnKey = ref<ColumnKey | null>(null)
const searchContainer = ref<HTMLElement | null>(null)
const isLoading = ref(false)
const isSearching = ref(false)
const showSearchDropdown = ref(false)
const isSubmittingOfferId = ref('')
const isTogglingProductId = ref<number | null>(null)
const isDeletingProductId = ref<number | null>(null)
const selectedProductId = ref<number | null>(null)
const productPendingDeleteId = ref<number | null>(null)
const categoryPreviewOverflow = ref<Record<number, boolean>>({})
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
const orderedColumns = computed(() =>
  columnOrder.value
    .map((key) => columnDefinitions.find((column) => column.key === key))
    .filter((column): column is TableColumnDefinition => Boolean(column)),
)
const visibleColumns = computed(() =>
  orderedColumns.value.filter((column) => columnVisibility.value[column.key]),
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
      String(product.initial_price ?? ''),
      String(product.market_price ?? ''),
      String(product.market_service_cost ?? ''),
      String(getRecommendedMarketPrice(product) ?? ''),
    ]

    return searchableValues.some((value) => value.toLowerCase().includes(query))
  })
})

let searchDebounceTimer: ReturnType<typeof setTimeout> | undefined
let toastTimer: ReturnType<typeof setTimeout> | undefined
let activeSearchRequest = 0

const categoryPreviewElements: Record<number, HTMLElement | null> = {}

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
    updateCategoryPreviewOverflow()
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
    await api.post('/yandex-market/products', {
      name: product.name,
      offer_id: product.offer_id,
      sku: product.sku ?? '',
      category: product.category ?? '',
      campaign_ids: product.campaign_ids ?? [],
      monitoring_enabled: true,
    })

    await loadProducts()
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

    products.value = products.value.map((item) =>
      item.id === product.id
        ? {
            ...item,
            ...data.product,
          }
        : item,
    )
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

function openColumnSettings() {
  isColumnSettingsOpen.value = true
}

function closeColumnSettings() {
  isColumnSettingsOpen.value = false
}

function resetColumnSettings() {
  columnOrder.value = [...defaultColumnOrder]
  columnVisibility.value = { ...defaultColumnVisibility }
}

function startColumnDrag(columnKey: ColumnKey) {
  draggedColumnKey.value = columnKey
}

function handleColumnDrop(targetKey: ColumnKey) {
  const draggedKey = draggedColumnKey.value

  if (!draggedKey || draggedKey === targetKey) {
    draggedColumnKey.value = null
    return
  }

  const nextOrder = [...columnOrder.value]
  const fromIndex = nextOrder.indexOf(draggedKey)
  const toIndex = nextOrder.indexOf(targetKey)

  if (fromIndex === -1 || toIndex === -1) {
    draggedColumnKey.value = null
    return
  }

  nextOrder.splice(fromIndex, 1)
  nextOrder.splice(toIndex, 0, draggedKey)
  columnOrder.value = nextOrder
  draggedColumnKey.value = null
}

function formatMoney(value: number | null | undefined, currency = 'RUR') {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return '—'
  }

  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value))
}

function getRecommendedMarketPrice(product: YandexMarketProduct) {
  if (product.recommended_market_price !== null && product.recommended_market_price !== undefined) {
    const recommendedPrice = Number(product.recommended_market_price)

    return Number.isNaN(recommendedPrice) ? null : recommendedPrice
  }

  if (product.initial_price === null || product.initial_price === undefined) {
    return null
  }

  if (product.market_service_cost === null || product.market_service_cost === undefined) {
    return null
  }

  const initialPrice = Number(product.initial_price)
  const marketServiceCost = Number(product.market_service_cost)

  if (Number.isNaN(initialPrice) || Number.isNaN(marketServiceCost)) {
    return null
  }

  return initialPrice + marketServiceCost
}

function formatTariffType(type: string) {
  const labels: Record<string, string> = {
    AGENCY_COMMISSION: 'Приём платежа',
    PAYMENT_TRANSFER: 'Перевод платежа',
    FEE: 'Размещение',
    DELIVERY_TO_CUSTOMER: 'Доставка покупателю',
    CROSSREGIONAL_DELIVERY: 'Межрегиональная доставка',
    EXPRESS_DELIVERY: 'Экспресс-доставка',
    SORTING: 'Обработка заказа',
    MIDDLE_MILE: 'Средняя миля',
  }

  return labels[type] ?? type
}

function splitCategoryPath(category: string | null) {
  if (!category) {
    return []
  }

  return category
    .split('/')
    .map((item) => item.trim())
    .filter(Boolean)
}

function previewCategoryPath(category: string | null, productId: number) {
  const parts = splitCategoryPath(category)

  if (!categoryPreviewOverflow.value[productId]) {
    return parts
  }

  return parts.slice(0, 3)
}

function setCategoryPreviewRef(productId: number, el: Element | null) {
  categoryPreviewElements[productId] = el instanceof HTMLElement ? el : null
}

function updateCategoryPreviewOverflow() {
  void nextTick(() => {
    const nextOverflowState: Record<number, boolean> = {}

    for (const product of filteredProducts.value) {
      const element = categoryPreviewElements[product.id]
      nextOverflowState[product.id] = Boolean(element && element.scrollHeight > element.clientHeight + 2)
    }

    categoryPreviewOverflow.value = nextOverflowState
  })
}

function handleWindowResize() {
  updateCategoryPreviewOverflow()
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

  if (isColumnSettingsOpen.value) {
    closeColumnSettings()
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

watch(
  columnOrder,
  () => {
    persistColumnSettings()
  },
  { deep: true },
)

watch(
  columnVisibility,
  () => {
    persistColumnSettings()
  },
  { deep: true },
)

watch(filteredProducts, () => {
  updateCategoryPreviewOverflow()
}, { deep: true })

watch(visibleColumns, () => {
  updateCategoryPreviewOverflow()
}, { deep: true })

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
  restoreColumnSettings()
  void loadProducts()
  window.addEventListener('click', handleWindowClick)
  window.addEventListener('keydown', handleEscapeKey)
  window.addEventListener('resize', handleWindowResize)
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
  window.removeEventListener('resize', handleWindowResize)
})
</script>

<template>
  <div class="grid w-full min-w-0 max-w-full gap-5 overflow-x-hidden">
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
              <p class="mt-1 text-sm text-slate-500">SKU: {{ selectedProduct.offer_id }}</p>
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
              <div class="text-xs font-medium text-slate-500">Артикул Маркета</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">{{ selectedProduct.sku || '—' }}</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Категория</div>
              <div v-if="splitCategoryPath(selectedProduct.category).length" class="mt-2 flex flex-wrap gap-1">
                <span
                  v-for="part in splitCategoryPath(selectedProduct.category)"
                  :key="part"
                  class="inline-flex rounded-xl border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium text-slate-700"
                >
                  {{ part }}
                </span>
              </div>
              <div v-else class="mt-1 text-sm font-semibold text-slate-900">—</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Начальная цена</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ formatMoney(selectedProduct.initial_price, selectedProduct.initial_price_currency || 'RUR') }}
              </div>
              <div class="mt-1 text-xs text-slate-500">
                Цена из ERP МойСклад
              </div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Наша цена на ЯМ</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ formatMoney(selectedProduct.market_price, selectedProduct.market_price_currency || 'RUR') }}
              </div>
              <div v-if="selectedProduct.market_price_updated_at" class="mt-1 text-xs text-slate-500">
                Обновлено: {{ formatDateTime(selectedProduct.market_price_updated_at) }}
              </div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Расходы Маркета</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ formatMoney(selectedProduct.market_service_cost, selectedProduct.market_service_cost_currency || 'RUR') }}
              </div>
              <div class="mt-1 text-xs text-slate-500">
                {{ selectedProduct.market_service_cost_note || 'Оценка по API Маркета' }}
              </div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
              <div class="text-xs font-medium text-slate-500">Рекомендованная цена на ЯМ</div>
              <div class="mt-1 text-sm font-semibold text-slate-900">
                {{ formatMoney(getRecommendedMarketPrice(selectedProduct), selectedProduct.recommended_market_price_currency || selectedProduct.initial_price_currency || selectedProduct.market_service_cost_currency || 'RUR') }}
              </div>
              <div class="mt-1 text-xs text-slate-500">
                {{ selectedProduct.recommended_market_price_note || 'Цена, при которой выплата после комиссий ≈ начальной цене' }}
              </div>
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
            <div v-if="selectedProduct.market_service_cost_breakdown?.length" class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
              <div class="text-xs font-medium text-slate-500">Детализация расходов</div>
              <ul class="mt-2 space-y-1 text-sm text-slate-700">
                <li
                  v-for="item in selectedProduct.market_service_cost_breakdown"
                  :key="`${item.type}-${item.amount}`"
                  class="flex items-center justify-between gap-3"
                >
                  <span>{{ formatTariffType(item.type) }}</span>
                  <span class="font-semibold text-slate-900">{{ formatMoney(item.amount, item.currency) }}</span>
                </li>
              </ul>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div class="text-xs font-medium text-slate-500">Добавлен в мониторинг</div>
                  <div class="mt-1 text-sm font-semibold text-slate-900">{{ formatDateTime(selectedProduct.created_at) }}</div>
                </div>

                <a
                  v-if="selectedProduct.market_url"
                  :href="selectedProduct.market_url"
                  target="_blank"
                  rel="noreferrer"
                  class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                  Открыть на Маркете
                </a>
              </div>
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
      </div>

      <div class="mt-5 relative" ref="searchContainer">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-slate-700">Найди и добавь товар из нашего каталога в ЯМ для отслеживания</span>
          <input
            v-model="searchQuery"
            type="text"
            class="w-full text-[14px] rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            placeholder="Например, витамин d3, SKU-1001 или артикул Маркета"
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
                    SKU: {{ product.offer_id }} · Артикул Маркета: {{ product.sku || '—' }}
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

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isColumnSettingsOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        @click="closeColumnSettings"
      >
        <div
          class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl"
          @click.stop
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">Настройка таблицы</p>
              <h3 class="mt-2 text-xl font-semibold text-slate-900">Отображаемые столбцы</h3>
              <p class="mt-1 text-sm text-slate-500">Включайте нужные столбцы и перетаскивайте их прямо в шапке таблицы.</p>
            </div>

            <button
              type="button"
              class="inline-flex cursor-pointer h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:bg-slate-50 hover:text-slate-700 hover:shadow-md"
              aria-label="Закрыть настройки столбцов"
              title="Закрыть"
              @click="closeColumnSettings"
            >
              <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              </svg>
            </button>
          </div>

          <div class="mt-5 grid gap-2 sm:grid-cols-2">
            <label
              v-for="column in orderedColumns"
              :key="column.key"
              class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
              <div class="flex items-center gap-3">
                <span class="text-slate-400">⋮⋮</span>
                <span class="text-sm font-medium text-slate-800">{{ column.label }}</span>
              </div>

              <input
                :checked="columnVisibility[column.key]"
                type="checkbox"
                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                :disabled="column.hideable === false"
                @change="column.hideable === false ? null : (columnVisibility[column.key] = !columnVisibility[column.key])"
              />
            </label>
          </div>

          <div class="mt-5 flex items-center justify-between gap-2">
            <button
              type="button"
              class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
              @click="resetColumnSettings"
            >
              Сбросить
            </button>

            <button
              type="button"
              class="cursor-pointer rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
              @click="closeColumnSettings"
            >
              Готово
            </button>
          </div>
        </div>
      </div>
    </transition>

    <div class="min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex-1 flex flex-wrap gap-3 justify-between">
          <label class="block basis-[full] w-full">
            <span class="mb-1 block text-sm font-medium text-slate-700">Поиск по добавленным товарам</span>

            <div class="relative">
              <input
                v-model="tableFilterQuery"
                type="text"
                class="w-full text-[14px] rounded-xl border border-slate-300 bg-white px-3 py-2 pr-11 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="Поиск по названию, SKU, артикулу Маркета или категории"
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

          <div class="flex flex-wrap gap-2 basis-3/4!">
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
          <div class="flex flex-wrap items-center justify-between gap-2 lg:justify-end basis-1/5!">
        
          <button
            type="button"
            class="group inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 hover:shadow-md"
            title="Настроить столбцы"
            aria-label="Настроить столбцы"
            @click="openColumnSettings"
          >
            <svg class="h-[18px] w-[18px] transition duration-150 group-hover:scale-105" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4.75 6.75H9.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              <path d="M4.75 12H14.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              <path d="M4.75 17.25H11.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              <circle cx="12.5" cy="6.75" r="1.75" stroke="currentColor" stroke-width="1.7"/>
              <circle cx="17.5" cy="12" r="1.75" stroke="currentColor" stroke-width="1.7"/>
              <circle cx="14.5" cy="17.25" r="1.75" stroke="currentColor" stroke-width="1.7"/>
            </svg>
          </button>

          <button
            type="button"
            class="group inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 hover:shadow-md"
            title="Обновить таблицу"
            aria-label="Обновить таблицу"
            @click="loadProducts"
          >
            <svg class="h-[18px] w-[18px] transition-transform duration-200 group-hover:rotate-180" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M20 11.5A8.5 8.5 0 1 1 17.2 5.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M20 4.75V8.5H16.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        </div>

        
      </div>

      <div v-if="isLoading" class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-600">
        Загружаем товары и актуальные данные Маркета...
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

      <div v-else class="w-full max-w-full overflow-x-auto overscroll-x-contain pb-2">
        <table class="min-w-[1700px] table-auto text-left text-sm">
          <thead class="bg-slate-200">
            <tr class="text-slate-500">
              <th
                v-for="(column, index) in visibleColumns"
                :key="column.key"
                draggable="true"
                class="select-none px-3 py-3 font-medium whitespace-nowrap"
                :class="[
                  index === 0 ? 'rounded-l-2xl' : '',
                  index === visibleColumns.length - 1 ? 'rounded-r-2xl' : '',
                  draggedColumnKey === column.key ? 'bg-slate-300' : '',
                ]"
                @dragstart="startColumnDrag(column.key)"
                @dragover.prevent
                @drop.prevent="handleColumnDrop(column.key)"
                @dragend="draggedColumnKey = null"
              >
                <div class="flex items-center gap-2" :class="column.key === 'delete' ? 'justify-center' : ''">
                  
                  <span class="text-slate-400 cursor-move">⋮⋮</span>
                  <span>{{ column.label }}</span>
                </div>
              </th>
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
              <template v-for="column in visibleColumns" :key="`${product.id}-${column.key}`">
                <td v-if="column.key === 'name'" class="min-w-[280px] px-3 py-3 font-medium text-slate-900 align-top">
                  <div>{{ product.name }}</div>
                </td>

                <td v-else-if="column.key === 'initial_price'" class="min-w-[180px] px-3 py-3 align-top whitespace-nowrap">
                  <div class="font-medium text-slate-900">
                    {{ formatMoney(product.initial_price, product.initial_price_currency || 'RUR') }}
                  </div>
                  <div class="mt-0.5 text-xs text-slate-400">
                    Цена из ERP
                  </div>
                </td>

                <td v-else-if="column.key === 'market_price'" class="min-w-[180px] px-3 py-3 align-top whitespace-nowrap">
                  <div class="font-medium text-slate-900">
                    {{ formatMoney(product.market_price, product.market_price_currency || 'RUR') }}
                  </div>
                  <div v-if="product.market_price_updated_at" class="mt-0.5 text-xs text-slate-400">
                    {{ formatDateTime(product.market_price_updated_at) }}
                  </div>
                </td>

                <td v-else-if="column.key === 'market_service_cost'" class="min-w-[300px] max-w-[300px] px-3 py-3 align-top whitespace-normal break-words">
                  <div class="font-medium text-slate-900 whitespace-nowrap">
                    {{ formatMoney(product.market_service_cost, product.market_service_cost_currency || 'RUR') }}
                  </div>
                  <div class="mt-0.5 text-xs leading-4 text-slate-400 whitespace-normal break-words flex items-center gap-1">
                    <span v-if="product.market_service_cost_has_all_real_data">
                      расчет с учетом реальных данных
                    </span>
                    <span v-else>
                      Отсутствуют некоторые данные
                      <span class="inline-block align-middle cursor-pointer group relative" tabindex="0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-500 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 100 14 7 7 0 000-14z" /></svg>
                        <div class="absolute left-1/2 z-10 mt-2 w-56 -translate-x-1/2 rounded-xl border border-yellow-200 bg-white p-3 text-xs text-yellow-900 shadow-lg opacity-0 group-hover:opacity-100 group-focus:opacity-100 pointer-events-none group-hover:pointer-events-auto group-focus:pointer-events-auto transition-opacity duration-200" style="min-width:180px;">
                          <div class="font-semibold mb-1">Не хватает данных:</div>
                          <ul class="list-disc pl-4">
                            <li v-for="field in product.market_service_cost_missing_data" :key="field">{{ field }}</li>
                          </ul>
                        </div>
                      </span>
                    </span>
                  </div>
                </td>

                <td v-else-if="column.key === 'recommended_market_price'" class="min-w-[210px] px-3 py-3 align-top whitespace-nowrap">
                  <div class="font-medium text-slate-900">
                    {{ formatMoney(getRecommendedMarketPrice(product), product.recommended_market_price_currency || product.initial_price_currency || product.market_service_cost_currency || 'RUR') }}
                  </div>
                  <div class="mt-0.5 text-xs text-slate-400">
                    {{ product.recommended_market_price_note || 'После комиссий выплата ≈ начальной цене' }}
                  </div>
                </td>

                <td v-else-if="column.key === 'offer_id'" class="min-w-[140px] px-3 py-3 align-top whitespace-nowrap">{{ product.offer_id }}</td>
                <td v-else-if="column.key === 'sku'" class="min-w-[140px] px-3 py-3 align-top whitespace-nowrap">{{ product.sku || '—' }}</td>
                <td v-else-if="column.key === 'category'" class="min-w-[260px] px-3 py-3 align-top">
                  <div v-if="splitCategoryPath(product.category).length" class="max-w-[260px]">
                    <div
                      :ref="(el) => setCategoryPreviewRef(product.id, el as Element | null)"
                      class="flex max-h-[3.25rem] flex-wrap content-start gap-1 overflow-hidden"
                    >
                      <span
                        v-for="part in previewCategoryPath(product.category, product.id)"
                        :key="`${product.id}-${part}`"
                        class="inline-flex h-6 items-center rounded-xl border border-slate-200 bg-slate-50 px-2 py-0 text-[11px] leading-none font-medium text-slate-700"
                      >
                        {{ part }}
                      </span>

                      <span
                        v-if="categoryPreviewOverflow[product.id]"
                        class="inline-flex h-6 items-center rounded-xl border border-slate-200 bg-slate-50 px-2 py-0 text-[11px] leading-none font-medium text-slate-700"
                      >
                        ...
                      </span>
                    </div>
                  </div>
                  <span v-else>—</span>
                </td>

                <td v-else-if="column.key === 'campaigns'" class="min-w-[160px] px-3 py-3 align-top whitespace-nowrap">
                  <span v-if="product.campaign_ids?.length">
                    {{ product.campaign_ids.join(', ') }}
                  </span>
                  <span v-else>—</span>
                </td>

                <td v-else-if="column.key === 'status'" class="min-w-[140px] px-3 py-3 align-top whitespace-nowrap">
                  <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                    {{ product.status }}
                  </span>
                </td>

                <td v-else-if="column.key === 'monitoring_enabled'" class="min-w-[150px] px-3 py-3 align-top whitespace-nowrap" @click.stop>
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

                <td v-else-if="column.key === 'delete'" class="min-w-[96px] px-3 py-3 text-center align-top" @click.stop>
                  <button
                    type="button"
                    class="group inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-500 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60"
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

                <td v-else-if="column.key === 'created_at'" class="min-w-[130px] px-3 py-3 align-top whitespace-nowrap">{{ formatDate(product.created_at) }}</td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
