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
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.goto('/registro')
  })
})

test('la pantalla explica cada resultado de verificación y limpia el token de la URL', async ({ page }) => {
  let simulated = { status: 200, body: { error: false, codigo: 'email_verified', mensaje: 'Correo verificado correctamente.' } }
  await page.route('**/api/auth/email/verify', async (route) => {
    await route.fulfill({ status: simulated.status, contentType: 'application/json', body: JSON.stringify(simulated.body) })
  })

  const scenarios = [
    [200, 'email_verified', 'Correo verificado correctamente.', 'Correo verificado'],
    [200, 'email_already_verified', 'Tu correo ya estaba verificado.', 'Tu correo ya estaba verificado'],
    [410, 'email_verification_expired', 'El enlace venció.', 'El enlace venció'],
    [409, 'email_verification_used', 'Este enlace ya fue utilizado.', 'El enlace ya fue utilizado'],
    [400, 'email_verification_invalid', 'El enlace no es válido.', 'El enlace no es válido'],
  ]

  await page.setViewportSize({ width: 390, height: 844 })
  for (const [status, codigo, mensaje, heading] of scenarios) {
    simulated = { status, body: { error: status >= 400, codigo, mensaje } }
    // El valor se crea durante la prueba y nunca representa un token persistido.
    const temporaryToken = `prueba-${Date.now()}-${Math.random().toString(36).slice(2)}`
    // El query cambia el documento entre escenarios; el token continúa sólo en el fragmento.
    await page.goto(`/verificar-email?caso=${codigo}#token=${temporaryToken}`)
    await expect(page.getByRole('heading', { name: heading })).toBeVisible()
    await expect.poll(() => page.url()).not.toContain('token=')
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(1)
  }
})
