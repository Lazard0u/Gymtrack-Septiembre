import { describe, expect, it } from 'vitest'
import { distanceInKm, formatDistance, groupByProjectedCell, isValidPosition } from '../geo'

describe('utilidades geográficas', () => {
  it('calcula una distancia reproducible sin enviar la ubicación', () => {
    const distance = distanceInKm(
      { latitude: -34.90451, longitude: -56.18502 },
      { latitude: -34.83534, longitude: -55.98213 },
    )
    expect(distance).toBeGreaterThan(19)
    expect(distance).toBeLessThan(21)
    expect(formatDistance(distance)).toBe('20 km')
  })

  it('valida coordenadas y formatea cercanía', () => {
    expect(isValidPosition({ latitude: -34.9, longitude: -56.1 })).toBe(true)
    expect(isValidPosition({ latitude: 120, longitude: -56.1 })).toBe(false)
    expect(formatDistance(0.42)).toBe('420 m')
    expect(distanceInKm(null, null)).toBeNull()
  })

  it('agrupa únicamente puntos que comparten celda proyectada', () => {
    const groups = groupByProjectedCell(
      [{ id: 1, x: 10, y: 10 }, { id: 2, x: 30, y: 20 }, { id: 3, x: 150, y: 10 }],
      (item) => item,
      72,
    )
    expect(groups.map((group) => group.map((item) => item.id))).toEqual([[1, 2], [3]])
  })
})
