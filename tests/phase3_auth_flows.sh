#!/usr/bin/env sh
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
AUTH_TEST_PASSWORD="${AUTH_TEST_PASSWORD:?Definí AUTH_TEST_PASSWORD con la política vigente}"
AUTH_TEST_NEW_PASSWORD="${AUTH_TEST_NEW_PASSWORD:?Definí AUTH_TEST_NEW_PASSWORD con la política vigente}"
email='phase3.auth.test@gymtrack.local'
tmp_dir="$(mktemp -d /tmp/gymtrack-phase3-auth.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

query() {
  docker compose exec -T db sh -lc 'mysql -N -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$1"' sh "$1"
}
cleanup() {
  query "DELETE FROM usuarios WHERE email_normalizado='$email' AND is_demo=0; DELETE FROM rate_limit_attempts WHERE sujeto_hash IS NOT NULL AND accion IN ('login','register','password_forgot','password_reset');" >/dev/null
}
assert_status() { [ "$1" = "$2" ] || { echo "$3: se esperaba $1 y llegó $2" >&2; [ -f "$4" ] && cat "$4" >&2; exit 1; }; }

cleanup
payload="$(jq -nc --arg email "$email" --arg password "$AUTH_TEST_PASSWORD" '{nombre:"Prueba",apellido:"Identidad",email:$email,password:$password,password_confirmation:$password,terms_accepted:true,privacy_accepted:true,marketing_accepted:false}')"
status="$(curl -sS -o "$tmp_dir/register.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$payload" "$API_BASE_URL/auth/registro")"
assert_status 201 "$status" registro "$tmp_dir/register.json"
assert_status 409 "$(curl -sS -o "$tmp_dir/duplicate.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$payload" "$API_BASE_URL/auth/registro")" duplicado "$tmp_dir/duplicate.json"
assert_status 64 "$(query "SELECT LENGTH(token_hash) FROM email_verification_tokens evt JOIN usuarios u ON u.id=evt.usuario_id WHERE u.email_normalizado='$email' AND evt.usado_en IS NULL ORDER BY evt.id DESC LIMIT 1;")" hash_verificacion "$tmp_dir/register.json"

login_payload="$(jq -nc --arg email "$email" --arg password "$AUTH_TEST_PASSWORD" '{email:$email,password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")" login_sin_verificar "$tmp_dir/login.json"
assert_status false "$(jq -r '.usuario.email_verified' "$tmp_dir/login.json")" estado_sin_verificar "$tmp_dir/login.json"
assert_status 403 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/protected.json" -w '%{http_code}' "$API_BASE_URL/clases")" ruta_sin_verificar "$tmp_dir/protected.json"

mail="$(docker compose exec -T -e APP_ENV=test backend php console.php auth:mail:latest "$email")"
verification_url="$(printf '%s\n' "$mail" | sed -n 's#.*\(http[^ ]*/verificar-email?token=[A-Za-z0-9_-]*\).*#\1#p' | tail -n 1)"
verification_token="${verification_url##*token=}"
assert_status 200 "$(curl -sS -o "$tmp_dir/verify.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$verification_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" verificar "$tmp_dir/verify.json"
assert_status 400 "$(curl -sS -o "$tmp_dir/verify-reuse.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$verification_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" token_verificacion_un_uso "$tmp_dir/verify-reuse.json"

assert_status 202 "$(curl -sS -o "$tmp_dir/forgot.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg email "$email" '{email:$email}')" "$API_BASE_URL/auth/password/forgot")" recuperar "$tmp_dir/forgot.json"
mail="$(docker compose exec -T -e APP_ENV=test backend php console.php auth:mail:latest "$email")"
reset_url="$(printf '%s\n' "$mail" | sed -n 's#.*\(http[^ ]*/restablecer/[A-Za-z0-9_-]*\).*#\1#p' | tail -n 1)"
reset_token="${reset_url##*/}"
reset_payload="$(jq -nc --arg token "$reset_token" --arg password "$AUTH_TEST_NEW_PASSWORD" '{token:$token,password:$password,password_confirmation:$password}')"
assert_status 200 "$(curl -sS -o "$tmp_dir/reset.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$reset_payload" "$API_BASE_URL/auth/password/reset")" restablecer "$tmp_dir/reset.json"
assert_status 400 "$(curl -sS -o "$tmp_dir/reset-reuse.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$reset_payload" "$API_BASE_URL/auth/password/reset")" token_reset_un_uso "$tmp_dir/reset-reuse.json"
assert_status 401 "$(curl -sS -b "$tmp_dir/cookies" -o /dev/null -w '%{http_code}' "$API_BASE_URL/me")" sesiones_revocadas "$tmp_dir/reset.json"

new_login="$(jq -nc --arg email "$email" --arg password "$AUTH_TEST_NEW_PASSWORD" '{email:$email,password:$password}')"
assert_status 200 "$(curl -sS -c "$tmp_dir/new-cookies" -o "$tmp_dir/new-login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$new_login" "$API_BASE_URL/auth/login")" login_nuevo "$tmp_dir/new-login.json"
csrf="$(jq -r '.csrf_token' "$tmp_dir/new-login.json")"
assert_status 200 "$(curl -sS -b "$tmp_dir/new-cookies" -o "$tmp_dir/sessions.json" -w '%{http_code}' "$API_BASE_URL/me/sessions")" listar_sesiones "$tmp_dir/sessions.json"
assert_status 200 "$(curl -sS -b "$tmp_dir/new-cookies" -o "$tmp_dir/logout-all.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data '{}' "$API_BASE_URL/auth/logout-all")" logout_total "$tmp_dir/logout-all.json"

cleanup
echo 'Flujos de registro, verificación, recuperación, sesiones y CSRF correctos.'
