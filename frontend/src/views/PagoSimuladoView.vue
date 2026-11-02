<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IconBuildingBank, IconCheck, IconClock, IconCreditCard, IconLock, IconWallet, IconX } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { usePagosStore } from '../stores/pagos'

const route = useRoute(); const router = useRouter(); const auth = useAuthStore(); const payments = usePagosStore()
// loading | error | ready | processing | approved | rejected | expired
const phase = ref('loading'); const payment = ref(null); const method = ref('credit_card'); const problem = ref(''); const touched = ref(false); const paidWith = ref('')
const card = reactive({ number: '', name: '', expiry: '', cvv: '' })
let timer = null

const METHODS = [
  { id: 'credit_card', title: 'Tarjeta de crédito', hint: 'Visa, Mastercard, American Express', icon: IconCreditCard, card: true },
  { id: 'debit_card', title: 'Tarjeta de débito', hint: 'Visa Débito, Maestro', icon: IconBuildingBank, card: true },
  { id: 'account_money', title: 'Dinero disponible en Mercado Pago', hint: 'Pagás con el saldo de tu cuenta', icon: IconWallet, card: false },
]
const current = computed(() => METHODS.find((item) => item.id === method.value))
const gymName = computed(() => (auth.user?.gimnasios || []).find((gym) => Number(gym.gimnasio_id) === Number(auth.user?.active_gym_id))?.nombre || 'tu gimnasio')
const total = computed(() => money(payment.value?.monto, payment.value?.moneda))
const duration = computed(() => payments.memberPlans.find((plan) => plan.nombre === payment.value?.plan)?.duracion_dias || null)
const brand = computed(() => { const n = card.number.replace(/\s/g, ''); if (/^4/.test(n)) return 'Visa'; if (/^(5[1-5]|2[2-7])/.test(n)) return 'Mastercard'; if (/^3[47]/.test(n)) return 'American Express'; return '' })
const errors = computed(() => {
  if (!current.value.card) return {}
  const [month, year] = card.expiry.split('/').map(Number); const now = new Date()
  const expired = !month || month < 1 || month > 12 || !year || 2000 + year < now.getFullYear() || (2000 + year === now.getFullYear() && month < now.getMonth() + 1)
  return {
    number: card.number.replace(/\s/g, '').length < 15 ? 'Revisá el número de tu tarjeta.' : '',
    name: card.name.trim().length < 2 ? 'Escribí el nombre como figura en la tarjeta.' : '',
    expiry: card.expiry.length < 5 || expired ? 'Revisá la fecha de vencimiento.' : '',
    cvv: card.cvv.length < 3 ? 'Revisá el código de seguridad.' : '',
  }
})
const valid = computed(() => Object.values(errors.value).every((message) => !message))

function money(value, currency = 'UYU') { const amount = Number(value || 0); return new Intl.NumberFormat('es-UY', { style: 'currency', currency: currency || 'UYU', minimumFractionDigits: Number.isInteger(amount) ? 0 : 2 }).format(amount) }
// El servidor guarda las fechas en UTC sin zona; sin la Z el navegador las leería como hora local.
function dateTime(value) { return value ? new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(`${String(value).replace(' ', 'T')}Z`)) : '' }
function onNumber(event) { card.number = event.target.value.replace(/\D/g, '').slice(0, 16).replace(/(.{4})/g, '$1 ').trim() }
function onExpiry(event) { const digits = event.target.value.replace(/\D/g, '').slice(0, 4); card.expiry = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits }
function onCvv(event) { card.cvv = event.target.value.replace(/\D/g, '').slice(0, 4) }
function fillTest(name) { Object.assign(card, { number: '5031 7557 3453 0604', name, expiry: '11/30', cvv: '123' }); touched.value = false }

// Como en el sandbox de Mercado Pago, el resultado lo decide el nombre del titular: FUND o OTHE rechazan, cualquier otro aprueba.
function outcome() {
  if (!current.value.card) return { ok: true }
  const holder = card.name.trim().toUpperCase()
  if (holder === 'FUND') return { ok: false, reason: 'Tu tarjeta no tiene fondos suficientes.' }
  if (holder === 'OTHE') return { ok: false, reason: 'El banco rechazó el pago. Probá con otro medio de pago.' }
  return { ok: true }
}

