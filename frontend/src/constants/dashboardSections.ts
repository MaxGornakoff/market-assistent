export type DashboardSectionKey = 'dashboard' | 'yandex-market' | 'moysklad' | 'analytics'

export interface DashboardSection {
  key: DashboardSectionKey
  label: string
  title: string
  description: string
  placeholderTitle: string
  placeholderText: string
  bullets: string[]
}

export const defaultDashboardSection: DashboardSectionKey = 'dashboard'

export const dashboardSections: DashboardSection[] = [
  {
    key: 'dashboard',
    label: 'Dashboard',
    title: 'Dashboard',
    description: 'Общий обзор ключевых рабочих зон и быстрый переход к главным операционным блокам.',
    placeholderTitle: 'Контейнер раздела «Dashboard»',
    placeholderText: 'Здесь позже появятся сводка по ключевым метрикам, быстрые действия и приоритетные уведомления по ценообразованию.',
    bullets: ['Ключевые показатели', 'Быстрые действия', 'Приоритетные задачи'],
  },
  {
    key: 'yandex-market',
    label: 'Яндекс Маркет',
    title: 'Яндекс Маркет',
    description: 'Управление ценами, стратегиями и публикацией данных в маркетплейс.',
    placeholderTitle: 'Контейнер раздела «Яндекс Маркет»',
    placeholderText: 'Здесь позже появятся инструменты управления ценами, ставки, статусы публикации и мониторинг карточек на Маркете.',
    bullets: ['Цены и стратегии', 'Публикация и статусы', 'Работа с карточками'],
  },
  {
    key: 'moysklad',
    label: 'ERP МойСклад',
    title: 'ERP МойСклад',
    description: 'Синхронизация остатков, закупочных цен и карточек товаров.',
    placeholderTitle: 'Контейнер раздела «ERP МойСклад»',
    placeholderText: 'Сюда можно будет подключить обмен остатками, закупочными ценами, товарами и служебными справочниками из ERP.',
    bullets: ['Остатки и себестоимость', 'Импорт товаров', 'Синхронизация справочников'],
  },
  {
    key: 'analytics',
    label: 'Аналитика',
    title: 'Аналитика',
    description: 'Маржинальность, рекомендованные цены и контроль конкурентов.',
    placeholderTitle: 'Контейнер раздела «Аналитика»',
    placeholderText: 'Этот блок будет использоваться для аналитических дашбордов, оценки маржинальности и поиска точек роста по ассортименту.',
    bullets: ['Маржинальность', 'Рекомендованные цены', 'Сравнение с конкурентами'],
  },
]

export function isDashboardSectionKey(value: string): value is DashboardSectionKey {
  return dashboardSections.some((section) => section.key === value)
}
