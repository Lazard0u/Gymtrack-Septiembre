#!/usr/bin/env sh

# Prueba de integración admin api. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar Administración}"
tmp_dir="$(mktemp -d /tmp/gymtrack-admin.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

assert_status() {
  expected="$1" actual="$2" label="$3" body="$4"
  [ "$expected" = "$actual" ] || { echo "$label: se esperaba HTTP $expected y llegó $actual" >&2; cat "$body" >&2; exit 1; }
}

login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"admin.demo@gymtrack.local",password:$password}')"
status="$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")"
assert_status 200 "$status" login "$tmp_dir/login.json"
csrf="$(jq -r '.csrf_token' "$tmp_dir/login.json")"

assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/context.json" -w '%{http_code}' "$API_BASE_URL/admin/context")" context "$tmp_dir/context.json"
jq -e '.ok == true and (.data.gyms | length) == 5 and .data.active_gym_id == null' "$tmp_dir/context.json" >/dev/null
gym_id="$(jq -r '.data.gyms[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/context.json")"

select_payload="$(jq -nc --argjson gym "$gym_id" '{gym_id:$gym,reason:""}')"
assert_status 422 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/no-reason.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data "$select_payload" "$API_BASE_URL/admin/context/select")" support_reason "$tmp_dir/no-reason.json"
jq -e '.codigo == "support_reason_required" and .request_id != ""' "$tmp_dir/no-reason.json" >/dev/null

select_payload="$(jq -nc --argjson gym "$gym_id" '{gym_id:$gym,reason:"Prueba automatizada de Administración"}')"
assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$tmp_dir/selected.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data "$select_payload" "$API_BASE_URL/admin/context/select")" select_context "$tmp_dir/selected.json"

for endpoint in permissions summary activity members staff classes reservations memberships payments exports; do
  assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/$endpoint.json" -w '%{http_code}' "$API_BASE_URL/admin/$endpoint?page=1&per_page=10")" "$endpoint" "$tmp_dir/$endpoint.json"
  jq -e '.ok == true and .request_id != ""' "$tmp_dir/$endpoint.json" >/dev/null
done

for endpoint in members classes reservations memberships payments; do
  jq -e '.meta.pagination.page == 1 and .meta.pagination.per_page == 10' "$tmp_dir/$endpoint.json" >/dev/null
done

cross_payload='{"gym_id":999999,"reason":"Prueba fuera de alcance"}'
new_csrf="$(jq -r '.data.csrf_token' "$tmp_dir/selected.json")"
assert_status 403 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/forbidden.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $new_csrf" --data "$cross_payload" "$API_BASE_URL/admin/context/select")" isolation "$tmp_dir/forbidden.json"

echo 'API administrativa, contexto, paginación, request ID y aislamiento correctos.'
