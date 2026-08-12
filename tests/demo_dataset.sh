#!/usr/bin/env sh

set -eu

DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:?Definí DEMO_USER_PASSWORD para la prueba}"
DEMO_DATASET_NAME="${DEMO_DATASET_NAME:-gymtrack-presentation}"
API_BASE_URL="${API_BASE_URL:-http://localhost:8080/api}"
production_guard_log="$(mktemp /tmp/gymtrack-production-guard.XXXXXX)"
trap 'rm -f "$production_guard_log"' EXIT HUP INT TERM

seed_command() {
  docker compose exec -T \
    -e APP_ENV=demo \
    -e SEED_DEMO_DATA=true \
    -e DEMO_DATASET_NAME="$DEMO_DATASET_NAME" \
    -e DEMO_USER_PASSWORD="$DEMO_USER_PASSWORD" \
    backend php console.php seed:demo "$@"
}

query() {
  docker compose exec -T db sh -lc \
    'mysql -N -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$1"' \
    sh "$1"
}

assert_equal() {
  expected="$1"
  actual="$2"
  label="$3"
  if [ "$expected" != "$actual" ]; then
    echo "$label: se esperaba '$expected' y llegó '$actual'." >&2
    exit 1
  fi
}

if docker compose exec -T \
  -e APP_ENV=production \
  -e SEED_DEMO_DATA=true \
  -e ALLOW_DEMO_DATA_IN_PRODUCTION=false \
  -e DEMO_DATASET_NAME="$DEMO_DATASET_NAME" \
  -e DEMO_USER_PASSWORD="$DEMO_USER_PASSWORD" \
  backend php console.php seed:demo >"$production_guard_log" 2>&1; then
  echo 'El seed demo no fue bloqueado en producción.' >&2
  exit 1
fi

seed_command
seed_command

real_users_before="$(query 'SELECT COUNT(*) FROM usuarios WHERE is_demo = 0;')"

counts="$(query "SELECT CONCAT(
  (SELECT COUNT(*) FROM gimnasios WHERE is_demo=1), ':',
  (SELECT COUNT(*) FROM usuarios WHERE is_demo=1), ':',
  (SELECT COUNT(*) FROM usuario_gimnasio_roles WHERE is_demo=1), ':',
  (SELECT COUNT(*) FROM clases WHERE is_demo=1), ':',
  (SELECT COUNT(*) FROM membresias WHERE is_demo=1), ':',
  (SELECT COUNT(*) FROM pagos WHERE is_demo=1), ':',
  (SELECT COUNT(*) FROM reservas WHERE is_demo=1)
);")"
assert_equal '5:4:5:3:2:10:1' "$counts" 'Idempotencia del dataset'

hashes="$(query 'SELECT COUNT(*) FROM usuarios WHERE is_demo=1 AND LEFT(password_hash, 4) = CHAR(36, 50, 121, 36);')"
assert_equal '4' "$hashes" 'Hash bcrypt de usuarios demo'

permission_matrix="$(query "SET NAMES utf8mb4; SELECT GROUP_CONCAT(CONCAT(nombre, ':', total) ORDER BY nombre SEPARATOR ',') FROM (SELECT r.nombre, COUNT(*) AS total FROM rol_permisos rp JOIN roles r ON r.id=rp.rol_id GROUP BY r.nombre) AS permission_counts;")"
assert_equal 'admin_general:23,dueño:23,empleado:11,socio:8' "$permission_matrix" 'Matriz de capacidades'

public_catalog="$(curl -fsS "$API_BASE_URL/public/gimnasios")"
assert_equal '5' "$(printf '%s' "$public_catalog" | jq -r '.gimnasios | length')" 'Catálogo público demo'
assert_equal '0' "$(printf '%s' "$public_catalog" | jq -r '[.gimnasios[] | select(.is_demo != true)] | length')" 'Aislamiento del catálogo público'

verify_login() {
  email="$1"
  expected_role="$2"
  expected_contexts="$3"
  expected_admin="$4"
  payload="$(jq -nc --arg email "$email" --arg password "$DEMO_USER_PASSWORD" '{email:$email,password:$password}')"
  cookie_file="$(mktemp /tmp/gymtrack-demo-cookie.XXXXXX)"
  response="$(curl -fsS -c "$cookie_file" -H 'Content-Type: application/json' --data "$payload" "$API_BASE_URL/auth/login")"
  role="$(printf '%s' "$response" | jq -r '.usuario.role // empty')"
  csrf="$(printf '%s' "$response" | jq -r '.csrf_token // empty')"
  contexts="$(printf '%s' "$response" | jq -r '.usuario.gimnasios | length')"
  is_demo="$(printf '%s' "$response" | jq -r '.usuario.is_demo')"
  assert_equal "$expected_role" "$role" "Rol de $email"
  assert_equal "$expected_contexts" "$contexts" "Contextos de $email"
  assert_equal 'true' "$is_demo" "Indicador demo de $email"
  assert_equal 'false' "$(printf '%s' "$response" | jq -r 'has("token")')" "Token no expuesto de $email"
  admin_status="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" "$API_BASE_URL/admin/socios")"
  assert_equal "$expected_admin" "$admin_status" "Permiso administrativo de $email"
  if [ "$expected_admin" = '200' ]; then
    admin_socios="$(curl -fsS -b "$cookie_file" "$API_BASE_URL/admin/socios")"
    assert_equal '1' "$(printf '%s' "$admin_socios" | jq -r '.socios | length')" 'Aislamiento de socios demo'
    assert_equal 'socio.demo@gymtrack.local' "$(printf '%s' "$admin_socios" | jq -r '.socios[0].email')" 'Dataset del panel demo'
    socio_id="$(printf '%s' "$admin_socios" | jq -r '.socios[0].id')"
    mutation_status="$(curl -sS -o /dev/null -w '%{http_code}' -X PUT -b "$cookie_file" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data '{"activo":false}' "$API_BASE_URL/admin/socios/$socio_id/estado")"
    assert_equal '409' "$mutation_status" 'Panel demo de solo lectura'
  fi
  curl -fsS -X POST -b "$cookie_file" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" --data '{}' "$API_BASE_URL/auth/logout" >/dev/null
  rm -f "$cookie_file"
}

verify_login 'socio.demo@gymtrack.local' socio 2 403
verify_login 'empleado.demo@gymtrack.local' empleado 1 403
verify_login 'dueno.demo@gymtrack.local' dueño 2 403
verify_login 'admin.demo@gymtrack.local' admin_general 5 200

seed_command --remove
assert_equal '0' "$(query "SELECT COUNT(*) FROM demo_datasets WHERE nombre='$DEMO_DATASET_NAME';")" 'Eliminación del dataset'
assert_equal "$real_users_before" "$(query 'SELECT COUNT(*) FROM usuarios WHERE is_demo = 0;')" 'Preservación de cuentas reales'

seed_command
seed_command --status

echo 'Contratos y dataset demo correctos.'
