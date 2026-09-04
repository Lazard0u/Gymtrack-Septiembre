#!/usr/bin/env sh

# Prueba de integración tenant operations. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para validar la operación multi-gimnasio}"
CONTRASENA_USUARIO_INVITADO="${CONTRASENA_USUARIO_INVITADO:-$DEMO_USER_PASSWORD}"
DEMO_DATASET_NAME="${DEMO_DATASET_NAME:-gymtrack-presentation}"
tmp_dir="$(mktemp -d /tmp/gymtrack-tenant-operations.XXXXXX)"

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

seed_reset
login_payload="$(jq -nc --arg password "$DEMO_USER_PASSWORD" '{email:"admin.demo@gymtrack.local",password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")" login "$tmp_dir/login.json"
csrf="$(jq -r '.csrf_token' "$tmp_dir/login.json")"

assert_status 200 "$(request GET '/admin/context' "$tmp_dir/context.json")" context "$tmp_dir/context.json"
gym_id="$(jq -r '.data.gyms[] | select(.nombre=="GymTrack Centro") | .gimnasio_id' "$tmp_dir/context.json")"
other_gym_id="$(jq -r '.data.gyms[] | select(.nombre=="Titan Training") | .gimnasio_id' "$tmp_dir/context.json")"
new_gym_payload='{"nombre":"Gimnasio Contrato Tenant","nombre_legal":"Gimnasio Contrato Tenant Demo SAS","descripcion":"Tenant temporal creado para validar el alta administrativa.","direccion":"Avenida Ficticia 515","ciudad":"Montevideo","departamento":"Montevideo","pais":"Uruguay","latitud":"-34.9100000","longitud":"-56.1700000","zona_horaria":"America/Montevideo","telefono":"+598 0000 0515","email":"tenant-contract@example.test","horarios":["Lunes a viernes 07:00–21:00"],"categorias":["Fitness"],"servicios":["Vestuarios"],"estado":"publicado","verificacion_estado":"verificado"}'
assert_status 201 "$(request POST '/admin/gyms' "$tmp_dir/gym-created.json" "$new_gym_payload")" gym_create_without_context "$tmp_dir/gym-created.json"
jq -e '.data.estado == "publicado" and .data.verificacion_estado == "verificado" and .data.slug == "gimnasio-contrato-tenant" and .data.pais == "Uruguay"' "$tmp_dir/gym-created.json" >/dev/null
assert_status 200 "$(curl -sS -o "$tmp_dir/public-after-create.json" -w '%{http_code}' "$API_BASE_URL/public/gimnasios")" public_after_create "$tmp_dir/public-after-create.json"
jq -e '.gimnasios[] | select(.slug == "gimnasio-contrato-tenant" and .latitud == -34.91 and .longitud == -56.17)' "$tmp_dir/public-after-create.json" >/dev/null
select_payload="$(jq -nc --argjson gym "$gym_id" '{gym_id:$gym,reason:"Prueba automatizada de operación tenant"}')"
assert_status 200 "$(request POST '/admin/context/select' "$tmp_dir/selected.json" "$select_payload")" select_context "$tmp_dir/selected.json"
csrf="$(jq -r '.data.csrf_token' "$tmp_dir/selected.json")"

for endpoint in "gyms/$gym_id" "gyms/$gym_id/locations" members staff trainers membership-plans memberships invitations; do
  file_name="$(printf '%s' "$endpoint" | tr '/' '-')"
  assert_status 200 "$(request GET "/admin/$endpoint?page=1&per_page=20" "$tmp_dir/$file_name.json")" "$endpoint" "$tmp_dir/$file_name.json"
  jq -e '.ok == true and .request_id != ""' "$tmp_dir/$file_name.json" >/dev/null
done

assert_status 403 "$(request GET "/admin/gyms/$other_gym_id" "$tmp_dir/cross-gym.json")" tenant_isolation "$tmp_dir/cross-gym.json"
jq -e '.codigo == "gym_context_mismatch"' "$tmp_dir/cross-gym.json" >/dev/null

