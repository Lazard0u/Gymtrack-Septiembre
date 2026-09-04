#!/usr/bin/env sh

# Prueba de integración map geolocation. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
tmp_dir="$(mktemp -d /tmp/gymtrack-map.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM

status="$(curl -sS -o "$tmp_dir/gyms.json" -w '%{http_code}' "$API_BASE_URL/public/gimnasios")"
[ "$status" = 200 ] || { echo "Catálogo público devolvió HTTP $status" >&2; cat "$tmp_dir/gyms.json" >&2; exit 1; }

jq -e '
  .error == false
  and (.gimnasios | length == 5)
  and ([.gimnasios[].id] | unique | length == 5)
  and ([.gimnasios[] | select((.latitud | type) == "number" and (.longitud | type) == "number")] | length == 5)
  and ([.gimnasios[] | select((.categorias | type) == "array" and (.servicios | type) == "array")] | length == 5)
  and ([.gimnasios[].estado] | index("temporalmente_cerrado") != null)
  and (.meta.filtros.ciudades | index("Montevideo") != null)
  and (.meta.filtros.categorias | index("Funcional") != null)
' "$tmp_dir/gyms.json" >/dev/null

echo 'Catálogo geográfico, coordenadas, filtros y escenarios demo correctos.'
