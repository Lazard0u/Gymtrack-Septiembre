<template>
  <div class="dashboard-shell">
    <!-- Hamburger menu trigger (mobile only) -->
    <button class="mobile-menu-toggle" type="button" @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Abrir menú" :aria-expanded="mobileMenuOpen">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <aside class="sidebar" :class="{ open: mobileMenuOpen }">
      <router-link to="/" class="brand">
        <img class="brand-mark" src="../assets/gymtrack-mark.svg" alt="GymTrack" />
        <span class="brand-word">Gym<span>Track</span></span>
      </router-link>
      <nav>
        <button
          v-for="item in navItems"
          :key="item"
          :class="{ active: item === activeNav }"
          :disabled="!isNavAvailable(item)"
          :title="isNavAvailable(item) ? '' : 'Disponible cuando finalice la migración operativa'"
          @click="handleNavClick(item)"
        >
          <span>{{ item.slice(0, 1) }}</span>{{ item }}
        </button>
      </nav>
      <button class="logout" type="button" @click="handleLogout">Cerrar sesión</button>
    </aside>

    <!-- Overlay for mobile menu -->
    <div v-if="mobileMenuOpen" class="mobile-menu-overlay" @click="mobileMenuOpen = false"></div>

    <main class="content">
      <header class="topbar">
        <div>
          <p class="kicker">{{ dashboardLabel }}</p>
          <h1>{{ greeting }}</h1>
          <span class="muted">{{ subtitle }}</span>
        </div>
        <div class="user-chip">
          <span>{{ initials }}</span>
          <div>
            <strong>{{ userName }}</strong>
            <small>{{ roleName }}</small>
          </div>
        </div>
      </header>

      <p class="demo-notice" role="status">
        Vista de demostración: los gimnasios y las métricas de este panel todavía no provienen de tu cuenta.
      </p>

      <section class="metrics">
        <article v-for="metric in metrics" :key="metric.label" class="metric-card">
          <small>{{ metric.label }}</small>
          <strong>{{ metric.value }}</strong>
          <span>{{ metric.delta }}</span>
        </article>
      </section>

      <section v-if="roleKey === 'socio'" class="dashboard-grid">
        <div class="main-column">
          <template v-if="activeNav === 'Inicio'">
            <div class="section-head">
              <h2>Mis gimnasios</h2>
              <button class="text-btn" @click="activeNav = 'Mis Gimnasios'">Ver todos</button>
            </div>
            <div class="cards-row">
              <article v-for="gym in joinedGyms.slice(0, 3)" :key="gym.id" class="gym-card">
                <img :src="gym.image" :alt="gym.name" />
                <strong>{{ gym.name }}</strong>
                <span>{{ gym.city }} · {{ gym.category }}</span>
                <button class="primary-btn" @click="openGym(gym)">Ver gimnasio</button>
              </article>
            </div>

            <div class="section-head">
              <h2>Explorar gimnasios</h2>
              <button class="text-btn" @click="activeNav = 'Explorar'">Ver mapa</button>
            </div>
            <div ref="dashboardMapEl" class="map-preview">
              <span class="map-badge">{{ dashboardMapStatus }}</span>
            </div>
            <div class="filter-row">
              <button
                v-for="cityName in cityFilters"
                :key="cityName"
                :class="{ active: selectedCity === cityName }"
                @click="selectedCity = cityName"
              >
                {{ cityName }}
              </button>
            </div>
          </template>

          <template v-else-if="activeNav === 'Mis Gimnasios'">
            <div class="section-head">
              <h2>Mis gimnasios</h2>
              <button class="text-btn" @click="activeNav = 'Explorar'">Explorar mas</button>
            </div>
            <div class="cards-row">
              <article v-for="gym in joinedGyms" :key="gym.id" class="gym-card">
                <img :src="gym.image" :alt="gym.name" />
                <strong>{{ gym.name }}</strong>
                <span>{{ gym.city }} · {{ gym.price }}/mes</span>
                <button class="primary-btn" @click="openGym(gym)">Ver clases</button>
              </article>
            </div>
          </template>

          <template v-else-if="activeNav === 'Reservas'">
            <div class="section-head">
              <h2>Mis reservas</h2>
              <button class="text-btn" @click="activeNav = 'Explorar'">Reservar otra clase</button>
            </div>
            <div class="list-stack">
              <article v-for="reservation in reservations" :key="reservation.id" class="action-row">
                <div>
                  <strong>{{ reservation.className }}</strong>
                  <span>{{ reservation.gym }} · {{ reservation.time }} · {{ reservation.status }}</span>
                </div>
                <button class="ghost-btn" @click="selectReservation(reservation)">Ver detalle</button>
              </article>
            </div>
          </template>

          <template v-else-if="activeNav === 'Membresias'">
            <div class="section-head">
              <h2>Membresias activas</h2>
              <button class="text-btn" @click="activeNav = 'Explorar'">Comprar otra</button>
            </div>
            <div class="list-stack">
              <article v-for="membership in memberships" :key="membership.id" class="action-row">
                <div>
                  <strong>{{ membership.gym }}</strong>
                  <span>{{ membership.plan }} · vence {{ membership.expires }} · {{ membership.price }}</span>
                </div>
                <span class="status">{{ membership.status }}</span>
              </article>
            </div>
          </template>

          <template v-else-if="activeNav === 'Progreso'">
            <div class="section-head">
              <h2>Progreso mensual</h2>
              <span class="muted">Objetivo: asistir a 20 clases este mes</span>
            </div>
            <div class="progress-explainer panel-card">
              <div class="ring"><span>{{ progressPercent }}%</span></div>
              <div>
                <h3>{{ progressPercent }}% de tu objetivo mensual completado</h3>
                <p class="muted">
                  Llevas {{ attendedClasses }} clases asistidas de {{ monthlyGoal }} planificadas.
                  Este porcentaje no mide tu estado fisico, mide cumplimiento de asistencia.
                </p>
                <div class="mini-stats">
                  <span>Racha: 4 semanas</span>
                  <span>Reservas activas: {{ reservations.length }}</span>
                  <span>Membresias: {{ memberships.length }}</span>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="activeNav === 'Explorar'">
            <div class="section-head">
              <h2>Explorar gimnasios</h2>
              <span class="muted">Un socio puede unirse a varios gimnasios</span>
            </div>
            <div ref="dashboardMapEl" class="map-preview tall">
              <span class="map-badge">{{ dashboardMapStatus }}</span>
            </div>
            <div class="filter-row">
              <button
                v-for="cityName in cityFilters"
                :key="cityName"
                :class="{ active: selectedCity === cityName }"
                @click="selectedCity = cityName"
              >
                {{ cityName }}
              </button>
            </div>
            <div class="cards-row">
              <article v-for="gym in filteredGyms" :key="gym.id" class="gym-card">
                <img :src="gym.image" :alt="gym.name" />
                <strong>{{ gym.name }}</strong>
                <span>{{ gym.city }} · {{ gym.category }} · {{ gym.price }}/mes</span>
                <button class="primary-btn" @click="toggleJoinGym(gym)">
                  {{ joinedGymIds.includes(gym.id) ? 'Ya unido' : 'Unirme' }}
                </button>
              </article>
            </div>
          </template>
        </div>

        <aside class="side-column">
          <article class="panel-card">
            <h3>Proxima clase</h3>
            <div class="class-card">
              <img :src="gyms[0].image" alt="Clase demo" />
              <div>
                <strong>{{ classes[0].name }}</strong>
                <span>{{ classes[0].coach }}</span>
              </div>
            </div>
            <p class="muted">{{ classes[0].time }} · {{ classes[0].gym }}</p>
            <button class="primary-btn" @click="activeNav = 'Reservas'; selectReservation(reservations[0])">Ver reserva</button>
          </article>
          <article class="panel-card progress-card">
            <h3>Tu progreso</h3>
            <div class="ring"><span>{{ progressPercent }}%</span></div>
            <p class="muted">Asistencia mensual: {{ attendedClasses }} de {{ monthlyGoal }} clases.</p>
            <div class="sparkline">
              <i v-for="value in [20, 34, 28, 42, 36, 52, 48, 66]" :key="value" :style="{ height: `${value}px` }"></i>
            </div>
          </article>
        </aside>
      </section>

      <section v-else-if="roleKey === 'owner'" class="owner-layout">
        <article class="panel-card chart-card">
          <div class="section-head">
            <h2>Grafico de ingresos</h2>
            <span class="muted">Ultimos 6 meses</span>
          </div>
          <div class="bar-chart">
            <i v-for="value in monthlyRevenue" :key="value" :style="{ height: `${value / 4}px` }"></i>
          </div>
        </article>
        <article class="panel-card">
          <h2>Clases populares</h2>
          <div v-for="item in classes" :key="item.name" class="list-item">
            <span>{{ item.name }}</span>
            <strong>{{ item.capacity }}</strong>
          </div>
        </article>
        <article class="panel-card quick-actions">
          <h2>Accesos rapidos</h2>
          <button class="ghost-btn" type="button" disabled>Administración en migración</button>
          <button class="ghost-btn" type="button" disabled>Nueva clase próximamente</button>
          <button class="ghost-btn" type="button" disabled>Agregar socio próximamente</button>
          <button class="ghost-btn" type="button" disabled>Reservas próximamente</button>
        </article>
      </section>

      <section v-else class="admin-layout">
        <article class="panel-card table-panel">
          <div class="section-head">
            <h2>Gimnasios registrados</h2>
            <router-link to="/admin" class="text-btn">Gestion operativa</router-link>
          </div>
          <table>
            <thead>
              <tr>
                <th>Gimnasio</th>
                <th>Owner</th>
                <th>Socios</th>
                <th>Plan</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="gym in gyms" :key="gym.id">
                <td>{{ gym.name }}</td>
                <td>{{ gym.owner }}</td>
                <td>{{ gym.members }}</td>
                <td>{{ gym.plan }}</td>
                <td><span class="status" :class="{ warn: gym.status === 'Suspendido' }">{{ gym.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </article>
        <aside class="admin-side">
          <article class="panel-card">
            <h3>Ingresos ultimos 6 meses</h3>
            <div class="bar-chart compact">
              <i v-for="value in monthlyRevenue" :key="value" :style="{ height: `${value / 5}px` }"></i>
            </div>
          </article>
          <article class="panel-card">
            <h3>Estado de pagos</h3>
            <div class="donut"></div>
            <p class="muted">16 al dia · 6 pendientes · 2 vencidos</p>
          </article>
        </aside>
      </section>

      <div v-if="selectedGym" class="modal-backdrop" @click.self="selectedGym = null">
        <article class="modal-card">
          <button class="modal-close" @click="selectedGym = null">Cerrar</button>
          <img :src="selectedGym.image" :alt="selectedGym.name" />
          <h2>{{ selectedGym.name }}</h2>
          <p class="muted">{{ selectedGym.city }} · {{ selectedGym.category }} · {{ selectedGym.price }}/mes</p>
          <div class="modal-grid">
            <div>
              <strong>Clases disponibles</strong>
              <span v-for="item in selectedGym.classes" :key="item">{{ item }}</span>
            </div>
            <div>
              <strong>Plan recomendado</strong>
              <span>Mensual premium</span>
              <span>Acceso a reservas y progreso</span>
            </div>
          </div>
          <button class="primary-btn" @click="toggleJoinGym(selectedGym)">
            {{ joinedGymIds.includes(selectedGym.id) ? 'Ya estas unido' : 'Unirme a este gimnasio' }}
          </button>
        </article>
      </div>

      <div v-if="selectedReservation" class="modal-backdrop" @click.self="selectedReservation = null">
        <article class="modal-card small">
          <button class="modal-close" @click="selectedReservation = null">Cerrar</button>
          <h2>{{ selectedReservation.className }}</h2>
          <p class="muted">{{ selectedReservation.gym }} · {{ selectedReservation.time }}</p>
          <p>Estado: <strong>{{ selectedReservation.status }}</strong></p>
          <button class="ghost-btn" @click="cancelReservation(selectedReservation)">Cancelar reserva demo</button>
        </article>
      </div>

      <div v-if="toast" class="toast">{{ toast }}</div>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { classes, gyms, monthlyRevenue } from '../data/demoData'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const router = useRouter()
const authStore = useAuthStore()
const mobileMenuOpen = ref(false)
const activeNav = ref('Inicio')
const dashboardMapEl = ref(null)
const dashboardMapStatus = ref('Cargando OpenStreetMap')
const selectedCity = ref('Todos')
const selectedGym = ref(null)
const selectedReservation = ref(null)
const toast = ref('')
const joinedGymIds = ref([1, 2, 3])
const monthlyGoal = 20
const attendedClasses = 17
const progressPercent = Math.round((attendedClasses / monthlyGoal) * 100)
const reservations = ref([
  { id: 1, className: 'Boxeo Funcional', gym: 'PuertoFit', time: 'Hoy, 18:00', status: 'Confirmada' },
  { id: 2, className: 'Spinning Pro', gym: 'Gishin / Cerro Largo FC', time: 'Manana, 08:30', status: 'Confirmada' },
])
const memberships = ref([
  { id: 1, gym: 'PuertoFit', plan: 'Mensual Pro', expires: '30/06/2026', price: '$ 1.890', status: 'Activa' },
  { id: 2, gym: 'Gishin / Cerro Largo FC', plan: 'Trimestral', expires: '15/08/2026', price: '$ 5.400', status: 'Activa' },
])

const userName = computed(() => authStore.user?.nombre || 'Santiago')
const initials = computed(() => userName.value.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase())
const roleKey = computed(() => {
  if (authStore.user?.rol_id === 2) return 'super'
  if (authStore.user?.rol_id === 3) return 'owner'
  return 'socio'
})
const roleName = computed(() => ({ socio: 'Socio', owner: 'Gym Owner', super: 'Super Admin' }[roleKey.value]))
const dashboardLabel = computed(() => ({ socio: 'Dashboard socio', owner: 'Dashboard gym owner', super: 'Dashboard super admin' }[roleKey.value]))
const greeting = computed(() => `Hola, ${userName.value}!`)
const subtitle = computed(() => ({
  socio: 'Busca gimnasios, reserva clases y controla tus membresias.',
  owner: 'Resumen operativo de tu gimnasio, socios, clases y pagos.',
  super: 'Vista general de la plataforma GymTrack y sus gimnasios.',
}[roleKey.value]))
const navItems = computed(() => {
  if (roleKey.value === 'super') return ['Dashboard', 'Gimnasios', 'Owners', 'Socios', 'Pagos', 'Reportes']
  if (roleKey.value === 'owner') return ['Dashboard', 'Mi Gimnasio', 'Socios', 'Clases', 'Membresias', 'Pagos']
  return ['Inicio', 'Mis Gimnasios', 'Reservas', 'Membresias', 'Progreso', 'Explorar']
})
const cityFilters = computed(() => ['Todos', ...new Set(gyms.map((gym) => gym.city))])
const joinedGyms = computed(() => gyms.filter((gym) => joinedGymIds.value.includes(gym.id)))
const filteredGyms = computed(() => {
  if (selectedCity.value === 'Todos') return gyms
  return gyms.filter((gym) => gym.city === selectedCity.value)
})
const metrics = computed(() => {
  if (roleKey.value === 'super') {
    return [
      { label: 'Gimnasios activos', value: '24', delta: '+8%' },
      { label: 'Suspendidos', value: '2', delta: '1 pago vencido' },
      { label: 'Socios totales', value: '2.453', delta: '+14%' },
      { label: 'Ingresos estimados', value: '$ 320.540', delta: '+18%' },
    ]
  }
  if (roleKey.value === 'owner') {
    return [
      { label: 'Socios activos', value: '126', delta: '+12%' },
      { label: 'Clases activas', value: '18', delta: '+8%' },
      { label: 'Ingresos mes', value: '$ 45.230', delta: '+15%' },
      { label: 'Ocupacion', value: '92%', delta: '+5%' },
    ]
  }
  return [
    { label: 'Gimnasios', value: joinedGyms.value.length, delta: 'Activos' },
    { label: 'Reservas', value: reservations.value.length, delta: 'Confirmadas' },
    { label: 'Membresias', value: memberships.value.length, delta: 'Vigentes' },
    { label: 'Progreso', value: `${progressPercent}%`, delta: 'Objetivo mensual' },
  ]
})

function showToast(message) {
  toast.value = message
  window.clearTimeout(showToast.timer)
  showToast.timer = window.setTimeout(() => {
    toast.value = ''
  }, 2600)
}

function handleNavClick(item) {
  if (!isNavAvailable(item)) return
  activeNav.value = item
  mobileMenuOpen.value = false
}

function isNavAvailable(item) {
  return roleKey.value === 'socio' || item === 'Dashboard'
}

function openGym(gym) {
  selectedGym.value = gym
}

function toggleJoinGym(gym) {
  if (joinedGymIds.value.includes(gym.id)) {
    showToast(`Ya estas unido a ${gym.name}.`)
    selectedGym.value = null
    return
  }
  joinedGymIds.value.push(gym.id)
  memberships.value.push({
    id: Date.now(),
    gym: gym.name,
    plan: 'Mensual demo',
    expires: '30/07/2026',
    price: gym.price,
    status: 'Activa',
  })
  showToast(`Te uniste a ${gym.name}. Membresia demo activada.`)
  selectedGym.value = null
}

function selectReservation(reservation) {
  selectedReservation.value = reservation
}

function cancelReservation(reservation) {
  reservations.value = reservations.value.filter((item) => item.id !== reservation.id)
  selectedReservation.value = null
  showToast(`Reserva de ${reservation.className} cancelada en modo demo.`)
}

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'home' })
}

