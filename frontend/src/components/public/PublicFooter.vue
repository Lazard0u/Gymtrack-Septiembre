<script setup>
import brandMark from '../../assets/gymtrack-mark.svg'
</script>

<template>
  <footer class="site-footer">
    <div class="container site-footer__grid">
      <div class="footer-brand">
        <RouterLink class="brand" :to="{ name: 'home' }"><img class="brand-mark" :src="brandMark" alt="" width="36" height="36" /><span class="brand-word">Gym<span>Track</span></span></RouterLink>
        <p>Una plataforma para conectar la experiencia de entrenar con la operación diaria del gimnasio.</p>
      </div>
      <nav aria-label="Producto"><strong>Producto</strong><RouterLink :to="{ name: 'gyms' }">Gimnasios</RouterLink><RouterLink :to="{ name: 'plans' }">Planes</RouterLink><RouterLink :to="{ name: 'for-gyms' }">Para gimnasios</RouterLink></nav>
      <nav aria-label="Información legal"><strong>Información</strong><RouterLink :to="{ name: 'privacy' }">Privacidad</RouterLink><RouterLink :to="{ name: 'terms' }">Términos</RouterLink><RouterLink :to="{ name: 'accessibility' }">Accesibilidad</RouterLink><RouterLink :to="{ name: 'contact' }">Contacto</RouterLink></nav>
    </div>
    <div class="container site-footer__bottom"><span>© {{ new Date().getFullYear() }} GymTrack</span><span>Versión 1.0 en construcción por fases</span></div>
  </footer>
</template>

<style scoped>
.site-footer { border-top: 1px solid var(--border-subtle); padding: var(--space-16) 0 var(--space-6); background: var(--bg-subtle); }
.site-footer__grid { display: grid; grid-template-columns: minmax(0, 2fr) repeat(2, minmax(9rem, 1fr)); gap: var(--space-12); }
.brand { display: inline-flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4); text-decoration: none; }.footer-brand p { max-width: 31rem; margin: 0; color: var(--text-secondary); font-size: .9rem; }
nav { display: grid; align-content: start; gap: var(--space-3); }nav strong { margin-bottom: var(--space-1); font-size: .85rem; }nav a { width: fit-content; color: var(--text-secondary); font-size: .85rem; text-decoration: none; }nav a:hover { color: var(--text-primary); }
.site-footer__bottom { display: flex; justify-content: space-between; gap: var(--space-4); margin-top: var(--space-12); padding-top: var(--space-5); border-top: 1px solid var(--border-subtle); color: var(--text-tertiary); font-size: .75rem; }
@media (max-width: 47.99rem) { .site-footer__grid { grid-template-columns: 1fr 1fr; }.footer-brand { grid-column: 1 / -1; }.site-footer__bottom { align-items: flex-start; flex-direction: column; } }
</style>
