<script setup>
import { IconArrowRight, IconChartLine, IconLock, IconSettings } from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import ownerImage from '../assets/images/gymtrack-owner-studio.webp'

const pillars = [
  { icon: IconSettings, title: 'Operación centralizada', text: 'Socios, equipo, clases, reservas, membresías y pagos en una sola navegación.' },
  { icon: IconChartLine, title: 'Decisiones con datos', text: 'Ingresos, deuda y comparación entre períodos, con filtros y reportes en Excel o PDF.' },
  { icon: IconLock, title: 'Control por gimnasio', text: 'Cada persona ve solo lo que le corresponde según su rol, y los datos de un gimnasio no se mezclan con los de otro.' },
]
const steps = [
  { title: 'Solicitás el alta', text: 'Con tu cuenta de socio o una nueva, enviás el nombre de tu gimnasio. Queda pendiente de revisión.' },
  { title: 'Revisamos y vinculamos', text: 'Un administrador valida la solicitud y vincula el gimnasio a tu cuenta.' },
  { title: 'Armás tu equipo', text: 'Invitás a empleados y entrenadores con enlaces de un solo uso y permisos por gimnasio.' },
  { title: 'Publicás', text: 'Cargás sedes, horarios, planes y fotos. Mientras no lo publiques, el gimnasio queda en borrador.' },
]
</script>

<template>
  <div class="public-page">
    <PublicHeader />
    <main>
      <section class="container owner-hero">
        <div class="owner-hero__copy">
          <p class="pb-index"><b>→</b>Para gimnasios</p>
          <h1>La gestión diaria, donde la esperás.</h1>
          <p class="pb-lead">GymTrack mantiene administración, finanzas y reportes visibles y conectados a la operación real.</p>
          <div class="owner-hero__actions">
            <AppLinkButton :to="{ name: 'owner-register' }" size="lg">Solicitar cuenta de dueño<template #icon><IconArrowRight :size="19" /></template></AppLinkButton>
            <AppLinkButton :to="{ name: 'login' }" variant="secondary" size="lg">Ingresar</AppLinkButton>
          </div>
        </div>
        <figure class="owner-hero__photo"><img :src="ownerImage" alt="Responsable de un gimnasio planificando la operación" width="1122" height="1402" /></figure>
      </section>

      <section class="pb-section" aria-labelledby="incluye-title">
        <div class="container">
          <div class="pb-head"><p class="pb-index"><b>01</b>Qué incluye</p><h2 id="incluye-title" class="pb-title">Lo que hoy se reparte en planillas sueltas.</h2></div>
          <ol class="pb-steps" style="--cols: 3">
            <li v-for="(pillar, index) in pillars" :key="pillar.title"><span class="pb-steps__n">{{ String(index + 1).padStart(2, '0') }}</span><component :is="pillar.icon" :size="22" stroke-width="1.7" aria-hidden="true" /><h3>{{ pillar.title }}</h3><p>{{ pillar.text }}</p></li>
          </ol>
        </div>
      </section>

      <section class="pb-section" aria-labelledby="empezar-title">
        <div class="container">
          <div class="pb-head"><p class="pb-index"><b>02</b>Cómo se empieza</p><h2 id="empezar-title" class="pb-title">De la solicitud al gimnasio publicado.</h2></div>
          <ol class="pb-steps">
            <li v-for="(step, index) in steps" :key="step.title"><span class="pb-steps__n">{{ String(index + 1).padStart(2, '0') }}</span><h3>{{ step.title }}</h3><p>{{ step.text }}</p></li>
          </ol>
        </div>
      </section>

      <section class="pb-section owner-cta" aria-labelledby="alta-title">
        <div class="container owner-cta__inner">
          <div><h2 id="alta-title" class="pb-title">Solicitá el alta de tu gimnasio.</h2><p class="pb-lead">La solicitud queda pendiente de revisión. Después, tu equipo se suma con invitaciones que vencen y permisos por gimnasio.</p></div>
          <div class="owner-cta__actions">
            <AppLinkButton :to="{ name: 'owner-register' }" size="lg">Solicitar cuenta de dueño<template #icon><IconArrowRight :size="19" /></template></AppLinkButton>
            <AppLinkButton :to="{ name: 'login' }" variant="secondary" size="lg">Ingresar</AppLinkButton>
          </div>
        </div>
      </section>
    </main>
    <PublicFooter />
  </div>
</template>

<style scoped>
.owner-hero { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); align-items: center; gap: clamp(var(--space-8), 5vw, var(--space-16)); padding-block: clamp(var(--space-8), 5vw, var(--space-12)) clamp(var(--space-10), 6vw, var(--space-16)); }
.owner-hero__copy { display: grid; gap: var(--space-6); justify-items: start; }
.owner-hero h1 { max-width: 12ch; margin: 0; font-size: clamp(2.4rem, 5.4vw, 4.4rem); line-height: 1.02; text-wrap: balance; }
.owner-hero__actions { display: flex; flex-wrap: wrap; gap: var(--space-3); }
.owner-hero__photo { height: min(34rem, 66vh); margin: 0; overflow: hidden; border-radius: var(--radius-dialog); }
.owner-hero__photo img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 30%; outline: 1px solid rgba(255, 255, 255, .08); outline-offset: -1px; }
.owner-cta { background: var(--bg-subtle); }
.owner-cta__inner { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-10); }
.owner-cta__inner .pb-title { margin-bottom: var(--space-4); max-width: 18ch; }
.owner-cta__actions { display: flex; flex: 0 0 auto; flex-wrap: wrap; gap: var(--space-3); }
@media (max-width: 63.99rem) {
  .owner-hero { grid-template-columns: 1fr; }
  .owner-hero__photo { order: -1; height: 22rem; }
  .owner-cta__inner { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 47.99rem) { .owner-cta__actions { width: 100%; flex-direction: column; } }
</style>
