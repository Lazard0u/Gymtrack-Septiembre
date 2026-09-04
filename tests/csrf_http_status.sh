#!/usr/bin/env sh

# Prueba de integración csrf http status. Prepara datos temporales, llama la API y compara estados y respuestas esperadas.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.

set -eu

API_BASE_URL=${API_BASE_URL:-http://localhost:8080/api}
TEST_ADMIN_EMAIL=${TEST_ADMIN_EMAIL:-admin.demo@gymtrack.local}
TEST_ADMIN_PASSWORD=${TEST_ADMIN_PASSWORD:-${DEMO_USER_PASSWORD:-}}

if [ -z "$TEST_ADMIN_PASSWORD" ]; then
    echo 'Definí TEST_ADMIN_PASSWORD o DEMO_USER_PASSWORD.' >&2
    exit 2
fi

tmp_dir=$(mktemp -d /tmp/gymtrack-csrf-status.XXXXXX)
trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM

login_payload=$(jq -nc --arg email "$TEST_ADMIN_EMAIL" --arg password "$TEST_ADMIN_PASSWORD" '{email:$email,password:$password}')
login_status=$(curl -sS -c "$tmp_dir/cookies" -o "$tmp_dir/login.json" -w '%{http_code}' \
    -H 'Content-Type: application/json' --data "$login_payload" "$API_BASE_URL/auth/login")
if [ "$login_status" != 200 ]; then
    echo "No se pudo iniciar sesión para probar CSRF: HTTP $login_status" >&2
    cat "$tmp_dir/login.json" >&2
    exit 1
fi

csrf_status=$(curl -sS -b "$tmp_dir/cookies" -o "$tmp_dir/csrf.json" -w '%{http_code}' \
    -H 'Content-Type: application/json' --data '{"gym_id":1}' "$API_BASE_URL/admin/context/select")
if [ "$csrf_status" != 419 ]; then
    echo "CSRF administrativo debía devolver 419 y devolvió $csrf_status." >&2
    cat "$tmp_dir/csrf.json" >&2
    exit 1
fi
jq -e '.codigo == "csrf_expired" and .error == true' "$tmp_dir/csrf.json" >/dev/null

echo 'HTTP 419 y csrf_expired correctos bajo Apache.'