function initDashboardMap() {
  if (dashboardMapEl.value) {
    if (dashboardMapEl.value._leaflet_id) return
    const map = L.map(dashboardMapEl.value, {
      zoomControl: false,
      attributionControl: true,
      dragging: true,
      scrollWheelZoom: false,
    }).setView([-32.85, -55.2], 7)

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)
    gyms.forEach((gym) => L.marker([gym.lat, gym.lng]).addTo(map).bindPopup(`${gym.name} · ${gym.city}`))
    dashboardMapStatus.value = 'OpenStreetMap activo'
    setTimeout(() => map.invalidateSize(), 120)
  }
}

onMounted(async () => {
  if (!authStore.user) await authStore.cargarPerfil()
  initDashboardMap()
})

watch(activeNav, async () => {
  await nextTick()
  initDashboardMap()
})
</script>

<style scoped>
.dashboard-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 240px 1fr;
  padding: 18px;
  background:
    radial-gradient(circle at 74% 5%, rgba(0, 119, 255, 0.16), transparent 26rem),
    #05070a;
}

.mobile-menu-toggle {
  display: none;
  position: fixed;
  top: 18px;
  left: 18px;
  z-index: 1001;
  width: 44px;
  height: 44px;
  border: 1px solid rgba(56, 189, 248, 0.34);
  border-radius: 8px;
  background: rgba(7, 12, 19, 0.98);
  cursor: pointer;
}