gym_update_payload="$(jq '.data | {nombre,nombre_legal,descripcion,direccion,ciudad,departamento,latitud,longitud,zona_horaria,telefono,email,horarios,categorias,servicios,estado,verificacion_estado} | .descripcion="Sede demostrativa urbana actualizada por la prueba tenant."' "$tmp_dir/gyms-$gym_id.json")"
assert_status 200 "$(request PATCH "/admin/gyms/$gym_id" "$tmp_dir/gym-updated.json" "$gym_update_payload")" gym_update "$tmp_dir/gym-updated.json"
jq -e '.data.descripcion == "Sede demostrativa urbana actualizada por la prueba tenant." and .data.verificacion_estado == "verificado"' "$tmp_dir/gym-updated.json" >/dev/null

member_id="$(jq -r '.data.items[0].id' "$tmp_dir/members.json")"
member_payload='{"estado":"activo","notas_operativas":"Validado por prueba tenant","motivo":"Prueba automatizada"}'
assert_status 200 "$(request PATCH "/admin/members/$member_id" "$tmp_dir/member-updated.json" "$member_payload")" member_update "$tmp_dir/member-updated.json"
jq -e '.data.estado == "activo" and .data.notas_operativas == "Validado por prueba tenant"' "$tmp_dir/member-updated.json" >/dev/null

employee_id="$(jq -r '.data.items[0].id' "$tmp_dir/staff.json")"
employee_payload='{"cargo":"Recepción y sala","estado":"activo","notas_operativas":"Prueba tenant","permissions":["members.read","classes.read"]}'
assert_status 200 "$(request PATCH "/admin/employees/$employee_id" "$tmp_dir/employee-updated.json" "$employee_payload")" employee_update "$tmp_dir/employee-updated.json"
jq -e '.data.cargo == "Recepción y sala" and (.data.permissions | sort) == ["classes.read","members.read"]' "$tmp_dir/employee-updated.json" >/dev/null

trainer_id="$(jq -r '.data.items[0].id' "$tmp_dir/trainers.json")"
trainer_payload='{"biografia":"Perfil técnico validado por prueba.","especialidades":["Fuerza","Movilidad"],"disponibilidad":["Lunes 08:00–12:00"],"estado":"activo"}'
assert_status 200 "$(request PATCH "/admin/trainers/$trainer_id" "$tmp_dir/trainer-updated.json" "$trainer_payload")" trainer_update "$tmp_dir/trainer-updated.json"
jq -e '.data.estado == "activo" and (.data.especialidades | length) == 2' "$tmp_dir/trainer-updated.json" >/dev/null

plan_payload='{"nombre":"Plan Prueba Tenant","descripcion":"Plan temporal para contratos automatizados.","duracion_dias":30,"precio":"990.00","moneda":"UYU","beneficios":["Sala general"],"estado":"activo"}'
assert_status 201 "$(request POST '/admin/membership-plans' "$tmp_dir/plan-created.json" "$plan_payload")" plan_create "$tmp_dir/plan-created.json"
plan_id="$(jq -r '.data.id' "$tmp_dir/plan-created.json")"
jq -e '.data.version == 1 and .data.estado == "activo"' "$tmp_dir/plan-created.json" >/dev/null

existing_membership_id="$(jq -r --argjson user "$member_id" '.data.items[] | select(.usuario_id == $user and .estado == "activa") | .id' "$tmp_dir/memberships.json" | head -n 1)"
if [ -n "$existing_membership_id" ]; then
  assert_status 200 "$(request PATCH "/admin/memberships/$existing_membership_id/status" "$tmp_dir/demo-membership-suspended.json" '{"estado":"suspendida","motivo":"Preparar renovación en prueba automatizada"}')" demo_membership_transition "$tmp_dir/demo-membership-suspended.json"
