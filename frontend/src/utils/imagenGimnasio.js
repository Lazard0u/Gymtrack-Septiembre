/** Banner de un gimnasio: su imagen propia o, si no tiene (o no carga), uno de relleno que es siempre el mismo para ese gimnasio. */

export const CANTIDAD_BANNERS = 8

export function imagenGimnasio(gym, fallida = false) {
  if (gym?.imagen_path && !fallida) return gym.imagen_path
  const clave = String(gym?.slug || gym?.id || gym?.nombre || '')
  let hash = 0
  for (const letra of clave) hash = (hash * 31 + letra.charCodeAt(0)) >>> 0
  return `/placeholders/banner-${(hash % CANTIDAD_BANNERS) + 1}.svg`
}
