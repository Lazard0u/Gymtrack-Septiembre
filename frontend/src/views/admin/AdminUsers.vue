<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Gestión de Socios</h1>
    </div>

    <!-- Buscador -->
    <div class="card">
      <div class="buscar-row">
        <input v-model="busqueda" type="text" placeholder="Buscar por nombre o email..."
               @input="buscar" />
        <button @click="buscar" class="btn btn-blue">Buscar</button>
        <button @click="limpiar" class="btn btn-gray">Limpiar</button>
      </div>
    </div>

    <!-- Tabla de socios -->
    <div class="card">
      <div v-if="cargando" class="loading">Cargando socios...</div>
      <div v-else-if="!usuarios.length" class="empty-state">No se encontraron socios.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Membresía</th>
            <th>Vencimiento</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in usuarios" :key="u.id">
            <td><strong>{{ u.nombre }}</strong></td>
            <td>{{ u.email }}</td>
            <td>
              <span :class="badgeMemb(u.membresia_estado)">
                {{ u.membresia_estado || 'sin membresía' }}
              </span>
            </td>
            <td>{{ u.fecha_vencimiento ? formatFecha(u.fecha_vencimiento) : '-' }}</td>
            <td>
              <button @click="abrirModal(u)" class="btn btn-dark btn-sm">Gestionar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal gestión de membresía -->
    <div v-if="modalVisible" class="modal-overlay" @click.self="cerrarModal">
      <div class="modal">
        <h3 class="modal-title">Gestionar membresía — {{ socioSeleccionado?.nombre }}</h3>

        <div v-if="msgModal" :class="`${msgModal.tipo === 'ok' ? 'success-msg' : 'error-msg'}`">
          {{ msgModal.texto }}
        </div>

        <div class="modal-section">
          <h4>Activar / Renovar membresía</h4>
          <div class="form-group">
            <label>Plan</label>
            <select v-model="formMemb.plan">
              <option value="mensual">Mensual</option>
              <option value="trimestral">Trimestral</option>
              <option value="anual">Anual</option>
            </select>
          </div>
          <div class="form-group">
            <label>Precio pagado ($)</label>
            <input v-model="formMemb.precio" type="number" min="0" step="0.01" placeholder="0.00" />
          </div>
          <button @click="activarMembresia" class="btn btn-blue" :disabled="procesando">
            {{ procesando ? 'Procesando...' : '✓ Activar membresía' }}
          </button>
        </div>

        <div class="modal-section">
          <h4>Suspender membresía</h4>
          <button @click="suspenderMembresia" class="btn btn-red" :disabled="procesando">
            Suspender membresía activa
          </button>
        </div>

        <button @click="cerrarModal" class="btn btn-gray modal-close">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const usuarios         = ref([])
const cargando         = ref(true)
const busqueda         = ref('')
const modalVisible     = ref(false)
const socioSeleccionado = ref(null)
const procesando       = ref(false)
const msgModal         = ref(null)
const formMemb         = ref({ plan: 'mensual', precio: '' })

onMounted(() => cargarUsuarios())

async function cargarUsuarios(q = '') {
  cargando.value = true
  try {
    const url = q ? `/admin/usuarios?buscar=${encodeURIComponent(q)}` : '/admin/usuarios'
    const { data } = await api.get(url)
    usuarios.value = data.usuarios
  } finally {
    cargando.value = false
  }
}

function buscar() { cargarUsuarios(busqueda.value) }
function limpiar() { busqueda.value = ''; cargarUsuarios() }

function abrirModal(usuario) {
  socioSeleccionado.value = usuario
  msgModal.value          = null
  formMemb.value          = { plan: 'mensual', precio: '' }
  modalVisible.value      = true
}

function cerrarModal() {
  modalVisible.value = false
  cargarUsuarios(busqueda.value)
}

async function activarMembresia() {
  if (!formMemb.value.precio) { msgModal.value = { tipo: 'error', texto: 'Ingresá el precio.' }; return }
  procesando.value = true
  try {
    await api.post('/membresia/activar', {
      usuario_id: socioSeleccionado.value.id,
      plan:       formMemb.value.plan,
      precio:     parseFloat(formMemb.value.precio),
    })
    msgModal.value = { tipo: 'ok', texto: `Membresía ${formMemb.value.plan} activada correctamente.` }
  } catch (e) {
    msgModal.value = { tipo: 'error', texto: e.response?.data?.mensaje || 'Error al activar.' }
  } finally {
    procesando.value = false
  }
}

async function suspenderMembresia() {
  if (!confirm('¿Suspender la membresía de este socio?')) return
  procesando.value = true
  try {
    await api.post('/membresia/suspender', { usuario_id: socioSeleccionado.value.id })
    msgModal.value = { tipo: 'ok', texto: 'Membresía suspendida correctamente.' }
  } catch (e) {
    msgModal.value = { tipo: 'error', texto: e.response?.data?.mensaje || 'Error al suspender.' }
  } finally {
    procesando.value = false
  }
}

function badgeMemb(estado) {
  const m = { activa: 'badge badge-green', vencida: 'badge badge-red', suspendida: 'badge badge-yellow' }
  return m[estado] || 'badge badge-gray'
}

function formatFecha(f) {
  if (!f) return '-'
  return new Date(f).toLocaleDateString('es-UY')
}
</script>

<style scoped>
.page-title { font-size: 24px; margin-bottom: 24px; color: #1C1C1C; }
.loading, .empty-state { padding: 24px; text-align: center; color: #9ca3af; font-size: 14px; }

.buscar-row { display: flex; gap: 10px; align-items: center; }
.buscar-row input { flex: 1; }

.btn-sm { padding: 5px 12px; font-size: 12px; }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 10px;
  padding: 32px;
  width: 100%;
  max-width: 460px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #1C1C1C; }
.modal-section {
  border-top: 1px solid #e5e7eb;
  padding-top: 16px;
  margin-top: 16px;
}
.modal-section h4 { font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #374151; }
.modal-close { margin-top: 20px; width: 100%; }
</style>
