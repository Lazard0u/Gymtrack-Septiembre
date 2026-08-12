#!/usr/bin/env sh
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar clases y reservas}"
INVITED_USER_PASSWORD="${SCHEDULE_INVITED_USER_PASSWORD:-$DEMO_USER_PASSWORD}"
DEMO_DATASET_NAME="${DEMO_DATASET_NAME:-gymtrack-presentation}"
tmp_dir="$(mktemp -d /tmp/gymtrack-schedule.XXXXXX)"

seed_reset() {
  docker compose exec -T -e APP_ENV=demo -e SEED_DEMO_DATA=true -e DEMO_DATASET_NAME="$DEMO_DATASET_NAME" -e DEMO_USER_PASSWORD="$DEMO_USER_PASSWORD" backend php console.php seed:demo --reset >/dev/null
}
cleanup() { seed_reset; rm -rf "$tmp_dir"; }
trap cleanup EXIT HUP INT TERM

assert_status() {
  expected="$1" actual="$2" label="$3" body="$4"
  [ "$expected" = "$actual" ] || { echo "$label: se esperaba HTTP $expected y llegó $actual" >&2; cat "$body" >&2; exit 1; }
}

request() {
  method="$1" endpoint="$2" output="$3" body="${4:-}"
  if [ -n "$body" ]; then
    curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$output" -w '%{http_code}' -X "$method" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data "$body" "$API_BASE_URL$endpoint"
  else
    curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$output" -w '%{http_code}' -X "$method" -H "X-CSRF-Token: $csrf" "$API_BASE_URL$endpoint"
  fi
}

request_with_key() {
  endpoint="$1" output="$2" body="$3" key="$4"
  curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$output" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" -H "Idempotency-Key: $key" --data "$body" "$API_BASE_URL$endpoint"
}

seed_reset
login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"dueno.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")" login "$tmp_dir/login.json"
csrf="$(jq -r '.csrf_token' "$tmp_dir/login.json")"
gym_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/login.json")"
other_gym_id="$(jq -r '.usuario.gimnasios[] | select(.nombre=="Arena Functional Gym") | .gimnasio_id' "$tmp_dir/login.json")"
select_payload="$(jq -nc --argjson gym "$gym_id" '{gym_id:$gym}')"
assert_status 200 "$(request POST '/admin/context/select' "$tmp_dir/selected.json" "$select_payload")" select_context "$tmp_dir/selected.json"
csrf="$(jq -r '.data.csrf_token' "$tmp_dir/selected.json")"

assert_status 200 "$(request GET '/admin/schedule/options' "$tmp_dir/options.json")" schedule_options "$tmp_dir/options.json"
assert_status 403 "$(request GET '/class-sessions' "$tmp_dir/admin-member-endpoint.json")" member_endpoint_role "$tmp_dir/admin-member-endpoint.json"
trainer_id="$(jq -r '.data.trainers[0].id' "$tmp_dir/options.json")"
location_id="$(jq -r '.data.locations[0].id' "$tmp_dir/options.json")"
member_id="$(jq -r '.data.members[0].id' "$tmp_dir/options.json")"
[ "$trainer_id" -gt 0 ] && [ "$location_id" -gt 0 ] && [ "$member_id" -gt 0 ]

first_date="$(date -d '+20 days' +%F)"
class_payload="$(jq -nc --arg date "$first_date" --argjson trainer "$trainer_id" --argjson location "$location_id" '{nombre:"Clase de cupo controlado",descripcion:"Contrato de reserva y espera",instructor_id:$trainer,sede_id:$location,first_date:$date,hora_inicio:"14:00",hora_fin:"15:00",cupo_maximo:1,repeat_weeks:1,cancelacion_minutos:120,color:"#0D7A56",notas:"Prueba automatizada"}')"
assert_status 201 "$(request POST '/admin/class-definitions' "$tmp_dir/class.json" "$class_payload")" create_class "$tmp_dir/class.json"
session_id="$(jq -r '.data.session_ids[0]' "$tmp_dir/class.json")"
jq -e '.data.sessions_created == 1' "$tmp_dir/class.json" >/dev/null

