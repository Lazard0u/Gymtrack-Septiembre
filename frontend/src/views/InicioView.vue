<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  IconArrowRight,
  IconBell,
  IconCalendarEvent,
  IconChartLine,
  IconCheck,
  IconChevronDown,
  IconCreditCard,
  IconMap2,
  IconShieldCheck,
  IconUsersGroup,
} from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import ExploradorGimnasios from '../components/public/ExploradorGimnasios.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import { api } from '../services/api'
import { useGimnasiosPublicosStore } from '../stores/gimnasiosPublicos'
import heroImage from '../assets/images/gymtrack-hero-training.webp'
import ownerImage from '../assets/images/gymtrack-owner-studio.webp'

const journey = [
  { icon: IconMap2, title: 'Descubrí', text: 'Encontrá gimnasios en el mapa y compará horarios, servicios y precios.' },
  { icon: IconCreditCard, title: 'Elegí tu plan', text: 'Cada plan muestra su costo por día. Lo elegís desde la ficha del gimnasio.' },
  { icon: IconShieldCheck, title: 'Pagá y empezá', text: 'El pago se confirma con el proveedor y, al aprobarse, quedás como socio.' },
  { icon: IconCalendarEvent, title: 'Reservá y asistí', text: 'Reservá clases, anotate en la lista de espera y mostrá tu carné con QR.' },
]

const capabilities = [
  { icon: IconUsersGroup, title: 'Socios y equipo', text: 'Información centralizada, con acceso según el rol de cada persona.', details: ['Socios, empleados y entrenadores', 'Invitaciones de un solo uso', 'Permisos distintos por gimnasio'] },
  { icon: IconCalendarEvent, title: 'Clases y reservas', text: 'Horarios, cupos, lista de espera y asistencia en un flujo consistente.', details: ['Clases recurrentes con cupos', 'Lista de espera automática', 'Asistencia y carné con QR'] },
  { icon: IconCreditCard, title: 'Membresías y pagos', text: 'Estados claros e historial verificable, sin confirmar pagos desde una redirección.', details: ['Planes con precio y vigencia', 'Pagos confirmados por el proveedor', 'Historial de cada socio'] },
  { icon: IconChartLine, title: 'Finanzas y reportes', text: 'Datos reales desde MySQL, con filtros y exportaciones operativas.', details: ['Ingresos y deuda por período', 'Reportes en Excel y PDF', 'Comparación entre períodos'] },
  { icon: IconBell, title: 'Comunicación', text: 'Promociones y avisos con preferencias, consentimiento e historial.', details: ['Promociones con audiencia y programación', 'Avisos internos y por correo', 'Preferencias y consentimiento registrados'] },
]
const openCapability = ref(0)

// Planes reales de los gimnasios publicados (el mismo endpoint de la página Planes).
const publicGyms = useGimnasiosPublicosStore()
const allPlanGyms = ref([]); const plansLoaded = ref(false)
const planGyms = computed(() => allPlanGyms.value.slice(0, 4))
// Cifras reales: salen de los gimnasios y planes publicados, nada está escrito a mano.
const stats = computed(() => [
  [publicGyms.items.length, 'gimnasios'],
  [new Set(publicGyms.items.map((gym) => gym.ciudad).filter(Boolean)).size, 'ciudades'],
  [allPlanGyms.value.reduce((total, gym) => total + gym.planes.length, 0), 'planes'],
].filter(([count]) => count > 0))
function desde(gym) {
  const currency = gym.planes[0].moneda
  const lowest = Math.min(...gym.planes.filter((plan) => plan.moneda === currency).map((plan) => Number(plan.precio)))
  return new Intl.NumberFormat('es-UY', { style: 'currency', currency, minimumFractionDigits: 0 }).format(lowest)
}
onMounted(async () => {
  publicGyms.load()
  const response = await api.get('/public/plans')
  allPlanGyms.value = (response.ok && !response.data?.error ? response.data.gimnasios || [] : []).filter((gym) => gym.planes?.length)
  plansLoaded.value = true
})

