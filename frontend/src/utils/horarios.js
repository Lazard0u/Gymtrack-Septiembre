/** Horarios de un gimnasio: estado "abierto ahora" y filas legibles de la semana. */

const DIAS = { monday: 'lunes', tuesday: 'martes', wednesday: 'miercoles', thursday: 'jueves', friday: 'viernes', saturday: 'sabado', sunday: 'domingo' }
const ETIQUETAS = { lunes_viernes: 'Lunes a viernes', lunes_sabado: 'Lunes a sábado', lunes: 'Lunes', martes: 'Martes', miercoles: 'Miércoles', jueves: 'Jueves', viernes: 'Viernes', sabado: 'Sábado', domingo: 'Domingo' }
const ORDEN = ['lunes_viernes', 'lunes_sabado', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo']
const zona = (gym) => gym?.zona_horaria || 'America/Montevideo'

function diaLocal(gym, now) {
  return DIAS[new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: zona(gym) }).format(now).toLowerCase()]
}
function aplicaHoy(clave, dia) {
  return clave === dia || (['lunes', 'martes', 'miercoles', 'jueves', 'viernes'].includes(dia) && clave === 'lunes_viernes') || (dia !== 'domingo' && clave === 'lunes_sabado')
}

/** Devuelve { label, tone } con el estado operativo del gimnasio en su zona horaria. */
export function estadoOperativo(gym, now = new Date()) {
  if (gym?.estado === 'temporalmente_cerrado') return { label: 'Cerrado temporalmente', tone: 'warning' }
  const schedules = gym?.horarios
  if (!schedules || Array.isArray(schedules)) return { label: 'Consultar horario', tone: 'neutral' }
  try {
    const dia = diaLocal(gym, now)
    const entry = Object.entries(schedules).find(([key]) => aplicaHoy(key, dia))
    if (!entry) return { label: 'Horario no publicado', tone: 'neutral' }
    const hours = String(entry[1])
    if (hours.toLocaleLowerCase('es').includes('cerrado')) return { label: 'Cerrado ahora', tone: 'neutral' }
    const range = hours.match(/(\d{1,2}):(\d{2})\D+(\d{1,2}):(\d{2})/)
    if (!range) return { label: hours, tone: 'neutral' }
    const parts = new Intl.DateTimeFormat('en-US', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: zona(gym) }).formatToParts(now)
    const minutes = Number(parts.find((part) => part.type === 'hour')?.value) * 60 + Number(parts.find((part) => part.type === 'minute')?.value)
    const opens = Number(range[1]) * 60 + Number(range[2])
    const closes = Number(range[3]) * 60 + Number(range[4])
    return minutes >= opens && minutes < closes ? { label: 'Abierto ahora', tone: 'success' } : { label: 'Cerrado ahora', tone: 'neutral' }
  } catch {
    return { label: 'Consultar horario', tone: 'neutral' }
  }
}

/** Filas { clave, dia, horas, cerrado, hoy } para mostrar la semana; marca la que aplica hoy. */
export function filasHorario(gym, now = new Date()) {
  const horarios = gym?.horarios
  if (!horarios || Array.isArray(horarios) || typeof horarios !== 'object') return []
  let dia = ''
  try { dia = diaLocal(gym, now) } catch { /* sin zona válida no se marca el día */ }
  const posicion = (clave) => (ORDEN.includes(clave) ? ORDEN.indexOf(clave) : ORDEN.length)
  return Object.entries(horarios).sort(([a], [b]) => posicion(a) - posicion(b)).map(([clave, horas]) => ({
    clave,
    dia: ETIQUETAS[clave] || clave.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()),
    horas: String(horas),
    cerrado: String(horas).toLocaleLowerCase('es').includes('cerrado'),
    hoy: aplicaHoy(clave, dia),
  }))
}
