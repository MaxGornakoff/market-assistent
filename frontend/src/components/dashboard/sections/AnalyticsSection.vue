<script setup lang="ts">
const widgets = [
  {
    title: 'Маржинальность',
    text: 'Панель расчёта прибыли и оценки влияния ценовых изменений на ассортимент и категории.',
  },
  {
    title: 'Рекомендованные цены',
    text: 'Блок под будущие модели расчёта оптимальной цены на основе правил, остатков и спроса.',
  },
  {
    title: 'Контроль отклонений',
    text: 'Отслеживание критичных изменений, выбросов, а также сравнение с конкурентной средой.',
  },
]
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Аналитика</p>
        <h2 class="mt-2 text-xl font-semibold text-slate-900">Аналитический рабочий блок</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
          Независимый компонент под дашборды, аналитические срезы, рекомендации и контроль показателей.
        </p>
      </div>

      <span class="inline-flex rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">
        Аналитический модуль
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
