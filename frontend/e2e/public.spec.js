import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const widths = [360, 390, 768, 1024, 1440, 1920]
const gymCard = (page, name) => page.locator('.gym-card').filter({ hasText: name })
const mediumSheetGeometry = (page, cardName) => page.evaluate((name) => {
  const workspace = document.querySelector('.explorer__workspace').getBoundingClientRect()
  const map = document.querySelector('.explorer__map-wrap').getBoundingClientRect()
  const panel = document.querySelector('.explorer__panel').getBoundingClientRect()
  const card = [...document.querySelectorAll('.gym-card')].find((item) => item.textContent.includes(name)).getBoundingClientRect()
  return {
    visibleMapHeight: Math.max(0, Math.min(map.bottom, panel.top, workspace.bottom) - Math.max(map.top, workspace.top)),
    visibleCardHeight: Math.max(0, Math.min(card.bottom, panel.bottom, workspace.bottom) - Math.max(card.top, panel.top, workspace.top)),
  }
}, cardName)

for (const width of widths) {
  test(`inicio sin desbordamiento a ${width} px`, async ({ page }) => {
    await page.setViewportSize({ width, height: width < 768 ? 800 : 900 })
    await page.goto('/', { waitUntil: 'domcontentloaded' })
    await expect(page.getByRole('heading', { name: 'Tu gimnasio, en un solo lugar.' })).toBeVisible()
    await expect(page.locator('.hero-gym-card')).toBeVisible()
    await page.locator('[aria-label="Cargando mapa"]').waitFor({ state: 'detached', timeout: 9_000 }).catch(() => {})
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(1)
  })
}

test('la navegación pública móvil es fija, operable y no tapa el contenido', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/', { waitUntil: 'domcontentloaded' })
  const mobileNav = page.getByRole('navigation', { name: 'Navegación principal móvil' })
  await expect(mobileNav).toBeVisible()
  await expect(mobileNav.getByRole('link')).toHaveCount(5)
  await expect(page.getByRole('link', { name: 'Crear cuenta' }).first()).toBeVisible()
  await page.getByRole('link', { name: 'Explorar gimnasios' }).click()
  await expect(page).toHaveURL(/\/gimnasios$/)
  await expect(page.getByRole('heading', { name: 'Explorá el mapa. Elegí con contexto.' })).toBeVisible()
})

test('el hero presenta el mapa, la búsqueda y la ficha seleccionada sin la tarjeta Constancia', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('region', { name: 'Mapa interactivo de gimnasios de GymTrack' })).toBeVisible()
  await expect(page.getByLabel('Buscar barrio, ciudad o gimnasio')).toBeVisible()
  await expect(page.getByText('Constancia', { exact: true })).toHaveCount(0)
  await page.getByLabel('Buscar barrio, ciudad o gimnasio').fill('Titan')
  await expect(page.locator('.hero-gym-card')).toContainText('Titan Training')
  await expect(page.locator('.gym-marker')).toHaveCount(1)
})

test('el explorador responde como panel en escritorio y bottom sheet en móvil', async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 800 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Ocultar lista' }).click()
  const reopenPanel = page.getByRole('button', { name: 'Mostrar lista de gimnasios' })
  await expect(reopenPanel).toBeVisible()
  await expect(reopenPanel).toBeFocused()
  await reopenPanel.click()
  await expect(page.getByRole('button', { name: 'Ocultar lista' })).toBeFocused()

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
  await expect(page.locator('.explorer__panel-content')).toHaveAttribute('inert', '')
  await openControl.click()
  await expect(page.getByRole('button', { name: 'Expandir lista' })).toBeVisible()
  await expect(page.locator('.explorer__panel-content')).not.toHaveAttribute('inert', '')
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
  await expect(page.locator('.gym-card .badge--info', { hasText: 'Demostración' })).toHaveCount(5)
  await expect(page.getByText('5 ubicaciones visibles')).toBeVisible()
  await page.getByRole('button', { name: 'Filtros y orden' }).click()
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
  await page.getByRole('button', { name: 'Filtros y orden' }).click()
  await page.getByLabel('Servicio').selectOption('Accesibilidad')
  await expect(gymCard(page, 'Punto Activo')).toBeVisible()
  await expect(page.locator('.gym-marker')).toHaveCount(1)
  await page.getByLabel('Estado').selectOption('open')
  await expect(page.getByText('No encontramos coincidencias')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await page.locator('.gym-marker').first().click()
  await expect(page.locator('.gym-card--selected')).toHaveCount(1)
})

test('el explorador recuerda filtros plegados y permite arrastrar el bottom sheet', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Filtros y orden' }).click()
  await page.getByLabel('Ciudad').selectOption('Salto')
  await page.reload()
  await expect(page.getByRole('button', { name: 'Ocultar filtros' })).toBeVisible()
  await expect(page.getByLabel('Ciudad')).toHaveValue('Salto')
  await expect(gymCard(page, 'Norte Fitness Club')).toBeVisible()
  await page.getByRole('button', { name: 'Ocultar filtros' }).click()

  await page.setViewportSize({ width: 390, height: 844 })
  await page.reload()
  await expect.poll(async () => (await mediumSheetGeometry(page, 'Norte Fitness Club')).visibleMapHeight).toBeGreaterThan(100)
  await expect.poll(async () => (await mediumSheetGeometry(page, 'Norte Fitness Club')).visibleCardHeight).toBeGreaterThan(80)
  const handle = page.getByRole('button', { name: 'Expandir lista' })
  const box = await handle.boundingBox()
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
  await page.mouse.down()
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2 - 220, { steps: 8 })
  await page.mouse.up()
  await expect(page.getByRole('button', { name: 'Reducir lista' })).toBeVisible()
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
