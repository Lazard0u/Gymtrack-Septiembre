#!/usr/bin/env sh

# Prueba HTTP completa de identidad: registro, correo, tokens, contraseñas y sesión.
# Las claves llegan por variables de entorno y nunca se imprimen en la salida.
set -eu

API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
AUTH_TEST_PASSWORD="${AUTH_TEST_PASSWORD:?Definí AUTH_TEST_PASSWORD con la política vigente}"
AUTH_TEST_NEW_PASSWORD="${AUTH_TEST_NEW_PASSWORD:?Definí AUTH_TEST_NEW_PASSWORD con la política vigente}"
email='auth.test@gymtrack.local'
tmp_dir="$(mktemp -d /tmp/gymtrack-autenticacion.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT

# Ejecuta SQL dentro del contenedor usando las credenciales que ya posee MySQL.
query() {
  docker compose exec -T db sh -lc 'mysql -N -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$1"' sh "$1"
}

# Cada ejecución deja la base como estaba para que la prueba sea repetible.
cleanup() {
  query "DELETE FROM usuarios WHERE email_normalizado='$email' AND is_demo=0; DELETE FROM rate_limit_attempts WHERE accion IN ('login','register','email_resend','password_forgot','password_reset');" >/dev/null
}

assert_value() {
  [ "$1" = "$2" ] || {
    echo "$3: se esperaba $1 y llegó $2" >&2
    [ -f "${4:-}" ] && sed -n '1,80p' "$4" >&2
    exit 1
  }
}

# Extrae el último enlace desde el buzón local; sólo se usa fuera de producción.
latest_mail() {
  docker compose exec -T -e APP_ENV=test backend php console.php auth:mail:latest "$email"
}

cleanup

# 1) Registro: la cuenta nace sin verificar y la base conserva sólo un hash.
payload="$(jq -nc --arg email "$email" --arg password "$AUTH_TEST_PASSWORD" '{nombre:"Prueba",apellido:"Identidad",email:$email,password:$password,password_confirmation:$password,terms_accepted:true,privacy_accepted:true,marketing_accepted:false}')"
status="$(curl -sS -o "$tmp_dir/register.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$payload" "$API_BASE_URL/auth/registro")"
assert_value 201 "$status" registro "$tmp_dir/register.json"
assert_value 409 "$(curl -sS -o "$tmp_dir/duplicate.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$payload" "$API_BASE_URL/auth/registro")" duplicado "$tmp_dir/duplicate.json"
assert_value 64 "$(query "SELECT LENGTH(token_hash) FROM email_verification_tokens evt JOIN usuarios u ON u.id=evt.usuario_id WHERE u.email_normalizado='$email' AND evt.usado_en IS NULL ORDER BY evt.id DESC LIMIT 1;")" hash_verificacion "$tmp_dir/register.json"

# La plantilla guardada tiene texto alternativo y HTML responsive de GymTrack.
docker compose exec -T backend php -r '
$path=getenv("MAIL_LOG_PATH")?:"/tmp/gymtrack-mail/mail.log";
$wanted=$argv[1];$lines=file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
foreach(array_reverse($lines) as $line){$mail=json_decode($line,true);if(($mail["para"]??"")===$wanted){$html=(string)($mail["html"]??"");exit(str_contains($html,"Verificar mi correo")&&str_contains($html,"Logo de GymTrack")&&str_contains($html,"@media only screen")?0:1);}}
exit(1);' "$email"

# El usuario puede iniciar sesión, pero PHP bloquea recursos hasta verificar.
login_payload="$(jq -nc --arg email "$email" --arg password "$AUTH_TEST_PASSWORD" '{email:$email,password:$password}')"
assert_value 200 "$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")" login_sin_verificar "$tmp_dir/login.json"
assert_value false "$(jq -r '.usuario.email_verified' "$tmp_dir/login.json")" estado_sin_verificar "$tmp_dir/login.json"
assert_value 403 "$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/protected.json" -w '%{http_code}' "$API_BASE_URL/clases")" ruta_sin_verificar "$tmp_dir/protected.json"

