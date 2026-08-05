<template>
  <div class="home-page">
    <header class="site-nav">
      <router-link to="/" class="brand">
        <img class="brand-mark" src="../assets/gymtrack-mark.svg" alt="GymTrack" />
        <span class="brand-word">Gym<span>Track</span></span>
      </router-link>

      <nav class="main-links">
        <a href="#funciones">Funciones</a>
        <a href="#owners">Para gimnasios</a>
        <a href="#gimnasios">Gimnasios</a>
        <a href="#owners">Owners</a>
      </nav>

      <div class="nav-actions">
        <template v-if="authStore.estaAutenticado">
          <router-link to="/dashboard" class="ghost-btn">Dashboard</router-link>
          <button class="primary-btn" @click="handleLogout">Salir</button>
        </template>
        <template v-else>
          <router-link to="/login" class="ghost-btn">Iniciar sesión</router-link>
          <router-link to="/registro" class="primary-btn">Registrarse</router-link>
        </template>
      </div>

      <!-- Mobile hamburger -->
      <button class="hamburger" type="button" aria-label="Abrir menú" aria-controls="mobile-navigation" @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen">
        <span class="hamburger-box">
          <span class="hamburger-inner"></span>
        </span>
      </button>

      <div class="mobile-drawer" v-if="mobileMenuOpen" @click.self="mobileMenuOpen = false">
        <div id="mobile-navigation" class="mobile-drawer-panel" role="dialog" aria-modal="true" aria-label="Navegación principal">
          <button class="drawer-close" type="button" @click="mobileMenuOpen = false">Cerrar menú</button>
          <ul>
            <li><router-link to="/dashboard" @click="mobileMenuOpen = false">Dashboard</router-link></li>
            <li><a href="#funciones" @click="mobileMenuOpen = false">Funciones</a></li>
            <li><a href="#owners" @click="mobileMenuOpen = false">Para gimnasios</a></li>
            <li><a href="#gimnasios" @click="mobileMenuOpen = false">Gimnasios</a></li>
            <li><a href="#owners" @click="mobileMenuOpen = false">Owners</a></li>
            <li v-if="authStore.estaAutenticado"><button class="ghost-btn" type="button" @click="handleMobileLogout">Salir</button></li>
          </ul>
        </div>
      </div>
    </header>

    <main class="hero">
      <section class="hero-copy">
        <p class="kicker">Plataforma multi-gym SaaS</p>
        <h1><span>Gestiona.</span><span>Conecta.</span><span>Crece.</span><strong>Gym<span>Track</span></strong></h1>
        <p>
          Una plataforma premium para que gimnasios independientes administren socios,
          membresias, clases, reservas y pagos desde una experiencia clara y moderna.
        </p>
        <div class="hero-actions">
          <a href="#gimnasios" class="primary-btn">Buscar gimnasios</a>
          <a href="#owners" class="ghost-btn">Soy dueño de gimnasio</a>
        </div>

      </section>

      <aside class="finder-panel panel-card" id="gimnasios">
        <div class="panel-title">
          <div>
            <span class="kicker">Explorar</span>
            <h2>Gimnasios cerca tuyo</h2>
          </div>
          <button class="locate-btn" type="button" @click="locateUser" title="Detectar ubicación" aria-label="Detectar mi ubicación">⌖</button>
        </div>
        <div class="filters">
          <input v-model="search" type="search" placeholder="Buscar por nombre o ciudad" />
          <select v-model="city">
            <option value="">Todas las ciudades</option>
            <option v-for="name in cities" :key="name" :value="name">{{ name }}</option>
          </select>
          <select v-model="category">
            <option value="">Todas las categorias</option>
            <option v-for="name in categories" :key="name" :value="name">{{ name }}</option>
          </select>
        </div>
        <div ref="mapEl" class="map-shell">
          <span class="map-status">{{ mapStatus }}</span>
          <span
            v-if="!mapReady"
            v-for="gym in filteredGyms"
            :key="gym.id"
            class="map-pin"
            :style="{ left: `${pinPosition(gym).x}%`, top: `${pinPosition(gym).y}%` }"
            :title="gym.name"
          ></span>
        </div>

        <div class="gym-carousel" aria-live="polite">
          <template v-if="filteredGyms.length">
            <div class="gym-carousel-scroll">
              <article v-for="gym in filteredGyms" :key="gym.id" class="gym-result carousel-item" role="button" tabindex="0" @click="openPublicGym(gym)" @keydown.enter="openPublicGym(gym)" @keydown.space.prevent="openPublicGym(gym)">
                <img :src="gym.image" :alt="gym.name" />
                <div>
                  <strong>{{ gym.name }}</strong>
                  <span>{{ gym.city }} · {{ gym.category }}</span>
                  <small>{{ gym.price }}/mes · {{ gym.classes.join(', ') }}</small>
                </div>
              </article>
            </div>
          </template>
          <div v-else class="no-results small">No hay gimnasios que coincidan.</div>
        </div>
      </aside>
    </main>

    <!-- feature section moved to the end (see below) -->

    <section class="showcase">
      <div class="gym-grid">
        <template v-if="loadingData">
          <article v-for="n in 2" :key="n" class="gym-card skeleton">
            <div class="skeleton-image"></div>
            <div class="skeleton-copy">
              <span class="skeleton-line short"></span>
              <span class="skeleton-line"></span>
              <span class="skeleton-line"></span>
            </div>
          </article>
        </template>

        <template v-else-if="filteredGyms.length">
          <article v-for="gym in filteredGyms" :key="gym.id" class="gym-card" :style="{ '--gym-accent': gym.brandColor }">
            <img :src="gym.image" :alt="gym.name" />
            <div class="gym-copy">
              <b class="gym-logo">{{ gym.logoText }}</b>
              <strong>{{ gym.name }}</strong>
              <span>{{ gym.city }} · {{ gym.plan }}</span>
              <small>{{ gym.price }}/mes · {{ gym.classes.join(', ') }}</small>
            </div>
            <button class="text-btn" @click="openPublicGym(gym)">Ver</button>
          </article>
        </template>

        <div v-else class="no-results">
          <strong>No se encontraron gimnasios</strong>
          <p>Prueba con otro término o borra los filtros para explorar todas las opciones.</p>
        </div>
      </div>
    </section>

    <!-- Feature section - moved after showcase for prominent display -->
    <section class="feature-section" id="funciones">
      <div class="section-copy">
        <span class="kicker">Funciones principales</span>
        <h2>Todo lo que necesitas para gestionar tu gimnasio</h2>
      </div>

      <div class="feature-grid">
        <article v-for="(feature, idx) in features" :key="feature.title" class="feature-card" role="button" tabindex="0" :class="{ open: activeFeature === idx }" @click="toggleFeature(idx)" @keydown.enter="toggleFeature(idx)" @keydown.space.prevent="toggleFeature(idx)" @mouseover="desktopHover(idx)" @mouseleave="desktopHover(null)" :aria-expanded="activeFeature === idx">
          <div class="feature-head">
            <span class="feature-icon">{{ feature.icon }}</span>
            <h3>{{ feature.title }}</h3>
          </div>
          <div class="feature-body" v-show="activeFeature === idx">
            <p>{{ feature.text }}</p>
          </div>
        </article>
      </div>
    </section>

    <section class="owner-banner" id="owners">
      <div class="owner-content">
        <span class="kicker">Para gym owners</span>
        <h2>Tu gimnasio, tus datos, tu comunidad</h2>
        <p>
          Cada gimnasio administra sus clases, socios, membresías, cupos y pagos internos. GymTrack opera como una plataforma SaaS, no como un gimnasio único.
        </p>
      </div>
      <button class="primary-btn" type="button" disabled title="Disponible en una próxima fase">Alta de gimnasios próximamente</button>
    </section>

    <div v-if="publicGym" class="modal-backdrop" @click.self="publicGym = null" @keydown.esc="publicGym = null">
      <article class="modal-card" role="dialog" aria-modal="true" :aria-labelledby="`gym-modal-${publicGym.id}`">
        <button class="modal-close" type="button" @click="publicGym = null">Cerrar</button>
        <img :src="publicGym.image" :alt="publicGym.name" />
        <div class="modal-title">
          <span class="gym-logo big" :style="{ background: publicGym.brandColor }">{{ publicGym.logoText }}</span>
          <div>
            <h2 :id="`gym-modal-${publicGym.id}`">{{ publicGym.name }}</h2>
            <p class="muted">{{ publicGym.city }} · {{ publicGym.category }} · {{ publicGym.price }}/mes</p>
          </div>
        </div>
        <div class="modal-grid">
          <div>
            <strong>Clases que podes revisar sin cuenta</strong>
            <span v-for="item in publicGym.classes" :key="item">{{ item }}</span>
          </div>
          <div>
            <strong>Para unirte o reservar</strong>
            <span>Necesitas crear una cuenta de socio.</span>
            <span>La membresia se coordina con el gimnasio.</span>
          </div>
        </div>
        <div class="modal-actions">
          <router-link to="/registro" class="primary-btn">Registrarme como socio</router-link>
          <button class="ghost-btn" type="button" disabled>Alta de gimnasios próximamente</button>
        </div>
      </article>
    </div>

    <footer class="footer">
      <div class="brand">
        <img class="brand-mark large" src="../assets/gymtrack-mark.svg" alt="GymTrack" />
        <span class="brand-word">Gym<span>Track</span></span>
      </div>
      <span>Vue 3 · PHP · MySQL · Docker · Leaflet/OpenStreetMap</span>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { gyms } from '../data/demoData'