.mobile-menu-toggle span {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 18px;
  height: 2px;
  background: var(--text);
  transform: translate(-50%, -50%);
  transition: transform 0.3s ease;
}

.mobile-menu-toggle span:nth-child(1) {
  transform: translate(-50%, calc(-50% - 6px));
}

.mobile-menu-toggle span:nth-child(3) {
  transform: translate(-50%, calc(-50% + 6px));
}

.mobile-menu-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.5);
}

.sidebar {
  position: sticky;
  top: 18px;
  height: calc(100vh - 36px);
  display: flex;
  flex-direction: column;
  gap: 26px;
  border: 1px solid rgba(56, 189, 248, 0.22);
  border-radius: 10px 0 0 10px;
  padding: 24px 16px;
  background: linear-gradient(180deg, rgba(7, 12, 19, 0.98), rgba(5, 7, 10, 0.94));
  box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.03);
  transition: transform 0.3s ease;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.sidebar nav {
  display: grid;
  gap: 8px;
}

.sidebar button,
.logout {
  min-height: 48px;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 0 12px;
  background: transparent;
  color: var(--muted);
  text-align: left;
  cursor: pointer;
  font-size: clamp(0.85rem, 1vw, 1rem);
  transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}

.sidebar button span {
  display: inline-grid;
  place-items: center;
  width: 24px;
  height: 24px;
  margin-right: 9px;
  border-radius: 7px;
  background: rgba(148, 163, 184, 0.1);
  color: var(--blue-2);
  font-size: 0.72rem;
  font-weight: 900;
}

