<script setup>
import { onMounted, ref } from 'vue'
import { IconCreditCard, IconRosetteDiscountCheck } from '@tabler/icons-vue'
import { api } from '../services/api'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppPageHeader from '../components/ui/AppPageHeader.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'

const gyms = ref([])
const status = ref('loading')

function money(value, currency = 'UYU') { return new Intl.NumberFormat('es-UY', { style: 'currency', currency }).format(Number(value || 0)) }

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
    <main class="container">
      <AppPageHeader eyebrow="Planes" title="Precios reales, por gimnasio." description="Cada tarjeta muestra los planes que ese gimnasio publicó desde su administración. No mostramos precios de ejemplo." />
      <div v-if="status === 'loading'" class="plans-loading" role="status" aria-label="Cargando planes"><AppSkeleton v-for="index in 3" :key="index" height="14rem" /></div>
      <AppErrorState v-else-if="status === 'error'" title="No pudimos cargar los planes" description="Los gimnasios publicados no respondieron. Probá de nuevo en unos minutos." @retry="load" />
      <AppCard v-else-if="status === 'empty'" class="state-card"><AppEmptyState title="Aún no hay planes publicados" description="Los gimnasios todavía no publicaron membresías desde administración."><template #icon><IconCreditCard :size="25" /></template><template #action><AppLinkButton :to="{ name: 'gyms' }" variant="secondary">Explorar gimnasios</AppLinkButton></template></AppEmptyState></AppCard>
      <template v-else>
        <section v-for="gym in gyms" :key="gym.gimnasio_id" class="gym-plans" :aria-labelledby="`gym-${gym.gimnasio_id}-title`">
          <div class="section-heading">
            <div><h2 :id="`gym-${gym.gimnasio_id}-title`">{{ gym.gimnasio_nombre }}</h2><p>{{ gym.ciudad }}, {{ gym.departamento }}</p></div>
            <AppLinkButton :to="{ name: 'gym-detail', params: { slug: gym.gimnasio_slug } }" variant="secondary" size="sm">Ver ficha</AppLinkButton>
          </div>
          <div class="plan-grid">
            <AppCard v-for="plan in gym.planes" :key="plan.id">
              <div class="plan-title"><div><h3>{{ plan.nombre }}</h3><p>{{ plan.descripcion }}</p></div><strong>{{ money(plan.precio, plan.moneda) }}</strong></div>
              <p class="duration">{{ plan.duracion_dias }} días</p>
              <ul v-if="plan.beneficios.length"><li v-for="benefit in plan.beneficios" :key="benefit"><IconRosetteDiscountCheck :size="17" />{{ benefit }}</li></ul>
            </AppCard>
          </div>
        </section>
      </template>
    </main>
    <PublicFooter />
  </div>
</template>

<style scoped>
.public-page main { min-height: calc(100vh - var(--header-height)); padding-bottom: var(--space-20); }
.state-card { margin-bottom: var(--space-20); }
.plans-loading { display: grid; gap: var(--space-6); margin-bottom: var(--space-16); }
.gym-plans { padding-block: var(--space-8); border-top: 1px solid var(--border-subtle); }
.gym-plans:first-of-type { border-top: 0; }
.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-6); }
.section-heading h2 { margin: 0 0 var(--space-2); font-size: clamp(1.35rem, 2.5vw, 1.85rem); }
.section-heading p { margin: 0; color: var(--text-secondary); font-size: .82rem; }
.plan-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); }
.plan-title { display: flex; justify-content: space-between; gap: var(--space-5); }
.plan-title h3 { margin: 0; font-size: 1rem; }
.plan-title p { margin: var(--space-2) 0 0; color: var(--text-secondary); font-size: .8rem; }
.plan-title > strong { white-space: nowrap; font-size: 1.2rem; }
.duration { margin: var(--space-3) 0 0; color: var(--text-tertiary); font-size: .75rem; }
.plan-grid ul { display: grid; gap: var(--space-2); margin: var(--space-5) 0 0; padding: var(--space-5) 0 0; border-top: 1px solid var(--border-subtle); list-style: none; }
.plan-grid li { display: flex; gap: var(--space-2); color: var(--text-secondary); font-size: .78rem; }
.plan-grid li svg { flex: 0 0 auto; color: var(--success); }
@media (max-width: 63.99rem) { .plan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 47.99rem) { .section-heading { align-items: flex-start; flex-direction: column; }.plan-grid { grid-template-columns: 1fr; } }
</style>