fi
membership_payload="$(jq -nc --argjson user "$member_id" --argjson plan "$plan_id" '{usuario_id:$user,plan_id:$plan,fecha_inicio:"2026-08-06",motivo:"Prueba automatizada de alta"}')"
assert_status 201 "$(request POST '/admin/memberships' "$tmp_dir/membership-created.json" "$membership_payload")" membership_create "$tmp_dir/membership-created.json"
membership_id="$(jq -r '.data.id' "$tmp_dir/membership-created.json")"
jq -e '.data.estado == "activa" and .data.plan_id == '"$plan_id" "$tmp_dir/membership-created.json" >/dev/null

assert_status 200 "$(request PATCH "/admin/memberships/$membership_id/status" "$tmp_dir/membership-suspended.json" '{"estado":"suspendida","motivo":"Prueba de transición controlada"}')" membership_transition "$tmp_dir/membership-suspended.json"
jq -e '.data.estado == "suspendida"' "$tmp_dir/membership-suspended.json" >/dev/null
renewal_payload="$(jq -nc --argjson user "$member_id" --argjson plan "$plan_id" '{usuario_id:$user,plan_id:$plan,fecha_inicio:"2026-09-06",motivo:"Renovación automatizada de contrato"}')"
assert_status 201 "$(request POST '/admin/memberships' "$tmp_dir/membership-renewed.json" "$renewal_payload")" membership_renewal "$tmp_dir/membership-renewed.json"
jq -e --arg number "$(jq -r '.data.numero_socio' "$tmp_dir/membership-created.json")" '.data.estado == "activa" and .data.numero_socio == $number' "$tmp_dir/membership-renewed.json" >/dev/null

invitation_payload='{"email":"tenant.persona@example.test","tipo":"socio","permissions":[]}'
assert_status 201 "$(request POST '/admin/invitations' "$tmp_dir/invitation-created.json" "$invitation_payload")" invitation_create "$tmp_dir/invitation-created.json"
invitation_id="$(jq -r '.data.invitation_id' "$tmp_dir/invitation-created.json")"
jq -e '.data.status == "pending" and .data.expires_in_hours == 72' "$tmp_dir/invitation-created.json" >/dev/null
assert_status 200 "$(request DELETE "/admin/invitations/$invitation_id" "$tmp_dir/invitation-revoked.json")" invitation_revoke "$tmp_dir/invitation-revoked.json"
jq -e '.data.revoked == true' "$tmp_dir/invitation-revoked.json" >/dev/null

accepted_email='tenant.aceptada@example.test'
accepted_invitation_payload="$(jq -nc --arg email "$accepted_email" '{email:$email,tipo:"socio",permissions:[]}')"
assert_status 201 "$(request POST '/admin/invitations' "$tmp_dir/invitation-to-accept.json" "$accepted_invitation_payload")" invitation_for_acceptance "$tmp_dir/invitation-to-accept.json"
mail_body="$(docker compose exec -T backend php console.php auth:mail:latest "$accepted_email")"
invitation_token="$(printf '%s\n' "$mail_body" | sed -n 's#.*\/invitacion/##p' | tail -n 1)"
[ -n "$invitation_token" ] || { echo 'No se encontró el token en el correo local.' >&2; exit 1; }
acceptance_payload="$(jq -nc --arg token "$invitation_token" --arg password "$CONTRASENA_USUARIO_INVITADO" '{token:$token,nombre:"Persona",apellido:"Invitada",telefono:"+598 0000 0777",password:$password,terminos:true,privacidad:true}')"
assert_status 201 "$(curl -sS -o "$tmp_dir/invitation-accepted.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$acceptance_payload" "$API_BASE_URL/invitations/accept")" invitation_accept "$tmp_dir/invitation-accepted.json"
jq -e '.data.type == "socio" and .data.user_id > 0' "$tmp_dir/invitation-accepted.json" >/dev/null
assert_status 410 "$(curl -sS -o "$tmp_dir/invitation-reused.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$acceptance_payload" "$API_BASE_URL/invitations/accept")" invitation_single_use "$tmp_dir/invitation-reused.json"
jq -e '.codigo == "invitation_invalid"' "$tmp_dir/invitation-reused.json" >/dev/null
accepted_login="$(jq -nc --arg email "$accepted_email" --arg password "$CONTRASENA_USUARIO_INVITADO" '{email:$email,password:$password}')"
assert_status 200 "$(curl -sS -o "$tmp_dir/accepted-login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$accepted_login" "$API_BASE_URL/auth/login")" accepted_user_login "$tmp_dir/accepted-login.json"
jq -e '.usuario.email == "tenant.aceptada@example.test" and .usuario.role == "socio"' "$tmp_dir/accepted-login.json" >/dev/null