.sidebar button.active,
.sidebar button:hover {
  background: linear-gradient(135deg, rgba(0, 119, 255, 0.86), rgba(0, 94, 220, 0.62));
  color: #fff;
  box-shadow: 0 12px 32px rgba(0, 119, 255, 0.18);
}

.logout {
  margin-top: auto;
}

.content {
  min-height: calc(100vh - 36px);
  border: 1px solid rgba(56, 189, 248, 0.22);
  border-left: 0;
  border-radius: 0 10px 10px 0;
  padding: 28px 30px;
  background:
    linear-gradient(180deg, rgba(12, 20, 30, 0.72), rgba(5, 7, 10, 0.84));
}

.demo-notice {
  margin: 0 0 18px;
  border: 1px solid rgba(245, 158, 11, 0.38);
  border-radius: 10px;
  padding: 12px 14px;
  background: rgba(245, 158, 11, 0.1);
  color: #fde68a;
  line-height: 1.5;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
  animation: riseIn 0.55s ease both;
}

.topbar h1 {
  margin: 5px 0;
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(1.8rem, 3vw, 2.8rem);
}

.user-chip {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 10px 12px;
  background: rgba(8, 13, 20, 0.82);
}

.user-chip > span {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: var(--blue);
  font-weight: 900;
}