const questions = [
  { question: '¿Los gimnasios de la presentación son reales?', answer: 'No. Son cinco sedes ficticias identificadas como datos de demostración. Existen en MySQL y pueden eliminarse por dataset sin mezclarse con futuros gimnasios reales.' },
  { question: '¿Puedo crear una cuenta?', answer: 'Sí. Podés registrarte como socio o enviar una solicitud para gestionar un gimnasio. Empleados y entrenadores ingresan mediante invitaciones protegidas del gimnasio.' },
  { question: '¿Los pagos están habilitados?', answer: 'El historial, los pagos manuales y la validación por webhook están implementados. El checkout con Mercado Pago sólo se habilita cuando el entorno tiene credenciales válidas.' },
  { question: '¿Funciona en el celular?', answer: 'La experiencia pública se adapta desde 360 px, incluye navegación móvil y un panel inferior para explorar el mapa sin scroll horizontal.' },
]
</script>

<template>
  <div class="public-shell">
    <PublicHeader />

    <main>
      <section class="hero">
        <div class="container hero__grid">
          <div class="hero__copy">
            <h1>Tu gimnasio, en un solo lugar.</h1>
            <p class="hero__lead">GymTrack conecta a socios y gimnasios con una experiencia clara para descubrir, reservar y llevar el día a día.</p>
            <div class="hero__actions">
              <AppLinkButton :to="{ name: 'gyms' }" size="lg">Explorar gimnasios<template #icon><IconMap2 :size="19" /></template></AppLinkButton>
              <AppLinkButton :to="{ name: 'for-gyms' }" variant="secondary" size="lg">Gestioná tu gimnasio<template #icon><IconArrowRight :size="19" /></template></AppLinkButton>
            </div>
            <ul v-if="stats.length" class="hero__stats" aria-label="GymTrack en números"><li v-for="[count, label] in stats" :key="label"><b class="pb-num">{{ count }}</b><span>{{ label }}</span></li></ul>
            <p class="hero__note">Los datos de esta demo son ficticios y están marcados como tal.</p>
          </div>
          <div class="hero__map"><ExploradorGimnasios variant="hero" compact-heading /></div>
        </div>
      </section>

      <section class="pb-section" aria-labelledby="member-title">
        <div class="container">
          <div class="pb-head"><p class="pb-index"><b>01</b>Para socios</p><h2 id="member-title" class="pb-title">Del mapa a tu primera clase, sin saltos.</h2></div>
          <ol class="pb-steps">
            <li v-for="(step, index) in journey" :key="step.title"><span class="pb-steps__n">{{ String(index + 1).padStart(2, '0') }}</span><component :is="step.icon" :size="22" stroke-width="1.7" aria-hidden="true" /><h3>{{ step.title }}</h3><p>{{ step.text }}</p></li>
          </ol>
        </div>
      </section>

      <section class="pb-section owner" aria-labelledby="owner-title">
        <div class="container owner__grid">
          <div class="owner__copy">
            <div class="pb-head"><p class="pb-index"><b>02</b>Para dueños</p><h2 id="owner-title" class="pb-title">La operación visible, no escondida.</h2><p class="pb-lead">Administración ofrece acceso directo a socios, equipo, clases, reservas, membresías, pagos, finanzas y reportes.</p></div>
            <ul class="owner__facts">
              <li><IconUsersGroup :size="20" aria-hidden="true" />Gestión diaria en una estructura predecible.</li>
              <li><IconChartLine :size="20" aria-hidden="true" />Finanzas y reportes a un clic.</li>
              <li><IconShieldCheck :size="20" aria-hidden="true" />Permisos y datos separados por gimnasio.</li>
            </ul>
            <AppLinkButton :to="{ name: 'for-gyms' }" variant="secondary">Conocer la propuesta<template #icon><IconArrowRight :size="18" /></template></AppLinkButton>
          </div>
          <div class="owner__photo"><img :src="ownerImage" alt="Responsable de un gimnasio organizando la operación del equipo" width="1122" height="1402" loading="lazy" /></div>
        </div>
      </section>

      <section id="funciones" class="pb-section" aria-labelledby="capabilities-title">
        <div class="container pb-split">
          <div class="pb-head"><p class="pb-index"><b>03</b>Funciones</p><h2 id="capabilities-title" class="pb-title">Una base para toda la experiencia.</h2><p class="pb-lead">Estas capacidades están disponibles en GymTrack 1.0 y se conectan a datos reales. Las integraciones beta se identifican por separado.</p></div>
          <div class="acc-list">
            <div v-for="(item, index) in capabilities" :key="item.title" :class="['acc', { 'acc--open': openCapability === index }]">
              <h3><button :id="`cap-${index}`" class="acc__trigger" type="button" :aria-expanded="openCapability === index" :aria-controls="`cap-panel-${index}`" @click="openCapability = openCapability === index ? -1 : index">
                <span class="acc__n pb-num" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span><span class="acc__title">{{ item.title }}</span><IconChevronDown class="acc__chev" :size="20" aria-hidden="true" />
              </button></h3>
              <div :id="`cap-panel-${index}`" class="acc__panel" role="region" :aria-labelledby="`cap-${index}`" :inert="openCapability !== index">
                <div class="acc__inner"><div class="acc__body"><p>{{ item.text }}</p><ul><li v-for="detail in item.details" :key="detail"><IconCheck :size="15" aria-hidden="true" />{{ detail }}</li></ul></div></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="pb-section" aria-labelledby="plans-title">
        <div class="container pb-split">
          <div class="pb-head"><p class="pb-index"><b>04</b>Planes</p><h2 id="plans-title" class="pb-title">Elegí con información real.</h2><p class="pb-lead">Cada gimnasio publica sus planes con precio, vigencia y beneficios. Compará y elegí desde su ficha.</p></div>
          <div>
            <ul v-if="planGyms.length" class="pb-rows plans-rows">
              <li v-for="gym in planGyms" :key="gym.gimnasio_id"><RouterLink :to="{ name: 'gym-detail', params: { slug: gym.gimnasio_slug } }"><span class="plans-rows__name"><strong>{{ gym.gimnasio_nombre }}</strong><small>{{ gym.ciudad }} · {{ gym.planes.length }} {{ gym.planes.length === 1 ? 'plan' : 'planes' }}</small></span><span class="plans-rows__from"><small>desde</small><b class="pb-num">{{ desde(gym) }}</b></span><IconArrowRight :size="18" aria-hidden="true" /></RouterLink></li>
            </ul>
            <AppEmptyState v-else-if="plansLoaded" title="Todavía no hay planes publicados" description="Cuando un gimnasio publique sus membresías las vas a ver acá.">
              <template #icon><IconCreditCard :size="24" /></template>
            </AppEmptyState>
            <div v-if="planGyms.length" class="plans-foot"><AppLinkButton :to="{ name: 'plans' }" variant="secondary" size="sm">Comparar todos los planes<template #icon><IconArrowRight :size="16" /></template></AppLinkButton></div>
          </div>
        </div>
      </section>

      <section class="pb-section" aria-labelledby="faq-title">
        <div class="container pb-split">
          <div class="pb-head"><p class="pb-index"><b>05</b>Preguntas frecuentes</p><h2 id="faq-title" class="pb-title">Lo importante, claro desde el inicio.</h2></div>
          <div class="faq-list">
            <details v-for="item in questions" :key="item.question">
              <summary>{{ item.question }}<span aria-hidden="true">+</span></summary>
              <p>{{ item.answer }}</p>
            </details>
          </div>
        </div>
      </section>

      <section class="final-cta">
        <img class="final-cta__img" :src="heroImage" alt="" width="1536" height="1024" loading="lazy" />
        <div class="container final-cta__inner">
          <h2>Tu próxima rutina merece una mejor experiencia.</h2>
          <div class="final-cta__actions"><AppLinkButton :to="{ name: 'registro' }" size="lg">Crear cuenta<template #icon><IconArrowRight :size="19" /></template></AppLinkButton><AppLinkButton :to="{ name: 'login' }" variant="secondary" size="lg">Ya tengo una cuenta</AppLinkButton></div>
          <p class="final-cta__owner-note">¿Ya sos socio y querés sumar tu propio gimnasio? <RouterLink :to="{ name: 'owner-register' }">Pedí tu cuenta de dueño</RouterLink></p>
        </div>
      </section>
    </main>

    <PublicFooter />
  </div>
