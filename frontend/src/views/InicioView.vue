<script setup>
import {
  IconArrowRight,
  IconBell,
  IconCalendarEvent,
  IconChartLine,
  IconCreditCard,
  IconHeart,
  IconMap2,
  IconQrcode,
  IconShieldCheck,
  IconUsersGroup,
} from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import ExploradorGimnasios from '../components/public/ExploradorGimnasios.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import ownerImage from '../assets/images/gymtrack-owner-studio.webp'

const memberBenefits = [
  { icon: IconMap2, title: 'Descubrí sin adivinar', text: 'Encontrá gimnasios en el mapa y compará información publicada por cada sede.' },
  { icon: IconCalendarEvent, title: 'Tu agenda, ordenada', text: 'Consultá clases, cupos, reservas y lista de espera desde un mismo lugar.' },
  { icon: IconHeart, title: 'Una experiencia propia', text: 'Reuní favoritos, membresías, pagos y asistencia dentro de tu perfil.' },
]

const capabilities = [
  { icon: IconUsersGroup, title: 'Socios y equipo', text: 'Información centralizada, con acceso según el rol de cada persona.', area: 'wide' },
  { icon: IconCalendarEvent, title: 'Clases y reservas', text: 'Horarios, cupos, lista de espera y asistencia en un flujo consistente.', area: 'standard' },
  { icon: IconCreditCard, title: 'Membresías y pagos', text: 'Estados claros e historial verificable, sin confirmar pagos desde una redirección.', area: 'standard' },
  { icon: IconChartLine, title: 'Finanzas y reportes', text: 'Datos reales desde MySQL, con filtros y exportaciones operativas.', area: 'standard' },
  { icon: IconBell, title: 'Comunicación', text: 'Promociones y avisos con preferencias, consentimiento e historial.', area: 'wide' },
]

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
            <span class="eyebrow">Entrenar y gestionar, sin fricción</span>
            <h1>Tu gimnasio, en un solo lugar.</h1>
            <p>GymTrack conecta a socios y gimnasios con una experiencia clara para descubrir, reservar y llevar el día a día.</p>
            <div class="hero__actions">
              <AppLinkButton :to="{ name: 'gyms' }" size="lg">Explorar gimnasios<template #icon><IconMap2 :size="19" /></template></AppLinkButton>
              <AppLinkButton :to="{ name: 'for-gyms' }" variant="secondary" size="lg">Gestioná tu gimnasio<template #icon><IconArrowRight :size="19" /></template></AppLinkButton>
            </div>
            <p class="hero__note"><IconShieldCheck :size="17" aria-hidden="true" /> Los datos ficticios se identifican siempre como demostración.</p>
          </div>

          <div class="hero__map">
            <ExploradorGimnasios variant="hero" compact-heading />
          </div>
        </div>
      </section>

      <section class="section member-section" aria-labelledby="member-title">
        <div class="container">
          <div class="section-heading"><span class="eyebrow">Para socios</span><h2 id="member-title">Menos pantallas sueltas. Más continuidad.</h2><p>La versión 1.0 reúne las decisiones importantes antes, durante y después de entrenar.</p></div>
          <div class="benefits-grid">
            <AppCard v-for="benefit in memberBenefits" :key="benefit.title" class="benefit-card">
              <component :is="benefit.icon" :size="24" stroke-width="1.7" aria-hidden="true" />
              <h3>{{ benefit.title }}</h3><p>{{ benefit.text }}</p>
            </AppCard>
          </div>
        </div>
      </section>

      <section class="section owner-section" aria-labelledby="owner-title">
        <div class="container owner-grid">
          <div class="owner-visual"><img :src="ownerImage" alt="Responsable de un gimnasio organizando la operación del equipo" width="1122" height="1402" loading="lazy" /></div>
          <div class="owner-copy">
            <span class="eyebrow">Para dueños</span>
            <h2 id="owner-title">La operación visible, no escondida.</h2>
            <p>Administración ofrece acceso directo a socios, equipo, clases, reservas, membresías, pagos, finanzas y reportes.</p>
            <ul>
              <li><IconUsersGroup :size="19" />Gestión diaria en una estructura predecible.</li>
              <li><IconChartLine :size="19" />Finanzas y reportes a un clic.</li>
              <li><IconShieldCheck :size="19" />Permisos y datos separados por gimnasio.</li>
            </ul>
            <AppLinkButton :to="{ name: 'for-gyms' }" variant="secondary">Conocer la propuesta<template #icon><IconArrowRight :size="18" /></template></AppLinkButton>
          </div>
        </div>
      </section>

      <section id="funciones" class="section capabilities-section" aria-labelledby="capabilities-title">
        <div class="container">
          <div class="section-heading"><span class="eyebrow">Sistema completo</span><h2 id="capabilities-title">Una base para toda la experiencia.</h2><p>Estas capacidades están disponibles en GymTrack 1.0 y se conectan a datos reales. Las integraciones beta se identifican por separado.</p></div>
          <div class="capabilities-grid">
            <article v-for="capability in capabilities" :key="capability.title" :class="['capability', `capability--${capability.area}`]">
              <component :is="capability.icon" :size="24" stroke-width="1.7" aria-hidden="true" /><div><h3>{{ capability.title }}</h3><p>{{ capability.text }}</p></div>
            </article>
          </div>
        </div>
      </section>

      <section class="section plans-section" aria-labelledby="plans-title">
        <div class="container plans-grid">
          <div class="section-heading"><span class="eyebrow">Planes</span><h2 id="plans-title">Elegí con información real.</h2><p>Cada gimnasio puede publicar planes versionados con moneda, vigencia y beneficios claros.</p></div>
          <AppCard class="plans-card">
            <AppEmptyState title="Consultá los planes por gimnasio" description="Los precios y beneficios se leen desde MySQL en la ficha de cada gimnasio; no mostramos una tarifa global inventada.">
              <template #icon><IconCreditCard :size="24" /></template>
              <template #action><AppLinkButton :to="{ name: 'plans' }" variant="secondary" size="sm">Ver estado de planes</AppLinkButton></template>
            </AppEmptyState>
          </AppCard>
        </div>
      </section>

      <section class="section faq-section" aria-labelledby="faq-title">
        <div class="container faq-grid">
          <div class="section-heading"><span class="eyebrow">Preguntas frecuentes</span><h2 id="faq-title">Lo importante, claro desde el inicio.</h2></div>
          <div class="faq-list">
            <details v-for="item in questions" :key="item.question">
              <summary>{{ item.question }}<span aria-hidden="true">+</span></summary>
              <p>{{ item.answer }}</p>
            </details>
          </div>
        </div>
      </section>

      <section class="final-cta">
        <div class="container final-cta__inner">
          <div><span class="eyebrow">Empezá por lo esencial</span><h2>Tu próxima rutina merece una mejor experiencia.</h2></div>
          <div class="final-cta__cta">
            <div class="final-cta__actions"><AppLinkButton :to="{ name: 'registro' }" size="lg">Crear cuenta<template #icon><IconArrowRight :size="19" /></template></AppLinkButton><AppLinkButton :to="{ name: 'login' }" variant="ghost" size="lg">Ya tengo una cuenta</AppLinkButton></div>
            <p class="final-cta__owner-note">¿Ya sos socio y querés sumar tu propio gimnasio? <RouterLink :to="{ name: 'owner-register' }">Pedí tu cuenta de dueño</RouterLink></p>
          </div>
        </div>
      </section>
    </main>

    <PublicFooter />
  </div>
