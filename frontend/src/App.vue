<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import DemoDataNotice from './components/system/DemoDataNotice.vue'
import { useAuthStore } from './stores/auth'
import { useSystemStore } from './stores/system'

const system = useSystemStore()
const auth = useAuthStore()
const route = useRoute()
onMounted(() => Promise.all([system.load(), auth.probe()]))
</script>

<template>
  <router-view />
  <!-- El checkout simulado imita un sitio aparte, que no mostraría el aviso de GymTrack. -->
  <DemoDataNotice v-if="route.name !== 'pago-simulado'" />
</template>