</template>

<style scoped>
.public-shell { min-height: 100vh; background: var(--bg-canvas); }

/* Portada */
.hero { overflow: hidden; }
.hero__grid { display: grid; grid-template-columns: minmax(19rem, .82fr) minmax(28rem, 1.18fr); align-items: center; gap: clamp(var(--space-8), 4vw, var(--space-12)); padding-block: clamp(var(--space-8), 5vw, var(--space-12)) clamp(var(--space-10), 6vw, var(--space-16)); min-height: min(42rem, calc(100svh - var(--header-height))); }
.hero__copy { position: relative; z-index: 2; animation: intro-copy 600ms var(--ease-out) both; }
.hero h1 { max-width: 10ch; margin-bottom: var(--space-5); }
.hero__lead { max-width: 36rem; margin: 0 0 var(--space-8); color: var(--text-secondary); font-size: clamp(1rem, 1.6vw, 1.15rem); line-height: 1.65; text-wrap: pretty; }
.hero__actions { display: flex; flex-wrap: wrap; gap: var(--space-3); }
.hero__stats { display: flex; gap: clamp(var(--space-6), 3vw, var(--space-10)); margin: var(--space-10) 0 0; padding: var(--space-5) 0 0; border-top: 1px solid var(--border-subtle); list-style: none; }
.hero__stats li { display: grid; gap: .1rem; }
.hero__stats b { font-size: clamp(1.9rem, 3vw, 2.5rem); line-height: 1; }
.hero__stats span { color: var(--text-secondary); font-size: .82rem; }
.hero__note { margin: var(--space-4) 0 0; color: var(--text-tertiary); font-size: .78rem; }
.hero__map { min-width: 0; animation: intro-visual 700ms 80ms var(--ease-out) both; }

