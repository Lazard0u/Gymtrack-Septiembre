<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { IconArrowLeft, IconBarbell, IconCheck, IconClipboardCheck, IconDroplet, IconLock, IconMail, IconMapPin, IconParking, IconPhone, IconRoute, IconSun, IconUsers, IconWifi } from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import MiniMapaSede from '../components/public/MiniMapaSede.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { usePagosStore } from '../stores/pagos'
import { estadoOperativo, filasHorario } from '../utils/horarios'

const route = useRoute(); const router = useRouter(); const auth = useAuthStore(); const payments = usePagosStore()
const gym = ref(null); const plans = ref([]); const status = ref('loading'); const plansStatus = ref('loading')
const selectedPlan = ref(null); const notice = ref(null); const paying = ref(false); const sedeIndex = ref(0)

const isClosed = computed(() => gym.value?.estado === 'temporalmente_cerrado')
const title = computed(() => gym.value?.nombre ? `${gym.value.nombre} | GymTrack` : 'Gimnasio | GymTrack')
const estado = computed(() => (gym.value ? estadoOperativo(gym.value) : null))
const horario = computed(() => (gym.value ? filasHorario(gym.value) : []))
const hoy = computed(() => horario.value.find((fila) => fila.hoy && !fila.cerrado))
const sedes = computed(() => (gym.value?.sedes?.length ? gym.value.sedes : [{ id: 0, nombre: 'Sede principal', direccion: gym.value?.direccion, ciudad: gym.value?.ciudad, departamento: gym.value?.departamento, latitud: gym.value?.latitud, longitud: gym.value?.longitud }]))
const sede = computed(() => sedes.value[sedeIndex.value] || sedes.value[0])
const chosen = computed(() => plans.value.find((plan) => plan.id === selectedPlan.value) || null)
const isMember = computed(() => (auth.user?.gimnasios || []).some((item) => Number(item.gimnasio_id) === Number(gym.value?.id) && item.rol_nombre === 'socio'))
const loginTarget = computed(() => ({ name: 'login', query: { redirect: router.resolve({ name: 'gym-detail', params: { slug: route.params.slug }, query: chosen.value ? { plan: chosen.value.id } : {} }).fullPath } }))
const hechos = computed(() => [[gym.value?.clases_activas, 'clases activas'], [plans.value.length, 'planes'], [sedes.value.length, sedes.value.length === 1 ? 'sede' : 'sedes']].filter(([count]) => Number(count) > 0))

const ICONOS = [[/vestuario|ducha/, IconDroplet], [/locker|casillero/, IconLock], [/evalua|medici/, IconClipboardCheck], [/clase|grupal/, IconUsers], [/exterior|aire libre/, IconSun], [/wifi/, IconWifi], [/estacion|parking|cochera/, IconParking], [/muscula|pesas|sala/, IconBarbell]]
function iconoServicio(nombre) { const limpio = nombre.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); return ICONOS.find(([patron]) => patron.test(limpio))?.[1] || IconCheck }
function money(value, currency) { const amount = Number(value || 0); return new Intl.NumberFormat('es-UY', { style: 'currency', currency, minimumFractionDigits: Number.isInteger(amount) ? 0 : 2 }).format(amount) }
function porDia(plan) { return money(Number(plan.precio) / Math.max(1, Number(plan.duracion_dias)), plan.moneda).replace(/[,.]\d+$/, '') }
function comoLlegar(item) { return `https://www.openstreetmap.org/directions?to=${encodeURIComponent(`${item.latitud},${item.longitud}`)}` }

async function load() {
  status.value = 'loading'
  const detail = await api.get(`/public/gyms/${encodeURIComponent(route.params.slug)}`)
  if (!detail.ok || detail.data?.error) { status.value = 'error'; return }
  gym.value = detail.data.gimnasio
  document.title = title.value
  status.value = 'ready'
  await loadPlans()
}
async function loadPlans() {
  plansStatus.value = 'loading'
  const response = await api.get(`/public/gyms/${gym.value.id}/plans`)
  if (!response.ok || response.data?.error) { plans.value = []; plansStatus.value = 'error'; return }
  plans.value = [...(response.data.planes || [])].sort((a, b) => Number(a.precio) - Number(b.precio))
  const wanted = Number(route.query.plan)
  selectedPlan.value = plans.value.find((plan) => plan.id === wanted)?.id ?? plans.value[0]?.id ?? null
  plansStatus.value = 'ready'
}

