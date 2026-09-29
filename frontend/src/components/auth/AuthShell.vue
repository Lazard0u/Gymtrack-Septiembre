<script setup>
import brandMark from '../../assets/gymtrack-mark.svg'
import heroImage from '../../assets/images/gymtrack-hero-training.webp'
</script>

<template>
  <div class="auth-shell">
    <RouterLink class="auth-shell__brand" :to="{ name: 'home' }" aria-label="GymTrack, volver al inicio">
      <img :src="brandMark" alt="" width="36" height="36" />
      <span class="brand-word">Gym<span>Track</span></span>
    </RouterLink>
    <div class="auth-shell__visual" aria-hidden="true">
      <img :src="heroImage" alt="" width="1536" height="1024" />
      <p>Entrenamiento y gestión con un acceso seguro.</p>
    </div>
    <main class="auth-shell__main">
      <section class="auth-shell__panel">
        <header class="auth-shell__header">
          <h1><slot name="title" /></h1>
          <p><slot name="description" /></p>
        </header>
        <slot />
        <footer v-if="$slots.footer" class="auth-shell__footer"><slot name="footer" /></footer>
      </section>
    </main>
  </div>
</template>

<style scoped>
.auth-shell { min-height: 100dvh; display: grid; grid-template-columns: minmax(20rem, .92fr) minmax(28rem, 1.08fr); background: var(--bg-canvas); }
.auth-shell__brand { position: fixed; inset-block-start: var(--space-6); inset-inline-start: var(--space-6); z-index: 2; display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }
.auth-shell__visual { position: relative; min-height: 100dvh; overflow: hidden; background: var(--bg-subtle); }
.auth-shell__visual::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(11,14,18,.06) 40%, rgba(11,14,18,.92)); }
.auth-shell__visual img { width: 100%; height: 100%; object-fit: cover; object-position: 61% center; filter: saturate(.78) contrast(1.04); }
.auth-shell__visual p { position: absolute; z-index: 1; inset-inline: clamp(1.5rem, 5vw, 4.5rem); inset-block-end: clamp(2rem, 7vw, 5rem); max-width: 16ch; margin: 0; color: var(--text-primary); font-size: clamp(1.65rem, 3.4vw, 3.25rem); font-weight: 730; line-height: 1.08; letter-spacing: -.035em; }
.auth-shell__main { display: grid; min-width: 0; place-items: center; padding: clamp(6rem, 10vw, 8rem) clamp(1rem, 6vw, 5rem) var(--space-10); }
.auth-shell__panel { width: min(100%, 30rem); }
.auth-shell__header { margin-bottom: var(--space-8); }
.auth-shell__header h1 { margin-bottom: var(--space-3); font-size: clamp(2rem, 4.5vw, 3.25rem); overflow-wrap: anywhere; }
.auth-shell__header p { max-width: 46ch; margin: 0; color: var(--text-secondary); }
.auth-shell__footer { margin-top: var(--space-6); color: var(--text-secondary); font-size: .875rem; }
.auth-shell__footer :deep(a) { color: var(--status-info-strong); font-weight: 700; text-underline-offset: .22em; }
@media (max-width: 63.99rem) { .auth-shell { grid-template-columns: minmax(0, 1fr); }.auth-shell__visual { display: none; }.auth-shell__brand { position: absolute; }.auth-shell__main { min-height: 100dvh; } }
@media (max-width: 29.99rem) { .auth-shell__brand { inset-block-start: var(--space-4); inset-inline-start: var(--space-4); }.auth-shell__main { padding-inline: var(--space-4); }.auth-shell__header { margin-bottom: var(--space-6); } }
</style>
