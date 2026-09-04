/**
 * Prueba automatizada de map-visual.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('captura el explorador geográfico en escritorio y móvil', async ({ page, context }) => {
  await context.grantPermissions(['geolocation'], { origin: 'http://127.0.0.1:4173' })
  await context.setGeolocation({ latitude: -34.9046, longitude: -56.1851, accuracy: 35 })

  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Usar mi ubicación' }).click()
  await expect(page.getByText('Ubicación disponible')).toBeVisible()
  await expect(page.locator('.gym-card').first()).toBeVisible()
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations.filter((item) => ['serious', 'critical'].includes(item.impact))).toEqual([])
  await page.screenshot({ path: 'artifacts/map/explorer-1440.png', fullPage: true })

  await page.setViewportSize({ width: 360, height: 844 })
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  const readMediumGeometry = () => page.evaluate(() => {
    const workspace = document.querySelector('.explorer__workspace').getBoundingClientRect()
    const map = document.querySelector('.explorer__map-wrap').getBoundingClientRect()
    const panel = document.querySelector('.explorer__panel').getBoundingClientRect()
    const card = document.querySelector('.gym-card').getBoundingClientRect()
    return {
      visibleMapHeight: Math.max(0, Math.min(map.bottom, panel.top, workspace.bottom) - Math.max(map.top, workspace.top)),
      visibleCardHeight: Math.max(0, Math.min(card.bottom, panel.bottom, workspace.bottom) - Math.max(card.top, panel.top, workspace.top)),
    }
  })
  await expect.poll(async () => (await readMediumGeometry()).visibleMapHeight).toBeGreaterThan(100)
  await expect.poll(async () => (await readMediumGeometry()).visibleCardHeight).toBeGreaterThan(80)
  await page.screenshot({ path: 'artifacts/map/explorer-360-medium.png', fullPage: true })
  await page.getByRole('button', { name: 'Expandir lista' }).click()
  await page.getByRole('button', { name: 'Filtros y orden' }).click()
  await expect(page.getByLabel('Servicio')).toBeVisible()
  await expect(page.locator('.explorer__panel-body')).toBeHidden()
  await expect.poll(() => page.locator('#gym-filters').evaluate((element) => element.scrollHeight > element.clientHeight)).toBe(true)
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations.filter((item) => ['serious', 'critical'].includes(item.impact))).toEqual([])
  await page.screenshot({ path: 'artifacts/map/explorer-360-full.png', fullPage: true })
})