.user-chip small,
.metric-card small {
  display: block;
  color: var(--muted);
  font-size: 0.76rem;
  font-weight: 800;
  text-transform: uppercase;
}

.metrics {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}

.metric-card strong {
  display: block;
  margin: 8px 0 4px;
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 700;
}

.metric-card,
.gym-card,
.panel-card,
.table-panel {
  animation: riseIn 0.55s ease both;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 18px 16px;
  background: rgba(9, 15, 23, 0.84);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.metric-card:nth-child(2),
.gym-card:nth-child(2) { animation-delay: 0.05s; }
.metric-card:nth-child(3),
.gym-card:nth-child(3) { animation-delay: 0.1s; }
.metric-card:nth-child(4) { animation-delay: 0.15s; }

.metric-card:hover,
.gym-card:hover,
.panel-card:hover {
  transform: translateY(-2px);
  border-color: rgba(56, 189, 248, 0.42);
  box-shadow: 0 26px 72px rgba(0, 0, 0, 0.42), 0 0 32px rgba(0, 119, 255, 0.08);
}

.metric-card span {
  color: var(--success);
  font-size: 0.82rem;
  font-weight: 800;
}

.dashboard-grid,
.admin-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  gap: 20px;
}

.main-column,
.side-column,
.admin-side {
  display: grid;
  gap: 18px;
  align-content: start;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cards-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.gym-card {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 12px;
  background: rgba(9, 15, 23, 0.84);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.gym-card img,
.class-card img {
  width: 100%;
  height: 110px;
  border-radius: 6px;
  object-fit: cover;
  margin-bottom: 10px;
}

.gym-card span,
.class-card span {
  display: block;
  margin: 4px 0 12px;
  color: var(--muted);
}

.map-preview {
  position: relative;
  min-height: 170px;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 8px;
  background:
    linear-gradient(rgba(11, 20, 30, 0.64), rgba(2, 6, 23, 0.62)),
    url('https://tile.openstreetmap.org/7/45/77.png') center/cover;
  box-shadow: inset 0 0 40px rgba(0, 119, 255, 0.08);
}

.map-preview.tall {
  min-height: 260px;
}

.map-badge {
  position: absolute;
  z-index: 401;
  left: 12px;
  bottom: 12px;
  border-radius: 999px;
  padding: 8px 12px;
  background: rgba(7, 12, 19, 0.82);
  color: #dbeafe;
  font-size: 0.78rem;
  font-weight: 900;
  backdrop-filter: blur(10px);
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-row button {
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 8px 12px;
  background: rgba(8, 13, 20, 0.82);
  color: var(--muted);
  cursor: pointer;
}

.filter-row button.active,
.filter-row button:hover {
  border-color: rgba(56, 189, 248, 0.48);
  color: #fff;
  background: rgba(0, 119, 255, 0.2);
}

.panel-card h3,
.panel-card h2 {
  margin-bottom: 14px;
}

.class-card {
  display: grid;
  grid-template-columns: 88px 1fr;
  gap: 12px;
  align-items: center;
  margin-bottom: 12px;
}

.class-card img {
  height: 72px;
  margin: 0;
}

.list-stack {
  display: grid;
  gap: 12px;
}

.action-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 16px;
  background: rgba(9, 15, 23, 0.84);
  animation: riseIn 0.45s ease both;
}

.action-row span {
  display: block;
  margin-top: 5px;
  color: var(--muted);
}

.progress-explainer {
  display: grid;
  grid-template-columns: 160px 1fr;
  gap: 24px;
  align-items: center;
}

.progress-explainer h3 {
  margin-bottom: 8px;
  font-size: 1.5rem;
}

.mini-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
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

.progress-card {
  text-align: center;
}

.ring,
.donut {
  width: 132px;
  height: 132px;
  margin: 14px auto;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: conic-gradient(var(--blue) 0 85%, rgba(148, 163, 184, 0.18) 85% 100%);
}

.ring span {
  display: grid;
  place-items: center;
  width: 92px;
  height: 92px;
  border-radius: 50%;
  background: #07111f;
  font-weight: 900;
}

.donut::after {
  content: '';
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: #07111f;
}

.sparkline,
.bar-chart {
  display: flex;
  align-items: end;
  gap: 10px;
  min-height: 110px;
  border-top: 1px solid var(--line);
  padding-top: 18px;
}

.sparkline i,
.bar-chart i {
  flex: 1;
  min-width: 12px;
  border-radius: 6px 6px 0 0;
  background: linear-gradient(180deg, var(--blue), rgba(0, 119, 255, 0.18));
  animation: growBar 0.8s ease both;
  transform-origin: bottom;
}

.owner-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) 0.8fr;
  gap: 20px;
}