async function load() {
  phase.value = 'loading'
  const ok = await payments.loadMember()
  const found = payments.memberItems.find((item) => item.referencia_externa === route.params.referencia)
  if (!ok || !found) { phase.value = 'error'; return }
  payment.value = found
  if (found.estado === 'rechazado') problem.value = 'El intento anterior fue rechazado. Probá con otro medio de pago.'
  phase.value = ({ aprobado: 'approved', vencido: 'expired', cancelado: 'expired', reembolsado: 'expired' })[found.estado] || 'ready'
}

async function pay() {
  touched.value = true
  if (!valid.value) return
  problem.value = ''; const decision = outcome(); phase.value = 'processing'
  paidWith.value = current.value.card ? `${brand.value || 'Tarjeta'} terminada en ${card.number.replace(/\s/g, '').slice(-4)}` : 'Dinero en cuenta'
  try {
    // Solo viaja la referencia, el medio y el resultado: los datos de la tarjeta nunca salen del navegador.
    const [result] = await Promise.all([payments.simulate(payment.value.referencia_externa, decision.ok ? 'approved' : 'rejected', method.value), new Promise((resolve) => { timer = setTimeout(resolve, 1900) })])
    payment.value = { ...payment.value, ...result }
    if (result.estado === 'aprobado') phase.value = 'approved'
    else { problem.value = decision.reason || 'No pudimos procesar el pago. Probá con otro medio.'; phase.value = 'rejected' }
  } catch (failure) {
    problem.value = failure.message; phase.value = 'ready'
  }
  await nextTick(); document.getElementById('resultado-titulo')?.focus()
}
function retry() { phase.value = 'ready'; touched.value = false }
function back() { router.push({ name: 'member-payments' }) }

