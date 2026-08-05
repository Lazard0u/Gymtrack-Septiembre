<template>
  <router-view />
</template>

<style>
:root {
  color-scheme: dark;
  --bg: #0d0d0d;
  --carbon: #0d0d0d;
  --graphite: #1c1c1c;
  --silver: #a0a0a0;
  --surface: #101820;
  --surface-2: #151f2b;
  --line: rgba(56, 189, 248, 0.22);
  --text: #ffffff;
  --muted: #a0a0a0;
  --blue: #0077ff;
  --blue-2: #38bdf8;
  --cyan: #00bfff;
  --success: #22c55e;
  --warning: #f59e0b;
  --danger: #ef4444;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-width: 320px;
  background:
    radial-gradient(circle at 50% -10%, rgba(0, 191, 255, 0.16), transparent 38rem),
    linear-gradient(145deg, #020304 0%, #0d0d0d 52%, #02060b 100%);
  color: var(--text);
}

::selection {
  background: rgba(56, 189, 248, 0.3);
  color: var(--text);
}

a {
  color: inherit;
}

button,
input,
select {
  font: inherit;
}

.brand-mark {
  width: 42px;
  height: 42px;
  object-fit: contain;
  filter: drop-shadow(0 0 18px rgba(0, 119, 255, 0.45));
}

.brand-word {
  font-family: Montserrat, Inter, sans-serif;
  font-weight: 900;
  letter-spacing: 0;
  text-transform: uppercase;
}

.brand-word span {
  color: var(--blue);
}

.primary-btn,
.ghost-btn,
.text-btn {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  padding: 0 18px;
  border: 1px solid transparent;
  text-decoration: none;
  font-weight: 800;
  font-size: clamp(0.85rem, 1vw, 0.95rem);
  cursor: pointer;
  transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
  white-space: nowrap;
}

.primary-btn {
  background: linear-gradient(135deg, #0077ff, #0058d6);
  color: white;
  box-shadow: 0 12px 30px rgba(0, 119, 255, 0.34);
}

.ghost-btn {
  background: rgba(13, 13, 13, 0.72);
  color: var(--text);
  border-color: var(--line);
}

.text-btn {
  min-height: auto;
  padding: 0;
  color: var(--blue-2);
  background: transparent;
  font-size: inherit;
}

.primary-btn:hover,
.ghost-btn:hover {
  transform: translateY(-1px);
  border-color: rgba(56, 189, 248, 0.55);
}

.primary-btn:active,
.ghost-btn:active {
  transform: translateY(0);
}

:where(a, button, input, select, textarea, [tabindex]):focus-visible {
  outline: 3px solid var(--blue-2);
  outline-offset: 3px;
}

:where(button, .primary-btn, .ghost-btn)[disabled],
:where(button, .primary-btn, .ghost-btn)[aria-disabled="true"] {
  cursor: not-allowed;
  opacity: 0.52;
  transform: none;
  box-shadow: none;
}

@media (max-width: 768px) {
  .primary-btn,
  .ghost-btn,
  .text-btn {
    min-height: 46px;
    font-size: 0.9rem;
    border-radius: 6px;
  }
}

@media (max-width: 480px) {
  .primary-btn,
  .ghost-btn {
    min-height: 44px;
    padding: 0 16px;
    font-size: 0.85rem;
    border-radius: 6px;
  }
}

.metric-card,
.panel-card,
.mini-card {
  border: 1px solid var(--line);
  background:
    linear-gradient(180deg, rgba(20, 30, 42, 0.86), rgba(7, 10, 15, 0.92));
  box-shadow: 0 24px 70px rgba(0, 0, 0, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.metric-card {
  border-radius: 8px;
  padding: 18px;
}

.panel-card {
  border-radius: 8px;
  padding: 22px;
}

.kicker {
  color: var(--blue-2);
  font-size: clamp(0.7rem, 1vw, 0.85rem);
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  display: block;
  margin-bottom: 4px;
}

.muted {
  color: var(--muted);
  font-size: clamp(0.85rem, 1vw, 0.95rem);
}

h1, h2, h3, h4, h5, h6 {
  font-weight: 700;
  line-height: 1.2;
}

h1 {
  font-size: clamp(1.8rem, 5vw, 3rem);
}

h2 {
  font-size: clamp(1.3rem, 4vw, 2rem);
}

h3 {
  font-size: clamp(1.1rem, 3vw, 1.5rem);
}

small {
  font-size: clamp(0.75rem, 1vw, 0.85rem);
}

label,
legend {
  font-size: clamp(0.8rem, 1vw, 0.9rem);
  font-weight: 600;
}

@media (max-width: 760px) {
  .primary-btn,
  .ghost-btn {
    width: auto;
  }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    scroll-behavior: auto !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
