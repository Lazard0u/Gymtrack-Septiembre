#!/usr/bin/env sh

# Prueba de integración promociones notificaciones. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar promociones y notificaciones}"
DEMO_DATASET_NAME="${DEMO_DATASET_NAME:-gymtrack-presentation}"
tmp_dir="$(mktemp -d /tmp/gymtrack-promociones-notificaciones.XXXXXX)"

seed_reset() {
  docker compose exec -T -e APP_ENV=demo -e SEED_DEMO_DATA=true \
    -e DEMO_DATASET_NAME="$DEMO_DATASET_NAME" -e DEMO_USER_PASSWORD="$DEMO_USER_PASSWORD" \
    backend php console.php seed:demo --reset >/dev/null
}

cleanup() {
  seed_reset
  rm -rf "$tmp_dir"
}
trap cleanup EXIT HUP INT TERM

assert_status() {
  expected="$1" actual="$2" label="$3" body="$4"
  if [ "$expected" != "$actual" ]; then
    echo "$label: se esperaba HTTP $expected y llegó $actual" >&2
    cat "$body" >&2
    exit 1
  fi
}

member_request() {
  method="$1" endpoint="$2" output="$3" body="${4:-}"
  if [ -n "$body" ]; then
    curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$output" -w '%{http_code}' \
      -X "$method" -H 'Content-Type: application/json' -H "X-CSRF-Token: $member_csrf" \
      --data "$body" "$API_BASE_URL$endpoint"
  else
    curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$output" -w '%{http_code}' \
      -X "$method" -H "X-CSRF-Token: $member_csrf" "$API_BASE_URL$endpoint"
  fi
}

admin_request() {
  method="$1" endpoint="$2" output="$3" body="${4:-}"
  if [ -n "$body" ]; then
    curl -sS -b "$tmp_dir/admin-cookies" -c "$tmp_dir/admin-cookies" -o "$output" -w '%{http_code}' \
      -X "$method" -H 'Content-Type: application/json' -H "X-CSRF-Token: $admin_csrf" \
      --data "$body" "$API_BASE_URL$endpoint"
  else
    curl -sS -b "$tmp_dir/admin-cookies" -c "$tmp_dir/admin-cookies" -o "$output" -w '%{http_code}' \
      -X "$method" -H "X-CSRF-Token: $admin_csrf" "$API_BASE_URL$endpoint"
  fi
}

seed_reset

member_login="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"socio.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-login.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' --data "$member_login" "$API_BASE_URL/auth/login")" member_login "$tmp_dir/member-login.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-login.json")"

preferences='{"internal_transactional":true,"internal_marketing":true,"email_transactional":true,"email_marketing":true,"whatsapp_marketing":false,"marketing_consent":true}'
assert_status 200 "$(member_request PUT '/me/notification-preferences' "$tmp_dir/preferences.json" "$preferences")" preferences "$tmp_dir/preferences.json"
jq -e '.data.marketing_consent == true and .data.internal_marketing == true and .data.email_marketing == true' "$tmp_dir/preferences.json" >/dev/null

assert_status 422 "$(member_request PUT '/me/notification-preferences' "$tmp_dir/preferences-invalid.json" '{"marketing_consent":"yes"}')" preferences_validation "$tmp_dir/preferences-invalid.json"
jq -e '.codigo == "validation_error" and (.fields | length) >= 1' "$tmp_dir/preferences-invalid.json" >/dev/null