# 2) Un valor inventado nunca encuentra una fila ni revela datos de usuario.
invalid_token="$(openssl rand -hex 32)"
assert_value 400 "$(curl -sS -o "$tmp_dir/verify-invalid.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$invalid_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" token_invalido "$tmp_dir/verify-invalid.json"
assert_value email_verification_invalid "$(jq -r '.codigo' "$tmp_dir/verify-invalid.json")" codigo_token_invalido "$tmp_dir/verify-invalid.json"

# 3) Forzamos el vencimiento del primer enlace y comprobamos el estado 410.
mail="$(latest_mail)"
verification_url="$(printf '%s\n' "$mail" | sed -n 's|.*\(http[^ ]*/verificar-email#token=[A-Za-z0-9_-]*\).*|\1|p' | tail -n 1)"
verification_token="${verification_url##*token=}"
query "UPDATE email_verification_tokens evt JOIN usuarios u ON u.id=evt.usuario_id SET evt.expira_en=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE u.email_normalizado='$email' AND evt.token_hash='$(printf '%s' "$verification_token" | sha256sum | cut -d' ' -f1)';" >/dev/null
assert_value 410 "$(curl -sS -o "$tmp_dir/verify-expired.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$verification_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" token_vencido "$tmp_dir/verify-expired.json"
assert_value email_verification_expired "$(jq -r '.codigo' "$tmp_dir/verify-expired.json")" codigo_token_vencido "$tmp_dir/verify-expired.json"

# 4) El reenvío genera un token nuevo y siempre responde de forma neutral.
assert_value 202 "$(curl -sS -o "$tmp_dir/resend.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg email "$email" '{email:$email}')" "$API_BASE_URL/auth/email/resend")" reenvio "$tmp_dir/resend.json"
assert_value 202 "$(curl -sS -o "$tmp_dir/resend-unknown.json" -w '%{http_code}' -H 'Content-Type: application/json' --data '{"email":"cuenta.inexistente@gymtrack.local"}' "$API_BASE_URL/auth/email/resend")" reenvio_neutral "$tmp_dir/resend-unknown.json"
assert_value "$(jq -r '.mensaje' "$tmp_dir/resend.json")" "$(jq -r '.mensaje' "$tmp_dir/resend-unknown.json")" mensaje_neutral "$tmp_dir/resend-unknown.json"

mail="$(latest_mail)"
verification_url="$(printf '%s\n' "$mail" | sed -n 's|.*\(http[^ ]*/verificar-email#token=[A-Za-z0-9_-]*\).*|\1|p' | tail -n 1)"
verification_token="${verification_url##*token=}"
assert_value 200 "$(curl -sS -o "$tmp_dir/verify.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$verification_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" verificar "$tmp_dir/verify.json"
assert_value email_verified "$(jq -r '.codigo' "$tmp_dir/verify.json")" codigo_verificado "$tmp_dir/verify.json"

# 5) El mismo enlace queda inutilizable después del primer consumo.
assert_value 409 "$(curl -sS -o "$tmp_dir/verify-reuse.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$verification_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" token_verificacion_un_uso "$tmp_dir/verify-reuse.json"
assert_value email_verification_used "$(jq -r '.codigo' "$tmp_dir/verify-reuse.json")" codigo_token_usado "$tmp_dir/verify-reuse.json"

# 6) Reenviar a una cuenta ya verificada no crea tokens ni cambia la respuesta.
token_count_before="$(query "SELECT COUNT(*) FROM email_verification_tokens evt JOIN usuarios u ON u.id=evt.usuario_id WHERE u.email_normalizado='$email';")"
assert_value 202 "$(curl -sS -o "$tmp_dir/resend-verified.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg email "$email" '{email:$email}')" "$API_BASE_URL/auth/email/resend")" reenvio_verificado "$tmp_dir/resend-verified.json"
assert_value "$token_count_before" "$(query "SELECT COUNT(*) FROM email_verification_tokens evt JOIN usuarios u ON u.id=evt.usuario_id WHERE u.email_normalizado='$email';")" sin_token_extra "$tmp_dir/resend-verified.json"

