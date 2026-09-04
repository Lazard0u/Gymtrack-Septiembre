#!/usr/bin/env sh

# Prueba de integración miembro http. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar la experiencia del socio}"
DEMO_DATASET_NAME="${DEMO_DATASET_NAME:-gymtrack-presentation}"
tmp_dir="$(mktemp -d /tmp/gymtrack-experiencia-miembro.XXXXXX)"

seed_reset() {
  docker compose exec -T -e APP_ENV=demo -e SEED_DEMO_DATA=true \
    -e DEMO_DATASET_NAME="$DEMO_DATASET_NAME" \
    -e DEMO_USER_PASSWORD="$DEMO_USER_PASSWORD" \
    backend php console.php seed:demo --reset >/dev/null
}
cleanup() {
  seed_reset
  rm -rf "$tmp_dir"
}
trap cleanup EXIT HUP INT TERM

assert_status() {
  expected="$1" actual="$2" label="$3" body="$4"
  [ "$expected" = "$actual" ] || {
    echo "$label: se esperaba HTTP $expected y llegó $actual" >&2
    cat "$body" >&2
    exit 1
  }
}

member_request() {
  method="$1" endpoint="$2" output="$3" body="${4:-}"
  if [ -n "$body" ]; then
    curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$output" \
      -w '%{http_code}' -X "$method" -H 'Content-Type: application/json' \
      -H "X-CSRF-Token: $member_csrf" --data "$body" "$API_BASE_URL$endpoint"
  else
    curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$output" \
      -w '%{http_code}' -X "$method" -H "X-CSRF-Token: $member_csrf" "$API_BASE_URL$endpoint"
  fi
}

member_request_with_key() {
  endpoint="$1" output="$2" body="$3" key="$4"
  curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$output" \
    -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
    -H "X-CSRF-Token: $member_csrf" -H "Idempotency-Key: $key" \
    --data "$body" "$API_BASE_URL$endpoint"
}

admin_request() {
  method="$1" endpoint="$2" output="$3" body="${4:-}"
  curl -sS -b "$tmp_dir/admin-cookies" -c "$tmp_dir/admin-cookies" -o "$output" \
    -w '%{http_code}' -X "$method" -H 'Content-Type: application/json' \
    -H "X-CSRF-Token: $admin_csrf" --data "$body" "$API_BASE_URL$endpoint"
}

seed_reset

member_login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" \
  '{email:"socio.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-login.json" \
  -w '%{http_code}' -H 'Content-Type: application/json' --data "$member_login_payload" \
  "$API_BASE_URL/auth/login")" member_login "$tmp_dir/member-login.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-login.json")"
centro_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/member-login.json")"
titan_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="Titan Training") | .gimnasio_id' "$tmp_dir/member-login.json")"
member_context="$(jq -nc --argjson gym "$centro_id" '{gym_id:$gym}')"
assert_status 200 "$(member_request POST '/me/gym-context' "$tmp_dir/member-context.json" "$member_context")" member_context "$tmp_dir/member-context.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-context.json")"

for endpoint in summary profile preferences favorites attendance memberships measurements card; do
  assert_status 200 "$(member_request GET "/member/$endpoint" "$tmp_dir/$endpoint.json")" "$endpoint" "$tmp_dir/$endpoint.json"
  jq -e '.ok == true and .request_id != ""' "$tmp_dir/$endpoint.json" >/dev/null
done

jq -e '.data.monthly_progress.goal == 10 and (.data.promotions | length) >= 1' "$tmp_dir/summary.json" >/dev/null
jq -e '.data.preferred_schedules == ["morning","evening"]' "$tmp_dir/preferences.json" >/dev/null
jq -e '(.data.gyms | length) == 2 and (.data.activities | length) == 1' "$tmp_dir/favorites.json" >/dev/null
jq -e '(.data.items | length) >= 1 and .meta.pagination.page == 1' "$tmp_dir/attendance.json" >/dev/null
jq -e '(.data.items | length) >= 1' "$tmp_dir/memberships.json" >/dev/null
jq -e '(.data.items | length) == 3 and (.data.items[] | select(.bmi != null) | .bmi_notice | length > 20)' "$tmp_dir/measurements.json" >/dev/null
jq -e '.data.token != "" and .data.ttl_seconds >= 60' "$tmp_dir/card.json" >/dev/null

preferences_payload='{"monthly_attendance_goal":10,"show_orientation_bmi":true,"preferred_schedules":["morning","evening"]}'
assert_status 200 "$(member_request PUT '/member/preferences' "$tmp_dir/preferences-updated.json" "$preferences_payload")" preferences_update "$tmp_dir/preferences-updated.json"
jq -e '.data.preferred_schedules == ["morning","evening"]' "$tmp_dir/preferences-updated.json" >/dev/null
assert_status 422 "$(member_request PUT '/member/preferences' "$tmp_dir/preferences-invalid.json" \
  '{"monthly_attendance_goal":10,"show_orientation_bmi":true,"preferred_schedules":["night"]}')" preferences_validation "$tmp_dir/preferences-invalid.json"
