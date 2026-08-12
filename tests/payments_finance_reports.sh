#!/usr/bin/env sh
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar pagos y finanzas}"
DEMO_DATASET_NAME="${DEMO_DATASET_NAME:-gymtrack-presentation}"
tmp_dir="$(mktemp -d /tmp/gymtrack-payments.XXXXXX)"

seed_reset(){ docker compose exec -T -e APP_ENV=demo -e SEED_DEMO_DATA=true -e DEMO_DATASET_NAME="$DEMO_DATASET_NAME" -e DEMO_USER_PASSWORD="$DEMO_USER_PASSWORD" backend php console.php seed:demo --reset >/dev/null; }
cleanup(){ seed_reset; rm -rf "$tmp_dir"; }
trap cleanup EXIT HUP INT TERM
assert_status(){ expected="$1" actual="$2" label="$3" body="$4"; [ "$expected" = "$actual" ] || { echo "$label: se esperaba HTTP $expected y llegó $actual" >&2; cat "$body" >&2; exit 1; }; }
request(){ method="$1" endpoint="$2" output="$3" body="${4:-}" key="${5:-}"; headers=""; [ -n "$key" ] && headers="-H Idempotency-Key:$key"; if [ -n "$body" ]; then curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$output" -w '%{http_code}' -X "$method" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" ${headers:+$headers} --data "$body" "$API_BASE_URL$endpoint"; else curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$output" -w '%{http_code}' -X "$method" -H "X-CSRF-Token: $csrf" "$API_BASE_URL$endpoint"; fi; }

seed_reset
login="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"dueno.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login" "$API_BASE_URL/auth/login")" login "$tmp_dir/login.json"
csrf="$(jq -r '.csrf_token' "$tmp_dir/login.json")"
gym_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/login.json")"
other_gym="$(jq -r '.usuario.gimnasios[] | select(.nombre=="Arena Functional Gym") | .gimnasio_id' "$tmp_dir/login.json")"
payload="$(jq -nc --argjson gym "$gym_id" '{gym_id:$gym}')"
assert_status 200 "$(request POST '/admin/context/select' "$tmp_dir/context.json" "$payload")" context "$tmp_dir/context.json"
csrf="$(jq -r '.data.csrf_token' "$tmp_dir/context.json")"

assert_status 200 "$(request GET '/admin/payments?page=1&per_page=20' "$tmp_dir/payments.json")" payments "$tmp_dir/payments.json"
jq -e '.meta.pagination.total == 10 and ([.data.items[].estado] | index("pendiente") != null) and ([.data.items[].estado] | index("reembolsado") != null)' "$tmp_dir/payments.json" >/dev/null
assert_status 200 "$(request GET '/admin/finance' "$tmp_dir/finance-before.json")" finance "$tmp_dir/finance-before.json"
jq -e '.data.months | length == 6' "$tmp_dir/finance-before.json" >/dev/null
jq -e '.data.current_month > 0 and .data.statuses.aprobado.count == 6 and .data.debt_total > 0' "$tmp_dir/finance-before.json" >/dev/null

assert_status 200 "$(request GET '/admin/payments/options' "$tmp_dir/options.json")" options "$tmp_dir/options.json"
membership_id="$(jq -r '.data.memberships[] | select(.plan=="Plan Centro Base") | .id' "$tmp_dir/options.json" | head -n1)"
manual_payload="$(jq -nc --argjson membership "$membership_id" --arg date "$(date +%F)" '{membership_id:$membership,amount:777,currency:"UYU",method:"efectivo",paid_at:$date,concept:"Pago de contrato automatizado",notes:"Prueba de idempotencia"}')"
manual_key='77777777-7777-4777-8777-777777777777'
assert_status 201 "$(request POST '/admin/payments/manual' "$tmp_dir/manual.json" "$manual_payload" "$manual_key")" manual "$tmp_dir/manual.json"
payment_id="$(jq -r '.data.id' "$tmp_dir/manual.json")"
jq -e '.data.estado == "aprobado" and .data.monto == "777.00"' "$tmp_dir/manual.json" >/dev/null
assert_status 201 "$(request POST '/admin/payments/manual' "$tmp_dir/manual-repeat.json" "$manual_payload" "$manual_key")" manual_idempotent "$tmp_dir/manual-repeat.json"
jq -e --argjson id "$payment_id" '.data.id == $id' "$tmp_dir/manual-repeat.json" >/dev/null

refund_key='88888888-8888-4888-8888-888888888888'
assert_status 200 "$(request POST "/admin/payments/$payment_id/refund" "$tmp_dir/refund.json" '{"reason":"Reembolso completo de prueba"}' "$refund_key")" refund "$tmp_dir/refund.json"
jq -e '.data.estado == "reembolsado"' "$tmp_dir/refund.json" >/dev/null
assert_status 200 "$(request POST "/admin/payments/$payment_id/refund" "$tmp_dir/refund-repeat.json" '{"reason":"Reembolso completo de prueba"}' "$refund_key")" refund_idempotent "$tmp_dir/refund-repeat.json"

for type in xlsx pdf; do
  export_payload="$(jq -nc --arg type "$type" '{type:$type,module:"payments",filters:{status:"aprobado"}}')"
  assert_status 201 "$(request POST '/admin/exports' "$tmp_dir/export-$type.json" "$export_payload")" "export_$type" "$tmp_dir/export-$type.json"
  export_id="$(jq -r '.data.id' "$tmp_dir/export-$type.json")"
  assert_status 200 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/report.$type" -w '%{http_code}' "$API_BASE_URL/admin/exports/$export_id/download")" "download_$type" "$tmp_dir/report.$type"
done
[ "$(od -An -tx1 -N4 "$tmp_dir/report.xlsx" | tr -d ' \n')" = '504b0304' ] || { echo 'El Excel no es un XLSX válido.' >&2; exit 1; }
[ "$(od -An -tx1 -N4 "$tmp_dir/report.pdf" | tr -d ' \n')" = '25504446' ] || { echo 'El PDF no es válido.' >&2; exit 1; }

other_payload="$(jq -nc --argjson gym "$other_gym" '{gym_id:$gym}')"
assert_status 200 "$(request POST '/admin/context/select' "$tmp_dir/other.json" "$other_payload")" other_context "$tmp_dir/other.json"
csrf="$(jq -r '.data.csrf_token' "$tmp_dir/other.json")"
assert_status 404 "$(request POST "/admin/payments/$payment_id/refund" "$tmp_dir/cross.json" '{"reason":"Intento fuera del gimnasio"}' '99999999-9999-4999-8999-999999999999')" cross_tenant "$tmp_dir/cross.json"

member_login="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"socio.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$member_login" "$API_BASE_URL/auth/login")" member_login "$tmp_dir/member-login.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-login.json")"
assert_status 200 "$(curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-context.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $member_csrf" --data "$payload" "$API_BASE_URL/me/gym-context")" member_context "$tmp_dir/member-context.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-context.json")"
assert_status 200 "$(curl -sS -b "$tmp_dir/member-cookies" -o "$tmp_dir/member-payments.json" -w '%{http_code}' "$API_BASE_URL/payments/mine")" member_payments "$tmp_dir/member-payments.json"
plan_id="$(jq -r '.data.plans[0].id' "$tmp_dir/member-payments.json")"
assert_status 503 "$(curl -sS -b "$tmp_dir/member-cookies" -o "$tmp_dir/checkout.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $member_csrf" -H 'Idempotency-Key: aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' --data "{\"plan_id\":$plan_id}" "$API_BASE_URL/payments/checkout")" checkout_unconfigured "$tmp_dir/checkout.json"
jq -e '.codigo == "payment_provider_unavailable"' "$tmp_dir/checkout.json" >/dev/null

assert_status 401 "$(curl -sS -o "$tmp_dir/webhook.json" -w '%{http_code}' -H 'Content-Type: application/json' -H 'X-Signature: ts=1,v1=invalid' -H 'X-Request-ID: 12345678-1234-4234-8234-123456789012' --data '{"id":"event-test","type":"payment","data":{"id":"123"}}' "$API_BASE_URL/webhooks/mercado-pago")" webhook_signature "$tmp_dir/webhook.json"

echo 'Pagos, idempotencia, finanzas, exportaciones, aislamiento y webhook correctos.'
