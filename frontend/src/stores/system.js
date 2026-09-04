/**
 * Store Pinia de system. Centraliza estado reactivo, llamadas a la API y errores para que las vistas compartan una única fuente de datos.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../services/api'

export const useSystemStore = defineStore('system', () => {
  const loaded = ref(false)
  const demoDataActive = ref(false)
  const demoDatasetName = ref(null)
  const turnstileEnabled = ref(false)
  const turnstileSiteKey = ref(null)
  const features = ref({
    whatsapp: false,
    google_calendar_sync: false,
    advanced_analytics: false,
    personal_recommendations: false,
  })

  const betaFeatures = computed(() => Object.entries(features.value)
    .filter(([, enabled]) => enabled)
    .map(([key]) => key))

  async function load() {
    const { ok, data } = await api.get('/system/context')
    if (ok && !data.error) {
      demoDataActive.value = Boolean(data.demo_data_active)
      demoDatasetName.value = data.demo_dataset_name || null
      turnstileEnabled.value = Boolean(data.turnstile_enabled)
      turnstileSiteKey.value = data.turnstile_site_key || null
      features.value = { ...features.value, ...(data.features || {}) }
    }
    loaded.value = true
  }

  return { loaded, demoDataActive, demoDatasetName, turnstileEnabled, turnstileSiteKey, features, betaFeatures, load }
})
