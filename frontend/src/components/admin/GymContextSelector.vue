<script setup>
import { computed, ref } from 'vue'
import { IconBuilding, IconLifebuoy } from '@tabler/icons-vue'
import { useAdminStore } from '../../stores/admin'
import AppAlert from '../ui/AppAlert.vue'
import AppBadge from '../ui/AppBadge.vue'
import AppButton from '../ui/AppButton.vue'
import AppDialog from '../ui/AppDialog.vue'
import AppSelect from '../ui/AppSelect.vue'
import AppTextarea from '../ui/AppTextarea.vue'

const emit = defineEmits(['changed'])
const admin = useAdminStore()
const dialogOpen = ref(false)
const pendingGymId = ref(null)
const reason = ref('')
const reasonError = ref('')
const requestError = ref('')

const options = computed(() => [
  { value: '', label: 'Seleccionar gimnasio' },
  ...admin.gyms.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre })),
])
const selectedName = computed(() => admin.gyms.find((gym) => gym.gimnasio_id === pendingGymId.value)?.nombre || 'el gimnasio')

async function requestChange(value) {
  const gymId = Number(value)
  if (!gymId || gymId === admin.activeGymId) return
  if (admin.isGlobalAdmin) {
    pendingGymId.value = gymId
    reason.value = ''
    reasonError.value = ''
    requestError.value = ''
    dialogOpen.value = true
    return
  }
  await change(gymId)
}

async function confirm() {
  if (reason.value.trim().length < 8) {
    reasonError.value = 'Escribí al menos 8 caracteres.'
    return
  }
  await change(pendingGymId.value, reason.value.trim())
}

async function change(gymId, supportReason = '') {
  requestError.value = ''
  try {
    await admin.selectGym(gymId, supportReason)
    dialogOpen.value = false
    emit('changed')
  } catch (error) {
    requestError.value = error.message
    reasonError.value = error.fields?.reason || ''
  }
}
</script>

<template>
  <div class="gym-context">
    <div class="gym-context__select">
      <IconBuilding :size="18" aria-hidden="true" />
      <AppSelect :model-value="String(admin.activeGymId || '')" label="Gimnasio activo" :options="options" :disabled="admin.status === 'switching'" @update:model-value="requestChange" />
    </div>
    <AppBadge v-if="admin.supportMode" tone="warning"><IconLifebuoy :size="13" /> Modo soporte</AppBadge>
  </div>

  <AppDialog :open="dialogOpen" title="Entrar en modo soporte" :description="`Vas a consultar ${selectedName}. Esta acción quedará registrada.`" @close="dialogOpen = false">
    <div class="support-form">
      <AppAlert tone="warning" title="Acceso administrativo global"><p>Indicá un motivo concreto. El gimnasio, tu cuenta y el identificador de la petición se guardarán en auditoría.</p></AppAlert>
      <AppTextarea v-model="reason" label="Motivo de soporte" name="support-reason" :rows="3" :error="reasonError" hint="Por ejemplo: revisión de permisos solicitada por el dueño." />
      <AppAlert v-if="requestError" tone="danger"><p>{{ requestError }}</p></AppAlert>
    </div>
    <template #footer>
      <AppButton variant="ghost" @click="dialogOpen = false">Cancelar</AppButton>
      <AppButton :loading="admin.status === 'switching'" @click="confirm">Entrar al gimnasio</AppButton>
    </template>
  </AppDialog>
</template>

<style scoped>
.gym-context { display: flex; align-items: center; gap: var(--space-3); }
.gym-context__select { display: flex; align-items: center; gap: var(--space-2); }
.gym-context__select > svg { flex: 0 0 auto; color: var(--text-tertiary); }
.gym-context__select :deep(.field) { display: block; }
.gym-context__select :deep(.field > span:first-child) { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
.gym-context__select :deep(.field__control) { width: clamp(11rem, 20vw, 17rem); min-height: 2.35rem; background: var(--surface-2); font-size: .8rem; }
.support-form { display: grid; gap: var(--space-5); }
@media (max-width: 47.99rem) { .gym-context { align-items: stretch; flex-direction: column; }.gym-context__select :deep(.field), .gym-context__select :deep(.field__control) { width: 100%; } }
</style>
