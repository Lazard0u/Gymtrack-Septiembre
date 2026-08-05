#!/usr/bin/env sh

set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
TEST_EMAIL="${TEST_EMAIL:-}"
TEST_PASSWORD="${TEST_PASSWORD:-}"

if [ -z "$TEST_EMAIL" ] || [ -z "$TEST_PASSWORD" ]; then
  echo "Definí TEST_EMAIL y TEST_PASSWORD para ejecutar el smoke test." >&2
  exit 2
fi

tmp_dir="$(mktemp -d /tmp/gymtrack-api-smoke.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

request_status() {
  method="$1"
  path="$2"
  token="${3:-}"
  body="${4:-}"
  output="$5"

  if [ -n "$token" ]; then
    curl -sS -o "$output" -w '%{http_code}' -X "$method" \
      -H 'Content-Type: application/json' \
      -H "Authorization: Bearer $token" \
      ${body:+--data "$body"} \
      "$API_BASE_URL$path"
  else
    curl -sS -o "$output" -w '%{http_code}' -X "$method" \
      -H 'Content-Type: application/json' \
      ${body:+--data "$body"} \
      "$API_BASE_URL$path"
  fi
}

assert_status() {
  expected="$1"
  actual="$2"
  label="$3"
  response_file="$4"
  if [ "$expected" != "$actual" ]; then
    echo "$label: se esperaba HTTP $expected y llegó $actual" >&2
    sed -n '1,20p' "$response_file" >&2
    exit 1
  fi
}

login_body="$(jq -nc --arg email "$TEST_EMAIL" --arg password "$TEST_PASSWORD" '{email:$email,password:$password}')"
login_status="$(request_status POST /auth/login '' "$login_body" "$tmp_dir/login.json")"
assert_status 200 "$login_status" login "$tmp_dir/login.json"

token="$(jq -r '.token // empty' "$tmp_dir/login.json")"
if [ -z "$token" ]; then
  echo "El login no devolvió un token." >&2
  exit 1
fi

perfil_status="$(request_status GET /perfil "$token" '' "$tmp_dir/perfil.json")"
assert_status 200 "$perfil_status" perfil "$tmp_dir/perfil.json"

clases_status="$(request_status GET /clases "$token" '' "$tmp_dir/clases.json")"
assert_status 200 "$clases_status" clases "$tmp_dir/clases.json"

membresia_status="$(request_status GET /membresia/mia "$token" '' "$tmp_dir/membresia.json")"
assert_status 200 "$membresia_status" membresia "$tmp_dir/membresia.json"

admin_status="$(request_status GET /admin/socios "$token" '' "$tmp_dir/admin.json")"
assert_status 403 "$admin_status" autorizacion_por_rol "$tmp_dir/admin.json"

logout_status="$(request_status POST /auth/logout "$token" '{}' "$tmp_dir/logout.json")"
assert_status 200 "$logout_status" logout "$tmp_dir/logout.json"

expirada_status="$(request_status GET /perfil "$token" '' "$tmp_dir/expirada.json")"
assert_status 401 "$expirada_status" revocacion "$tmp_dir/expirada.json"

echo "Smoke API de Fase 1 correcto."
