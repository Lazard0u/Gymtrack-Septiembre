<template>
  <div class="admin-container">
    <header class="admin-header">
      <div>
        <h1>Panel de Administración</h1>
        <p>Gestioná socios, membresías, clases, reservas y estadísticas.</p>
      </div>
      <div class="admin-actions">
        <router-link to="/" class="btn-outline">Inicio</router-link>
        <router-link to="/dashboard" class="btn-outline">Volver al Dashboard</router-link>
      </div>
    </header>

    <p v-if="cargando" class="feedback loading" role="status" aria-live="polite">
      Cargando información administrativa…
    </p>

    <section class="stats-row">
      <article class="stat-card">
        <strong>{{ stats.socios_activos }}</strong>
        <span>Socios activos</span>
      </article>
      <article class="stat-card">
        <strong>{{ stats.clases_hoy }}</strong>
        <span>Clases hoy</span>
      </article>
      <article class="stat-card">
        <strong>{{ stats.socios_membresias_por_vencer }}</strong>
        <span>Socios con membresía por vencer</span>
      </article>
    </section>

    <section id="socios" class="admin-section">
      <div class="section-header">
        <h2>Gestión de socios</h2>
        <span>Activa o desactiva cuentas y revisa el perfil de cada socio.</span>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Activo</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="socio in socios" :key="socio.id">
              <td>{{ socio.nombre }}</td>
              <td>{{ socio.email }}</td>
              <td>{{ socio.telefono || '—' }}</td>
              <td>{{ socio.activo ? 'Sí' : 'No' }}</td>
              <td>{{ formatearFecha(socio.creado_en) }}</td>
              <td>
                <button class="btn-small" @click="toggleActivo(socio)" :disabled="Boolean(accionEnCurso)">
                  {{ accionEnCurso === `socio-${socio.id}` ? 'Guardando…' : socio.activo ? 'Desactivar' : 'Activar' }}
                </button>
                <button class="btn-small secondary" @click="verPerfil(socio.id)" :disabled="Boolean(accionEnCurso)">Perfil</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section id="membresias" class="admin-section">
      <div class="section-header">
        <h2>Membresías</h2>
        <span>Crear membresías y ver vencimientos.</span>
      </div>

      <form class="card-form" @submit.prevent="crearMembresia">
        <div class="form-grid">
          <label>
            Socio
            <select v-model="membresia.usuario_id" required>
              <option value="" disabled>Seleccioná un socio</option>
              <option v-for="socio in socios" :value="socio.id" :key="socio.id">
                {{ socio.nombre }} — {{ socio.email }}
              </option>
            </select>
          </label>
          <label>
            Plan
            <select v-model="membresia.plan" required>
              <option value="mensual">Mensual</option>
              <option value="trimestral">Trimestral</option>
              <option value="anual">Anual</option>
            </select>
          </label>
          <label>
            Fecha inicio
            <input type="date" v-model="membresia.fecha_inicio" required />
          </label>
          <label>
            Fecha vencimiento
            <input type="date" v-model="membresia.fecha_vencimiento" required />
          </label>
          <label>
            Precio pagado
            <input type="number" min="0" step="0.01" v-model="membresia.precio_pagado" required />
          </label>
        </div>
        <button class="btn-primary" type="submit" :disabled="Boolean(accionEnCurso)">
          {{ accionEnCurso === 'crear-membresia' ? 'Creando…' : 'Crear membresía' }}
        </button>
      </form>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Socio</th>
              <th>Plan</th>
              <th>Inicio</th>
              <th>Vence</th>
              <th>Estado</th>
              <th>Precio</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in membresias" :key="item.id">
              <td>{{ item.usuario_nombre }}</td>
              <td>{{ item.plan }}</td>
              <td>{{ item.fecha_inicio }}</td>
              <td>{{ item.fecha_vencimiento }}</td>
              <td>{{ item.estado }}</td>
              <td>{{ item.precio_pagado }} USD</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section id="clases" class="admin-section">
      <div class="section-header">
        <h2>Clases</h2>
        <span>Crear nuevas clases, editarlas y ver quiénes están inscriptos.</span>
      </div>

      <form class="card-form" @submit.prevent="crearClase">
        <div class="form-grid">
          <label>
            Nombre
            <input type="text" v-model="clase.nombre" required />
          </label>
          <label>
            Instructor (ID)
            <select v-model="clase.instructor_id" required>
              <option value="" disabled>Seleccioná instructor</option>
              <option v-for="socio in socios" :value="socio.id" :key="socio.id">
                {{ socio.nombre }}
              </option>
            </select>
          </label>
          <label>
            Día de la semana
            <select v-model="clase.dia_semana" required>
              <option value="lunes">Lunes</option>
              <option value="martes">Martes</option>
              <option value="miercoles">Miércoles</option>
              <option value="jueves">Jueves</option>
              <option value="viernes">Viernes</option>
              <option value="sabado">Sábado</option>
              <option value="domingo">Domingo</option>
            </select>
          </label>
          <label>
            Hora inicio
            <input type="time" v-model="clase.hora_inicio" required />
          </label>
          <label>
            Hora fin
            <input type="time" v-model="clase.hora_fin" required />
          </label>
          <label>
            Cupo máximo
            <input type="number" min="1" v-model="clase.cupo_maximo" required />
          </label>
        </div>
        <button class="btn-primary" type="submit" :disabled="Boolean(accionEnCurso)">
          {{ accionEnCurso === 'crear-clase' ? 'Creando…' : 'Crear clase' }}
        </button>
      </form>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Instructor</th>
              <th>Horario</th>
              <th>Cupo</th>
              <th>Disponibles</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cl in clases" :key="cl.id">
              <td>{{ cl.nombre }}</td>
              <td>{{ cl.instructor_nombre }}</td>
              <td>{{ cl.dia_semana }} {{ cl.hora_inicio }}-{{ cl.hora_fin }}</td>
              <td>{{ cl.cupo_maximo }}</td>
              <td>{{ cl.cupos_disponibles }}</td>
              <td>{{ cl.activa ? 'Activa' : 'Cancelada' }}</td>
              <td>
                <button class="btn-small" @click="cargarInscriptos(cl.id)" :disabled="Boolean(accionEnCurso)">Ver inscriptos</button>
                <button class="btn-small secondary" @click="cancelarClase(cl.id)" :disabled="!cl.activa || Boolean(accionEnCurso)">
                  {{ accionEnCurso === `clase-${cl.id}` ? 'Cancelando…' : 'Cancelar' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="inscriptos.length" class="table-wrapper small-table">
        <h3>Inscriptos en la clase</h3>
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Reserva</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in inscriptos" :key="i.reserva_id">
              <td>{{ i.usuario_nombre }}</td>
              <td>{{ i.usuario_email }}</td>
              <td>{{ i.fecha_reserva }}</td>
              <td>{{ i.estado }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section id="reservas" class="admin-section">
      <div class="section-header">
        <h2>Reservas</h2>
        <span>Ver las reservas de hoy o de la semana y cancelarlas.</span>
      </div>

      <div class="reservas-filters">
        <button class="btn-small" :class="{ active: periodo === 'day' }" @click="cambiarPeriodo('day')" :disabled="cargando">Hoy</button>
        <button class="btn-small" :class="{ active: periodo === 'week' }" @click="cambiarPeriodo('week')" :disabled="cargando">Semana</button>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Socio</th>
              <th>Email</th>
              <th>Clase</th>
              <th>Fecha reserva</th>
              <th>Estado</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="reserva in reservas" :key="reserva.id">
              <td>{{ reserva.usuario_nombre }}</td>
              <td>{{ reserva.usuario_email }}</td>
              <td>{{ reserva.clase_nombre }}</td>
              <td>{{ reserva.fecha_reserva }}</td>
              <td>{{ reserva.estado }}</td>
              <td>
                <button class="btn-small secondary" @click="cancelarReserva(reserva.id)" :disabled="reserva.estado === 'cancelada' || Boolean(accionEnCurso)">
                  {{ accionEnCurso === `reserva-${reserva.id}` ? 'Cancelando…' : 'Cancelar' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="mensaje" class="feedback success" role="status" aria-live="polite">{{ mensaje }}</div>
    <div v-if="error" class="feedback error" role="alert" aria-live="assertive">{{ error }}</div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'

const authStore = useAuthStore()
const router = useRouter()

const stats = ref({ socios_activos: 0, clases_hoy: 0, socios_membresias_por_vencer: 0 })
const socios = ref([])
const membresias = ref([])
const clases = ref([])
const reservas = ref([])
const inscriptos = ref([])
const periodo = ref('day')
const mensaje = ref('')
const error = ref('')
const cargando = ref(true)
const accionEnCurso = ref('')

const membresia = reactive({ usuario_id: '', plan: 'mensual', fecha_inicio: '', fecha_vencimiento: '', precio_pagado: '' })
const clase = reactive({ nombre: '', instructor_id: '', dia_semana: 'lunes', hora_inicio: '08:00', hora_fin: '09:00', cupo_maximo: 12 })

const cargarTodo = async () => {
  cargando.value = true
  error.value = ''
  try {
    await Promise.all([cargarSocios(), cargarMembresias(), cargarClases(), cargarReservas(), cargarEstadisticas()])
  } finally {
    cargando.value = false
  }
}

const cargarSocios = async () => {
  const { ok, data } = await api.get('/admin/socios')
  if (ok && !data.error) {
    socios.value = data.socios
    return
  }
  manejarError(data)
}

const cargarMembresias = async () => {
  const { ok, data } = await api.get('/admin/membresias')
  if (ok && !data.error) {
    membresias.value = data.membresias
    return
  }
  manejarError(data)
}

const cargarClases = async () => {
  const { ok, data } = await api.get('/admin/clases')
  if (ok && !data.error) {
    clases.value = data.clases
    return
  }
  manejarError(data)
}

const cargarReservas = async () => {
  const { ok, data } = await api.get(`/admin/reservas?period=${periodo.value}`)
  if (ok && !data.error) {
    reservas.value = data.reservas
    return
  }
  manejarError(data)
}

const cargarEstadisticas = async () => {
  const { ok, data } = await api.get('/admin/estadisticas')
  if (ok && !data.error) {
    stats.value = data.datos
    return
  }
  manejarError(data)
}

const ejecutarAccion = async (clave, tarea) => {
  if (accionEnCurso.value) return
  accionEnCurso.value = clave
  mensaje.value = ''
  error.value = ''
  try {
    await tarea()
  } finally {
    accionEnCurso.value = ''
  }
}

const toggleActivo = (socio) => ejecutarAccion(`socio-${socio.id}`, async () => {
  const { ok, data } = await api.put(`/admin/socios/${socio.id}/estado`, { activo: !socio.activo })
  if (!ok || data.error) return manejarError(data)
  mensaje.value = data.mensaje
  await cargarSocios()
})

const verPerfil = (id) => ejecutarAccion(`perfil-${id}`, async () => {
  const { ok, data } = await api.get(`/admin/socios/${id}`)
  if (!ok || data.error) return manejarError(data)
  const perfil = data.socio
  mensaje.value = `Perfil de ${perfil.nombre}: ${perfil.email}`
})

const crearMembresia = () => ejecutarAccion('crear-membresia', async () => {
  const payload = {
    usuario_id: membresia.usuario_id,
    plan: membresia.plan,
    fecha_inicio: membresia.fecha_inicio,
    fecha_vencimiento: membresia.fecha_vencimiento,
    precio_pagado: Number(membresia.precio_pagado)
  }

  const { ok, data } = await api.postAuth('/admin/membresias', payload)
  if (!ok || data.error) return manejarError(data)
  mensaje.value = data.mensaje
  Object.assign(membresia, { usuario_id: '', plan: 'mensual', fecha_inicio: '', fecha_vencimiento: '', precio_pagado: '' })
  await cargarMembresias()
})

const crearClase = () => ejecutarAccion('crear-clase', async () => {
  const { ok, data } = await api.postAuth('/admin/clases', {
    nombre: clase.nombre,
    instructor_id: clase.instructor_id,
    dia_semana: clase.dia_semana,
    hora_inicio: clase.hora_inicio,
    hora_fin: clase.hora_fin,
    cupo_maximo: Number(clase.cupo_maximo)
  })

  if (!ok || data.error) return manejarError(data)
  mensaje.value = data.mensaje
  Object.assign(clase, { nombre: '', instructor_id: '', dia_semana: 'lunes', hora_inicio: '08:00', hora_fin: '09:00', cupo_maximo: 12 })
  await cargarClases()
})

const cancelarClase = (claseId) => ejecutarAccion(`clase-${claseId}`, async () => {
  const { ok, data } = await api.postAuth(`/admin/clases/${claseId}/cancelar`, {})
  if (!ok || data.error) return manejarError(data)
  mensaje.value = data.mensaje
  await cargarClases()
})

const cargarInscriptos = (claseId) => ejecutarAccion(`inscriptos-${claseId}`, async () => {
  const { ok, data } = await api.get(`/admin/clases/${claseId}/inscriptos`)
  if (!ok || data.error) return manejarError(data)
  inscriptos.value = data.inscriptos
  mensaje.value = data.inscriptos.length
    ? `Mostrando inscriptos para la clase ${claseId}.`
    : 'Esta clase todavía no tiene inscriptos.'
})

const cancelarReserva = (reservaId) => ejecutarAccion(`reserva-${reservaId}`, async () => {
  const { ok, data } = await api.put(`/admin/reservas/${reservaId}/cancelar`, {})
  if (!ok || data.error) return manejarError(data)
  mensaje.value = data.mensaje
  await cargarReservas()
})

const cambiarPeriodo = async (nuevoPeriodo) => {
  periodo.value = nuevoPeriodo
  cargando.value = true
  try {
    await cargarReservas()
  } finally {
    cargando.value = false
  }
}

const formatearFecha = (fecha) => {
  return fecha ? fecha.split(' ')[0] : '—'
}

const manejarError = (data) => {
  mensaje.value = ''
  error.value = data?.mensaje || 'Error en el servidor.'
}

onMounted(async () => {
  if (!authStore.estaAutenticado) {
    router.push({ name: 'login' })
    return
  }

  await authStore.cargarPerfil()
  if (!authStore.esAdmin) {
    router.push({ name: 'home' })
    return
  }

  await cargarTodo()
})
</script>

<style scoped>
.admin-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 30px 24px 60px;
  color: #F0FDF4;
  font-family: 'Poppins', sans-serif;
}
.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 30px;
}
.admin-header h1 {
  margin-bottom: 8px;
  font-size: 2.2rem;
}
.admin-header p {
  color: #94A3B8;
}
.admin-actions .btn-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 22px;
  border: 1px solid #94A3B840;
  color: #F0FDF4;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.3s;
}
.admin-actions .btn-outline:hover {
  border-color: #0077ff;
  color: #0077ff;
}
.stats-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 30px;
}
.stat-card {
  background: #161B22;
  border: 1px solid #94A3B820;
  border-radius: 18px;
  padding: 22px;
  min-height: 110px;
}
.stat-card strong {
  display: block;
  font-size: 2rem;
  margin-bottom: 10px;
  color: #0077ff;
}
.stat-card span {
  color: #94A3B8;
}
.admin-section {
  margin-bottom: 40px;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 20px;
  margin-bottom: 18px;
}
.section-header h2 {
  margin: 0;
  font-size: 1.5rem;
}
.section-header span {
  color: #94A3B8;
}
.card-form {
  background: #161B22;
  border: 1px solid #94A3B820;
  border-radius: 18px;
  padding: 20px;
  margin-bottom: 20px;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
label {
  display: flex;
  flex-direction: column;
  gap: 8px;
  color: #F0FDF4;
  font-size: 0.95rem;
}
input,
select {
  width: 100%;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #94A3B840;
  background: #0D1117;
  color: #F0FDF4;
}
button,
.btn-small {
  cursor: pointer;
}
.btn-primary {
  background: #0077ff;
  color: #111827;
  border: none;
  padding: 12px 20px;
  border-radius: 10px;
  font-weight: 700;
}
.table-wrapper {
  overflow-x: auto;
  background: #161B22;
  border: 1px solid #94A3B820;
  border-radius: 18px;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th,
td {
  text-align: left;
  padding: 14px 16px;
  border-bottom: 1px solid #94A3B820;
}
th {
  color: #94A3B8;
  font-size: 0.95rem;
}
tbody tr:last-child td {
  border-bottom: none;
}
.btn-small {
  border: none;
  background: #0D1117;
  color: #F0FDF4;
  padding: 8px 12px;
  margin-right: 6px;
  border-radius: 8px;
  transition: background 0.3s;
}
.btn-small:hover {
  background: #0077ff;
  color: #111827;
}
.btn-primary:disabled,
.btn-small:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
.btn-small.secondary {
  background: transparent;
  border: 1px solid #94A3B840;
}
.btn-small.secondary:hover {
  background: #94A3B82b;
}
.reservas-filters {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}
.btn-small.active {
  background: #0077ff;
  color: #111827;
}
.feedback {
  margin-top: 20px;
  padding: 14px 18px;
  border-radius: 14px;
}
.feedback.success {
  background: rgba(16, 185, 129, 0.15);
  color: #D1FAE5;
}
.feedback.error {
  background: rgba(241, 90, 96, 0.15);
  color: #FECACA;
}
.feedback.loading {
  margin-bottom: 20px;
  background: rgba(56, 189, 248, 0.12);
  color: #BAE6FD;
}
</style>