location_payload='{"nombre":"Sede Contrato","direccion":"Calle Ficticia 505","ciudad":"Montevideo","departamento":"Montevideo","pais":"Uruguay","latitud":"-34.9050000","longitud":"-56.1800000","zona_horaria":"America/Montevideo","telefono":"+598 0000 0505","email":"sede-contrato@example.test","horarios":["Lunes a viernes 08:00–20:00"],"estado":"activa"}'
assert_status 201 "$(request POST "/admin/gyms/$gym_id/locations" "$tmp_dir/location-created.json" "$location_payload")" location_create "$tmp_dir/location-created.json"
location_id="$(jq -r '.data.id' "$tmp_dir/location-created.json")"
jq -e '.data.nombre == "Sede Contrato" and .data.es_principal == false' "$tmp_dir/location-created.json" >/dev/null
location_updated="$(printf '%s' "$location_payload" | jq '.telefono="+598 0000 0555"')"
assert_status 200 "$(request PATCH "/admin/gyms/$gym_id/locations/$location_id" "$tmp_dir/location-updated.json" "$location_updated")" location_update "$tmp_dir/location-updated.json"
jq -e '.data.telefono == "+598 0000 0555"' "$tmp_dir/location-updated.json" >/dev/null

assert_status 201 "$(curl -sS -b "$tmp_dir/cookies" -c "$tmp_dir/cookies" -o "$tmp_dir/upload.json" -w '%{http_code}' -H "X-CSRF-Token: $csrf" -F categoria=gimnasio_imagen -F archivo=@frontend/src/assets/images/gymtrack-owner-studio.webp "$API_BASE_URL/admin/files")" file_upload "$tmp_dir/upload.json"
file_id="$(jq -r '.data.id' "$tmp_dir/upload.json")"
assert_status 200 "$(curl -sS -o "$tmp_dir/public-file.webp" -w '%{http_code}' "$API_BASE_URL/public/files/$file_id")" public_file "$tmp_dir/public-file.webp"
[ "$(wc -c < "$tmp_dir/public-file.webp")" -gt 1000 ] || { echo 'El archivo público quedó vacío.' >&2; exit 1; }

assert_status 200 "$(curl -sS -o "$tmp_dir/public-detail.json" -w '%{http_code}' "$API_BASE_URL/public/gyms/gymtrack-centro")" public_detail "$tmp_dir/public-detail.json"
jq -e '.gimnasio.nombre == "GymTrack Centro" and (.gimnasio.sedes | length) >= 1' "$tmp_dir/public-detail.json" >/dev/null
assert_status 200 "$(curl -sS -o "$tmp_dir/public-plans.json" -w '%{http_code}' "$API_BASE_URL/public/gyms/$gym_id/plans")" public_plans "$tmp_dir/public-plans.json"
jq -e '(.planes | length) >= 1' "$tmp_dir/public-plans.json" >/dev/null