booking_payload="$(jq -nc --argjson member "$member_id" '{usuario_id:$member}')"
booking_key='11111111-1111-4111-8111-111111111111'
assert_status 201 "$(request_with_key "/admin/class-sessions/$session_id/bookings" "$tmp_dir/first-booking.json" "$booking_payload" "$booking_key")" first_booking "$tmp_dir/first-booking.json"
first_booking_id="$(jq -r '.data.booking.id' "$tmp_dir/first-booking.json")"
jq -e '.data.booking.estado == "confirmada" and .data.idempotent == false' "$tmp_dir/first-booking.json" >/dev/null
assert_status 201 "$(request_with_key "/admin/class-sessions/$session_id/bookings" "$tmp_dir/idempotent.json" "$booking_payload" "$booking_key")" idempotent_booking "$tmp_dir/idempotent.json"
jq -e --argjson booking "$first_booking_id" '.data.booking.id == $booking and .data.idempotent == true' "$tmp_dir/idempotent.json" >/dev/null

invited_email='agenda.socio@example.test'
invitation_payload="$(jq -nc --arg email "$invited_email" '{email:$email,tipo:"socio",permissions:[]}')"
assert_status 201 "$(request POST '/admin/invitations' "$tmp_dir/invitation.json" "$invitation_payload")" invite_member "$tmp_dir/invitation.json"
mail_body="$(docker compose exec -T backend php console.php auth:mail:latest "$invited_email")"
invitation_token="$(printf '%s\n' "$mail_body" | sed -n 's#.*\/invitacion/##p' | tail -n 1)"
[ -n "$invitation_token" ] || { echo 'No se encontró el token de invitación.' >&2; exit 1; }
acceptance_payload="$(jq -nc --arg token "$invitation_token" --arg password "$INVITED_USER_PASSWORD" '{token:$token,nombre:"Agenda",apellido:"Socio",telefono:"+598 0000 0888",password:$password,terminos:true,privacidad:true}')"
assert_status 201 "$(curl -sS -o "$tmp_dir/accepted.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$acceptance_payload" "$API_BASE_URL/invitations/accept")" accept_member "$tmp_dir/accepted.json"
second_member_id="$(jq -r '.data.user_id' "$tmp_dir/accepted.json")"

assert_status 200 "$(request GET '/admin/membership-plans?page=1&per_page=20' "$tmp_dir/plans.json")" membership_plans "$tmp_dir/plans.json"
plan_id="$(jq -r '.data.items[0].id' "$tmp_dir/plans.json")"
membership_payload="$(jq -nc --argjson user "$second_member_id" --argjson plan "$plan_id" --arg start "$(date +%F)" '{usuario_id:$user,plan_id:$plan,fecha_inicio:$start,motivo:"Habilitar reserva de contrato"}')"
assert_status 201 "$(request POST '/admin/memberships' "$tmp_dir/membership.json" "$membership_payload")" create_membership "$tmp_dir/membership.json"

second_booking_payload="$(jq -nc --argjson member "$second_member_id" '{usuario_id:$member}')"
assert_status 201 "$(request_with_key "/admin/class-sessions/$session_id/bookings" "$tmp_dir/waiting.json" "$second_booking_payload" '22222222-2222-4222-8222-222222222222')" waiting_booking "$tmp_dir/waiting.json"
second_booking_id="$(jq -r '.data.booking.id' "$tmp_dir/waiting.json")"
jq -e '.data.booking.estado == "lista_espera" and .data.booking.posicion_espera == 1' "$tmp_dir/waiting.json" >/dev/null

assert_status 200 "$(request DELETE "/admin/bookings/$first_booking_id" "$tmp_dir/cancelled.json" '{"motivo":"Liberar cupo para validar promoción"}')" cancel_booking "$tmp_dir/cancelled.json"
jq -e --argjson promoted "$second_booking_id" '.data.promoted.booking_id == $promoted' "$tmp_dir/cancelled.json" >/dev/null
assert_status 200 "$(request GET "/admin/class-sessions/$session_id/roster" "$tmp_dir/roster.json")" roster "$tmp_dir/roster.json"
jq -e --argjson booking "$second_booking_id" '.data.session.cupos_reservados == 1 and .data.session.espera_total == 0 and .data.session.asistencia_habilitada == false and (.data.items[] | select(.id == $booking) | .estado == "confirmada")' "$tmp_dir/roster.json" >/dev/null

