/**
 * Prueba automatizada de payments.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

const password = process.env.DEMO_USER_PASSWORD

async function login(page, email) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(password)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page).toHaveURL(/\/dashboard/)
}

async function openAdmin(page, section) {
  await login(page, 'dueno.demo@gymtrack.local')
  await page.getByRole('link', { name: 'Abrir administración' }).click()
  await page.getByLabel('Gimnasio activo').last().selectOption({ label: 'GymTrack Centro' })
  await page.getByRole('link', { name: section }).first().click()
}

async function inspect(page, path) {
  const violations = await new AxeBuilder({ page }).analyze()
  expect(violations.violations.filter((item) => ['serious', 'critical'].includes(item.impact))).toEqual([])
  for (const width of [360, 390, 768, 1024, 1440, 1920]) {
    await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  }
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.screenshot({ path: `artifacts/payments/${path}-1440.png`, fullPage: true })
  await page.setViewportSize({ width: 360, height: 844 })
  await page.screenshot({ path: `artifacts/payments/${path}-360.png`, fullPage: true })
}

test.describe('Pagos, finanzas y reportes', () => {
  test.skip(!password, 'DEMO_USER_PASSWORD no está definida.')

  test('la administración consulta estados y abre un pago real', async ({ page }) => {
    const errors = []; page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()) })
    await openAdmin(page, 'Pagos')
    await expect(page.getByRole('heading', { name: 'Pagos' })).toBeVisible()
    await expect(page.getByText('GT-DEMO-PENDING')).toBeVisible()
    await page.getByRole('button', { name: 'Ver detalle' }).first().click()
    await expect(page.getByRole('dialog', { name: 'Detalle de transacción' })).toBeVisible()
    await page.getByRole('button', { name: 'Cerrar menú' }).click()
    await inspect(page, 'admin-payments')
    expect(errors).toEqual([])
  })

  test('finanzas presenta seis meses y deuda sin datos inventados', async ({ page }) => {
    await openAdmin(page, 'Finanzas')
    await expect(page.getByRole('heading', { name: 'Finanzas' })).toBeVisible()
    await expect(page.getByText('Últimos seis meses')).toBeVisible()
    await expect(page.getByText('Deuda total')).toBeVisible()
    await expect(page.locator('.revenue-chart__column')).toHaveCount(6)
    await inspect(page, 'admin-finance')
  })

  test('reportes genera un Excel descargable', async ({ page }) => {
    await openAdmin(page, 'Reportes')
    await expect(page.getByRole('heading', { name: 'Reportes' })).toBeVisible()
    await page.getByRole('button', { name: 'Generar Excel' }).click()
    await expect(page.getByText('Reporte generado')).toBeVisible()
    await expect(page.getByText(/gymtrack-payments/).first()).toBeVisible()
    await inspect(page, 'admin-reports')
  })

  test('el socio ve historial y un checkout honestamente deshabilitado', async ({ page }) => {
    await login(page, 'socio.demo@gymtrack.local')
    await page.goto('/pagos')
    await page.getByLabel('Gimnasio').selectOption({ label: 'GymTrack Centro' })
    await expect(page.getByRole('heading', { name: 'Tus pagos, sin estados ambiguos.' })).toBeVisible()
    await expect(page.getByText('Mercado Pago sin configurar')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Pagar con Mercado Pago' }).first()).toBeDisabled()
    await inspect(page, 'member-payments')
  })
})
