import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const widths = [360, 390, 768, 1024, 1440, 1920]
const gymCard = (page, name) => page.locator('.gym-card').filter({ hasText: name })

for (const width of widths) {
  test(`inicio sin desbordamiento a ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: width < 768 ? 800 : 900 })
    await page.goto('/', { waitUntil: 'domcontentloaded' })
    await expect(page.getByRole('heading', { name: 'Tu gimnasio, en un solo lugar.' })).toBeVisible()
    await expect(gymCard(page, 'GymTrack Centro')).toBeVisible()
    await page.locator('[aria-label="Cargando mapa"]').waitFor({ state: 'detached', timeout: 9_000 }).catch(() => {})
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(1)
  })
}

test('navegación pública y menú móvil son operables', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await page.getByRole('button', { name: 'Abrir menú' }).click()
  await expect(page.getByRole('dialog', { name: 'Menú' })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog', { name: 'Menú' })).toBeHidden()
  await page.getByRole('link', { name: 'Explorar gimnasios' }).click()
  await expect(page).toHaveURL(/\/gimnasios$/)
  await expect(page.getByRole('heading', { name: 'Explorá el mapa. Elegí con contexto.' })).toBeVisible()
})

test('el explorador responde como panel en escritorio y bottom sheet en móvil', async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 800 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Ocultar lista' }).click()
  await expect(page.getByRole('button', { name: 'Mostrar lista de gimnasios' })).toBeVisible()

  await page.setViewportSize({ width: 390, height: 844 })
  await page.reload()
  const sheetControl = page.getByRole('button', { name: 'Expandir lista' })
  await expect(sheetControl).toBeVisible()
  await sheetControl.click()
  const reduceControl = page.getByRole('button', { name: 'Reducir lista' })
  await expect(reduceControl).toBeVisible()
  await reduceControl.click()
  const openControl = page.getByRole('button', { name: 'Abrir lista' })
  await expect(openControl).toBeVisible()
  await openControl.click()
  await expect(page.getByRole('button', { name: 'Expandir lista' })).toBeVisible()
})

test('el catálogo demo llega desde la API y sus filtros actualizan lista y mapa', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/gimnasios')
  await expect(page.getByText('Datos de demostración').first()).toBeVisible()
  await expect(gymCard(page, 'GymTrack Centro')).toBeVisible()
  await expect(gymCard(page, 'Norte Fitness Club')).toBeVisible()
  await expect(gymCard(page, 'Titan Training')).toBeVisible()
  await expect(gymCard(page, 'Punto Activo')).toBeVisible()
  await expect(gymCard(page, 'Arena Functional Gym')).toBeVisible()
  await page.getByLabel('Ciudad').selectOption('Salto')
  await expect(gymCard(page, 'Norte Fitness Club')).toBeVisible()
  await expect(gymCard(page, 'GymTrack Centro')).toBeHidden()
  await expect(page.locator('.gym-marker')).toHaveCount(1)
  await page.getByLabel('Ciudad').selectOption('')
  await page.getByLabel('Buscar').fill('Titan')
  await expect(gymCard(page, 'Titan Training')).toBeVisible()
  await expect(page.locator('.gym-marker')).toHaveCount(1)
})

test('la ubicación se solicita con consentimiento y ordena por cercanía sin salir del navegador', async ({ page }) => {
  await page.context().grantPermissions(['geolocation'], { origin: 'http://127.0.0.1:4173' })
  await page.context().setGeolocation({ latitude: -34.9046, longitude: -56.1851, accuracy: 35 })
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Usar mi ubicación' }).click()
  await expect(page.getByText('Ubicación disponible')).toBeVisible()
  await expect(page.getByLabel('Ordenar por')).toHaveValue('distance')
  await expect(gymCard(page, 'GymTrack Centro').getByText(/A \d+ m de tu ubicación/)).toBeVisible()
  await expect(page.locator('.gym-card').first()).toContainText('GymTrack Centro')
  await expect(page.locator('.user-location')).toHaveCount(1)
  await page.getByRole('button', { name: 'Volver a mi ubicación' }).click()
})

test('el permiso rechazado conserva el catálogo y ofrece la vista general', async ({ page }) => {
  await page.context().clearPermissions()
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Usar mi ubicación' }).click()
  await expect(page.getByText('Permiso de ubicación rechazado')).toBeVisible()
  await page.getByRole('button', { name: 'Usar vista general' }).click()
  await expect(page.getByText('Vista general activa')).toBeVisible()
  await expect(gymCard(page, 'GymTrack Centro')).toBeAttached()
})

test('servicios, estado y marcadores permanecen sincronizados', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/gimnasios')
  await page.getByLabel('Servicio').selectOption('Accesibilidad')
  await expect(gymCard(page, 'Punto Activo')).toBeVisible()
  await expect(page.locator('.gym-marker')).toHaveCount(1)
  await page.getByLabel('Estado').selectOption('open')
  await expect(page.getByText('No encontramos coincidencias')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await page.locator('.gym-marker').first().click()
  await expect(page.locator('.gym-card--selected')).toHaveCount(1)
})

test('los marcadores se agrupan cuando el mapa muestra una región amplia', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/gimnasios')
  await expect(gymCard(page, 'GymTrack Centro')).toBeVisible()
  const zoomOut = page.locator('.leaflet-control-zoom-out')
  await zoomOut.click()
  await zoomOut.click()
  await expect(page.locator('.gym-cluster')).not.toHaveCount(0)
})

test('la portada no tiene violaciones automáticas serias o críticas', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/')
  const results = await new AxeBuilder({ page }).analyze()
  const severe = results.violations.filter((item) => ['serious', 'critical'].includes(item.impact))
  expect(severe).toEqual([])
})

test('las rutas públicas no generan errores de consola', async ({ page }) => {
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text())
  })

  for (const path of ['/', '/gimnasios', '/planes', '/para-gimnasios', '/privacidad', '/terminos', '/accesibilidad', '/contacto']) {
    await page.goto(path, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(150)
  }

  expect(errors).toEqual([])
})
