<script setup>
import { computed, onMounted, ref } from 'vue'
import { IconArrowRight, IconCreditCard } from '@tabler/icons-vue'
import { api } from '../services/api'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'

const gyms = ref([])
const status = ref('loading')
const summary = computed(() => ({ gimnasios: gyms.value.length, planes: gyms.value.reduce((total, gym) => total + gym.planes.length, 0) }))

function money(value, currency = 'UYU') { const amount = Number(value || 0); return new Intl.NumberFormat('es-UY', { style: 'currency', currency, minimumFractionDigits: Number.isInteger(amount) ? 0 : 2 }).format(amount) }
function porDia(plan) { return money(Number(plan.precio) / Math.max(1, Number(plan.duracion_dias)), plan.moneda).replace(/[,.]\d+$/, '') }
// El plan con menor costo por día de ese gimnasio (solo si hay más de uno para comparar).
function mejorId(gym) {
  if (gym.planes.length < 2) return null
  const dia = (plan) => Number(plan.precio) / Math.max(1, Number(plan.duracion_dias))
  return gym.planes.reduce((mejor, plan) => (dia(plan) < dia(mejor) ? plan : mejor)).id
}

async function load() {
  status.value = 'loading'
  const response = await api.get('/public/plans')
  if (!response.ok || response.data?.error) { status.value = 'error'; return }
  gyms.value = response.data.gimnasios || []
  status.value = gyms.value.length ? 'ready' : 'empty'
}
onMounted(load)
</script>

<template>
  <div class="public-page">
    <PublicHeader />
    <main>
      <header class="container plans-head">
        <p class="pb-index"><b>→</b>Planes</p>
        <h1>Compará planes y elegí el tuyo.</h1>
        <p class="pb-lead">Precio, duración y costo por día de cada gimnasio, lado a lado. Elegí un plan y pagalo desde la ficha del gimnasio.</p>
        <ul v-if="status === 'ready'" class="plans-head__stats" aria-label="Planes publicados"><li><b class="pb-num">{{ summary.gimnasios }}</b>gimnasios</li><li><b class="pb-num">{{ summary.planes }}</b>planes</li></ul>
      </header>

      <div class="container plans-body">
        <div v-if="status === 'loading'" class="plans-loading" role="status" aria-label="Cargando planes"><AppSkeleton v-for="index in 3" :key="index" height="3.5rem" /></div>
        <AppErrorState v-else-if="status === 'error'" title="No pudimos cargar los planes" description="Los gimnasios publicados no respondieron. Probá de nuevo en unos minutos." @retry="load" />
        <AppEmptyState v-else-if="status === 'empty'" title="Aún no hay planes publicados" description="Los gimnasios todavía no publicaron membresías.">
          <template #icon><IconCreditCard :size="25" /></template>
          <template #action><AppLinkButton :to="{ name: 'gyms' }" variant="secondary">Explorar gimnasios</AppLinkButton></template>
        </AppEmptyState>
        <template v-else>
          <section v-for="gym in gyms" :key="gym.gimnasio_id" class="gym" :aria-labelledby="`gym-${gym.gimnasio_id}-title`">
            <header class="gym__head">
              <div><h2 :id="`gym-${gym.gimnasio_id}-title`">{{ gym.gimnasio_nombre }}</h2><p>{{ gym.ciudad }}, {{ gym.departamento }}</p></div>
              <RouterLink class="gym__link" :to="{ name: 'gym-detail', params: { slug: gym.gimnasio_slug } }">Ver ficha<IconArrowRight :size="17" aria-hidden="true" /></RouterLink>
            </header>
            <div class="table" role="list">
              <div class="table__cols" aria-hidden="true"><span>Plan</span><span>Duración</span><span>Costo por día</span><span>Precio</span><span></span></div>
              <RouterLink v-for="plan in gym.planes" :key="plan.id" class="row" role="listitem" :to="{ name: 'gym-detail', params: { slug: gym.gimnasio_slug }, query: { plan: plan.id } }">
                <span class="row__main"><strong>{{ plan.nombre }}</strong><small v-if="plan.descripcion">{{ plan.descripcion }}</small><small v-if="plan.beneficios?.length" class="row__perks">{{ plan.beneficios.join(' · ') }}</small></span>
                <span class="row__dur pb-num"><span class="row__label">Duración</span>{{ plan.duracion_dias }} días</span>
                <span class="row__day pb-num"><span class="row__label">Costo por día</span><b>{{ porDia(plan) }}</b><small v-if="plan.id === mejorId(gym)" class="row__best">menor costo</small></span>
                <span class="row__price pb-num">{{ money(plan.precio, plan.moneda) }}</span>
                <IconArrowRight class="row__go" :size="18" aria-hidden="true" />
              </RouterLink>
            </div>
          </section>
        </template>
      </div>
    </main>
    <PublicFooter />
  </div>