assert_status 200 "$(member_request GET '/bookings/mine' "$tmp_dir/bookings.json")" bookings "$tmp_dir/bookings.json"
booking_id="$(jq -r '.data.items[] | select(.estado=="confirmada") | .id' "$tmp_dir/bookings.json" | head -n 1)"
[ -n "$booking_id" ] || { echo 'Falta una reserva confirmada demo para validar calendario.' >&2; exit 1; }
assert_status 200 "$(member_request GET "/bookings/$booking_id/calendar" "$tmp_dir/calendar.json")" calendar_payload "$tmp_dir/calendar.json"
jq -e '.data.uid != "" and .data.timezone == "America/Montevideo" and (.data.google_calendar_url | startswith("https://calendar.google.com/")) and .data.automatic_sync.enabled == false' "$tmp_dir/calendar.json" >/dev/null
assert_status 200 "$(curl -sS -b "$tmp_dir/member-cookies" -D "$tmp_dir/calendar.headers" -o "$tmp_dir/calendar.ics" -w '%{http_code}' "$API_BASE_URL/bookings/$booking_id/calendar.ics")" calendar_ics "$tmp_dir/calendar.ics"
grep -qi '^Content-Type: text/calendar' "$tmp_dir/calendar.headers"
grep -q '^BEGIN:VCALENDAR' "$tmp_dir/calendar.ics"
grep -q '^UID:gymtrack-booking-' "$tmp_dir/calendar.ics"

assert_status 200 "$(member_request GET '/me/notifications?unread=true' "$tmp_dir/inbox.json")" inbox "$tmp_dir/inbox.json"
jq -e '.data.unread_count >= 1 and (.data.items | length) >= 1' "$tmp_dir/inbox.json" >/dev/null
notification_id="$(jq -r '.data.items[0].id' "$tmp_dir/inbox.json")"
assert_status 419 "$(curl -sS -b "$tmp_dir/member-cookies" -o "$tmp_dir/no-csrf.json" -w '%{http_code}' -X PATCH "$API_BASE_URL/me/notifications/$notification_id/read")" notification_csrf "$tmp_dir/no-csrf.json"
assert_status 200 "$(member_request PATCH "/me/notifications/$notification_id/read" "$tmp_dir/read.json" '{}')" notification_read "$tmp_dir/read.json"
jq -e '.data.leida == true and .data.leida_en != null' "$tmp_dir/read.json" >/dev/null

admin_login="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"admin.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/admin-cookies" -o "$tmp_dir/admin-login.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' --data "$admin_login" "$API_BASE_URL/auth/login")" admin_login "$tmp_dir/admin-login.json"
admin_csrf="$(jq -r '.csrf_token' "$tmp_dir/admin-login.json")"
assert_status 200 "$(admin_request GET '/admin/context' "$tmp_dir/context.json")" admin_context "$tmp_dir/context.json"
centro_id="$(jq -r '.data.gyms[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/context.json")"
titan_id="$(jq -r '.data.gyms[] | select(.nombre=="Titan Training") | .gimnasio_id' "$tmp_dir/context.json")"
select_centro="$(jq -nc --argjson gym "$centro_id" '{gym_id:$gym,reason:"Validación automatizada de promociones y notificaciones"}')"
assert_status 200 "$(admin_request POST '/admin/context/select' "$tmp_dir/selected.json" "$select_centro")" select_context "$tmp_dir/selected.json"
admin_csrf="$(jq -r '.data.csrf_token' "$tmp_dir/selected.json")"

start_at="$(TZ=America/Montevideo date -d '2 minutes ago' '+%Y-%m-%d %H:%M:%S')"
end_at="$(TZ=America/Montevideo date -d '1 day' '+%Y-%m-%d %H:%M:%S')"
internal_payload="$(jq -nc --arg start "$start_at" --arg end "$end_at" --argjson gym "$centro_id" '{nombre:"Promoción contrato interna",descripcion:"Entrega interna real para validar la cola de notificaciones.",audiencia:"socios_activos",inicio_en:$start,fin_en:$end,zona_horaria:"America/Montevideo",tipo_descuento:"sin_descuento",valor_descuento:null,moneda:"UYU",codigo_descuento:"PROMOCION_INTERNA",imagen_archivo_id:null,gimnasio_ids:[$gym],canales:["internal"]}')"
assert_status 419 "$(curl -sS -b "$tmp_dir/admin-cookies" -o "$tmp_dir/admin-no-csrf.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$internal_payload" "$API_BASE_URL/admin/promotions")" promotion_csrf "$tmp_dir/admin-no-csrf.json"
assert_status 201 "$(admin_request POST '/admin/promotions' "$tmp_dir/promotion-created.json" "$internal_payload")" promotion_create "$tmp_dir/promotion-created.json"
promotion_id="$(jq -r '.data.id' "$tmp_dir/promotion-created.json")"
jq -e '.data.estado == "borrador" and .data.version == 1' "$tmp_dir/promotion-created.json" >/dev/null

