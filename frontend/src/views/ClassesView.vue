<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Clases Disponibles</h1>
      <div class="filtros">
        <select v-model="filtroDia">
          <option value="">Todos los días</option>
          <option v-for="d in dias" :key="d" :value="d">{{ capitalizar(d) }}</option>
        </select>
      </div>
    </div>

    <div v-if="!memActiva" class="alerta-memb">
      ⚠️ Tu membresía está vencida o inactiva. Contactá a la administración para renovarla.
    </div>

    <div v-if="cargando" class="loading">Cargando clases...</div>
    <div v-else-if="!clasesFiltradas.length" class="empty-state">
      No hay clases disponibles para el filtro seleccionado.
    </div>

    <div v-else class="clases-grid">
      <div v-for="clase in clasesFiltradas" :key="clase.id" class="clase-card">
        <div class="clase-header">
          <h3>{{ clase.nombre }}</h3>
          <span :class="clase.cupos_disponibles > 0 ? 'badge badge-green' : 'badge badge-red'">
            {{ clase.cupos_disponibles > 0 ? `${clase.cupos_disponibles} cupos` : 'Sin cupos' }}
          </span>
        </div>
        <div class="clase-info">
          <div class="info-row">
            <span class="info-label">Día:</span>
            <span>{{ capitalizar(clase.dia_semana) }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Horario:</span>
            <span>{{ clase.hora_inicio }} - {{ clase.hora_fin }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Instructor:</span>
            <span>{{ clase.instructor_nombre }}</span>
          </div>
        </div>
        <button
          @click="reservar(clase.id)"
          class="btn btn-blue btn-full"
          :disabled="!memActiva || clase.cupos_disponibles === 0 || reservando === clase.id"
          :title="!memActiva ? 'Membresía inactiva' : clase.cupos_disponibles === 0 ? 'Sin cupos' : ''"
        >
          {{ reservando === clase.id ? 'Reservando...' : 'Reservar' }}
        </button>
      </div>
    </div>

    <!-- Toast de confirmación -->
    <div v-if="toast.visible" :class="`toast toast-${toast.tipo}`">{{ toast.mensaje }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const clases    = ref([])
const cargando  = ref(true)
const memActiva = ref(false)
const filtroDia = ref('')
const reservando = ref(null)
const dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo']
const toast = ref({ visible: false, mensaje: '', tipo: 'ok' })

onMounted(async () => {
  await Promise.all([cargarClases(), verificarMembresia()])
})

async function cargarClases() {
  try {
    const { data } = await api.get('/clases')
    clases.value = data.clases
  } finally {
    cargando.value = false
  }
}

async function verificarMembresia() {
  try {
    const { data } = await api.get('/membresia/mia')
    memActiva.value = data.membresia?.estado === 'activa'
  } catch (_) { memActiva.value = false }
}

async function reservar(claseId) {
  reservando.value = claseId
  try {
    await api.post('/reservas', { clase_id: claseId })
    mostrarToast('¡Reserva confirmada exitosamente!', 'ok')
    await cargarClases() // Actualizar cupos
  } catch (e) {
    mostrarToast(e.response?.data?.mensaje || 'Error al reservar.', 'error')
  } finally {
    reservando.value = null
  }
}

function mostrarToast(mensaje, tipo) {
  toast.value = { visible: true, mensaje, tipo }
  setTimeout(() => { toast.value.visible = false }, 3000)
}

const clasesFiltradas = computed(() =>
  filtroDia.value
    ? clases.value.filter(c => c.dia_semana === filtroDia.value)
    : clases.value
)

function capitalizar(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title { font-size: 24px; color: #1C1C1C; }

.filtros select { width: auto; min-width: 160px; }

.alerta-memb {
  background: #fee2e2;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 20px;
  font-size: 14px;
}

.loading, .empty-state {
  text-align: center;
  padding: 40px;
  color: #9ca3af;
  font-size: 15px;
}

.clases-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}

.clase-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  border-top: 3px solid #38BDF8;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.clase-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.clase-header h3 { font-size: 16px; font-weight: bold; color: #1C1C1C; }

.clase-info { flex: 1; }

.info-row {
  display: flex;
  gap: 8px;
  font-size: 13px;
  margin-bottom: 5px;
  color: #374151;
}

.info-label { color: #9ca3af; width: 72px; flex-shrink: 0; }

.btn-full { width: 100%; }

/* Toast */
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  padding: 14px 20px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  z-index: 9999;
  animation: fadeIn 0.2s ease;
}

.toast-ok    { background: #dcfce7; color: #16a34a; }
.toast-error { background: #fee2e2; color: #dc2626; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; } }
</style>
