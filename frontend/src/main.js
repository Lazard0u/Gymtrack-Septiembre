/**
 * Punto de entrada del frontend. Carga estilos, Pinia y router antes de montar Vue en el elemento #app.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@fontsource-variable/manrope'
import './styles/tokens.css'
import './styles/base.css'

import App from './App.vue'
import router from './router'

createApp(App)
  .use(createPinia())
  .use(router)
  .mount('#app')
