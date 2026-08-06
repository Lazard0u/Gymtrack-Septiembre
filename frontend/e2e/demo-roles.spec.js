import { expect, test } from '@playwright/test'

const password = process.env.DEMO_USER_PASSWORD
const accounts = [
  { email: 'socio.demo@gymtrack.local', role: 'Socio', gyms: ['GymTrack Centro', 'Titan Training'] },
  { email: 'empleado.demo@gymtrack.local', role: 'Empleado', gyms: ['GymTrack Centro'] },
  { email: 'dueno.demo@gymtrack.local', role: 'Dueño', gyms: ['GymTrack Centro', 'Arena Functional Gym'] },
  { email: 'admin.demo@gymtrack.local', role: 'Administrador general', gyms: ['GymTrack Centro', 'Norte Fitness Club', 'Titan Training', 'Punto Activo', 'Arena Functional Gym'] },
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
      for (const gym of account.gyms) {
        await expect(page.getByRole('heading', { name: gym })).toBeVisible()
      }
      if (account.role === 'Administrador general') {
        await expect(page.getByRole('link', { name: 'Abrir administración' })).toBeVisible()
      } else {
        await expect(page.getByRole('link', { name: 'Abrir administración' })).toHaveCount(0)
      }
    })
  }

  test('el panel demo no mezcla cuentas reales ni ofrece mutaciones de fases posteriores', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Correo electrónico').fill('admin.demo@gymtrack.local')
    await page.getByLabel('Contraseña').fill(password)
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await page.getByRole('link', { name: 'Abrir administración' }).click()
    await expect(page).toHaveURL(/\/admin$/)
    await expect(page.getByText(/Esta vista permite recorrer y consultar el dataset/)).toBeVisible()
    await expect(page.getByText('socio.demo@gymtrack.local').first()).toBeVisible()
    await expect(page.getByText('usuario@gmail.com')).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Crear membresía' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Crear clase' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Desactivar' })).toHaveCount(0)
  })
})
