<!--
  Vista administrativa AdminDashboard. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<template>
  <div class="admin-dashboard">
    <header class="admin-header">
      <div>
        <p class="kicker">Administración</p>
        <h1>Panel de control</h1>
        <span class="muted">Gestiona tu gimnasio desde aquí</span>
      </div>
      <router-link to="/dashboard" class="ghost-btn">← Volver</router-link>
    </header>

    <section class="metrics-grid">
      <article class="metric-card" v-for="metric in statsCards" :key="metric.label">
        <small>{{ metric.label }}</small>
        <strong>{{ metric.valor }}</strong>
        <span v-if="metric.delta" class="metric-delta" :class="metric.deltaTrend">{{ metric.delta }}</span>
      </article>
    </section>

    <div class="dashboard-grid">
      <!-- Main content -->
      <main class="main-column">
        <!-- Attendance Chart -->
        <article class="panel-card">
          <h3>Asistencia diaria</h3>
          <p class="muted">Últimos 7 días</p>
          <div class="chart-container">
            <div class="bar-chart">
              <div v-for="(day, idx) in attendanceData" :key="idx" class="bar-wrapper">
                <div class="bar" :style="{ height: `${day.value * 100 / maxAttendance}px` }">
                  <span class="bar-label">{{ day.value }}</span>
                </div>
                <label>{{ day.day }}</label>
              </div>
            </div>
          </div>
          <div class="mini-stats">
            <span>📊 Promedio: {{ avgAttendance }}</span>
            <span>📈 Pico: {{ maxAttendanceDay }}</span>
          </div>
        </article>

        <!-- Membership Status -->
        <article class="panel-card">
          <h3>Estado de membresías</h3>
          <p class="muted">Resumen actual</p>
          <div class="status-grid">
            <div class="status-item">
              <div class="status-ring" :style="{ background: 'conic-gradient(#22c55e 0 ' + (activePercentage * 3.6) + 'deg, #374151 0 360deg)' }">
                <span>{{ activePercentage }}%</span>
              </div>
              <label>Activas</label>
              <strong>{{ stats.mem_activas || 0 }}</strong>
            </div>
            <div class="status-item">
              <div class="status-ring" :style="{ background: 'conic-gradient(#f59e0b 0 ' + (expiringSoonPercentage * 3.6) + 'deg, #374151 0 360deg)' }">
                <span>{{ expiringSoonPercentage }}%</span>
              </div>
              <label>Por vencer (7d)</label>
              <strong>{{ stats.por_vencer || 0 }}</strong>
            </div>
            <div class="status-item">
              <div class="status-ring" :style="{ background: 'conic-gradient(#ef4444 0 ' + (expiredPercentage * 3.6) + 'deg, #374151 0 360deg)' }">
                <span>{{ expiredPercentage }}%</span>
              </div>
              <label>Vencidas</label>
              <strong>{{ stats.mem_vencidas || 0 }}</strong>
            </div>
          </div>
        </article>

        <!-- Recent Members -->
        <article class="panel-card">
          <div class="card-header">
            <h3>Nuevos socios</h3>
            <router-link to="/admin#socios" class="text-btn">Ver todos →</router-link>
          </div>
          <div v-if="recientes.length" class="list-stack">
            <div v-for="member in recientes.slice(0, 5)" :key="member.id" class="member-item">
              <div class="member-avatar">{{ member.nombre.charAt(0).toUpperCase() }}</div>
              <div class="member-info">
                <strong>{{ member.nombre }}</strong>
                <span>{{ member.email }}</span>
                <small>Registrado {{ formatFecha(member.creado_en) }}</small>
              </div>
              <button class="text-btn" type="button" disabled title="Usá el panel administrativo actual">Perfil próximamente</button>
            </div>
          </div>
          <div v-else class="empty-state">No hay socios registrados</div>
        </article>
      </main>

      <!-- Sidebar -->
      <aside class="side-column">
        <!-- Classes Today -->
        <article class="panel-card">
          <h3>Clases hoy</h3>
          <strong class="stat-number">{{ stats.clases_hoy || 0 }}</strong>
          <p class="muted">Clases programadas</p>
          <button class="primary-btn" type="button" disabled title="Usá el panel administrativo actual">Gestión próximamente</button>
        </article>

        <!-- Attendance Rate -->
        <article class="panel-card">
          <h3>Tasa de asistencia</h3>
          <div class="donut-chart">
            <div class="donut" :style="{ background: `conic-gradient(#0077ff 0 ${attendanceRate * 3.6}deg, #374151 0 360deg)` }">
              <span>{{ attendanceRate }}%</span>
            </div>
          </div>
          <p class="muted">{{ stats.reservas_hoy || 0 }} de {{ stats.clases_activas || 0 }} asientos ocupados</p>
        </article>

        <!-- Revenue -->
        <article class="panel-card">
          <h3>Ingresos</h3>
          <strong class="stat-number">${{ revenueThisMonth }}</strong>
          <p class="muted">Este mes</p>
          <div class="mini-spark">
            <i v-for="v in [45, 62, 38, 71, 54]" :key="v" :style="{ height: `${v}px` }"></i>
          </div>
        </article>

        <!-- Quick Actions -->
        <article class="panel-card action-card">
          <h3>Acciones rápidas</h3>
          <router-link to="/admin#socios" class="action-link">Gestionar socios</router-link>
          <router-link to="/admin#clases" class="action-link">Crear clase</router-link>
          <button class="action-link" type="button" disabled title="Se habilitará cuando la exportación use datos reales">Exportación próximamente</button>
        </article>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const stats = ref({})
