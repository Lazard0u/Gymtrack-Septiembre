import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('captura el explorador geográfico en escritorio y móvil', async ({ page, context }) => {
  await context.grantPermissions(['geolocation'], { origin: 'http://127.0.0.1:4173' })
  await context.setGeolocation({ latitude: -34.9046, longitude: -56.1851, accuracy: 35 })

  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/gimnasios')
  await page.getByRole('button', { name: 'Usar mi ubicación' }).click()
  await expect(page.getByText('Ubicación disponible')).toBeVisible()
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  const desktopAccessibility = await new AxeBuilder({ page }).analyze()
  expect(desktopAccessibility.violations.filter((item) => ['serious', 'critical'].includes(item.impact))).toEqual([])
  await page.screenshot({ path: 'artifacts/map/explorer-1440.png', fullPage: true })

  await page.setViewportSize({ width: 360, height: 844 })
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  await page.screenshot({ path: 'artifacts/map/explorer-360-medium.png', fullPage: true })
  await page.getByRole('button', { name: 'Expandir lista' }).click()
  await page.getByRole('button', { name: 'Filtros' }).click()
  await expect(page.getByLabel('Servicio')).toBeVisible()
  const mobileAccessibility = await new AxeBuilder({ page }).analyze()
  expect(mobileAccessibility.violations.filter((item) => ['serious', 'critical'].includes(item.impact))).toEqual([])
  await page.screenshot({ path: 'artifacts/map/explorer-360-full.png', fullPage: true })
})
