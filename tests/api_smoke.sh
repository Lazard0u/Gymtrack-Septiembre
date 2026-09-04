#!/usr/bin/env sh

# Prueba de integración api smoke. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
TEST_EMAIL="${TEST_EMAIL:?Definí TEST_EMAIL}"
TEST_PASSWORD="${TEST_PASSWORD:?Definí TEST_PASSWORD}"
tmp_dir="$(mktemp -d /tmp/gymtrack-api-smoke.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

status="$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg email "$TEST_EMAIL" --arg password "$TEST_PASSWORD" '{email:$email,password:$password}')" "$API_BASE_URL/auth/login")"
[ "$status" = 200 ] || { cat "$tmp_dir/login.json" >&2; exit 1; }
[ "$(jq -r 'has("token")' "$tmp_dir/login.json")" = false ] || { echo 'El login expuso un token.' >&2; exit 1; }
csrf="$(jq -r '.csrf_token' "$tmp_dir/login.json")"
[ "${#csrf}" -gt 20 ] || { echo 'El login no entregó CSRF.' >&2; exit 1; }

for path in /me /perfil /clases /membresia/mia; do
  status="$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/response.json" -w '%{http_code}' "$API_BASE_URL$path")"
  [ "$status" = 200 ] || { echo "$path devolvió $status" >&2; cat "$tmp_dir/response.json" >&2; exit 1; }
done

status="$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/admin.json" -w '%{http_code}' "$API_BASE_URL/admin/socios")"
[ "$status" = 403 ] || { echo "admin debía devolver 403 y devolvió $status" >&2; exit 1; }
status="$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/csrf.json" -w '%{http_code}' -H 'Content-Type: application/json' --data '{}' "$API_BASE_URL/auth/logout")"
[ "$status" = 419 ] || { echo "CSRF debía devolver 419 y devolvió $status" >&2; exit 1; }
status="$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/logout.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data '{}' "$API_BASE_URL/auth/logout")"
[ "$status" = 200 ] || exit 1
status="$(curl -sS -b "$tmp_dir/cookies" -o /dev/null -w '%{http_code}' "$API_BASE_URL/me")"
[ "$status" = 401 ] || { echo "La sesión revocada devolvió $status" >&2; exit 1; }
echo 'Smoke API con cookie HttpOnly y CSRF correcto.'
