<template>
  <main class="status-page">
    <router-link to="/" class="brand" aria-label="Ir al inicio de GymTrack">
      <img class="brand-mark" src="../assets/gymtrack-mark.svg" alt="" />
      <span class="brand-word">Gym<span>Track</span></span>
    </router-link>

    <section class="status-panel" aria-labelledby="status-title">
      <p class="status-code">{{ code }}</p>
      <h1 id="status-title">{{ title }}</h1>
      <p>{{ message }}</p>
      <div class="status-actions">
        <router-link to="/dashboard" class="primary-btn">Volver a mi panel</router-link>
        <router-link to="/" class="ghost-btn">Ir al inicio</router-link>
      </div>
    </section>
  </main>
</template>

<script setup>
defineProps({
  code: { type: String, required: true },
  title: { type: String, required: true },
  message: { type: String, required: true },
})
</script>

<style scoped>
.status-page {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 24px;
  background: #05070a;
}

.brand {
  position: fixed;
  inset-block-start: 24px;
  inset-inline-start: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.status-panel {
  width: min(560px, 100%);
  text-align: center;
}

.status-code {
  margin: 0 0 8px;
  color: var(--blue-2);
  font-size: 1rem;
  font-weight: 900;
  letter-spacing: 0.08em;
}

h1 {
  margin: 0;
  font-size: clamp(2rem, 7vw, 3.75rem);
  letter-spacing: -0.035em;
}

.status-panel > p:not(.status-code) {
  max-width: 52ch;
  margin: 18px auto 0;
  color: var(--muted);
  line-height: 1.65;
}

.status-actions {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}
</style>
