import { describe, expect, it } from 'vitest'
import { CANTIDAD_BANNERS, imagenGimnasio } from '../imagenGimnasio'

describe('imagenGimnasio', () => {
  it('usa la imagen propia del gimnasio', () => {
    expect(imagenGimnasio({ slug: 'a', imagen_path: '/api/public/files/33' })).toBe('/api/public/files/33')
  })
  it('sin imagen, o si la imagen no carga, usa un banner de relleno', () => {
    expect(imagenGimnasio({ slug: 'a' })).toMatch(/^\/placeholders\/banner-\d\.svg$/)
    expect(imagenGimnasio({ slug: 'a', imagen_path: '/rota.png' }, true)).toMatch(/^\/placeholders\/banner-\d\.svg$/)
  })
  it('el mismo gimnasio siempre recibe el mismo banner', () => {
    const gym = { slug: 'norte-fitness-club' }
    expect(imagenGimnasio(gym)).toBe(imagenGimnasio({ ...gym }))
  })
  it('reparte los banners y nunca se sale del rango', () => {
    const usados = new Set()
    for (let i = 0; i < 200; i += 1) {
      const numero = Number(imagenGimnasio({ slug: `gimnasio-${i}` }).match(/banner-(\d+)/)[1])
      expect(numero).toBeGreaterThanOrEqual(1)
      expect(numero).toBeLessThanOrEqual(CANTIDAD_BANNERS)
      usados.add(numero)
    }
    expect(usados.size).toBe(CANTIDAD_BANNERS)
  })
  it('no falla con datos incompletos', () => {
    expect(imagenGimnasio(null)).toMatch(/^\/placeholders\/banner-\d\.svg$/)
  })
})
