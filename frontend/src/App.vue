<script setup lang="ts">
import { RouterView } from 'vue-router'

import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <div>
        <p class="eyebrow">{{ appStore.projectTitle }}</p>
        <h1>{{ appStore.subtitle }}</h1>
      </div>

      <code class="api-badge">{{ appStore.apiBaseUrl }}</code>
    </header>

    <RouterView />
  </div>
</template>