// Solo un socio del gimnasio puede pagar: el alta la hace hoy el propio gimnasio, por eso los demás reciben una explicación y no un error.
async function startCheckout() {
  if (!chosen.value || isClosed.value) return
  notice.value = null
  if (!auth.initialized) await auth.probe()
  if (!auth.estaAutenticado) { router.push(loginTarget.value); return }
  if (auth.user.role !== 'socio') { notice.value = { tone: 'info', title: 'Los planes se pagan con una cuenta de socio', text: 'Iniciaste sesión con otro rol. Ingresá con tu cuenta de socio para pagar una membresía.' }; return }
  if (!isMember.value) { notice.value = { tone: 'info', title: 'Todavía no sos socio de este gimnasio', text: 'Hoy el alta como socio la hace el gimnasio. Escribile para que te asocie y después vas a poder pagar el plan desde acá.' }; return }
  paying.value = true
  try {
    if (Number(auth.user.active_gym_id) !== Number(gym.value.id)) await auth.cambiarGimnasio(gym.value.id)
    const result = await payments.checkout(chosen.value.id)
    const url = result.checkout_url
    if (!url) notice.value = { tone: 'warning', title: 'Checkout todavía no disponible', text: 'El pago se registró, pero el proveedor no devolvió una dirección de pago.' }
    else if (url.startsWith('/')) router.push(url)
    else window.location.assign(url)
  } catch (error) {
    notice.value = { tone: 'danger', title: 'No se pudo iniciar el pago', text: error.message }
  } finally { paying.value = false }
}
onMounted(load)
</script>