.chart-card,
.quick-actions {
  grid-column: span 2;
}

.list-item {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 10px;
  background: rgba(5, 9, 14, 0.64);
}

.quick-actions {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.quick-actions h2 {
  grid-column: 1 / -1;
}

.table-panel {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 12px;
  border-bottom: 1px solid var(--line);
  text-align: left;
}

th {
  color: var(--muted);
  font-size: 0.78rem;
  text-transform: uppercase;
}

.status {
  border-radius: 999px;
  padding: 5px 9px;
  background: rgba(34, 197, 94, 0.12);
  color: var(--success);
  font-size: 0.8rem;
  font-weight: 900;
}

.status.warn {
  background: rgba(245, 158, 11, 0.12);
  color: var(--warning);
}

.compact {
  min-height: 150px;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.72);
  backdrop-filter: blur(10px);
}

.modal-card {
  width: min(620px, 100%);
  border: 1px solid rgba(56, 189, 248, 0.34);
  border-radius: 10px;
  padding: 22px;
  background: linear-gradient(180deg, rgba(16, 24, 36, 0.98), rgba(5, 7, 10, 0.98));
  box-shadow: 0 30px 90px rgba(0, 0, 0, 0.55);
  animation: riseIn 0.25s ease both;
}

.modal-card.small {
  width: min(460px, 100%);
}

