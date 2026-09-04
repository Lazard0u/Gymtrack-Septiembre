/**
 * Prueba automatizada de setup. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { afterEach } from 'vitest'
import { config } from '@vue/test-utils'

config.global.stubs = {
  transition: false,
}

afterEach(() => {
  document.body.innerHTML = ''
  document.body.style.overflow = ''
  localStorage.clear()
})