<template>
  <div class="public-page"><PublicHeader /><main class="container gym">
    <button class="gym__back" type="button" @click="router.push({ name: 'gyms' })"><IconArrowLeft :size="18" />Volver al mapa</button>
    <div v-if="status === 'loading'" class="gym__loading" role="status" aria-label="Cargando el gimnasio"><AppSkeleton height="22rem" /><AppSkeleton height="14rem" /></div>
    <AppErrorState v-else-if="status === 'error'" title="No encontramos este gimnasio" description="Puede que no esté publicado o que el enlace haya cambiado." @retry="load" />
    <template v-else>
      <header class="hero" :class="{ 'hero--empty': !gym.imagen_path }">
        <img v-if="gym.imagen_path" class="hero__img" :src="gym.imagen_path" :alt="`Vista de ${gym.nombre}`" width="1200" height="700" />
        <IconBarbell v-else class="hero__icon" :size="96" stroke-width="1" aria-hidden="true" />
        <div class="hero__body">
          <p class="hero__status">
            <span v-if="estado" class="state" :class="`state--${estado.tone}`"><i aria-hidden="true"></i>{{ estado.label }}</span>
            <span v-if="hoy" class="hero__hours">· hoy {{ hoy.horas }}</span>
            <span v-if="gym.is_demo" class="hero__demo">Datos de demostración</span>
          </p>
          <h1>{{ gym.nombre }}</h1>
          <p class="hero__where"><IconMapPin :size="17" aria-hidden="true" />{{ gym.direccion }} · {{ gym.ciudad }}</p>
        </div>
      </header>

      <div class="layout">
        <div class="layout__main">
          <section class="block" aria-labelledby="sobre-titulo">
            <h2 id="sobre-titulo">Sobre este gimnasio</h2>
            <p class="about">{{ gym.descripcion }}</p>
            <ul v-if="hechos.length" class="facts"><li v-for="[count, label] in hechos" :key="label"><strong>{{ count }}</strong> {{ label }}</li></ul>
            <div v-if="gym.categorias?.length" class="chips"><span v-for="category in gym.categorias" :key="category">{{ category }}</span></div>
          </section>

          <section v-if="gym.servicios?.length" class="block" aria-labelledby="servicios-titulo">
            <h2 id="servicios-titulo">Qué incluye</h2>
            <ul class="amenities"><li v-for="service in gym.servicios" :key="service"><span class="amenities__icon" aria-hidden="true"><component :is="iconoServicio(service)" :size="19" /></span>{{ service }}</li></ul>
          </section>

          <section v-if="horario.length" class="block" aria-labelledby="horarios-titulo">
            <h2 id="horarios-titulo">Horarios</h2>
            <dl class="hours"><div v-for="fila in horario" :key="fila.clave" :class="{ 'hours__today': fila.hoy }"><dt>{{ fila.dia }}<span v-if="fila.hoy" class="hours__tag">Hoy</span></dt><dd :class="{ 'hours__closed': fila.cerrado }">{{ fila.horas }}</dd></div></dl>
          </section>

          <section class="block" aria-labelledby="donde-titulo">
            <h2 id="donde-titulo">Cómo llegar</h2>
            <div class="where">
              <MiniMapaSede v-if="sede?.latitud && sede?.longitud" :latitud="sede.latitud" :longitud="sede.longitud" :nombre="sede.nombre" />
              <div class="where__info">
                <div v-if="sedes.length > 1" class="where__tabs" role="group" aria-label="Sedes"><button v-for="(item, index) in sedes" :key="item.id" type="button" :aria-pressed="index === sedeIndex" @click="sedeIndex = index">{{ item.nombre }}</button></div>
                <address class="where__address"><strong>{{ sede.nombre }}</strong><span>{{ sede.direccion }}</span><span>{{ sede.ciudad }}, {{ sede.departamento }}</span></address>
                <div class="actions">
                  <a v-if="sede?.latitud" class="action" :href="comoLlegar(sede)" target="_blank" rel="noopener noreferrer"><IconRoute :size="18" />Cómo llegar</a>
                  <a v-if="sede.telefono || gym.telefono" class="action" :href="`tel:${sede.telefono || gym.telefono}`"><IconPhone :size="18" />Llamar</a>
                  <a v-if="sede.email || gym.email" class="action" :href="`mailto:${sede.email || gym.email}`"><IconMail :size="18" />Escribir</a>
                </div>
              </div>
            </div>
          </section>
        </div>

        <aside id="planes" class="layout__side" aria-labelledby="planes-titulo">
          <div class="plans">
            <div><h2 id="planes-titulo">Membresías</h2><p class="plans__hint">Elegí un plan y pagá online.</p></div>
            <AppAlert v-if="notice" :tone="notice.tone" :title="notice.title"><p class="plans__alert">{{ notice.text }}</p></AppAlert>
            <AppAlert v-if="isClosed" tone="warning" title="Inscripciones pausadas"><p class="plans__alert">El gimnasio cerró temporalmente las altas nuevas.</p></AppAlert>
            <div v-if="plansStatus === 'loading'" class="plans__loading" role="status" aria-label="Cargando planes"><AppSkeleton v-for="index in 3" :key="index" height="4rem" /></div>
            <AppErrorState v-else-if="plansStatus === 'error'" title="No pudimos cargar los planes" description="Probá de nuevo en unos minutos." @retry="loadPlans" />
            <AppEmptyState v-else-if="!plans.length" title="Todavía no hay planes publicados" description="Escribile al gimnasio para consultar precios." />
            <template v-else>
              <div class="plans__list" role="radiogroup" aria-labelledby="planes-titulo">
                <label v-for="plan in plans" :key="plan.id" class="plan" :class="{ 'plan--on': selectedPlan === plan.id }">
                  <input v-model="selectedPlan" class="plan__input" type="radio" name="plan" :value="plan.id" :disabled="isClosed" />
                  <span class="plan__dot" aria-hidden="true"></span>
                  <span class="plan__text"><strong>{{ plan.nombre }}</strong><small>{{ plan.duracion_dias }} días · {{ porDia(plan) }} por día</small></span>
                  <span class="plan__price">{{ money(plan.precio, plan.moneda) }}</span>
                </label>
              </div>
              <div v-if="chosen" class="plans__detail">
                <p v-if="chosen.descripcion">{{ chosen.descripcion }}</p>
                <ul v-if="chosen.beneficios?.length"><li v-for="benefit in chosen.beneficios" :key="benefit"><IconCheck :size="16" />{{ benefit }}</li></ul>
              </div>
              <AppButton block :disabled="isClosed || !chosen" :loading="paying" @click="startCheckout">{{ chosen && !isClosed ? `Pagar ${money(chosen.precio, chosen.moneda)}` : 'Elegí un plan' }}</AppButton>
              <p class="plans__foot">Pagás con Mercado Pago. La membresía se activa cuando el pago queda aprobado.</p>
              <p v-if="!auth.estaAutenticado" class="plans__foot">¿Ya sos socio? <RouterLink :to="loginTarget">Iniciá sesión</RouterLink> para pagar.</p>
            </template>
          </div>
        </aside>
      </div>
    </template>
  </main><PublicFooter /></div>
</template>

<style scoped>
.gym { padding-block: var(--space-6) var(--space-20); }
.gym__back { display: inline-flex; align-items: center; gap: var(--space-2); min-height: 2.75rem; border: 0; padding: 0; background: transparent; color: var(--text-secondary); cursor: pointer; font: inherit; font-size: .85rem; font-weight: 700; transition-property: color; transition-duration: var(--duration-fast); }
.gym__back:hover { color: var(--text-primary); }
.gym__loading { display: grid; gap: var(--space-6); margin-top: var(--space-4); }

