<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Gestión de Clases</h1>
      <button @click="abrirFormNueva" class="btn btn-blue">+ Nueva clase</button>
    </div>

    <div v-if="msg" :class="msg.tipo === 'ok' ? 'success-msg' : 'error-msg'">{{ msg.texto }}</div>

    <!-- Formulario crear/editar -->
    <div v-if="formVisible" class="card">
      <h3 class="card-title">{{ editando ? 'Editar clase' : 'Nueva clase' }}</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Nombre de la clase</label>
          <input v-model="form.nombre" type="text" placeholder="Ej: Spinning" />
        </div>
        <div class="form-group">
          <label>Día de la semana</label>
          <select v-model="form.dia_semana">
            <option v-for="d in dias" :key="d" :value="d">{{ capitalizar(d) }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Hora inicio</label>
          <input v-model="form.hora_inicio" type="time" />
        </div>
        <div class="form-group">
          <label>Hora fin</label>
          <input v-model="form.hora_fin" type="time" />
        </div>
        <div class="form-group">
          <label>Cupo máximo</label>
          <input v-model="form.cupo_maximo" type="number" min="1" placeholder="15" />
        </div>
        <div class="form-group">
          <label>ID Instructor</label>
          <input v-model="form.instructor_id" type="number" min="1" placeholder="ID del usuario instructor" />
        </div>
      </div>
      <div class="form-actions">
        <button @click="guardar" class="btn btn-blue" :disabled="procesando">
          {{ procesando ? 'Guardando...' : (editando ? 'Actualizar' : 'Crear clase') }}
        </button>
        <button @click="cancelarForm" class="btn btn-gray">Cancelar</button>
      </div>
    </div>

    <!-- Tabla de clases -->
    <div class="card">
      <div v-if="cargando" class="loading">Cargando clases...</div>
      <div v-else-if="!clases.length" class="empty-state">No hay clases creadas todavía.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Día</th>
            <th>Horario</th>
            <th>Cupos</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in clases" :key="c.id">
            <td><strong>{{ c.nombre }}</strong><br><small>{{ c.instructor_nombre }}</small></td>
            <td>{{ capitalizar(c.dia_semana) }}</td>
            <td>{{ c.hora_inicio }} - {{ c.hora_fin }}</td>
            <td>{{ c.cupos_disponibles }} / {{ c.cupo_maximo }}</td>
            <td>
              <span :class="c.activa ? 'badge badge-green' : 'badge badge-gray'">
                {{ c.activa ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="acciones">
              <button @click="editar(c)" class="btn btn-dark btn-sm">Editar</button>
              <button @click="eliminar(c.id)" class="btn btn-red btn-sm">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const clases      = ref([])
const cargando    = ref(true)
const formVisible = ref(false)
const editando    = ref(false)
const procesando  = ref(false)
const msg         = ref(null)
const dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo']

const formVacio = () => ({
  id: null, nombre: '', dia_semana: 'lunes', hora_inicio: '',
  hora_fin: '', cupo_maximo: '', instructor_id: '', activa: 1
})
const form = ref(formVacio())

onMounted(() => cargarClases())

async function cargarClases() {
  cargando.value = true
  try {
    const { data } = await api.get('/clases/todas')
    clases.value = data.clases
  } finally {
    cargando.value = false
  }
}

function abrirFormNueva() {
  form.value    = formVacio()
  editando.value = false
  formVisible.value = true
  msg.value = null
}

function editar(clase) {
  form.value = { ...clase }
  editando.value    = true
  formVisible.value = true
  msg.value = null
}

function cancelarForm() {
  formVisible.value = false
  form.value = formVacio()
}

async function guardar() {
  if (!form.value.nombre || !form.value.hora_inicio || !form.value.hora_fin || !form.value.instructor_id) {
    msg.value = { tipo: 'error', texto: 'Completá todos los campos obligatorios.' }
    return
  }
  procesando.value = true
  try {
    if (editando.value) {
      await api.put(`/clases/${form.value.id}`, form.value)
      msg.value = { tipo: 'ok', texto: 'Clase actualizada correctamente.' }
    } else {
      await api.post('/clases', form.value)
      msg.value = { tipo: 'ok', texto: 'Clase creada correctamente.' }
    }
    formVisible.value = false
    await cargarClases()
  } catch (e) {
    msg.value = { tipo: 'error', texto: e.response?.data?.mensaje || 'Error al guardar.' }
  } finally {
    procesando.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar esta clase? Se marcará como inactiva.')) return
  try {
    await api.delete(`/clases/${id}`)
    msg.value = { tipo: 'ok', texto: 'Clase eliminada.' }
    await cargarClases()
  } catch (e) {
    msg.value = { tipo: 'error', texto: e.response?.data?.mensaje || 'Error al eliminar.' }
  }
}

function capitalizar(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.page-title { font-size: 24px; color: #1C1C1C; }
.card-title { font-size: 16px; font-weight: bold; margin-bottom: 16px; }
.loading, .empty-state { padding: 24px; text-align: center; color: #9ca3af; font-size: 14px; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-actions { display: flex; gap: 12px; margin-top: 8px; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.acciones { display: flex; gap: 8px; }

small { color: #9ca3af; font-size: 11px; }

@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
</style>