</template>

<style scoped>
.public-shell { min-height: 100vh; background: var(--bg-canvas); }
.hero { overflow: hidden; border-bottom: 1px solid var(--border-subtle); }
.hero__grid { display: grid; min-height: min(43rem, calc(100svh - var(--header-height))); grid-template-columns: minmax(19rem, .82fr) minmax(28rem, 1.18fr); align-items: center; gap: clamp(var(--space-8), 4vw, var(--space-12)); padding-block: clamp(var(--space-8), 5vw, var(--space-12)); }
.hero__copy { position: relative; z-index: 2; animation: intro-copy 600ms var(--ease-out) both; }.hero h1 { max-width: 10ch; margin-bottom: var(--space-6); }.hero__copy > p { max-width: 38rem; color: var(--text-secondary); font-size: clamp(1rem, 2vw, 1.2rem); }.hero__actions { display: flex; flex-wrap: wrap; gap: var(--space-3); margin-top: var(--space-8); }.hero__note { display: flex; align-items: center; gap: var(--space-2); margin: var(--space-5) 0 0; color: var(--text-tertiary) !important; font-size: .78rem !important; }
.hero__map { min-width: 0; animation: intro-visual 700ms 80ms var(--ease-out) both; }
.section-heading { max-width: 45rem; margin-bottom: var(--space-10); }.section-heading h2 { margin-bottom: var(--space-4); }.section-heading > p { max-width: 42rem; margin: 0; color: var(--text-secondary); }
.member-section { border-top: 1px solid var(--border-subtle); }.benefits-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); }.benefit-card :deep(svg) { margin-bottom: var(--space-8); color: var(--info); }.benefit-card h3 { margin-bottom: var(--space-3); }.benefit-card p { margin: 0; color: var(--text-secondary); font-size: .9rem; }
.owner-section { border-block: 1px solid var(--border-subtle); background: var(--bg-subtle); }.owner-grid { display: grid; grid-template-columns: minmax(18rem, .8fr) minmax(0, 1.2fr); align-items: center; gap: clamp(var(--space-8), 8vw, 7rem); }.owner-visual { position: relative; min-height: 36rem; overflow: hidden; border-radius: var(--radius-dialog); }.owner-visual img { width: 100%; height: 100%; min-height: 36rem; object-fit: cover; }.owner-copy h2 { max-width: 12ch; margin-bottom: var(--space-5); }.owner-copy > p { max-width: 38rem; color: var(--text-secondary); }.owner-copy ul { display: grid; gap: var(--space-3); margin: var(--space-8) 0; padding: 0; list-style: none; }.owner-copy li { display: flex; align-items: center; gap: var(--space-3); color: var(--text-secondary); font-size: .9rem; }.owner-copy li svg { flex: 0 0 auto; color: var(--info); }
.capabilities-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); }.capability { display: flex; min-height: 13rem; flex-direction: column; justify-content: space-between; gap: var(--space-8); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); padding: var(--space-6); background: var(--surface-1); }.capability--wide { grid-column: span 2; }.capability > svg { color: var(--info); }.capability h3 { margin-bottom: var(--space-2); }.capability p { max-width: 34rem; margin: 0; color: var(--text-secondary); font-size: .88rem; }
.plans-section { background: var(--bg-subtle); }.plans-grid { display: grid; grid-template-columns: minmax(0, .8fr) minmax(24rem, 1.2fr); align-items: center; gap: var(--space-12); }.plans-grid .section-heading { margin: 0; }.plans-card { background: var(--surface-1); }
.faq-grid { display: grid; grid-template-columns: minmax(16rem, .75fr) minmax(0, 1.25fr); gap: var(--space-16); }.faq-grid .section-heading { margin: 0; }.faq-list { border-top: 1px solid var(--border-subtle); }.faq-list details { border-bottom: 1px solid var(--border-subtle); }.faq-list summary { display: flex; min-height: 4.5rem; align-items: center; justify-content: space-between; gap: var(--space-4); cursor: pointer; color: var(--text-primary); font-weight: 680; list-style: none; }.faq-list summary::-webkit-details-marker { display: none; }.faq-list summary span { color: var(--text-tertiary); font-size: 1.4rem; transition: transform var(--duration-normal) var(--ease-out); }.faq-list details[open] summary span { transform: rotate(45deg); }.faq-list p { max-width: 44rem; margin: 0; padding: 0 var(--space-10) var(--space-6) 0; color: var(--text-secondary); font-size: .9rem; }
.final-cta { border-top: 1px solid var(--border-subtle); padding-block: clamp(var(--space-12), 8vw, var(--space-20)); background: var(--surface-1); }.final-cta__inner { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-10); }.final-cta h2 { max-width: 18ch; margin: 0; font-size: clamp(2rem, 5vw, 4rem); }.final-cta__cta { display: grid; flex: 0 0 auto; gap: var(--space-4); justify-items: start; }.final-cta__actions { display: flex; gap: var(--space-3); }.final-cta__owner-note { margin: 0; color: var(--text-tertiary); font-size: .82rem; }.final-cta__owner-note a { color: var(--status-info-strong); text-underline-offset: .2em; }
@keyframes intro-copy { from { opacity: 0; transform: translateY(12px); } }@keyframes intro-visual { from { opacity: 0; transform: translateY(18px) scale(.99); } }