.hero { position: relative; display: flex; align-items: flex-end; min-height: 23rem; margin-top: var(--space-2); overflow: hidden; border-radius: var(--radius-dialog); background: linear-gradient(135deg, var(--surface-2), var(--surface-1)); isolation: isolate; }
.hero__img { position: absolute; inset: 0; z-index: -2; width: 100%; height: 100%; object-fit: cover; outline: 1px solid rgba(255, 255, 255, .1); outline-offset: -1px; }
.hero::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(to top, rgba(8, 11, 15, .95) 0%, rgba(8, 11, 15, .6) 36%, rgba(8, 11, 15, .08) 74%); }
.hero__icon { position: absolute; top: var(--space-8); right: var(--space-10); z-index: -1; color: var(--text-tertiary); opacity: .35; }
.hero__body { width: 100%; padding: clamp(var(--space-5), 3vw, var(--space-10)); }
.hero__status { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2) var(--space-3); margin: 0; font-size: .85rem; font-weight: 650; color: rgba(255, 255, 255, .86); }
.state { display: inline-flex; align-items: center; gap: var(--space-2); font-weight: 800; }
.state i { width: .55rem; height: .55rem; border-radius: 50%; background: var(--text-tertiary); }
.state--success { color: var(--status-success-strong); }.state--success i { background: var(--success); box-shadow: 0 0 0 3px rgba(67, 193, 122, .25); }
.state--warning { color: var(--warning); }.state--warning i { background: var(--warning); }
.hero__hours { font-variant-numeric: tabular-nums; }
.hero__demo { margin-left: auto; border: 1px solid rgba(255, 255, 255, .28); border-radius: var(--radius-pill); padding: .15rem .65rem; font-size: .7rem; font-weight: 700; color: rgba(255, 255, 255, .8); }
.hero h1 { margin: var(--space-3) 0 var(--space-2); max-width: 20ch; font-size: clamp(2.1rem, 4.8vw, 3.2rem); line-height: 1.04; letter-spacing: -.025em; text-wrap: balance; }
.hero__where { display: flex; align-items: center; gap: var(--space-2); margin: 0; color: rgba(255, 255, 255, .78); font-size: .95rem; }

