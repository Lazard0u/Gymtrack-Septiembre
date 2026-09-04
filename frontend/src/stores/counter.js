/**
 * Store Pinia de counter. Centraliza estado reactivo, llamadas a la API y errores para que las vistas compartan una única fuente de datos.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { ref, computed } from 'vue'
import { defineStore } from 'pinia'

export const useCounterStore = defineStore('counter', () => {
  const count = ref(0)
  const doubleCount = computed(() => count.value * 2)
  function increment() {
    count.value++
  }

  return { count, doubleCount, increment }
})
