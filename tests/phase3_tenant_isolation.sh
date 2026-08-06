#!/usr/bin/env sh
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar aislamiento por gimnasio}"
tmp_dir="$(mktemp -d /tmp/gymtrack-phase3-tenant.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

assert_status() {
  expected="$1"
  actual="$2"
  label="$3"
  body="$4"
  [ "$expected" = "$actual" ] || {
    echo "$label: se esperaba HTTP $expected y llegó $actual" >&2
    cat "$body" >&2
    exit 1
  }
}

login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"socio.demo@gymtrack.local",password:$password}')"
status="$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")"
assert_status 200 "$status" login "$tmp_dir/login.json"

csrf="$(jq -r '.csrf_token' "$tmp_dir/login.json")"
centro_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/login.json")"
titan_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="Titan Training") | .gimnasio_id' "$tmp_dir/login.json")"

assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/classes-centro.json" -w '%{http_code}' "$API_BASE_URL/clases")" clases_centro "$tmp_dir/classes-centro.json"
class_id="$(jq -r '.clases[0].id // empty' "$tmp_dir/classes-centro.json")"
[ -n "$class_id" ] || { echo 'El gimnasio Centro debe tener al menos una clase demo.' >&2; exit 1; }

switch_payload="$(jq -nc --argjson gym "$titan_id" '{gym_id:$gym}')"
assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$tmp_dir/switch.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data "$switch_payload" "$API_BASE_URL/me/gym-context")" cambio_contexto "$tmp_dir/switch.json"
assert_status "$titan_id" "$(jq -r '.usuario.active_gym_id' "$tmp_dir/switch.json")" contexto_activo "$tmp_dir/switch.json"

assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/classes-titan.json" -w '%{http_code}' "$API_BASE_URL/clases")" clases_titan "$tmp_dir/classes-titan.json"
assert_status 0 "$(jq '.clases | length' "$tmp_dir/classes-titan.json")" clases_aisladas "$tmp_dir/classes-titan.json"
assert_status 404 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/idor.json" -w '%{http_code}' "$API_BASE_URL/clases/$class_id")" idor_clase "$tmp_dir/idor.json"

# El contexto debe venir de la sesión, nunca de un ID enviado por query string.
assert_status 0 "$(curl -sS -b "$tmp_dir/cookies" "$API_BASE_URL/clases?gimnasio_id=$centro_id" | jq '.clases | length')" query_ignorada "$tmp_dir/classes-titan.json"

echo 'Aislamiento por gimnasio y protección contra IDOR correctos.'
