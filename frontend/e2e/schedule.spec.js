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

test.describe('Agenda, reservas y asistencia', () => {
  test.skip(!password, 'DEMO_USER_PASSWORD no está definida.')

  test('la agenda administrativa muestra cupos reales y abre la lista', async ({ page }) => {
    const consoleErrors = []
    page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()) })
    await login(page, 'dueno.demo@gymtrack.local')
    await page.getByRole('link', { name: 'Abrir administración' }).click()
    await page.getByLabel('Gimnasio activo').last().selectOption({ label: 'GymTrack Centro' })
    await page.getByRole('link', { name: 'Clases' }).first().click()
    await expect(page.getByRole('heading', { name: 'Agenda de clases' })).toBeVisible()
    await expect(page.getByText('Entrenamiento funcional').first()).toBeVisible()
    await page.locator('.session-row').filter({ hasText: 'Entrenamiento funcional' }).getByRole('button', { name: 'Ver lista' }).click()
    await expect(page.getByText('Socio Demo')).toBeVisible()
    await expect(page.getByText('Asistencia disponible 30 min antes')).toBeVisible()

    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact))).toEqual([])
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.screenshot({ path: 'artifacts/schedule/admin-roster-1440.png', fullPage: true })
    await page.getByRole('button', { name: 'Cerrar' }).last().click()
    await expect(page.getByRole('dialog')).not.toBeVisible()
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.screenshot({ path: 'artifacts/schedule/admin-1440.png', fullPage: true })
    for (const width of [360, 390, 768, 1024, 1440, 1920]) {
      await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 })
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
    }
    await page.setViewportSize({ width: 360, height: 844 })
    await page.screenshot({ path: 'artifacts/schedule/admin-360.png', fullPage: true })
    expect(consoleErrors).toEqual([])
  })

  test('el socio consulta la agenda y ve su reserva confirmada', async ({ page }) => {
    await login(page, 'socio.demo@gymtrack.local')
    await page.goto('/agenda')
    await page.getByLabel('Gimnasio').selectOption({ label: 'GymTrack Centro' })
    await expect(page.getByRole('heading', { name: 'Tu próxima clase empieza acá.' })).toBeVisible()
    await expect(page.getByText('Reserva confirmada').first()).toBeVisible()
    await expect(page.getByText('Entrenamiento funcional').first()).toBeVisible()
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.screenshot({ path: 'artifacts/schedule/member-1440.png', fullPage: true })
    await page.setViewportSize({ width: 360, height: 844 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
    await page.screenshot({ path: 'artifacts/schedule/member-360.png', fullPage: true })
  })
})