whatsapp_payload="$(printf '%s' "$internal_payload" | jq '.nombre="Promoción WhatsApp no disponible" | .canales=["whatsapp"]')"
assert_status 409 "$(admin_request POST '/admin/promotions' "$tmp_dir/whatsapp.json" "$whatsapp_payload")" whatsapp_fail_closed "$tmp_dir/whatsapp.json"
jq -e '.codigo == "whatsapp_beta_unavailable"' "$tmp_dir/whatsapp.json" >/dev/null

assert_status 200 "$(admin_request POST "/admin/promotions/$promotion_id/schedule" "$tmp_dir/scheduled.json" '{}')" promotion_schedule "$tmp_dir/scheduled.json"
jq -e '.data.promotion.estado == "activa" and .data.queued == 1' "$tmp_dir/scheduled.json" >/dev/null
dispatch="$(docker compose exec -T backend php console.php notifications:dispatch 100 "$promotion_id")"
printf '%s' "$dispatch" | jq -e '.sent == 1 and .failed == 0' >/dev/null
assert_status 200 "$(admin_request GET "/admin/promotions/$promotion_id/results" "$tmp_dir/results.json")" promotion_results "$tmp_dir/results.json"
jq -e '.data.totals.recipients == 1 and .data.totals.sent == 1 and .data.totals.failed == 0' "$tmp_dir/results.json" >/dev/null
assert_status 200 "$(admin_request POST "/admin/promotions/$promotion_id/pause" "$tmp_dir/paused.json" '{}')" promotion_pause "$tmp_dir/paused.json"
jq -e '.data.estado == "pausada"' "$tmp_dir/paused.json" >/dev/null
assert_status 200 "$(admin_request POST "/admin/promotions/$promotion_id/schedule" "$tmp_dir/rescheduled.json" '{}')" promotion_reschedule "$tmp_dir/rescheduled.json"
jq -e '.data.promotion.estado == "activa" and .data.queued == 0' "$tmp_dir/rescheduled.json" >/dev/null
assert_status 200 "$(admin_request POST "/admin/promotions/$promotion_id/finish" "$tmp_dir/finished.json" '{}')" promotion_finish "$tmp_dir/finished.json"
jq -e '.data.estado == "finalizada"' "$tmp_dir/finished.json" >/dev/null

versioned_payload="$(printf '%s' "$internal_payload" | jq '.nombre="Promoción contrato versionada" | .codigo_descuento="PROMOCION_VERSIONADA"')"
assert_status 201 "$(admin_request POST '/admin/promotions' "$tmp_dir/versioned-created.json" "$versioned_payload")" versioned_create "$tmp_dir/versioned-created.json"
versioned_id="$(jq -r '.data.id' "$tmp_dir/versioned-created.json")"
assert_status 200 "$(admin_request POST "/admin/promotions/$versioned_id/schedule" "$tmp_dir/versioned-scheduled.json" '{}')" versioned_schedule "$tmp_dir/versioned-scheduled.json"
assert_status 200 "$(admin_request POST "/admin/promotions/$versioned_id/pause" "$tmp_dir/versioned-paused.json" '{}')" versioned_pause "$tmp_dir/versioned-paused.json"
versioned_update="$(printf '%s' "$versioned_payload" | jq '.nombre="Promoción contrato versionada editada"')"
assert_status 200 "$(admin_request PATCH "/admin/promotions/$versioned_id" "$tmp_dir/versioned-updated.json" "$versioned_update")" versioned_update "$tmp_dir/versioned-updated.json"
jq -e '.data.estado == "pausada" and .data.version == 2' "$tmp_dir/versioned-updated.json" >/dev/null
assert_status 200 "$(admin_request POST "/admin/promotions/$versioned_id/schedule" "$tmp_dir/versioned-rescheduled.json" '{}')" versioned_reschedule "$tmp_dir/versioned-rescheduled.json"
jq -e '.data.queued == 1' "$tmp_dir/versioned-rescheduled.json" >/dev/null
versioned_dispatch="$(docker compose exec -T backend php console.php notifications:dispatch 100 "$versioned_id")"
printf '%s' "$versioned_dispatch" | jq -e '.sent == 1 and .failed == 0' >/dev/null
assert_status 200 "$(admin_request GET "/admin/promotions/$versioned_id/results" "$tmp_dir/versioned-results.json")" versioned_results "$tmp_dir/versioned-results.json"
jq -e '.data.totals.total == 2 and .data.totals.sent == 1 and .data.totals.skipped == 1' "$tmp_dir/versioned-results.json" >/dev/null
assert_status 200 "$(admin_request POST "/admin/promotions/$versioned_id/finish" "$tmp_dir/versioned-finished.json" '{}')" versioned_finish "$tmp_dir/versioned-finished.json"

