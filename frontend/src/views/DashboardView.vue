<script setup lang="ts">
const cards = [
  {
    title: 'ERP МойСклад',
    text: 'Подготовлена зона для синхронизации остатков, закупочных цен и карточек товаров.',
  },
  {
    title: 'Яндекс Маркет',
    text: 'Здесь будет управление ценами, стратегиями и публикацией данных в маркетплейс.',
  },
  {
    title: 'Аналитика',
    text: 'Отдельный модуль под маржинальность, рекомендованные цены и контроль конкурентов.',
  },
]
</script>

<template>
  <section class="dashboard">
    <div class="hero">
      <p class="eyebrow">Market Assistant</p>
      <h1>Помощник менеджера по ценообразованию</h1>
      <p class="lead">
        Базовая структура готова: `Vue 3 + Vite` для интерфейса и `Laravel API` для интеграций и бизнес-логики.
      </p>
    </div>

    <div class="card-grid">
      <article v-for="card in cards" :key="card.title" class="card">
        <h2>{{ card.title }}</h2>
        <p>{{ card.text }}</p>
      </article>
    </div>
  </section>
</template>
