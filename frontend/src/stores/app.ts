import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', () => {
  const projectTitle = ref('Витаминов Маркет')
  const apiBaseUrl = ref(import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api')

  const subtitle = computed(() => 'Управление ценообразованием для Яндекс Маркета')

  return {
    apiBaseUrl,
    projectTitle,
    subtitle,
  }
})