const recientes = ref([])
const cargando = ref(true)

// Demo data for attendance
const attendanceData = ref([
  { day: 'Lun', value: 45 },
  { day: 'Mar', value: 52 },
  { day: 'Mié', value: 38 },
  { day: 'Jue', value: 61 },
  { day: 'Vie', value: 58 },
  { day: 'Sab', value: 42 },
  { day: 'Dom', value: 28 },
])

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/dashboard')
    stats.value = data.stats
    recientes.value = data.recientes
  } catch (error) {
    console.log('Using demo stats')
    stats.value = {
      total_socios: 145,
      mem_activas: 132,
      mem_vencidas: 8,
      por_vencer: 5,
      clases_activas: 32,
      clases_hoy: 6,
      reservas_hoy: 28,
    }
    recientes.value = []
  } finally {
    cargando.value = false
  }
})

const statsCards = computed(() => [
  {
    label: 'Socios activos',
    valor: stats.value.total_socios || 0,
    delta: '+12 este mes',
    deltaTrend: 'positive'
  },
  {
    label: 'Membresías activas',
    valor: stats.value.mem_activas || 0,
    delta: '+4 esta semana',
    deltaTrend: 'positive'
  },
  {
    label: 'Clases activas',
    valor: stats.value.clases_activas || 0,
    delta: 'todas disponibles',
    deltaTrend: 'positive'
  },
  {
    label: 'Reservas hoy',
    valor: stats.value.reservas_hoy || 0,
    delta: `${Math.round((stats.value.reservas_hoy || 0) / (stats.value.clases_hoy || 1) * 100)}% ocupación`,
    deltaTrend: 'neutral'
  },
])

const maxAttendance = computed(() => Math.max(...attendanceData.value.map(d => d.value)))

const avgAttendance = computed(() => {
  const sum = attendanceData.value.reduce((acc, d) => acc + d.value, 0)
  return Math.round(sum / attendanceData.value.length)
})

const maxAttendanceDay = computed(() => {
  const max = Math.max(...attendanceData.value.map(d => d.value))
  return attendanceData.value.find(d => d.value === max)?.day || '-'
})

const activePercentage = computed(() => {
  const total = (stats.value.mem_activas || 0) + (stats.value.mem_vencidas || 0) + (stats.value.por_vencer || 0)
  return total > 0 ? Math.round((stats.value.mem_activas || 0) / total * 100) : 0
})

const expiringSoonPercentage = computed(() => {
  const total = (stats.value.mem_activas || 0) + (stats.value.mem_vencidas || 0) + (stats.value.por_vencer || 0)
  return total > 0 ? Math.round((stats.value.por_vencer || 0) / total * 100) : 0
})

const expiredPercentage = computed(() => {
  const total = (stats.value.mem_activas || 0) + (stats.value.mem_vencidas || 0) + (stats.value.por_vencer || 0)
  return total > 0 ? Math.round((stats.value.mem_vencidas || 0) / total * 100) : 0
})

const attendanceRate = computed(() => {
  const total = stats.value.clases_activas || 0
  const reserved = stats.value.reservas_hoy || 0
  return total > 0 ? Math.round(reserved / total * 100) : 0
})