new_gym_id="$(jq -r '.data.id' "$tmp_dir/gym-created.json")"
archive_context_payload="$(jq -nc --argjson gym "$new_gym_id" '{gym_id:$gym,reason:"Archivar tenant de prueba automatizada"}')"
assert_status 200 "$(request POST '/admin/context/select' "$tmp_dir/archive-context.json" "$archive_context_payload")" archive_context "$tmp_dir/archive-context.json"
csrf="$(jq -r '.data.csrf_token' "$tmp_dir/archive-context.json")"
assert_status 200 "$(request GET "/admin/gyms/$new_gym_id" "$tmp_dir/new-gym-before-status.json")" new_gym_before_status "$tmp_dir/new-gym-before-status.json"
inactive_payload="$(jq '.data | {nombre,nombre_legal,descripcion,direccion,ciudad,departamento,pais,latitud,longitud,zona_horaria,telefono,email,horarios,categorias,servicios,estado,verificacion_estado} | .nombre="Gimnasio Contrato Actualizado" | .direccion="Avenida Ficticia 919" | .estado="inactivo" | .latitud="-34.9200000" | .longitud="-56.1900000"' "$tmp_dir/new-gym-before-status.json")"
assert_status 200 "$(request PATCH "/admin/gyms/$new_gym_id" "$tmp_dir/new-gym-inactive.json" "$inactive_payload")" new_gym_inactive "$tmp_dir/new-gym-inactive.json"
assert_status 200 "$(curl -sS -o "$tmp_dir/public-after-inactive.json" -w '%{http_code}' "$API_BASE_URL/public/gimnasios")" public_after_inactive "$tmp_dir/public-after-inactive.json"
jq -e --argjson gym "$new_gym_id" '([.gimnasios[].id] | index($gym)) == null' "$tmp_dir/public-after-inactive.json" >/dev/null
published_payload="$(printf '%s' "$inactive_payload" | jq '.estado="publicado"')"
assert_status 200 "$(request PATCH "/admin/gyms/$new_gym_id" "$tmp_dir/new-gym-republished.json" "$published_payload")" new_gym_republished "$tmp_dir/new-gym-republished.json"
assert_status 200 "$(curl -sS -o "$tmp_dir/public-after-move.json" -w '%{http_code}' "$API_BASE_URL/public/gimnasios")" public_after_move "$tmp_dir/public-after-move.json"
jq -e --argjson gym "$new_gym_id" '.gimnasios[] | select(.id == $gym and .nombre == "Gimnasio Contrato Actualizado" and .direccion == "Avenida Ficticia 919" and .latitud == -34.92 and .longitud == -56.19)' "$tmp_dir/public-after-move.json" >/dev/null
assert_status 200 "$(request DELETE "/admin/gyms/$new_gym_id" "$tmp_dir/gym-archived.json" '{"motivo":"Finalización de prueba automatizada"}')" gym_archive "$tmp_dir/gym-archived.json"
jq -e '.data.archived == true' "$tmp_dir/gym-archived.json" >/dev/null || { echo 'La respuesta no confirmó el archivo lógico.' >&2; cat "$tmp_dir/gym-archived.json" >&2; exit 1; }
assert_status 200 "$(curl -sS -o "$tmp_dir/public-after-archive.json" -w '%{http_code}' "$API_BASE_URL/public/gimnasios")" public_after_archive "$tmp_dir/public-after-archive.json"
jq -e --argjson gym "$new_gym_id" '([.gimnasios[].id] | index($gym)) == null' "$tmp_dir/public-after-archive.json" >/dev/null
assert_status 200 "$(request GET '/admin/context' "$tmp_dir/context-after-archive.json")" context_after_archive "$tmp_dir/context-after-archive.json"
jq -e --argjson gym "$new_gym_id" '.data.active_gym_id == null and ([.data.gyms[].gimnasio_id] | index($gym)) == null' "$tmp_dir/context-after-archive.json" >/dev/null || { echo 'El gimnasio archivado siguió visible en el contexto.' >&2; cat "$tmp_dir/context-after-archive.json" >&2; exit 1; }

echo 'Tenant, personas, entrenadores, sedes, archivos, planes, membresías y aislamiento correctos.'