/* Para dueños */
.owner__grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); align-items: center; gap: clamp(var(--space-8), 5vw, var(--space-16)); }
.owner__copy { display: grid; gap: var(--space-6); justify-items: start; }
.owner__copy .pb-head { margin-bottom: 0; }
.owner__facts { display: grid; gap: var(--space-3); margin: 0; padding: 0; list-style: none; color: var(--text-secondary); }
.owner__facts li { display: flex; align-items: center; gap: var(--space-3); }
.owner__facts svg { flex: 0 0 auto; color: var(--status-info-strong); }
.owner__photo { height: min(30rem, 62vh); overflow: hidden; border-radius: var(--radius-dialog); }
.owner__photo img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 25%; outline: 1px solid rgba(255, 255, 255, .08); outline-offset: -1px; }

/* Funciones: filas numeradas que se abren con un clic */
.acc-list { border-top: 1px solid var(--border-subtle); }
.acc { border-bottom: 1px solid var(--border-subtle); }
.acc h3 { margin: 0; }
.acc__trigger { display: flex; align-items: center; gap: var(--space-5); width: 100%; min-height: 4.5rem; border: 0; padding: var(--space-4) 0; background: transparent; color: inherit; cursor: pointer; font: inherit; text-align: left; }
.acc__trigger:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; border-radius: var(--radius-control); }
.acc__n { width: 2rem; flex: 0 0 auto; color: var(--text-tertiary); font-size: .9rem; font-weight: 800; transition: color var(--duration-fast); }
.acc--open .acc__n, .acc__trigger:hover .acc__n { color: var(--status-info-strong); }
.acc__title { flex: 1; font-size: clamp(1.15rem, 2vw, 1.5rem); font-weight: 720; letter-spacing: -.02em; }
.acc__chev { color: var(--text-tertiary); transition: transform var(--duration-normal) var(--ease-out), color var(--duration-fast); }
.acc__trigger:hover .acc__chev { color: var(--text-primary); }
.acc--open .acc__chev { color: var(--text-primary); transform: rotate(180deg); }
.acc__panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows var(--duration-normal) var(--ease-out); }
.acc--open .acc__panel { grid-template-rows: 1fr; }
.acc__inner { min-height: 0; overflow: hidden; }
.acc__body { display: grid; gap: var(--space-4); padding: 0 0 var(--space-6) calc(2rem + var(--space-5)); }
.acc__body p { margin: 0; max-width: 46ch; color: var(--text-secondary); line-height: 1.65; text-wrap: pretty; }
.acc__body ul { display: flex; flex-wrap: wrap; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.acc__body li { display: inline-flex; align-items: center; gap: var(--space-2); border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); padding: .3rem .8rem; color: var(--text-secondary); font-size: .78rem; font-weight: 650; }
.acc__body li svg { color: var(--success); }

