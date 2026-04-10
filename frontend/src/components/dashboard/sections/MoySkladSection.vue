<script setup lang="ts">
const widgets = [
  {
    title: 'Остатки и наличие',
    text: 'Сюда можно подключить обмен остатками, резервами и доступностью товаров из ERP МойСклад.',
  },
  {
    title: 'Себестоимость',
    text: 'Отдельный блок под закупочные цены, расчёт маржи и обновление данных для ценообразования.',
  },
  {
    title: 'Номенклатура',
    text: 'Импорт товаров, категорий, атрибутов и служебных справочников из ERP в приложение.',
  },
]
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">ERP МойСклад</p>
        <h2 class="mt-2 text-xl font-semibold text-slate-900">Контейнер синхронизации ERP</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
          Независимый компонент под интеграцию с МойСкладом: остатки, себестоимость и номенклатура.
        </p>
      </div>

      <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
        ERP-контур
      </span>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-3">
      <div
        v-for="widget in widgets"
        :key="widget.title"
        class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4"
      >
        <h3 class="mb-2 text-sm font-semibold text-slate-900">{{ widget.title }}</h3>
        <p class="text-sm leading-6 text-slate-600">{{ widget.text }}</p>
      </div>
    </div>
  </div>
</template>