const authStore = useAuthStore()
const router = useRouter()
const search = ref('')
const city = ref('')
const category = ref('')
const mapEl = ref(null)
const mapStatus = ref('Mapa demo con OpenStreetMap')
const mapReady = ref(false)
const publicGym = ref(null)

const cities = computed(() => [...new Set(gyms.map((gym) => gym.city))])
const categories = computed(() => [...new Set(gyms.map((gym) => gym.category))])
const filteredGyms = computed(() => {
  const term = search.value.toLowerCase().trim()
  return gyms.filter((gym) => {
    const matchesTerm = !term || `${gym.name} ${gym.city}`.toLowerCase().includes(term)
    const matchesCity = !city.value || gym.city === city.value
    const matchesCategory = !category.value || gym.category === category.value
    return matchesTerm && matchesCity && matchesCategory
  })
})

const loadingData = ref(true)

const features = [
  { icon: '▦', title: 'Multigimnasios', text: 'Gestiona varios gimnasios con owners, socios y datos separados.' },
  { icon: '⌖', title: 'Geolocalizacion', text: 'Busqueda por ciudad y deteccion de ubicacion para gimnasios cercanos.' },
  { icon: '□', title: 'Reservas y clases', text: 'Cupos, profesores, horarios y reservas en una experiencia simple.' },
  { icon: '$', title: 'Membresias y pagos', text: 'Planes demo, vencimientos y estados para defender el flujo SaaS.' },
]

