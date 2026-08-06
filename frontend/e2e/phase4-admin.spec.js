import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

const password = process.env.DEMO_USER_PASSWORD
const widths = [360, 390, 768, 1024, 1440, 1920]

test.describe('Administración Fase 4', () => {
  test.skip(!password, 'DEMO_USER_PASSWORD no está definida.')

  test('shell, contexto, responsive, consola y accesibilidad', async ({ page }) => {
    const consoleErrors = []
    page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()) })

    await page.goto('/login')
    await page.getByLabel('Correo electrónico').fill('admin.demo@gymtrack.local')
    await page.getByLabel('Contraseña').fill(password)
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await page.getByRole('link', { name: 'Abrir administración' }).click()
    await page.getByLabel('Gimnasio activo').last().selectOption({ label: 'GymTrack Centro' })
    await page.getByLabel('Motivo de soporte').fill('Evidencia visual y responsive de Fase 4')
    await page.getByRole('button', { name: 'Entrar al gimnasio' }).click()
    await expect(page.getByRole('heading', { name: 'Resumen operativo' })).toBeVisible()

    for (const width of widths) {
      await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 })
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
      await page.screenshot({ path: `artifacts/phase4/admin-summary-${width}.png`, fullPage: true })
    }

    await page.setViewportSize({ width: 1440, height: 1000 })
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([])
    await page.getByRole('link', { name: 'Socios' }).first().click()
    await expect(page.getByText('socio.demo@gymtrack.local').first()).toBeVisible()
    await page.screenshot({ path: 'artifacts/phase4/admin-members-1440.png', fullPage: true })
    await page.setViewportSize({ width: 390, height: 844 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
    await page.screenshot({ path: 'artifacts/phase4/admin-members-390.png', fullPage: true })

    expect(consoleErrors).toEqual([])
  })
})