.layout { display: grid; grid-template-columns: minmax(0, 1fr) 23rem; gap: clamp(var(--space-8), 5vw, var(--space-16)); align-items: start; margin-top: var(--space-10); }
.block { padding-block: var(--space-8); border-top: 1px solid var(--border-subtle); }
.block:first-child { padding-top: 0; border-top: 0; }
.block h2, .plans h2 { margin: 0 0 var(--space-4); font-size: 1.15rem; letter-spacing: -.01em; }
.about { max-width: 62ch; margin: 0; color: var(--text-secondary); line-height: 1.7; text-wrap: pretty; }
.facts { display: flex; flex-wrap: wrap; gap: var(--space-2) var(--space-8); margin: var(--space-6) 0 0; padding: 0; list-style: none; color: var(--text-secondary); font-size: .88rem; }
.facts strong { margin-right: .2rem; color: var(--text-primary); font-size: 1.5rem; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
.chips { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-5); }
.chips span { border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding: .3rem .85rem; color: var(--text-secondary); font-size: .78rem; font-weight: 650; }
.amenities { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: var(--space-4) var(--space-6); margin: 0; padding: 0; list-style: none; }
.amenities li { display: flex; align-items: center; gap: var(--space-3); color: var(--text-secondary); font-size: .92rem; }
.amenities__icon { display: grid; flex: 0 0 auto; width: 2.4rem; height: 2.4rem; place-items: center; border-radius: var(--radius-control); background: var(--surface-2); color: var(--status-info-strong); }
.hours { display: grid; max-width: 28rem; margin: 0; }
.hours > div { display: flex; align-items: baseline; justify-content: space-between; gap: var(--space-4); padding: var(--space-3) 0; border-bottom: 1px solid var(--border-subtle); font-variant-numeric: tabular-nums; }
.hours dt { color: var(--text-secondary); }
.hours dd { margin: 0; font-weight: 650; }
.hours__closed { color: var(--text-tertiary); font-weight: 500 !important; }
.hours__today dt, .hours__today dd { color: var(--text-primary); font-weight: 800; }
.hours__tag { margin-left: var(--space-2); border-radius: var(--radius-pill); padding: .1rem .5rem; background: var(--accent-soft); color: var(--status-info-strong); font-size: .68rem; font-weight: 800; }
.where { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: var(--space-6); align-items: start; }
.where__info { display: grid; gap: var(--space-5); }
.where__tabs { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.where__tabs button { min-height: 2.5rem; border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding: 0 var(--space-4); background: transparent; color: var(--text-secondary); cursor: pointer; font: inherit; font-size: .8rem; font-weight: 700; transition-property: background-color, border-color, color; transition-duration: var(--duration-fast); }
.where__tabs button[aria-pressed='true'] { border-color: var(--accent); background: var(--accent-soft); color: var(--text-primary); }
.where__address { display: grid; gap: .15rem; font-style: normal; color: var(--text-secondary); }
.where__address strong { color: var(--text-primary); font-size: 1rem; }
.actions { display: flex; flex-wrap: wrap; gap: var(--space-3); }
.action { display: inline-flex; align-items: center; gap: var(--space-2); min-height: 2.75rem; border: 1px solid var(--border-strong); border-radius: var(--radius-control); padding: 0 var(--space-4); color: var(--text-primary); font-size: .85rem; font-weight: 700; text-decoration: none; transition-property: background-color, border-color, transform; transition-duration: var(--duration-fast); }
.action:hover { border-color: var(--text-tertiary); background: var(--surface-2); }
.action:active { transform: scale(.97); }

.layout__side { position: sticky; top: calc(var(--header-height, 4.5rem) + var(--space-5)); }
.plans { display: grid; gap: var(--space-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); padding: var(--space-6); background: var(--surface-1); box-shadow: var(--shadow-md); }
.plans h2 { margin-bottom: var(--space-1); }
.plans__hint { margin: 0; color: var(--text-secondary); font-size: .85rem; }
.plans__alert { margin: 0; color: var(--text-secondary); font-size: .85rem; }
.plans__loading { display: grid; gap: var(--space-3); }
.plans__list { display: grid; gap: var(--space-2); }
.plan { position: relative; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: var(--space-3); min-height: 3.75rem; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); padding: var(--space-3) var(--space-4); background: var(--bg-subtle); cursor: pointer; transition-property: border-color, background-color; transition-duration: var(--duration-fast); }
.plan:hover { border-color: var(--text-tertiary); }
.plan__input { position: absolute; opacity: 0; pointer-events: none; }
.plan:has(.plan__input:focus-visible) { outline: 2px solid var(--focus); outline-offset: 2px; }
.plan:has(.plan__input:disabled) { cursor: not-allowed; opacity: .55; }
.plan--on { border-color: var(--accent); background: var(--accent-soft); }
.plan__dot { width: 1.15rem; height: 1.15rem; border: 2px solid var(--border-strong); border-radius: 50%; transition-property: border-color, box-shadow; transition-duration: var(--duration-fast); }
.plan--on .plan__dot { border-color: var(--accent); box-shadow: inset 0 0 0 3px var(--bg-subtle), inset 0 0 0 10px var(--accent); }
.plan__text { display: grid; gap: .1rem; min-width: 0; }
.plan__text strong { font-size: .92rem; }
.plan__text small { color: var(--text-tertiary); font-size: .74rem; font-variant-numeric: tabular-nums; }
.plan__price { font-size: 1.05rem; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
.plans__detail { display: grid; gap: var(--space-3); border-top: 1px solid var(--border-subtle); padding-top: var(--space-4); }
.plans__detail p { margin: 0; color: var(--text-secondary); font-size: .85rem; line-height: 1.55; text-wrap: pretty; }
.plans__detail ul { display: grid; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.plans__detail li { display: flex; align-items: center; gap: var(--space-2); color: var(--text-secondary); font-size: .85rem; }
.plans__detail li svg { color: var(--success); }
.plans__foot { margin: 0; color: var(--text-tertiary); font-size: .75rem; line-height: 1.5; text-wrap: pretty; }
.plans__foot a { color: var(--status-info-strong); text-underline-offset: .2em; }

@media (max-width: 63.99rem) {
  .layout { grid-template-columns: 1fr; margin-top: var(--space-6); }
  .layout__side { position: static; order: -1; }
  .hero { min-height: 19rem; }
  .where { grid-template-columns: 1fr; }
}
@media (max-width: 47.99rem) {
  .hero { min-height: 17rem; }
  .hero__demo { margin-left: 0; }
  .plans { padding: var(--space-5); }
}
@media (prefers-reduced-motion: reduce) { .action, .plan, .plan__dot, .where__tabs button { transition: none; } .action:active { transform: none; } }
</style>