</template>

<style scoped>
.plans-head { display: grid; gap: var(--space-5); justify-items: start; padding-block: clamp(var(--space-8), 5vw, var(--space-12)) clamp(var(--space-8), 4vw, var(--space-10)); }
.plans-head h1 { max-width: 14ch; margin: 0; font-size: clamp(2.2rem, 5vw, 3.8rem); line-height: 1.03; text-wrap: balance; }
.plans-head__stats { display: flex; gap: var(--space-10); margin: var(--space-2) 0 0; padding: 0; list-style: none; color: var(--text-secondary); font-size: .85rem; }
.plans-head__stats li { display: flex; align-items: baseline; gap: var(--space-2); }
.plans-head__stats b { color: var(--text-primary); font-size: 1.9rem; line-height: 1; }
.plans-body { padding-bottom: clamp(var(--space-12), 6vw, var(--space-16)); }
.plans-loading { display: grid; gap: var(--space-3); }

.gym { padding-block: var(--space-8) var(--space-6); border-top: 1px solid var(--border-subtle); }
.gym__head { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-5); }
.gym__head h2 { margin: 0 0 .15rem; font-size: clamp(1.4rem, 2.4vw, 1.9rem); line-height: 1.1; }
.gym__head p { margin: 0; color: var(--text-secondary); font-size: .88rem; }
.gym__link { display: inline-flex; align-items: center; gap: var(--space-2); min-height: 2.75rem; color: var(--status-info-strong); font-size: .88rem; font-weight: 700; text-decoration: none; }
.gym__link svg { transition: transform var(--duration-fast) var(--ease-out); }
.gym__link:hover svg { transform: translateX(3px); }

.table { border-top: 1px solid var(--border-strong); }
.table__cols, .row { display: grid; grid-template-columns: minmax(0, 1fr) 6.5rem 8.5rem 9rem 1.5rem; align-items: center; gap: var(--space-5); }
.table__cols { padding: var(--space-3) var(--space-3); color: var(--text-tertiary); font-size: .72rem; font-weight: 700; }
.table__cols span:nth-child(3), .table__cols span:nth-child(4) { text-align: right; }
.row { padding: var(--space-4) var(--space-3); border-top: 1px solid var(--border-subtle); color: inherit; text-decoration: none; transition-property: background-color; transition-duration: var(--duration-fast); }
.row:hover { background: var(--surface-1); }
.row__main { display: grid; gap: .2rem; min-width: 0; }
.row__main strong { font-size: 1.05rem; letter-spacing: -.01em; }
.row__main small { color: var(--text-secondary); font-size: .82rem; line-height: 1.45; text-wrap: pretty; }
.row__perks { color: var(--text-tertiary) !important; }
.row__dur { color: var(--text-secondary); font-size: .9rem; }
.row__day { display: grid; justify-items: end; text-align: right; }
.row__day b { font-size: 1rem; }
.row__best { color: var(--status-success-strong); font-size: .72rem; font-weight: 700; }
.row__price { text-align: right; font-size: 1.35rem; font-weight: 800; }
.row__go { color: var(--text-tertiary); transition: transform var(--duration-fast) var(--ease-out), color var(--duration-fast); }
.row:hover .row__go { color: var(--text-primary); transform: translateX(3px); }
.row__label { display: none; }

@media (max-width: 63.99rem) {
  .table__cols { display: none; }
  .row { grid-template-columns: minmax(0, 1fr) auto; gap: var(--space-3) var(--space-4); }
  .row__main { grid-column: 1 / -1; }
  .row__dur, .row__day { display: grid; justify-items: start; text-align: left; }
  .row__label { display: block; color: var(--text-tertiary); font-size: .7rem; font-weight: 600; }
  .row__price { grid-column: 2; grid-row: 2 / 4; align-self: center; }
  .row__go { display: none; }
}
@media (max-width: 47.99rem) {
  .gym__head { align-items: flex-start; flex-direction: column; gap: var(--space-2); }
  .plans-head__stats { gap: var(--space-6); }
}
@media (prefers-reduced-motion: reduce) { .gym__link svg, .row, .row__go { transition: none; } }
</style>