@media (max-width: 63.99rem) {
  .hero__grid { min-height: auto; grid-template-columns: 1fr; }.hero__copy { padding-top: var(--space-6); }
  .owner-grid { gap: var(--space-8); }.capabilities-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.capability--wide { grid-column: span 1; }.plans-grid, .faq-grid { grid-template-columns: 1fr; gap: var(--space-8); }
  .final-cta__inner { align-items: flex-start; flex-direction: column; }
}

@media (max-width: 47.99rem) {
  .hero__grid { gap: var(--space-5); padding-block: var(--space-6) var(--space-8); }.hero h1 { max-width: 12ch; margin-bottom: var(--space-4); font-size: clamp(2.4rem, 11vw, 3.2rem); }.hero__copy > p { font-size: .96rem; }.hero__actions { display: grid; grid-template-columns: 1fr; margin-top: var(--space-5); }.hero__note { display: none; }.hero__map { margin-inline: -.25rem; }
  .benefits-grid, .owner-grid, .capabilities-grid { grid-template-columns: 1fr; }.owner-visual, .owner-visual img { min-height: 27rem; max-height: 34rem; }.owner-visual { order: 2; }.capability { min-height: 11rem; }.plans-grid { gap: var(--space-6); }.final-cta__actions { width: 100%; align-items: stretch; flex-direction: column; }
}
</style>