jq -e '.codigo == "validation_error" and .fields.preferred_schedules != null' "$tmp_dir/preferences-invalid.json" >/dev/null

profile_payload="$(jq -c '{first_name:.data.first_name,last_name:.data.last_name,phone:.data.phone,birth_date:.data.birth_date}' "$tmp_dir/profile.json")"
assert_status 200 "$(member_request PATCH '/member/profile' "$tmp_dir/profile-updated.json" "$profile_payload")" profile_update "$tmp_dir/profile-updated.json"
assert_status 404 "$(member_request GET '/member/avatar' "$tmp_dir/avatar.json")" avatar_empty_state "$tmp_dir/avatar.json"

measurement_payload='{"measured_at":"2026-08-18","height_cm":173,"weight_kg":77.5,"notes":"Contrato HTTP de idempotencia"}'
measurement_key='00000000-0000-4000-8000-000000000110'
assert_status 201 "$(member_request_with_key '/member/measurements' "$tmp_dir/measurement-created.json" "$measurement_payload" "$measurement_key")" measurement_create "$tmp_dir/measurement-created.json"
jq -e '.data.idempotent == false and .meta.idempotent == false and .data.measurement.bmi != null' "$tmp_dir/measurement-created.json" >/dev/null
assert_status 201 "$(member_request_with_key '/member/measurements' "$tmp_dir/measurement-idempotent.json" "$measurement_payload" "$measurement_key")" measurement_idempotency "$tmp_dir/measurement-idempotent.json"
jq -e '.data.idempotent == true and .meta.idempotent == true' "$tmp_dir/measurement-idempotent.json" >/dev/null

assert_status 200 "$(member_request DELETE "/member/favorites/gym/$titan_id" "$tmp_dir/favorite-removed.json")" favorite_remove "$tmp_dir/favorite-removed.json"
jq -e '.data.favorite == false and .data.removed == true' "$tmp_dir/favorite-removed.json" >/dev/null
favorite_payload="$(jq -nc --argjson gym "$titan_id" '{type:"gym",target_id:$gym}')"
assert_status 201 "$(member_request POST '/member/favorites' "$tmp_dir/favorite-restored.json" "$favorite_payload")" favorite_restore "$tmp_dir/favorite-restored.json"
jq -e '.data.favorite == true' "$tmp_dir/favorite-restored.json" >/dev/null

card_token="$(jq -r '.data.token' "$tmp_dir/card.json")"
admin_login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" \
  '{email:"admin.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/admin-cookies" -o "$tmp_dir/admin-login.json" \
  -w '%{http_code}' -H 'Content-Type: application/json' --data "$admin_login_payload" \
  "$API_BASE_URL/auth/login")" admin_login "$tmp_dir/admin-login.json"
admin_csrf="$(jq -r '.csrf_token' "$tmp_dir/admin-login.json")"

select_centro="$(jq -nc --argjson gym "$centro_id" \
  '{gym_id:$gym,reason:"Verificación automatizada del carné de socio"}')"
assert_status 200 "$(admin_request POST '/admin/context/select' "$tmp_dir/admin-centro.json" "$select_centro")" admin_context "$tmp_dir/admin-centro.json"
admin_csrf="$(jq -r '.data.csrf_token' "$tmp_dir/admin-centro.json")"
verify_payload="$(jq -nc --arg token "$card_token" '{token:$token}')"
assert_status 200 "$(admin_request POST '/admin/member-card/verify' "$tmp_dir/card-verified.json" "$verify_payload")" card_verify "$tmp_dir/card-verified.json"
jq -e '.data.valid == true and .data.member.member_number != null' "$tmp_dir/card-verified.json" >/dev/null

select_titan="$(jq -nc --argjson gym "$titan_id" \
  '{gym_id:$gym,reason:"Prueba automatizada de aislamiento del carné"}')"
assert_status 200 "$(admin_request POST '/admin/context/select' "$tmp_dir/admin-titan.json" "$select_titan")" admin_other_context "$tmp_dir/admin-titan.json"
admin_csrf="$(jq -r '.data.csrf_token' "$tmp_dir/admin-titan.json")"
assert_status 403 "$(admin_request POST '/admin/member-card/verify' "$tmp_dir/card-cross-tenant.json" "$verify_payload")" card_tenant_isolation "$tmp_dir/card-cross-tenant.json"
jq -e '.codigo == "member_card_scope_mismatch"' "$tmp_dir/card-cross-tenant.json" >/dev/null

echo 'Perfil, preferencias, favoritos, asistencia, membresías, mediciones y carné del socio correctos.'
