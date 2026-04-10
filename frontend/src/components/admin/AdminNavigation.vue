<template>
  <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
    <RouterLink
      :to="{ name: 'admin-users' }"
      class="rounded-full border px-3 py-1.5 text-sm transition"
      active-class="border-slate-900 bg-slate-900 text-white"
      exact-active-class="border-slate-900 bg-slate-900 text-white"
    >
      Пользователи системы
    </RouterLink>

    <RouterLink
      :to="{ name: 'admin-integrations' }"
      class="rounded-full border px-3 py-1.5 text-sm transition"
      active-class="border-slate-900 bg-slate-900 text-white"
      exact-active-class="border-slate-900 bg-slate-900 text-white"
    >
      Интеграции
    </RouterLink>
  </div>
</template>