email_payload="$(printf '%s' "$internal_payload" | jq '.nombre="Promoción contrato correo" | .codigo_descuento="NOTIFICACION_EMAIL" | .canales=["email"]')"
assert_status 201 "$(admin_request POST '/admin/promotions' "$tmp_dir/email-created.json" "$email_payload")" email_promotion_create "$tmp_dir/email-created.json"
email_promotion_id="$(jq -r '.data.id' "$tmp_dir/email-created.json")"
assert_status 200 "$(admin_request POST "/admin/promotions/$email_promotion_id/schedule" "$tmp_dir/email-scheduled.json" '{}')" email_promotion_schedule "$tmp_dir/email-scheduled.json"
email_dispatch="$(docker compose exec -T backend php console.php notifications:dispatch 100 "$email_promotion_id")"
printf '%s' "$email_dispatch" | jq -e '.sent == 1 and .failed == 0' >/dev/null
mail_body="$(docker compose exec -T backend php console.php auth:mail:latest socio.demo@gymtrack.local)"
unsubscribe_token="$(printf '%s\n' "$mail_body" | sed -n 's/.*notificaciones\/unsubscribe?token=//p' | tail -n 1)"
[ "${#unsubscribe_token}" -eq 64 ] || { echo 'No se generó un token de baja publicitaria válido.' >&2; exit 1; }
unsubscribe_payload="$(jq -nc --arg token "$unsubscribe_token" '{token:$token}')"
assert_status 200 "$(curl -sS -o "$tmp_dir/unsubscribed.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$unsubscribe_payload" "$API_BASE_URL/notifications/unsubscribe")" unsubscribe "$tmp_dir/unsubscribed.json"
jq -e '.data.unsubscribed == true' "$tmp_dir/unsubscribed.json" >/dev/null
assert_status 400 "$(curl -sS -o "$tmp_dir/unsubscribe-reused.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$unsubscribe_payload" "$API_BASE_URL/notifications/unsubscribe")" unsubscribe_single_use "$tmp_dir/unsubscribe-reused.json"
assert_status 200 "$(member_request GET '/me/notification-preferences' "$tmp_dir/preferences-after.json")" preferences_after_unsubscribe "$tmp_dir/preferences-after.json"
jq -e '.data.marketing_consent == false and .data.internal_marketing == false and .data.email_marketing == false' "$tmp_dir/preferences-after.json" >/dev/null

select_titan="$(jq -nc --argjson gym "$titan_id" '{gym_id:$gym,reason:"Validar aislamiento entre gimnasios"}')"
assert_status 200 "$(admin_request POST '/admin/context/select' "$tmp_dir/titan.json" "$select_titan")" switch_tenant "$tmp_dir/titan.json"
admin_csrf="$(jq -r '.data.csrf_token' "$tmp_dir/titan.json")"
assert_status 404 "$(admin_request GET "/admin/promotions/$promotion_id" "$tmp_dir/cross-tenant.json")" promotion_tenant_isolation "$tmp_dir/cross-tenant.json"
jq -e '.codigo == "promotion_not_found"' "$tmp_dir/cross-tenant.json" >/dev/null

echo 'Calendario, promociones, consentimiento, correo, baja, cola e aislamiento correctos.'
