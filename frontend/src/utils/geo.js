const EARTH_RADIUS_KM = 6371.0088

function validCoordinate(value, min, max) {
  const coordinate = Number(value)
  return Number.isFinite(coordinate) && coordinate >= min && coordinate <= max
}

export function isValidPosition(position) {
  return Boolean(position)
    && validCoordinate(position.latitude, -90, 90)
    && validCoordinate(position.longitude, -180, 180)
}

export function distanceInKm(origin, destination) {
  if (!isValidPosition(origin) || !isValidPosition(destination)) return null
  const toRadians = (degrees) => degrees * (Math.PI / 180)
  const latitudeDelta = toRadians(Number(destination.latitude) - Number(origin.latitude))
  const longitudeDelta = toRadians(Number(destination.longitude) - Number(origin.longitude))
  const originLatitude = toRadians(Number(origin.latitude))
  const destinationLatitude = toRadians(Number(destination.latitude))
  const haversine = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(originLatitude) * Math.cos(destinationLatitude) * Math.sin(longitudeDelta / 2) ** 2
  return 2 * EARTH_RADIUS_KM * Math.asin(Math.sqrt(Math.min(1, haversine)))
}

export function formatDistance(distance) {
  if (!Number.isFinite(distance)) return ''
  if (distance < 1) return `${Math.max(1, Math.round(distance * 1000))} m`
  if (distance < 10) return `${distance.toFixed(1).replace('.', ',')} km`
  return `${Math.round(distance)} km`
}

export function groupByProjectedCell(items, project, cellSize = 72) {
  const cells = new Map()
  items.forEach((item) => {
    const point = project(item)
    const key = `${Math.floor(point.x / cellSize)}:${Math.floor(point.y / cellSize)}`
    const group = cells.get(key) || []
    group.push(item)
    cells.set(key, group)
  })
  return [...cells.values()]
}
