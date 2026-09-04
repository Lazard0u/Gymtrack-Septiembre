/**
 * Prueba automatizada de demo-roles.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { expect, test } from '@playwright/test'

const password = process.env.DEMO_USER_PASSWORD
const accounts = [
  { email: 'socio.demo@gymtrack.local', role: 'Socio', gyms: ['GymTrack Centro', 'Titan Training'] },
  { email: 'empleado.demo@gymtrack.local', role: 'Empleado', gyms: ['GymTrack Centro'], dashboardGyms: ['GymTrack Centro'] },
  { email: 'dueno.demo@gymtrack.local', role: 'Dueño', gyms: ['GymTrack Centro', 'Arena Functional Gym'], dashboardGyms: ['Arena Functional Gym', 'GymTrack Centro'] },
  { email: 'admin.demo@gymtrack.local', role: 'Administrador general', gyms: ['GymTrack Centro', 'Norte Fitness Club', 'Titan Training', 'Punto Activo', 'Arena Functional Gym'], dashboardGyms: ['Arena Functional Gym', 'GymTrack Centro', 'Norte Fitness Club'] },
]

test.describe('roles y contexto demo', () => {
  test.skip(!password, 'DEMO_USER_PASSWORD no está definida.')

  for (const account of accounts) {
    test(`${account.role} recibe únicamente su contexto`, async ({ page }) => {
      await page.goto('/login')
      await page.getByLabel('Correo electrónico').fill(account.email)
      await page.getByLabel('Contraseña').fill(password)
      await page.getByRole('button', { name: 'Ingresar' }).click()
      await expect(page).toHaveURL(/\/dashboard$/)
      await expect(page.getByText(account.role, { exact: true }).first()).toBeVisible()
      await expect(page.getByText('Cuenta demo')).toBeVisible()
      for (const gym of account.role === 'Socio' ? account.gyms : account.dashboardGyms) {
        if (account.role === 'Socio') {
          await expect(page.getByLabel('Contexto activo').locator('option', { hasText: gym })).toHaveCount(1)
        } else {
          await expect(page.getByRole('heading', { name: gym })).toBeVisible()
        }
      }
      if (account.role === 'Administrador general') await expect(page.getByText('5', { exact: true }).first()).toBeVisible()
      if (['Administrador general', 'Empleado', 'Dueño'].includes(account.role)) {
        await expect(page.getByRole('link', { name: 'Abrir administración' })).toBeVisible()
      } else {
        await expect(page.getByRole('link', { name: 'Abrir administración' })).toHaveCount(0)
      }
    })
  }

  test('el panel del socio prioriza el día y mantiene navegación móvil accesible', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto('/login')
    await page.getByLabel('Correo electrónico').fill('socio.demo@gymtrack.local')
    await page.getByLabel('Contraseña').fill(password)
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await expect(page).toHaveURL(/\/dashboard$/)
    await expect(page.getByRole('heading', { name: 'Lo importante para hoy' })).toBeVisible()
    await expect(page.locator('.quick-stats > div')).toHaveCount(3)
    await expect(page.getByRole('heading', { name: 'Actividad reciente' })).toBeVisible()
    const navigation = page.getByRole('navigation', { name: 'Navegación móvil del socio' })
    await expect(navigation.getByRole('link')).toHaveCount(5)
    const geometry = await page.evaluate(() => {
      const nav = document.querySelector('.member-bottom-nav').getBoundingClientRect()
      return { bottom: Math.round(nav.bottom), viewport: window.innerHeight, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth }
    })
    expect(geometry.bottom).toBe(geometry.viewport)
    expect(geometry.overflow).toBeLessThanOrEqual(1)
  })

  test('el panel demo no mezcla cuentas reales y distingue acciones estables de funciones beta', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Correo electrónico').fill('admin.demo@gymtrack.local')
    await page.getByLabel('Contraseña').fill(password)
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await page.getByRole('link', { name: 'Abrir administración' }).click()
    await expect(page).toHaveURL(/\/administracion\/resumen$/)
    await expect(page.getByRole('heading', { name: 'Elegí un gimnasio para continuar' })).toBeVisible()
    await page.getByLabel('Gimnasio activo').last().selectOption({ label: 'GymTrack Centro' })
    await page.getByLabel('Motivo de soporte').fill('Validación funcional de la presentación')
    await page.getByRole('button', { name: 'Entrar al gimnasio' }).click()
    await expect(page.getByRole('heading', { name: 'Resumen operativo' })).toBeVisible()
    await page.getByRole('link', { name: 'Socios' }).first().click()
    await expect(page.getByText('socio.demo@gymtrack.local').first()).toBeVisible()
    await expect(page.getByText('usuario@gmail.com')).toHaveCount(0)
    await page.getByRole('link', { name: 'Resumen' }).first().click()
    await expect(page.getByRole('link', { name: /Crear clase/ })).toHaveAttribute('href', /\/administracion\/clases/)
    await expect(page.getByRole('link', { name: /Registrar pago manual/ })).toHaveAttribute('href', /\/administracion\/pagos\?action=manual/)
    await expect(page.getByRole('link', { name: /Crear promoción/ })).toHaveAttribute('href', /\/administracion\/promociones\?action=create/)
    await expect(page.getByRole('link', { name: /Finanzas/ }).first()).toBeVisible()
    await expect(page.getByRole('link', { name: /Reportes/ }).first()).toBeVisible()
  })

})