const featuresExpanded = ref(false)
const activeFeature = ref(null)

function toggleFeature(idx) {
  // mobile: only one open at a time
  activeFeature.value = activeFeature.value === idx ? null : idx
}

function desktopHover(idx) {
  // on desktop, hovering shows info but does not persist selection
  if (window.matchMedia && window.matchMedia('(hover: hover)').matches) {
    activeFeature.value = idx
  }
}
const mobileMenuOpen = ref(false)

function pinPosition(gym) {
  const index = gyms.findIndex((item) => item.id === gym.id)
  return { x: 18 + index * 15, y: 34 + (index % 3) * 14 }
}

function locateUser() {
  if (!navigator.geolocation) {
    mapStatus.value = 'Geolocalizacion no disponible en este navegador'
    return
  }
  mapStatus.value = 'Detectando ubicacion...'
  navigator.geolocation.getCurrentPosition(
    () => { mapStatus.value = 'Ubicacion detectada · mostrando gimnasios cercanos' },
    () => { mapStatus.value = 'No se pudo detectar ubicacion · mostrando demo por ciudad' },
    { timeout: 4500 }
  )
}

function openPublicGym(gym) {
  publicGym.value = gym
}

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'home' })
}

async function handleMobileLogout() {
  mobileMenuOpen.value = false
  await handleLogout()
}

onMounted(() => {
  if (window.L && mapEl.value) {
    const map = window.L.map(mapEl.value, { zoomControl: false, attributionControl: true }).setView([-32.36, -54.18], 7)
    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map)
    gyms.forEach((gym) => window.L.marker([gym.lat, gym.lng]).addTo(map).bindPopup(`${gym.name} · ${gym.city}`))
    mapReady.value = true
    mapStatus.value = 'OpenStreetMap activo'
  }

  setTimeout(() => { loadingData.value = false }, 220)
})
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  padding: 22px;
  overflow-x: hidden;
  background:
    radial-gradient(circle at 28% 8%, rgba(56, 189, 248, 0.12), transparent 26rem),
    radial-gradient(circle at 78% 18%, rgba(0, 119, 255, 0.1), transparent 28rem),
    #05070a;
}

