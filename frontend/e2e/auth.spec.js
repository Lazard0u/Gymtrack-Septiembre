import { expect, test } from '@playwright/test'

const password = process.env.DEMO_USER_PASSWORD

test.describe('identidad segura', () => {
  test.skip(!password, 'DEMO_USER_PASSWORD no está definida.')

  test('la sesión usa cookie HttpOnly y no localStorage', async ({ page, context }) => {
    await page.goto('/login')
    await page.getByLabel('Correo electrónico').fill('socio.demo@gymtrack.local')
    await page.getByLabel('Contraseña').fill(password)
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await expect(page).toHaveURL(/\/dashboard$/)
    expect(await page.evaluate(() => localStorage.getItem('token'))).toBeNull()
    const cookie = (await context.cookies()).find((item) => item.name === 'gymtrack_session')
    expect(cookie?.httpOnly).toBe(true)
    expect(cookie?.sameSite).toBe('Lax')
  })

  test('un socio no puede abrir administración', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Correo electrónico').fill('socio.demo@gymtrack.local')
    await page.getByLabel('Contraseña').fill(password)
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await expect(page).toHaveURL(/\/dashboard$/)
    await page.goto('/admin')
    await expect(page).toHaveURL(/\/403$/)
    await expect(page.getByRole('heading', { name: 'No tenés permiso para entrar acá' })).toBeVisible()
  })

  test('recuperación y registro no desbordan en móvil', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 })
    for (const path of ['/login', '/registro', '/registro-gimnasio', '/recuperar', '/verificar-email']) {
      await page.goto(path)
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
      expect(overflow).toBeLessThanOrEqual(1)
    }

    await page.goto('/login')
    await page.screenshot({ path: 'artifacts/phase3/login-360.png', fullPage: true })
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.goto('/registro')
    await page.screenshot({ path: 'artifacts/phase3/register-1440.png', fullPage: true })
  })
})