onMounted(load)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="mp">
    <header class="mp__bar"><div class="mp__bar-in"><span class="mp__brand">mercado pago</span><span class="mp__secure"><IconLock :size="15" />Pago seguro</span></div></header>
    <p class="mp__note" role="note">Simulación del checkout para el proyecto GymTrack. No ingreses datos reales: nada se envía ni se cobra.</p>

    <main class="mp__main">
      <div v-if="phase === 'loading'" class="grid" role="status" aria-label="Cargando el pago"><div class="card sk" style="height: 24rem"></div><div class="card sk" style="height: 16rem"></div></div>

      <section v-else-if="phase === 'error'" class="card center">
        <span class="badge badge--error" aria-hidden="true"><IconX :size="34" stroke-width="2.5" /></span>
        <h1>No encontramos este pago</h1>
        <p class="muted">Puede que ya no exista o que sea de otro gimnasio. Volvé a GymTrack para iniciar uno nuevo.</p>
        <button class="btn" type="button" @click="back">Volver a GymTrack</button>
      </section>

      <div v-else-if="phase === 'ready'" class="grid">
        <form class="card" novalidate @submit.prevent="pay">
          <h1>Elegí cómo pagar</h1>
          <p v-if="problem" class="alert" role="alert">{{ problem }}</p>
          <div class="methods" role="radiogroup" aria-label="Medio de pago">
            <div v-for="item in METHODS" :key="item.id" class="method" :class="{ 'method--on': method === item.id }">
              <label class="method__head">
                <input v-model="method" class="method__input" type="radio" name="medio" :value="item.id" />
                <span class="method__radio" aria-hidden="true"></span>
                <component :is="item.icon" class="method__icon" :size="26" aria-hidden="true" />
                <span class="method__text"><strong>{{ item.title }}</strong><small>{{ item.hint }}</small></span>
              </label>
              <div v-if="method === item.id && item.card" class="fields">
                <div class="field field--wide">
                  <label for="tc-numero">Número de tarjeta</label>
                  <div class="field__box" :class="{ 'field__box--bad': touched && errors.number }"><input id="tc-numero" :value="card.number" inputmode="numeric" autocomplete="off" placeholder="1234 1234 1234 1234" :aria-invalid="touched && !!errors.number" aria-describedby="tc-numero-e" @input="onNumber" /><span v-if="brand" class="field__brand">{{ brand }}</span></div>
                  <p v-if="touched && errors.number" id="tc-numero-e" class="field__error">{{ errors.number }}</p>
                </div>
                <div class="field field--wide">
                  <label for="tc-nombre">Nombre y apellido como figura en la tarjeta</label>
                  <div class="field__box" :class="{ 'field__box--bad': touched && errors.name }"><input id="tc-nombre" v-model="card.name" autocomplete="off" placeholder="Como figura en la tarjeta" :aria-invalid="touched && !!errors.name" aria-describedby="tc-nombre-e" /></div>
                  <p v-if="touched && errors.name" id="tc-nombre-e" class="field__error">{{ errors.name }}</p>
                </div>
                <div class="field">
                  <label for="tc-vence">Vencimiento</label>
                  <div class="field__box" :class="{ 'field__box--bad': touched && errors.expiry }"><input id="tc-vence" :value="card.expiry" inputmode="numeric" autocomplete="off" placeholder="MM/AA" :aria-invalid="touched && !!errors.expiry" aria-describedby="tc-vence-e" @input="onExpiry" /></div>
                  <p v-if="touched && errors.expiry" id="tc-vence-e" class="field__error">{{ errors.expiry }}</p>
                </div>
                <div class="field">
                  <label for="tc-cvv">Código de seguridad</label>
                  <div class="field__box" :class="{ 'field__box--bad': touched && errors.cvv }"><input id="tc-cvv" :value="card.cvv" inputmode="numeric" autocomplete="off" placeholder="123" :aria-invalid="touched && !!errors.cvv" aria-describedby="tc-cvv-e" @input="onCvv" /></div>
                  <p v-if="touched && errors.cvv" id="tc-cvv-e" class="field__error">{{ errors.cvv }}</p>
                </div>
                <div class="testdata field--wide">
                  <span>Tarjetas de prueba:</span>
                  <button type="button" @click="fillTest('APRO')">Pago aprobado</button>
                  <button type="button" @click="fillTest('FUND')">Fondos insuficientes</button>
                  <button type="button" @click="fillTest('OTHE')">Rechazada por el banco</button>
                </div>
              </div>
            </div>
          </div>
          <button class="btn btn--block" type="submit"><IconLock :size="18" />Pagar {{ total }}</button>
        </form>

        <aside class="card summary" aria-label="Resumen de tu compra">
          <h2>Resumen de tu compra</h2>
          <div class="item"><span class="item__name"><strong>{{ payment.plan }}</strong><small>Membresía{{ duration ? ` · ${duration} días` : '' }}</small></span><span class="item__price">{{ total }}</span></div>
          <dl>
            <div><dt>Vendedor</dt><dd>{{ gymName }}</dd></div>
            <div><dt>Referencia</dt><dd class="mono">{{ payment.referencia_externa }}</dd></div>
            <div><dt>La solicitud vence</dt><dd>{{ dateTime(payment.fecha_vencimiento) }}</dd></div>
          </dl>
          <div class="total"><span>Pagás</span><strong>{{ total }}</strong></div>
          <button class="link" type="button" @click="back">Cancelar y volver</button>
        </aside>
      </div>

      <section v-else-if="phase === 'processing'" class="card center" role="status" aria-live="polite">
        <div class="spinner" aria-hidden="true"></div>
        <h1>Estamos procesando tu pago</h1>
        <p class="muted">Esto tarda unos segundos. No cierres la ventana.</p>
        <div class="bar" aria-hidden="true"><span></span></div>
      </section>

      <section v-else class="card center" aria-live="polite">
        <span class="badge" :class="phase === 'approved' ? 'badge--ok' : phase === 'rejected' ? 'badge--error' : 'badge--wait'" aria-hidden="true">
          <IconCheck v-if="phase === 'approved'" :size="36" stroke-width="3" /><IconX v-else-if="phase === 'rejected'" :size="34" stroke-width="2.5" /><IconClock v-else :size="34" stroke-width="2.5" />
        </span>
        <h1 id="resultado-titulo" tabindex="-1">{{ phase === 'approved' ? '¡Listo! Se acreditó tu pago' : phase === 'rejected' ? 'No pudimos procesar tu pago' : 'Esta solicitud venció' }}</h1>
        <p class="muted">{{ phase === 'approved' ? `Pagaste ${total} a ${gymName}. Tu membresía ya está activa.` : phase === 'rejected' ? problem : 'Volvé a GymTrack para iniciar una nueva solicitud.' }}</p>
        <dl v-if="phase === 'approved'" class="receipt">
          <div><dt>Plan</dt><dd>{{ payment.plan }}</dd></div>
          <div><dt>Medio de pago</dt><dd>{{ paidWith }}</dd></div>
          <div v-if="payment.fecha_pago"><dt>Fecha</dt><dd>{{ dateTime(payment.fecha_pago) }}</dd></div>
          <div><dt>N.º de operación</dt><dd class="mono">{{ payment.referencia_externa }}</dd></div>
        </dl>
        <div class="actions">
          <button v-if="phase === 'rejected'" class="btn" type="button" @click="retry">Elegir otro medio de pago</button>
          <button class="btn" :class="{ 'btn--ghost': phase === 'rejected' }" type="button" @click="back">Volver a GymTrack</button>
        </div>
      </section>
    </main>
    <footer class="mp__foot">Simulación sin valor real · GymTrack</footer>
  </div>