.site-nav,
.hero,
.feature-section,
.showcase,
.footer {
  width: min(1180px, 100%);
  margin: 0 auto;
}

.site-nav {
  display: flex;
  min-height: 56px;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  border: 1px solid rgba(56, 189, 248, 0.24);
  border-radius: 14px;
  padding: 10px 16px;
  background: rgba(6, 10, 16, 0.82);
  backdrop-filter: blur(18px);
  box-shadow: 0 0 30px rgba(0, 119, 255, 0.12);
  animation: fadeDown 0.45s ease both;
}

.brand,
.nav-actions,
nav {
  display: flex;
  align-items: center;
  gap: 14px;
}

nav a {
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  text-transform: uppercase;
  padding: 6px 8px;
  border-radius: 999px;
  transition: color 0.18s ease, background 0.18s ease;
}

nav a:hover,
nav a:focus-visible {
  color: var(--text);
  background: rgba(13, 23, 38, 0.8);
}

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1.12fr) 300px;
  gap: 22px;
  align-items: start;
  padding: 18px 0 14px;
  border: 1px solid rgba(56, 189, 248, 0.18);
  border-radius: 16px;
  margin-top: 16px;
  padding-inline: 30px;
  background:
    linear-gradient(90deg, rgba(5, 7, 10, 0.97) 0%, rgba(5, 7, 10, 0.82) 32%, rgba(5, 7, 10, 0.54) 62%, rgba(5, 7, 10, 0.94) 100%),
    url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1600&q=90') center/cover;
  box-shadow: inset 0 0 90px rgba(0, 119, 255, 0.12), 0 24px 84px rgba(0, 0, 0, 0.32);
  overflow: hidden;
}

/* Reduce hero vertical footprint so most content fits on first screen */
.hero { min-height: calc(100vh - 96px); }

.hero-copy {
  min-height: auto;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 22px 0 24px 24px;
  animation: riseIn 0.65s ease both;
}

.hero h1 {
  max-width: 620px;
  margin: 12px 0 18px;
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(2.8rem, 5vw, 4.8rem);
  line-height: 1.02;
  letter-spacing: 0;
  text-transform: uppercase;
}

.hero h1 span,
.hero h1 strong {
  display: block;
}

.hero h1 strong span {
  display: inline;
  color: var(--blue);
}

.hero-copy p:not(.kicker) {
  max-width: 560px;
  color: #dbeafe;
  font-size: 1rem;
  line-height: 1.65;
  margin-top: 16px;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 22px;
}

.hero-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 20px;
}

.hero-stat {
  border: 1px solid rgba(56, 189, 248, 0.18);
  background: rgba(10, 15, 26, 0.88);
  border-radius: 16px;
  padding: 16px;
  min-height: 82px;
  transition: transform 0.18s ease, border-color 0.18s ease;
}

.hero-stat strong {
  display: block;
  font-size: 1.45rem;
  line-height: 1.1;
}