const revenueThisMonth = computed(() => {
  return ((stats.value.mem_activas || 0) * 1890).toLocaleString('es-UY', { maximumFractionDigits: 0 })
})

function formatFecha(f) {
  if (!f) return 'Hoy'
  const date = new Date(f)
  const today = new Date()
  if (date.toDateString() === today.toDateString()) return 'Hoy'
  return date.toLocaleDateString('es-UY', { day: 'short', month: 'short' })
}

</script>

<style scoped>
.admin-dashboard {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 28px 30px;
  background:
    linear-gradient(180deg, rgba(12, 20, 30, 0.72), rgba(5, 7, 10, 0.84));
  border: 1px solid rgba(56, 189, 248, 0.22);
  border-radius: 12px;
}

.admin-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 12px;
  animation: riseIn 0.55s ease both;
}

.admin-header h1 {
  margin: 5px 0;
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(1.8rem, 3vw, 2.8rem);
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}

.metric-card {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 18px 16px;
  background: rgba(9, 15, 23, 0.84);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
  animation: riseIn 0.55s ease both;
}

.metric-card:nth-child(2) { animation-delay: 0.05s; }
.metric-card:nth-child(3) { animation-delay: 0.1s; }
.metric-card:nth-child(4) { animation-delay: 0.15s; }

.metric-card strong {
  display: block;
  margin: 8px 0 4px;
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 700;
}

.metric-card small {
  display: block;
  color: var(--muted);
  font-size: clamp(0.7rem, 1vw, 0.78rem);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.metric-delta {
  display: block;
  margin-top: 8px;
  color: var(--success);
  font-size: 0.8rem;
  font-weight: 800;
}

.metric-delta.positive {
  color: var(--success);
}

.metric-delta.negative {
  color: var(--danger);
}

.metric-delta.neutral {
  color: var(--blue-2);
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 20px;
}

.main-column,
.side-column {
  display: grid;
  gap: 18px;
  align-content: start;
}

.panel-card {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 22px;
  background: rgba(9, 15, 23, 0.84);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
  animation: riseIn 0.55s ease both;
  transition: transform 0.2s ease, border-color 0.2s ease;
}

.panel-card:hover {
  transform: translateY(-2px);
  border-color: rgba(56, 189, 248, 0.42);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), 0 12px 32px rgba(0, 119, 255, 0.12);
}

.panel-card h3 {
  margin-bottom: 14px;
  font-size: 1.1rem;
  font-weight: 700;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.chart-container {
  margin: 20px 0;
}

.bar-chart {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  gap: 12px;
  min-height: 200px;
  padding: 12px 0;
  border-top: 1px solid rgba(56, 189, 248, 0.22);
  border-bottom: 1px solid rgba(56, 189, 248, 0.22);
}

.bar-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.bar {
  width: 100%;
  max-width: 40px;
  border-radius: 6px 6px 0 0;
  background: linear-gradient(180deg, var(--blue), rgba(0, 119, 255, 0.2));
  position: relative;
  min-height: 20px;
  animation: growBar 0.8s ease both;
}

.bar-label {
  position: absolute;
  top: -20px;
  font-size: 0.75rem;
  font-weight: 800;
  color: var(--text);
}

.bar-wrapper label {
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 800;
  text-transform: uppercase;
}

.mini-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 16px;
}

.mini-stats span {
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 8px 12px;
  background: rgba(0, 119, 255, 0.12);
  color: #dbeafe;
  font-size: 0.85rem;
  font-weight: 800;
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin: 20px 0;
}

.status-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  text-align: center;
}

.status-ring {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 900;
  font-size: 1.3rem;
  position: relative;
}

.status-ring span {
  position: absolute;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(9, 15, 23, 0.84);
  display: grid;
  place-items: center;
  font-size: 1rem;
}

.status-item label {
  font-size: 0.8rem;
  color: var(--muted);
  font-weight: 800;
  text-transform: uppercase;
}

.status-item strong {
  font-size: 1.4rem;
  color: var(--text);
}

.donut-chart {
  display: grid;
  place-items: center;
  min-height: 140px;
  margin: 16px 0;
}

.donut {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 900;
  font-size: 1.2rem;
  position: relative;
}

.donut span {
  position: absolute;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: rgba(9, 15, 23, 0.84);
  display: grid;
  place-items: center;
}

.stat-number {
  display: block;
  font-size: clamp(1.8rem, 4vw, 2.4rem);
  margin: 12px 0;
}