# Simula que otro enlace verificó la cuenta antes de abrir el actual.
already_token="$(openssl rand -hex 32)"
already_hash="$(printf '%s' "$already_token" | sha256sum | cut -d' ' -f1)"
query "INSERT INTO email_verification_tokens(usuario_id,token_hash,creado_en,expira_en) SELECT id,'$already_hash',NOW(),DATE_ADD(NOW(),INTERVAL 1 HOUR) FROM usuarios WHERE email_normalizado='$email';" >/dev/null
assert_value 200 "$(curl -sS -o "$tmp_dir/already-verified.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$already_token" '{token:$token}')" "$API_BASE_URL/auth/email/verify")" usuario_ya_verificado "$tmp_dir/already-verified.json"
assert_value email_already_verified "$(jq -r '.codigo' "$tmp_dir/already-verified.json")" codigo_ya_verificado "$tmp_dir/already-verified.json"

# 7) El adaptador informa un fallo del proveedor y el controlador descarta el candidato.
docker compose exec -T -e APP_ENV=test -e MAIL_TRANSPORT=proveedor_inexistente backend php -r '
spl_autoload_register(function($class){foreach(["config","controllers","models","services"] as $dir){$path=__DIR__."/$dir/$class.php";if(is_file($path)){require_once $path;return;}}});
$pdo=Database::conectar();$stmt=$pdo->prepare("SELECT id,email,nombre FROM usuarios WHERE email_normalizado=?");$stmt->execute([$argv[1]]);$user=$stmt->fetch();
$controller=new AuthController();$method=new ReflectionMethod($controller,"deliverVerificationEmail");$method->setAccessible(true);
$sent=$method->invoke($controller,(int)$user["id"],$user["email"],$user["nombre"]);
$stmt=$pdo->prepare("SELECT COUNT(*) FROM email_verification_tokens WHERE usuario_id=? AND usado_en IS NULL");$stmt->execute([(int)$user["id"]]);
exit($sent===false&&(int)$stmt->fetchColumn()===0?0:1);' "$email"

# 8) La recuperación de contraseña conserva su contrato y revoca sesiones.
assert_value 202 "$(curl -sS -o "$tmp_dir/forgot.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$(jq -nc --arg email "$email" '{email:$email}')" "$API_BASE_URL/auth/password/forgot")" recuperar "$tmp_dir/forgot.json"
mail="$(latest_mail)"
reset_url="$(printf '%s\n' "$mail" | sed -n 's#.*\(http[^ ]*/restablecer/[A-Za-z0-9_-]*\).*#\1#p' | tail -n 1)"
reset_token="${reset_url##*/}"
reset_payload="$(jq -nc --arg token "$reset_token" --arg password "$AUTH_TEST_NEW_PASSWORD" '{token:$token,password:$password,password_confirmation:$password}')"
assert_value 200 "$(curl -sS -o "$tmp_dir/reset.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$reset_payload" "$API_BASE_URL/auth/password/reset")" restablecer "$tmp_dir/reset.json"
assert_value 400 "$(curl -sS -o "$tmp_dir/reset-reuse.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$reset_payload" "$API_BASE_URL/auth/password/reset")" token_reset_un_uso "$tmp_dir/reset-reuse.json"
assert_value 401 "$(curl -sS -b "$tmp_dir/cookies" -o /dev/null -w '%{http_code}' "$API_BASE_URL/me")" sesiones_revocadas "$tmp_dir/reset.json"

new_login="$(jq -nc --arg email "$email" --arg password "$AUTH_TEST_NEW_PASSWORD" '{email:$email,password:$password}')"
assert_value 200 "$(curl -sS -c "$tmp_dir/new-cookies" -o "$tmp_dir/new-login.json" -w '%{http_code}' -H 'Content-Type: application/json' --data "$new_login" "$API_BASE_URL/auth/login")" login_nuevo "$tmp_dir/new-login.json"
csrf="$(jq -r '.csrf_token' "$tmp_dir/new-login.json")"
assert_value 200 "$(curl -sS -b "$tmp_dir/new-cookies" -o "$tmp_dir/sessions.json" -w '%{http_code}' "$API_BASE_URL/me/sessions")" listar_sesiones "$tmp_dir/sessions.json"
assert_value 200 "$(curl -sS -b "$tmp_dir/new-cookies" -o "$tmp_dir/logout-all.json" -w '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data '{}' "$API_BASE_URL/auth/logout-all")" logout_total "$tmp_dir/logout-all.json"

cleanup
echo 'Registro, verificación, reenvío, estados de token, fallo de correo, recuperación y sesión correctos.'