.hero-stat span {
  color: var(--muted);
  font-size: 0.83rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hero-stat:hover {
  transform: translateY(-1px);
  border-color: rgba(56, 189, 248, 0.32);
}

.finder-panel {
  align-self: stretch;
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: auto 0;
  background: rgba(7, 12, 19, 0.76);
  border: 1px solid rgba(56, 189, 248, 0.14);
  border-radius: 18px;
  padding: 18px 18px 20px;
  box-shadow: 0 18px 60px rgba(0, 119, 255, 0.12);
  animation: slideIn 0.72s ease 0.08s both;
  max-height: 440px;
}

.panel-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.panel-title h2 {
  margin-top: 4px;
  font-family: Montserrat, Inter, sans-serif;
  font-size: 1.08rem;
  text-transform: uppercase;
}

.locate-btn {
  width: 40px;
  height: 40px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: rgba(0, 119, 255, 0.16);
  color: var(--blue-2);
  cursor: pointer;
  transition: transform 0.18s ease, background 0.18s ease;
}

.locate-btn:hover {
  transform: translateY(-1px);
  background: rgba(0, 119, 255, 0.2);
}

.filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.filters input,
.filters select {
  min-height: 44px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 12px 14px;
  background: rgba(5, 10, 20, 0.82);
  color: var(--text);
  transition: border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
}

.filters input:focus,
.filters select:focus {
  border-color: rgba(56, 189, 248, 0.45);
  background: rgba(8, 14, 24, 0.94);
  transform: translateY(-1px);
  outline: none;
}

.map-shell {
  position: relative;
  min-height: 120px;
  overflow: hidden;
  border: 1px solid rgba(56, 189, 248, 0.14);
  border-radius: 16px;
  background:
    linear-gradient(rgba(10, 16, 26, 0.9), rgba(8, 11, 18, 0.82)),
    url('https://tile.openstreetmap.org/7/45/77.png') center/cover;
}

.gym-list {
  margin-top: 12px;
  max-height: 240px;
  overflow-y: auto;
  scroll-behavior: smooth;
  padding-right: 6px;
}

.gym-list::-webkit-scrollbar { width: 8px; }
.gym-list::-webkit-scrollbar-thumb { background: rgba(56,189,248,0.18); border-radius: 8px; }
.gym-list::-webkit-scrollbar-track { background: transparent; }

.no-results.small { padding: 12px; color: var(--muted); }

.gym-carousel {
  min-width: 0;
  overflow: hidden;
}

.gym-carousel-scroll {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  padding-bottom: 6px;
  scroll-snap-type: x proximity;
  scrollbar-color: rgba(56, 189, 248, 0.28) transparent;
  scrollbar-width: thin;
}

.carousel-item {
  flex: 0 0 100%;
  min-width: 0;
  scroll-snap-align: start;
}

.map-status {
  position: absolute;
  left: 12px;
  bottom: 12px;
  z-index: 1;
  border-radius: 999px;
  padding: 7px 12px;
  background: rgba(3, 8, 18, 0.88);
  color: #dbeafe;
  font-size: 0.78rem;
}

.map-pin {
  position: absolute;
  z-index: 1;
  width: 16px;
  height: 16px;
  border: 3px solid white;
  border-radius: 999px 999px 999px 2px;
  background: var(--blue);
  transform: rotate(-45deg);
  box-shadow: 0 0 24px rgba(0, 119, 255, 0.7);
}

.gym-result,
.gym-card {
  display: grid;
  grid-template-columns: 68px 1fr;
  gap: 12px;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 16px;
  padding: 14px;
  background: rgba(10, 16, 26, 0.88);
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.gym-result:hover,
.gym-card:hover {
  transform: translateY(-2px);
  border-color: rgba(56, 189, 248, 0.24);
  box-shadow: 0 14px 32px rgba(0, 0, 0, 0.22);
}

.gym-result img,
.gym-card img {
  width: 68px;
  height: 52px;
  border-radius: 12px;
  object-fit: cover;
  filter: saturate(0.92);
}

.gym-result span,
.gym-result small,
.gym-card span,
.gym-card small {
  display: block;
  margin-top: 4px;
  color: var(--muted);
}


.showcase {
  display: grid;
  gap: 18px;
  margin-bottom: 34px;
}

.gym-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.gym-card {
  display: grid;
  grid-template-columns: 68px minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
  border: 1px solid rgba(56, 189, 248, 0.16);
  border-radius: 18px;
  padding: 16px;
  background: rgba(11, 17, 26, 0.92);
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.gym-card:hover {
  transform: translateY(-3px);
  border-color: rgba(56, 189, 248, 0.28);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
}

.gym-copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.gym-logo {
  display: inline-grid;
  place-items: center;
  width: 36px;
  height: 36px;
  margin-bottom: 6px;
  border-radius: 12px;
  background: var(--gym-accent, var(--blue));
  color: #fff;
  font-size: 0.78rem;
  font-weight: 900;
  box-shadow: 0 0 24px rgba(0, 119, 255, 0.28);
}

.gym-logo.big {
  width: 54px;
  height: 54px;
  margin: 0;
  font-size: 1rem;
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
  width: min(660px, 100%);
  border: 1px solid rgba(56, 189, 248, 0.34);
  border-radius: 10px;
  padding: 22px;
  background: linear-gradient(180deg, rgba(16, 24, 36, 0.98), rgba(5, 7, 10, 0.98));
  box-shadow: 0 30px 90px rgba(0, 0, 0, 0.55);
  animation: riseIn 0.25s ease both;
}

.modal-card > img {
  width: 100%;
  height: 230px;
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

.modal-title {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
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

.modal-actions {
  display: flex;
  gap: 12px;
}

@keyframes fadeDown {
  from {
    opacity: 0.92;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes riseIn {
  from {
    opacity: 0.92;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideIn {
  from {
    opacity: 0.92;
    transform: translateX(18px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.owner-copy {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 18px;
}

.footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-top: 1px solid var(--line);
  padding: 24px 0 10px;
  color: var(--muted);
}

.brand-mark.large {
  width: 78px;
  height: 78px;
}

/* Base de funciones. Antes estaba anidada por error dentro del breakpoint móvil. */
.feature-section {
  display: grid;
  gap: 24px;
  margin: 44px auto;
}

.section-copy {
  display: flex;
  flex-direction: column;
  gap: 8px;
  text-align: center;
}

.section-copy h2 {
  max-width: 680px;
  margin: 0 auto;
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(1.8rem, 4vw, 2.6rem);
  line-height: 1.12;
  text-transform: uppercase;
}

.feature-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  min-width: 0;
}

.feature-card {
  position: relative;
  min-width: 0;
  min-height: 160px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(56,189,248,0.14);
  border-radius: 16px;
  padding: 24px;
  background: linear-gradient(135deg, rgba(14,22,36,0.92), rgba(10,16,26,0.88));
  cursor: pointer;
  transition: transform 220ms ease, border-color 220ms ease, background 220ms ease;
}

.feature-card .feature-head {
  display: flex;
  align-items: center;
  gap: 14px;
}

.feature-icon {
  flex: 0 0 48px;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border: 2px solid rgba(56,189,248,0.24);
  border-radius: 12px;
  background: rgba(0,119,255,0.1);
  font-size: 1.5rem;
}

.feature-body {
  max-height: 0;
  margin-top: 16px;
  overflow: hidden;
  color: var(--muted);
  font-size: 0.94rem;
  line-height: 1.56;
  opacity: 0;
  transform: translateY(-8px);
  transition: opacity 220ms ease, transform 220ms ease;
}

.feature-card.open .feature-body {
  max-height: 300px;
  opacity: 1;
  transform: translateY(0);
}

.owner-banner {
  width: min(1180px, 100%);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin: 44px auto;
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 28px;
  background: rgba(7, 12, 19, 0.92);
}

.owner-content {
  max-width: 68ch;
}

.owner-content p {
  margin-top: 12px;
  color: var(--muted);
  line-height: 1.65;
}

@media (max-width: 1020px) {
  nav {
    display: none;
  }

  .hero {
    grid-template-columns: 1fr;
    padding-inline: 24px;
  }

  .hero-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .hero-stats {
    grid-template-columns: 1fr;
  }

  .finder-panel {
    padding: 18px;
  }

  .filters {
    grid-template-columns: 1fr;
  }

  .feature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .gym-grid {
    grid-template-columns: 1fr;
  }

  .owner-banner {
    flex-direction: column;
    align-items: stretch;
  }

  .owner-banner > .primary-btn {
    width: 100%;
  }
}

/* Mobile-focused adjustments */
@media (max-width: 480px) {
  .site-nav {
    padding: 8px 12px;
    min-height: 44px;
    gap: 8px;
  }
  .brand-mark { width: 36px; height: 36px; }
  .brand-word { font-size: 1rem; }
  nav.main-links, .nav-actions { display: none; }
  .hamburger { display: inline-flex; background: transparent; border: none; padding: 8px; margin-left: 6px; }
  .hamburger-box { width: 22px; height: 16px; display: block; position: relative; }
  .hamburger-inner, .hamburger-inner::before, .hamburger-inner::after { background: var(--text); width: 22px; height: 2px; display: block; border-radius: 2px; position: absolute; transition: transform 180ms ease, opacity 180ms ease; }
  .hamburger-inner::before { content: ''; top: -6px; }
  .hamburger-inner::after { content: ''; top: 6px; }

  .mobile-drawer { position: fixed; inset: 0; background: rgba(2,6,12,0.6); display: flex; align-items: flex-start; justify-content: flex-end; z-index: 60; }
  .mobile-drawer-panel { width: min(320px, 86%); background: #071018; padding: 18px; box-shadow: -6px 0 30px rgba(0,0,0,0.6); border-left: 1px solid rgba(56,189,248,0.06); height: 100vh; overflow-y: auto; }
  .mobile-drawer-panel ul { list-style: none; padding: 6px 0; margin: 36px 0 0; display: flex; flex-direction: column; gap: 12px; }
  .mobile-drawer-panel a, .mobile-drawer-panel button { color: var(--text); text-decoration: none; font-weight: 700; padding: 12px 10px; border-radius: 10px; background: transparent; }
  .drawer-close { position: absolute; right: 12px; top: 10px; background: transparent; border: none; color: var(--muted); font-size: 0.85rem; }

  /* Hero compact */
  .hero { grid-template-columns: 1fr; padding-inline: 18px; min-height: calc(100vh - 64px); }
  .hero-copy h1 { font-size: 1.8rem; line-height: 1.05; }
  .hero-copy p { margin-top: 10px; font-size: 0.95rem; }
  .hero-actions { margin-top: 12px; gap: 8px; }

  /* Stats 2x2 compact */
  .hero-stats { grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 12px; }
  .hero-stat { padding: 10px; min-height: 56px; }
  .hero-stat strong { font-size: 1.05rem; }

  /* Filters compact */
  .filters input, .filters select { min-height: 40px; padding: 8px 10px; border-radius: 10px; }
  .map-shell { min-height: 100px; }
  .finder-panel { max-height: 380px; padding: 12px; }

  .gym-list { max-height: 180px; }
  .gym-result { gap: 10px; padding: 8px; }
  .gym-result img { width: 56px; height: 44px; border-radius: 8px; }

  .gym-card { padding: 10px; grid-template-columns: 56px 1fr auto; border-radius: 12px; }
  .gym-card img { width: 56px; height: 44px; border-radius: 8px; }

    /* Carousel scroll styles */
  .gym-carousel-scroll { display: flex; gap: 12px; overflow-x: auto; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; padding-bottom: 6px; }
  .gym-carousel-scroll::-webkit-scrollbar { height: 4px; }
  .gym-carousel-scroll::-webkit-scrollbar-thumb { background: rgba(56,189,248,0.12); border-radius: 2px; }
  .carousel-item { flex: 0 0 calc(100% - 12px); min-width: 260px; }
  .carousel-item img { width: 64px; height: 48px; }

/* Feature section - Premium design */
.feature-section {
  display: grid;
  gap: 24px;
  margin: 44px auto;
  padding: 0;
  animation: fadeDown 0.52s ease both;
}

.section-copy {
  display: flex;
  flex-direction: column;
  gap: 8px;
  text-align: center;
}

.section-copy h2 {
  font-family: Montserrat, Inter, sans-serif;
  font-size: clamp(1.8rem, 4vw, 2.6rem);
  line-height: 1.12;
  text-transform: uppercase;
  max-width: 680px;
  margin: 0 auto;
}

.feature-grid {
  display: grid !important;
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 16px !important;
  width: min(1180px, 100%) !important;
  margin: 0 auto !important;
}

.feature-section .feature-grid {
  display: grid !important;
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 16px !important;
}

div.feature-grid {
  display: grid !important;
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 16px !important;
}

/* Feature card behavior - Premium styling */
.feature-card {
  position: relative;
  background: linear-gradient(135deg, rgba(14,22,36,0.92) 0%, rgba(10,16,26,0.88) 100%);
  border: 1px solid rgba(56,189,248,0.14);
  border-radius: 16px;
  padding: 24px;
  cursor: pointer;
  transition: transform 220ms ease, border-color 220ms ease, box-shadow 220ms ease, background 220ms ease;
  overflow: hidden;
  min-height: 160px;
  display: flex;
  flex-direction: column;
}

.feature-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(56,189,248,0.08), transparent 70%);
  pointer-events: none;
}

.feature-card .feature-head {
  display: flex;
  align-items: center;
  gap: 14px;
  position: relative;
  z-index: 1;
}

.feature-icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border: 2px solid rgba(56,189,248,0.24);
  border-radius: 12px;
  font-size: 1.5rem;
  background: rgba(0,119,255,0.1);
  transition: transform 220ms ease, background 220ms ease, border-color 220ms ease;
}

.feature-card h3 {
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--text);
}

.feature-body {
  margin-top: 16px;
  color: var(--muted);
  line-height: 1.56;
  font-size: 0.94rem;
  transition: opacity 220ms ease, transform 220ms ease;
  max-height: 0;
  opacity: 0;
  transform: translateY(-8px);
  position: relative;
  z-index: 1;
  overflow: hidden;
}

.feature-card.open .feature-body {
  max-height: 300px;
  opacity: 1;
  transform: translateY(0);
}

.feature-card:hover:not(.open) {
  transform: translateY(-4px);
  border-color: rgba(56,189,248,0.24);
  background: linear-gradient(135deg, rgba(14,22,36,0.96) 0%, rgba(10,16,26,0.92) 100%);
}

.feature-card.open {
  transform: translateY(-6px);
  border-color: rgba(56,189,248,0.32);
  box-shadow: 0 14px 40px rgba(0,119,255,0.15), 0 0 30px rgba(0,119,255,0.08);
  background: linear-gradient(135deg, rgba(14,28,46,0.98) 0%, rgba(12,18,32,0.95) 100%);
}

.feature-card.open .feature-icon {
  background: rgba(0,119,255,0.18);
  border-color: rgba(56,189,248,0.42);
  transform: scale(1.1) rotate(5deg);
}

  /* Feature accordion compact */
  .feature-section { padding-inline: 18px; margin: 32px auto; }
  .feature-grid,
  .feature-section .feature-grid,
  div.feature-grid { grid-template-columns: 1fr !important; gap: 12px !important; }
  .feature-card { padding: 16px; min-height: 140px; }
  .feature-icon { width: 40px; height: 40px; font-size: 1.2rem; }
  .feature-card h3 { font-size: 0.98rem; }
  .feature-body { margin-top: 12px; font-size: 0.88rem; line-height: 1.5; }

  /* CTA owner card */
  .owner-banner { padding: 14px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.6); border: 1px solid rgba(56,189,248,0.06); }
  .owner-banner .primary-btn { padding: 10px 12px; font-size: 1rem; }

  /* Footer compact */
  .footer { padding: 10px 0; gap: 8px; }
}

@media (hover: hover) and (pointer: fine) {
  .feature-toggle {
    width: max-content;
  }
}

@media (max-width: 768px) {
  .home-page {
    padding: 18px;
  }

  .site-nav {
    flex-direction: column;
    align-items: stretch;
    padding: 16px;
  }

  .nav-actions {
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
  }

  .hero {
    padding: 20px 0 16px;
  }

  .hero-copy {
    padding-left: 0;
  }

  .hero h1 {
    font-size: clamp(2.4rem, 7vw, 3.4rem);
  }

  .finder-panel {
    gap: 16px;
  }

  .map-shell {
    min-height: 140px;
  }

  .filters input,
  .filters select {
    min-height: 44px;
  }

  .feature-toggle {
    width: 100%;
  }

  .feature-panel.open {
    max-height: 1000px;
  }
}

@media (max-width: 560px) {
  .home-page {
    padding: 16px;
  }

  .site-nav {
    padding: 14px;
  }

  .hero {
    padding-inline: 16px;
  }

  .hero-actions {
    gap: 10px;
  }

  .hero h1 {
    font-size: clamp(2rem, 9vw, 2.6rem);
  }

  .filters input,
  .filters select {
    padding: 10px 12px;
  }

  .map-shell {
    min-height: 120px;
  }

  .gym-card {
    grid-template-columns: 1fr;
  }

  .gym-card img {
    width: 100%;
    height: 140px;
  }

  .text-btn {
    width: 100%;
  }

  .owner-banner {
    padding: 20px;
  }
}

@media (max-width: 420px) {
  .home-page {
    padding: 12px;
  }

  .site-nav {
    padding: 12px;
  }

  .hero {
    padding: 16px;
  }

  .hero h1 {
    font-size: clamp(1.8rem, 10vw, 2.2rem);
    line-height: 1.08;
  }

  .panel-title {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .filters {
    gap: 10px;
  }

  .hero-stats {
    gap: 10px;
  }

  .owner-banner {
    padding: 18px;
  }
}

@media (max-width: 560px) {
  .site-nav {
    flex-direction: row;
    align-items: center;
  }

  .hero,
  .hero-copy,
  .finder-panel,
  .hero-actions,
  .filters,
  .gym-carousel {
    min-width: 0;
    max-width: 100%;
  }

  .hero {
    width: 100%;
    min-height: auto;
    padding: 16px;
  }

  .hero-copy {
    width: 100%;
    padding: 16px 0 24px;
    overflow-wrap: anywhere;
  }

  .hero-actions > * {
    width: 100%;
    white-space: normal;
    text-align: center;
  }

  .finder-panel {
    width: 100%;
    max-height: none;
    margin: 0;
    overflow: hidden;
  }

  .filters > * {
    min-width: 0;
    width: 100%;
  }

  .carousel-item {
    flex-basis: 100%;
    min-width: 0;
  }
}
</style>
