/**
 * Store Pinia de payments. Centraliza estado reactivo, llamadas a la API y errores para que las vistas compartan una única fuente de datos.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { defineStore } from 'pinia'
import { api } from '../services/api'

function message(response, fallback) { return response.data?.mensaje || fallback }
function key() { return crypto.randomUUID() }

export const usePaymentsStore = defineStore('payments', {
  state: () => ({
    items: [], pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 }, options: { memberships: [], provider: {} },
    finance: null, exports: [], exportPagination: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    memberItems: [], memberPlans: [], memberProvider: {},
    paymentsStatus: 'idle', financeStatus: 'idle', exportsStatus: 'idle', memberStatus: 'idle',
    paymentsError: '', financeError: '', exportsError: '', memberError: '', working: '', controller: null,
  }),
  actions: {
    async loadPayments(query = {}) {
      this.paymentsStatus = 'loading'; this.paymentsError = ''
      const response = await api.get('/admin/payments', { params: query })
      if (!response.ok) { this.paymentsStatus = 'error'; this.paymentsError = message(response, 'No se pudieron cargar los pagos.'); return false }
      this.items = response.data?.data?.items || []; this.pagination = response.data?.meta?.pagination || this.pagination; this.paymentsStatus = this.items.length ? 'ready' : 'empty'; return true
    },
    async loadOptions() {
      const response = await api.get('/admin/payments/options')
      if (response.ok) this.options = response.data?.data || this.options
      return response.ok
    },
    async createManual(payload) {
      this.working = 'manual'
      const response = await api.post('/admin/payments/manual', payload, { headers: { 'Idempotency-Key': key() } })
      this.working = ''
      if (!response.ok) throw new Error(message(response, 'No se pudo registrar el pago.'))
      return response.data.data
    },
    async refund(paymentId, reason) {
      this.working = `refund-${paymentId}`
      const response = await api.post(`/admin/payments/${paymentId}/refund`, { reason }, { headers: { 'Idempotency-Key': key() } })
      this.working = ''
      if (!response.ok) throw new Error(message(response, 'No se pudo reembolsar el pago.'))
      return response.data.data
    },
    async loadFinance() {
      this.financeStatus = 'loading'; this.financeError = ''
      const response = await api.get('/admin/finance')
      if (!response.ok) { this.financeStatus = 'error'; this.financeError = message(response, 'No se pudieron cargar las finanzas.'); return false }
      this.finance = response.data.data; this.financeStatus = 'ready'; return true
    },
    async loadExports() {
      this.exportsStatus = 'loading'; this.exportsError = ''
      const response = await api.get('/admin/exports', { params: { page: 1, per_page: 20, sort: 'created_at', direction: 'desc' } })
      if (!response.ok) { this.exportsStatus = 'error'; this.exportsError = message(response, 'No se pudo cargar el historial.'); return false }
      this.exports = response.data?.data?.items || []; this.exportPagination = response.data?.meta?.pagination || this.exportPagination; this.exportsStatus = this.exports.length ? 'ready' : 'empty'; return true
    },
    async generateReport(type, module, filters = {}) {
      this.working = `export-${type}`
      const response = await api.post('/admin/exports', { type, module, filters })
      this.working = ''
      if (!response.ok) throw new Error(message(response, 'No se pudo generar el reporte.'))
      await this.loadExports(); return response.data.data
    },
    async loadMember() {
      this.memberStatus = 'loading'; this.memberError = ''
      const response = await api.get('/payments/mine')
      if (!response.ok) { this.memberStatus = 'error'; this.memberError = message(response, 'No se pudieron cargar tus pagos.'); return false }
      const data = response.data.data || {}; this.memberItems = data.items || []; this.memberPlans = data.plans || []; this.memberProvider = data.provider || {}; this.memberStatus = 'ready'; return true
    },
    async checkout(planId) {
      this.working = `checkout-${planId}`
      const response = await api.post('/payments/checkout', { plan_id: planId }, { headers: { 'Idempotency-Key': key() } })
      this.working = ''
      if (!response.ok) throw new Error(message(response, 'No se pudo iniciar el pago.'))
      return response.data.data
    },
    reset() { this.$reset() },
  },
})