.modal-card > img {
  width: 100%;
  height: 210px;
  border-radius: 8px;
  object-fit: cover;
  margin-bottom: 16px;
}

.modal-close {
  float: right;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 8px 12px;
  background: rgba(8, 13, 20, 0.9);
  color: var(--text);
  cursor: pointer;
}

.modal-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin: 18px 0;
}

.modal-grid div {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 14px;
  background: rgba(8, 13, 20, 0.64);
}

.modal-grid span {
  display: block;
  margin-top: 8px;
  color: var(--muted);
}

.toast {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 1100;
  max-width: 360px;
  border: 1px solid rgba(56, 189, 248, 0.45);
  border-radius: 8px;
  padding: 14px 16px;
  background: rgba(7, 12, 19, 0.95);
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.4);
  color: #fff;
  font-weight: 800;
  animation: riseIn 0.25s ease both;
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
  .dashboard-shell {
    grid-template-columns: 1fr;
    padding: 12px;
  }

  .mobile-menu-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 100%;
    max-width: 280px;
    height: 100vh;
    z-index: 1000;
    border-radius: 0;
    transform: translateX(-100%);
  }

  .sidebar.open {
    transform: translateX(0);
  }

  .mobile-menu-overlay {
    display: block;
  }

  .mobile-menu-overlay.active {
    display: block;
  }

  .content {
    border: 1px solid rgba(56, 189, 248, 0.22);
    border-radius: 10px;
    padding: 28px 20px;
  }

  .sidebar nav {
    grid-template-columns: repeat(2, 1fr);
  }

  .metrics,
  .cards-row,
  .quick-actions {
    grid-template-columns: repeat(2, 1fr);
  }

  .dashboard-grid,
  .admin-layout,
  .owner-layout {
    grid-template-columns: 1fr;
  }

  .chart-card,
  .quick-actions {
    grid-column: auto;
  }
}

@media (max-width: 768px) {
  .dashboard-shell {
    padding: 12px;
  }

  .mobile-menu-toggle {
    display: flex;
  }

  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: min(320px, 90vw);
    height: 100vh;
    max-height: 100vh;
    z-index: 1000;
    border-radius: 0;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    padding: 24px 16px;
    overflow-y: auto;
  }

  .sidebar.open {
    transform: translateX(0);
  }

  .mobile-menu-overlay {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 999;
    background: rgba(0, 0, 0, 0.6);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }

  .sidebar.open ~ .mobile-menu-overlay {
    opacity: 1;
    pointer-events: auto;
  }

  .content {
    border: none;
    border-radius: 0;
    padding: 18px 16px;
    margin-top: 60px;
    position: relative;
    z-index: 1;
  }

  .topbar {
    margin-bottom: 18px;
    padding-top: 10px;
  }

  .topbar h1 {
    font-size: clamp(1.5rem, 5vw, 2rem);
  }

  .user-chip {
    min-width: 140px;
    padding: 8px 10px;
    gap: 8px;
  }

  .user-chip > span {
    width: 38px;
    height: 38px;
    font-size: 0.9rem;
  }

  .sidebar nav {
    grid-template-columns: 1fr;
    gap: 6px;
  }

  .sidebar button,
  .logout {
    min-height: 46px;
    padding: 0 10px;
    font-size: 0.9rem;
  }

  .metrics,
  .cards-row,
  .quick-actions {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .metric-card {
    padding: 16px 14px;
  }

  .metric-card strong {
    font-size: clamp(1.3rem, 4vw, 1.8rem);
  }

  .gym-card {
    grid-template-columns: 1fr;
  }

  .gym-card img {
    height: 140px;
  }

  .gym-card button {
    min-height: 44px;
    font-size: 0.9rem;
  }
}

