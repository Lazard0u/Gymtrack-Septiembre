<script setup>
import { computed, onMounted } from 'vue'
import AdminUnavailableView from './AdminUnavailableView.vue'
import { useSystemStore } from '../../stores/system'

const system = useSystemStore()
onMounted(() => system.load())
const enabled = computed(() => system.features.promotions_beta === true || system.betaFeatures.includes('promotions_beta'))
</script>

<template><AdminUnavailableView title="Promociones" description="Espacio reservado para campañas segmentadas del gimnasio activo." :works="enabled ? 'La función beta está habilitada y comunica el alcance del escenario promocional, sin enviar campañas ni aplicar descuentos.' : 'La sección está visible para planificación, pero su feature flag permanece desactivado.'" pending="Crear, programar, pausar y medir promociones con audiencia, consentimiento, canales e idempotencia." phase="Fase 9" beta /></template>