.mini-spark {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  min-height: 60px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(56, 189, 248, 0.22);
}

.mini-spark i {
  flex: 1;
  min-width: 8px;
  border-radius: 3px 3px 0 0;
  background: linear-gradient(180deg, var(--blue), rgba(0, 119, 255, 0.2));
  animation: growBar 0.8s ease both;
}

.list-stack {
  display: grid;
  gap: 12px;
}

.member-item {
  display: grid;
  grid-template-columns: 42px 1fr auto;
  gap: 12px;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 14px;
  background: rgba(5, 9, 14, 0.64);
  transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
}

.member-item:hover {
  border-color: rgba(56, 189, 248, 0.34);
  background: rgba(8, 15, 25, 0.74);
}

.member-avatar {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: var(--blue);
  font-weight: 900;
  font-size: 0.9rem;
  color: white;
}

.member-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.member-info strong {
  font-size: 0.95rem;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.member-info span {
  font-size: 0.8rem;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.member-info small {
  font-size: 0.75rem;
  color: var(--muted);
}

.action-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.action-link {
  display: block;
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 6px;
  background: rgba(0, 119, 255, 0.08);
  color: var(--blue-2);
  text-align: left;
  text-decoration: none;
  font-weight: 800;
  font-size: 0.9rem;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
  white-space: nowrap;
  min-height: 44px;
  display: flex;
  align-items: center;
}

.action-link:hover {
  background: rgba(0, 119, 255, 0.18);
  border-color: rgba(56, 189, 248, 0.42);
}

.empty-state {
  padding: 24px;
  text-align: center;
  color: var(--muted);
  font-size: 0.9rem;
}

@keyframes riseIn {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes growBar {
  from {
    transform: scaleY(0.2);
    opacity: 0.45;
  }
  to {
    transform: scaleY(1);
    opacity: 1;
  }
}

@media (max-width: 1040px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .metrics-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .status-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .admin-dashboard {
    padding: 16px;
    gap: 16px;
  }

  .admin-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .admin-header h1 {
    font-size: clamp(1.4rem, 5vw, 1.8rem);
  }

  .metrics-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .bar-chart {
    min-height: 160px;
  }

  .status-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .status-ring {
    width: 80px;
    height: 80px;
    font-size: 1.1rem;
  }

  .status-ring span {
    width: 64px;
    height: 64px;
    font-size: 0.85rem;
  }

  .member-item {
    grid-template-columns: 38px 1fr auto;
    padding: 12px;
  }

  .member-avatar {
    width: 38px;
    height: 38px;
    font-size: 0.8rem;
  }

  .action-link {
    min-height: 42px;
    padding: 10px;
    font-size: 0.85rem;
  }
}

@media (max-width: 480px) {
  .admin-dashboard {
    padding: 12px;
    gap: 14px;
  }

  .admin-header {
    gap: 10px;
  }

  .admin-header h1 {
    font-size: 1.3rem;
  }

  .metrics-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .metric-card {
    padding: 14px 12px;
  }

  .metric-card strong {
    font-size: 1.2rem;
  }

  .metric-card small {
    font-size: 0.65rem;
  }

  .panel-card {
    padding: 16px;
  }

  .panel-card h3 {
    font-size: 1rem;
  }

  .bar-chart {
    min-height: 140px;
    gap: 8px;
  }

  .bar {
    max-width: 32px;
  }

  .bar-wrapper label {
    font-size: 0.65rem;
  }

  .status-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .status-ring {
    width: 70px;
    height: 70px;
    font-size: 0.95rem;
  }

  .status-ring span {
    width: 56px;
    height: 56px;
    font-size: 0.75rem;
  }

  .status-item strong {
    font-size: 1.1rem;
  }

  .donut {
    width: 100px;
    height: 100px;
    font-size: 1rem;
  }

  .donut span {
    width: 78px;
    height: 78px;
  }

  .stat-number {
    font-size: 1.6rem;
  }

  .member-item {
    grid-template-columns: 36px 1fr;
    padding: 10px;
    gap: 10px;
  }

  .member-avatar {
    width: 36px;
    height: 36px;
    font-size: 0.75rem;
  }

  .member-info span {
    display: none;
  }

  .action-link {
    min-height: 40px;
    padding: 8px;
    font-size: 0.8rem;
  }

  .mini-spark {
    min-height: 50px;
    gap: 4px;
  }

  .mini-spark i {
    min-width: 6px;
  }
}
</style>