@media (max-width: 480px) {
  .dashboard-shell {
    grid-template-columns: 1fr;
    padding: 10px;
  }

  .mobile-menu-toggle {
    width: 42px;
    height: 42px;
    top: 12px;
    left: 12px;
  }

  .content {
    margin-top: 70px;
    padding: 14px;
  }

  .topbar {
    gap: 12px;
    margin-bottom: 14px;
  }

  .topbar h1 {
    font-size: clamp(1.3rem, 6vw, 1.8rem);
    margin: 3px 0;
  }

  .topbar p {
    font-size: 0.8rem;
  }

  .user-chip {
    padding: 6px 8px;
    gap: 6px;
    min-width: auto;
  }

  .user-chip > span {
    width: 36px;
    height: 36px;
    font-size: 0.85rem;
  }

  .user-chip small,
  .user-chip strong {
    font-size: 0.75rem;
  }

  .metrics,
  .cards-row,
  .quick-actions,
  .sidebar nav {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .metric-card {
    padding: 14px 12px;
    border-radius: 6px;
  }

  .metric-card strong {
    font-size: 1.2rem;
    margin: 6px 0 2px;
  }

  .metric-card small {
    font-size: 0.65rem;
  }

  .section-head {
    gap: 8px;
  }

  .section-head h2 {
    font-size: clamp(1.2rem, 4vw, 1.5rem);
  }

  .gym-card {
    padding: 10px;
    border-radius: 6px;
  }

  .gym-card img {
    height: 120px;
    margin-bottom: 8px;
    border-radius: 4px;
  }

  .gym-card button {
    min-height: 42px;
    border-radius: 6px;
    font-size: 0.85rem;
    padding: 0 10px;
  }

  .class-card {
    gap: 10px;
  }

  .class-card img {
    width: 60px;
    height: 60px;
  }

  .progress-explainer,
  .modal-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .action-row {
    padding: 12px;
    gap: 12px;
  }

  .filter-row {
    gap: 6px;
  }

  .filter-row button {
    padding: 6px 10px;
    font-size: 0.8rem;
  }

  .map-preview {
    min-height: 150px;
    border-radius: 6px;
  }

  .modal-card {
    border-radius: 8px;
    padding: 16px;
  }

  .modal-card > img {
    height: 180px;
    margin-bottom: 12px;
  }

  .toast {
    right: 12px;
    bottom: 12px;
    left: 12px;
    max-width: none;
    border-radius: 6px;
    padding: 12px 14px;
    font-size: 0.9rem;
  }
}

@media (max-width: 360px) {
  .dashboard-shell {
    padding: 8px;
  }

  .mobile-menu-toggle {
    width: 40px;
    height: 40px;
    top: 10px;
    left: 10px;
  }

  .content {
    margin-top: 65px;
    padding: 12px;
  }

  .topbar {
    gap: 10px;
    margin-bottom: 12px;
  }

  .topbar h1 {
    font-size: 1.2rem;
  }

  .metric-card {
    padding: 12px 10px;
  }

  .metric-card strong {
    font-size: 1.1rem;
  }

  .section-head h2 {
    font-size: 1.1rem;
  }

  .gym-card img {
    height: 100px;
  }

  .filter-row button {
    padding: 5px 8px;
    font-size: 0.75rem;
  }
}

@media (max-width: 680px) {
  .content {
    padding: 16px;
  }

  .topbar,
  .section-head {
    align-items: stretch;
    flex-direction: column;
  }

  .metrics,
  .cards-row,
  .quick-actions,
  .sidebar nav {
    grid-template-columns: 1fr;
  }

  .progress-explainer,
  .modal-grid {
    grid-template-columns: 1fr;
  }

  .action-row {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