/* Planes */
.plans-rows a { display: flex; align-items: center; gap: var(--space-5); min-height: 5rem; padding: var(--space-4) var(--space-2); color: inherit; text-decoration: none; transition-property: background-color; transition-duration: var(--duration-fast); }
.plans-rows a:hover { background: var(--surface-1); }
.plans-rows__name { display: grid; flex: 1; min-width: 0; gap: .15rem; }
.plans-rows__name strong { font-size: 1.1rem; letter-spacing: -.015em; }
.plans-rows small { color: var(--text-tertiary); font-size: .78rem; font-weight: 500; }
.plans-rows__from { display: grid; text-align: right; }
.plans-rows__from b { font-size: 1.6rem; line-height: 1.1; }
.plans-rows svg { flex: 0 0 auto; color: var(--text-tertiary); transition: transform var(--duration-fast) var(--ease-out), color var(--duration-fast); }
.plans-rows a:hover svg { color: var(--text-primary); transform: translateX(3px); }
.plans-foot { margin-top: var(--space-5); }

/* Preguntas */
.faq-list { border-top: 1px solid var(--border-subtle); }
.faq-list details { border-bottom: 1px solid var(--border-subtle); }
.faq-list summary { display: flex; align-items: center; justify-content: space-between; gap: var(--space-4); min-height: 4rem; padding: var(--space-3) 0; cursor: pointer; font-size: 1.05rem; font-weight: 700; list-style: none; }
.faq-list summary::-webkit-details-marker { display: none; }
.faq-list summary span { color: var(--text-tertiary); font-size: 1.4rem; font-weight: 400; transition: transform var(--duration-normal) var(--ease-out); }
.faq-list details[open] summary span { transform: rotate(45deg); }
.faq-list p { max-width: 56ch; margin: 0 0 var(--space-5); color: var(--text-secondary); line-height: 1.65; text-wrap: pretty; }

/* Cierre con foto */
.final-cta { position: relative; isolation: isolate; overflow: hidden; border-top: 1px solid var(--border-subtle); }
.final-cta__img { position: absolute; inset: 0; z-index: -2; width: 100%; height: 100%; object-fit: cover; object-position: 70% 50%; }
.final-cta::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, rgba(11, 14, 18, .96) 22%, rgba(11, 14, 18, .55) 60%, rgba(11, 14, 18, .2)); }
.final-cta__inner { display: grid; gap: var(--space-6); justify-items: start; padding-block: clamp(var(--space-16), 9vw, 7rem); }
.final-cta h2 { max-width: 16ch; margin: 0; font-size: clamp(2.1rem, 4.6vw, 3.6rem); line-height: 1.04; text-wrap: balance; }
.final-cta__actions { display: flex; flex-wrap: wrap; gap: var(--space-3); }
.final-cta__owner-note { margin: 0; color: var(--text-secondary); font-size: .9rem; }
.final-cta__owner-note a { color: var(--status-info-strong); text-underline-offset: .2em; }

@keyframes intro-copy { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
@keyframes intro-visual { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

@media (max-width: 63.99rem) {
  .hero__grid { min-height: auto; grid-template-columns: 1fr; }
  .owner__grid { grid-template-columns: 1fr; }
  .owner__photo { order: -1; height: 22rem; }
}
@media (max-width: 47.99rem) {
  .hero__stats { gap: var(--space-6); }
  .acc__body { padding-left: 0; }
  .plans-rows a { gap: var(--space-3); }
  .plans-rows__from b { font-size: 1.35rem; }
  .final-cta::after { background: linear-gradient(180deg, rgba(11, 14, 18, .55), rgba(11, 14, 18, .94)); }
}
@media (prefers-reduced-motion: reduce) {
  .hero__copy, .hero__map { animation: none; }
  .acc__panel, .acc__chev, .acc__n, .plans-rows svg, .plans-rows a, .faq-list summary span { transition: none; }
}
</style>