</template>

<style scoped>
.mp { --yellow: #ffe600; --blue: #009ee3; --blue-dark: #0086c3; --ink: #333; --muted: #6b6b6b; --line: #e6e6e6; --ok: #00a650; --bad: #f23d4f; min-height: 100vh; display: flex; flex-direction: column; background: #ededed; color: var(--ink); color-scheme: light; font-family: -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
.mp h1, .mp h2, .mp p, .mp dl, .mp dd { margin: 0; }
.mp h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: 0; color: var(--ink); }
.mp h2 { font-size: 1.05rem; font-weight: 600; color: var(--ink); }
.mp__bar { background: var(--yellow); box-shadow: 0 1px 0 rgba(0, 0, 0, .1); }
.mp__bar-in { display: flex; align-items: center; justify-content: space-between; width: min(100% - 2rem, 62rem); height: 3.5rem; margin-inline: auto; }
.mp__brand { color: #2d3277; font-size: 1.2rem; font-weight: 800; letter-spacing: -.02em; }
.mp__secure { display: inline-flex; align-items: center; gap: .35rem; color: #2d3277; font-size: .8rem; font-weight: 600; }
.mp__note { padding: .55rem 1rem; background: #fff8cc; border-bottom: 1px solid #f0e38a; color: #5b5200; font-size: .78rem; text-align: center; text-wrap: balance; }
.mp__main { flex: 1; width: min(100% - 2rem, 62rem); margin: 1.5rem auto 2rem; }
.mp__foot { padding: 1rem; color: var(--muted); font-size: .75rem; text-align: center; }
.grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
.card { padding: clamp(1.25rem, 3vw, 2rem); border-radius: 6px; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .14); }
.card > * + * { margin-top: 1.1rem; }
.sk { background: linear-gradient(90deg, #f3f3f3, #fafafa, #f3f3f3); background-size: 200% 100%; animation: shimmer 1.4s linear infinite; }
.muted { color: var(--muted); font-size: .95rem; line-height: 1.5; text-wrap: pretty; }
.alert { padding: .75rem 1rem; border-left: 3px solid var(--bad); border-radius: 4px; background: #fdecee; color: #8c1d29; font-size: .88rem; }
.methods { border: 1px solid var(--line); border-radius: 6px; }
.method + .method { border-top: 1px solid var(--line); }
.method--on { background: #fafcfe; }
.method__head { position: relative; display: flex; align-items: center; gap: .9rem; min-height: 4.25rem; padding: .75rem 1rem; cursor: pointer; }
.method__input { position: absolute; opacity: 0; pointer-events: none; }
.method__head:has(.method__input:focus-visible) { outline: 2px solid var(--blue); outline-offset: -2px; border-radius: 6px; }
.method__radio { width: 1.15rem; height: 1.15rem; flex: 0 0 auto; border: 2px solid #bfbfbf; border-radius: 50%; transition-property: border-color, box-shadow; transition-duration: .15s; }
.method--on .method__radio { border-color: var(--blue); box-shadow: inset 0 0 0 3px #fff, inset 0 0 0 10px var(--blue); }
.method__icon { flex: 0 0 auto; color: var(--blue); }
.method__text { display: grid; gap: .1rem; }
.method__text strong { font-size: .95rem; font-weight: 600; }
.method__text small { color: var(--muted); font-size: .8rem; }
.fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding: .25rem 1rem 1.25rem 2.95rem; }
.field--wide { grid-column: 1 / -1; }
.field label { display: block; margin-bottom: .35rem; color: var(--ink); font-size: .82rem; font-weight: 600; }
.field__box { display: flex; align-items: center; gap: .5rem; height: 2.75rem; padding: 0 .85rem; border: 1px solid #c9c9c9; border-radius: 6px; background: #fff; transition-property: border-color, box-shadow; transition-duration: .15s; }
.field__box:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(0, 158, 227, .18); }
.field__box--bad { border-color: var(--bad); }
.field__box input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; color: var(--ink); font: inherit; font-size: .95rem; font-variant-numeric: tabular-nums; }
.field__box input::placeholder { color: #a3a3a3; }
.field__brand { color: var(--muted); font-size: .72rem; font-weight: 700; white-space: nowrap; }
.field__error { margin-top: .3rem; color: var(--bad); font-size: .76rem; }
.testdata { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding-top: .75rem; border-top: 1px dashed var(--line); color: var(--muted); font-size: .76rem; }
.testdata button { min-height: 2rem; border: 1px solid #c9c9c9; border-radius: 999px; padding: 0 .75rem; background: #fff; color: var(--ink); cursor: pointer; font: inherit; font-size: .76rem; font-weight: 600; transition-property: border-color, color; transition-duration: .15s; }
.testdata button:hover { border-color: var(--blue); color: var(--blue-dark); }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; min-height: 3rem; border: 0; border-radius: 6px; padding: 0 1.5rem; background: var(--blue); color: #fff; cursor: pointer; font: inherit; font-size: 1rem; font-weight: 600; transition-property: background-color, transform; transition-duration: .15s; }
.btn:hover { background: var(--blue-dark); }
.btn:active { transform: scale(.98); }
.btn:focus-visible, .link:focus-visible, .testdata button:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
.btn--block { width: 100%; }
.btn--ghost { background: #e8f6fd; color: var(--blue-dark); }
.btn--ghost:hover { background: #d3eefb; }
.summary h2 { padding-bottom: .9rem; border-bottom: 1px solid var(--line); }
.item { display: flex; justify-content: space-between; gap: 1rem; }
.item__name { display: grid; gap: .1rem; }
.item__name small { color: var(--muted); font-size: .8rem; }
.item__price { font-weight: 600; font-variant-numeric: tabular-nums; }
.summary dl { display: grid; gap: .6rem; padding-top: 1rem; border-top: 1px solid var(--line); }
.summary dl div, .receipt div { display: flex; justify-content: space-between; gap: 1rem; font-size: .84rem; }
.summary dl div:has(.mono), .receipt div:has(.mono) { flex-direction: column; gap: .2rem; }
.summary dl div:has(.mono) dd, .receipt div:has(.mono) dd { text-align: left; }
.summary dt, .receipt dt { color: var(--muted); }
.summary dd, .receipt dd { text-align: right; font-weight: 600; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem !important; overflow-wrap: anywhere; }
.total { display: flex; align-items: baseline; justify-content: space-between; padding-top: 1rem; border-top: 1px solid var(--line); }
.total strong { font-size: 1.6rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.link { min-height: 2.75rem; border: 0; background: none; color: var(--blue-dark); cursor: pointer; font: inherit; font-size: .88rem; font-weight: 600; }
.link:hover { text-decoration: underline; }
.center { display: grid; justify-items: center; gap: .9rem; width: min(100%, 32rem); margin-inline: auto; padding-block: 2.5rem; text-align: center; }
.center > * + * { margin-top: 0; }
.badge { display: grid; width: 4.25rem; height: 4.25rem; place-items: center; border-radius: 50%; color: #fff; }
.badge--ok { background: var(--ok); animation: pop .35s cubic-bezier(.22, 1, .36, 1); }
.badge--error { background: var(--bad); }
.badge--wait { background: #f5a623; }
.receipt { display: grid; gap: .7rem; width: 100%; margin-top: .5rem; padding: 1rem 1.1rem; border: 1px solid var(--line); border-radius: 6px; text-align: left; }
.actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; margin-top: .5rem; }
.center h1:focus { outline: none; }
.spinner { width: 3rem; height: 3rem; border: 4px solid #d8eefa; border-top-color: var(--blue); border-radius: 50%; animation: spin .8s linear infinite; }
.bar { width: 100%; height: 4px; overflow: hidden; border-radius: 2px; background: #e6e6e6; }
.bar span { display: block; width: 40%; height: 100%; background: var(--blue); animation: slide 1.1s ease-in-out infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes shimmer { to { background-position: -200% 0; } }
@keyframes slide { from { transform: translateX(-100%); } to { transform: translateX(260%); } }
@keyframes pop { from { transform: scale(.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }
@media (max-width: 63.99rem) { .grid { grid-template-columns: 1fr; }.summary { order: -1; } }
@media (max-width: 47.99rem) { .fields { grid-template-columns: 1fr; padding-left: 1rem; }.mp h1 { font-size: 1.3rem; } }
@media (prefers-reduced-motion: reduce) { .spinner, .bar span, .sk { animation-duration: 3s; }.badge--ok { animation: none; }.btn, .method__radio, .field__box { transition: none; } }
</style>
