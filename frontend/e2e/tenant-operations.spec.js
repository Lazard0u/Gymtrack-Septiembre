import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

const password = process.env.DEMO_USER_PASSWORD
const widths = [360, 390, 768, 1024, 1440, 1920]

async function enterAdministration(page) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill('admin.demo@gymtrack.local')
  await page.getByLabel('Contraseña').fill(password)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await page.getByRole('link', { name: 'Abrir administración' }).click()
  await page.getByLabel('Gimnasio activo').last().selectOption({ label: 'GymTrack Centro' })
  await page.getByLabel('Motivo de soporte').fill('Validación responsive de operación multi-gimnasio')
  await page.getByRole('button', { name: 'Entrar al gimnasio' }).click()
  await expect(page.getByRole('heading', { name: 'Resumen operativo' })).toBeVisible()
}

test.describe('Operación multi-gimnasio', () => {
  test.skip(!password, 'DEMO_USER_PASSWORD no está definida.')

  test('personas, entrenadores, membresías y configuración son operables y responsive', async ({ page }) => {
    const consoleErrors = []
    page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()) })
    await enterAdministration(page)

    await page.getByRole('link', { name: 'Socios' }).first().click()
    await expect(page.getByRole('heading', { name: 'Socios' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Invitar socio' })).toBeVisible()
    await expect(page.getByText('socio.demo@gymtrack.local').first()).toBeVisible()

    await page.goto('/administracion/entrenadores')
    await expect(page.getByRole('heading', { name: 'Entrenadores' })).toBeVisible()
    await expect(page.getByText('empleado.demo@gymtrack.local').first()).toBeVisible()

    await page.goto('/administracion/membresias?view=planes')
    await expect(page.getByRole('heading', { name: 'Membresías' })).toBeVisible()
    await expect(page.getByText('Plan Centro Base').first()).toBeVisible()
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([])

    await page.goto('/administracion/configuracion')
    await expect(page.getByRole('heading', { name: 'Configuración del gimnasio' })).toBeVisible()
    await expect(page.locator('.gym-overview')).toContainText('GymTrack Centro')
    for (const width of widths) {
      await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 })
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
    }

    expect(consoleErrors).toEqual([])
  })

  test('la ficha pública usa sedes y planes reales sin acciones falsas', async ({ page }) => {
    await page.goto('/gimnasios/gymtrack-centro')
    await expect(page.getByRole('heading', { name: 'GymTrack Centro' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Sedes publicadas' })).toBeVisible()
    await expect(page.getByText('Plan Centro Base')).toBeVisible()
    await expect(page.getByRole('main').getByText('Datos de demostración')).toBeVisible()
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([])
  })
})
