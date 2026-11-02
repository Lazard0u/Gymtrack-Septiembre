import { describe, expect, it } from 'vitest'
import { estadoOperativo, filasHorario } from '../horarios'

const gym = { zona_horaria: 'America/Montevideo', horarios: { lunes_viernes: '06:00–22:00', sabado: '08:00–18:00', domingo: 'Cerrado' } }
// 2026-11-02 es lunes y Montevideo está en UTC-3 todo el año.
const lunesMediodia = new Date('2026-11-02T15:00:00Z')
const lunesMadrugada = new Date('2026-11-02T07:00:00Z')
const domingoNoche = new Date('2026-11-02T02:00:00Z')

describe('horarios', () => {
  it('abierto dentro del rango del día', () => {
    expect(estadoOperativo(gym, lunesMediodia)).toEqual({ label: 'Abierto ahora', tone: 'success' })
  })
  it('cerrado fuera del rango', () => {
    expect(estadoOperativo(gym, lunesMadrugada).label).toBe('Cerrado ahora')
  })
  it('cerrado en un día marcado como Cerrado', () => {
    expect(estadoOperativo(gym, domingoNoche).label).toBe('Cerrado ahora')
  })
  it('el cierre temporal manda sobre el horario', () => {
    expect(estadoOperativo({ ...gym, estado: 'temporalmente_cerrado' }, lunesMediodia).tone).toBe('warning')
  })
  it('ordena la semana de lunes a domingo aunque el servidor la mande desordenada', () => {
    const desordenado = { ...gym, horarios: { domingo: 'Cerrado', sabado: '08:00–18:00', lunes_viernes: '06:00–22:00' } }
    expect(filasHorario(desordenado).map((fila) => fila.clave)).toEqual(['lunes_viernes', 'sabado', 'domingo'])
  })
  it('marca la fila que aplica hoy', () => {
    expect(filasHorario(gym, lunesMediodia).find((fila) => fila.hoy).clave).toBe('lunes_viernes')
    expect(filasHorario(gym, domingoNoche).find((fila) => fila.hoy).clave).toBe('domingo')
  })
})