assert_status 409 "$(request PUT "/admin/bookings/$second_booking_id/attendance" "$tmp_dir/attendance-early.json" '{"estado":"presente","notas":"Intento anticipado"}')" attendance_not_open "$tmp_dir/attendance-early.json"
jq -e '.codigo == "attendance_not_open"' "$tmp_dir/attendance-early.json" >/dev/null
past_start="$(date -d '-2 hours' '+%F %T')"
past_end="$(date -d '-1 hour' '+%F %T')"
docker compose exec -T db mysql -uroot -p"${MYSQL_ROOT_PASSWORD:-root}" "${DB_DATABASE:-gymtrack}" -e "UPDATE sesiones_clase SET inicio_en='$past_start', fin_en='$past_end' WHERE id=$session_id" >/dev/null
assert_status 200 "$(request PUT "/admin/bookings/$second_booking_id/attendance" "$tmp_dir/attendance.json" '{"estado":"tarde","notas":"Ingreso validado"}')" attendance "$tmp_dir/attendance.json"
jq -e '.data.estado == "asistio"' "$tmp_dir/attendance.json" >/dev/null
assert_status 200 "$(request PUT "/admin/bookings/$second_booking_id/attendance" "$tmp_dir/attendance-justified.json" '{"estado":"justificada","notas":"Ausencia documentada"}')" attendance_update "$tmp_dir/attendance-justified.json"
jq -e '.data.estado == "no_asistio"' "$tmp_dir/attendance-justified.json" >/dev/null

member_login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"socio.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$member_login_payload" "$API_BASE_URL/auth/login")" member_login "$tmp_dir/member-login.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-login.json")"
member_context_payload="$(jq -nc --argjson gym "$gym_id" '{gym_id:$gym}')"
assert_status 200 "$(curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-context.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $member_csrf" --data "$member_context_payload" "$API_BASE_URL/me/gym-context")" member_context "$tmp_dir/member-context.json"
member_csrf="$(jq -r '.csrf_token' "$tmp_dir/member-context.json")"
assert_status 200 "$(curl -sS -b "$tmp_dir/member-cookies" -o "$tmp_dir/member-sessions.json" -w '%{http_code}' "$API_BASE_URL/class-sessions?per_page=30")" member_sessions "$tmp_dir/member-sessions.json"
member_session_id="$(jq -r '.data.items[] | select(.reserva_id == null and .cupos_disponibles > 0) | .id' "$tmp_dir/member-sessions.json" | head -n 1)"
[ -n "$member_session_id" ] || { echo 'No se encontró una sesión libre para el flujo del socio.' >&2; exit 1; }
assert_status 201 "$(curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-booking.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $member_csrf" -H 'Idempotency-Key: 33333333-3333-4333-8333-333333333333' --data '{}' "$API_BASE_URL/class-sessions/$member_session_id/book")" member_booking "$tmp_dir/member-booking.json"
member_booking_id="$(jq -r '.data.booking.id' "$tmp_dir/member-booking.json")"
jq -e '.data.booking.estado == "confirmada"' "$tmp_dir/member-booking.json" >/dev/null
assert_status 200 "$(curl -sS -b "$tmp_dir/member-cookies" -c "$tmp_dir/member-cookies" -o "$tmp_dir/member-cancelled.json" -w '%{http_code}' -X DELETE -H 'Content-Type: application/json' -H "X-CSRF-Token: $member_csrf" --data '{"motivo":"Cambio de disponibilidad personal"}' "$API_BASE_URL/bookings/$member_booking_id")" member_cancel "$tmp_dir/member-cancelled.json"
jq -e '.data.booking.estado == "cancelada"' "$tmp_dir/member-cancelled.json" >/dev/null

other_context="$(jq -nc --argjson gym "$other_gym_id" '{gym_id:$gym}')"
assert_status 200 "$(request POST '/admin/context/select' "$tmp_dir/other-context.json" "$other_context")" switch_other_gym "$tmp_dir/other-context.json"
csrf="$(jq -r '.data.csrf_token' "$tmp_dir/other-context.json")"
assert_status 404 "$(request GET "/admin/class-sessions/$session_id/roster" "$tmp_dir/cross-tenant.json")" roster_isolation "$tmp_dir/cross-tenant.json"
jq -e '.codigo == "session_not_found"' "$tmp_dir/cross-tenant.json" >/dev/null

echo 'Clases, cupos, idempotencia, lista de espera, promoción, asistencia y aislamiento correctos.'
