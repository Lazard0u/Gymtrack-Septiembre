#!/usr/bin/env sh

# Valida antes del despliegue que secretos, HTTPS, Docker y servicios tengan una configuración segura.
# set -eu detiene la ejecución ante el primer fallo o variable obligatoria ausente.

set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
env_file=${1:-"$project_root/.env.production"}
failures=0

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    failures=$((failures + 1))
}

notice() {
    printf 'OK: %s\n' "$1"
}

if [ ! -f "$env_file" ]; then
    printf 'No existe el archivo de entorno: %s\n' "$env_file" >&2
    exit 1
fi

env_file=$(CDPATH= cd -- "$(dirname -- "$env_file")" && pwd)/$(basename -- "$env_file")

read_value() {
    key=$1
    current=$(printenv "$key" 2>/dev/null || true)
    if [ -n "$current" ]; then
        printf '%s' "$current"
        return
    fi
    raw=$(awk -v wanted="$key" '
        /^[[:space:]]*(#|$)/ { next }
        {
            line=$0
            sub(/^[[:space:]]*export[[:space:]]+/, "", line)
            if (index(line, wanted "=") == 1) {
                sub("^" wanted "=", "", line)
                sub(/\r$/, "", line)
                print line
                exit
            }
        }
    ' "$env_file")
    case "$raw" in
        \"*\") raw=${raw#\"}; raw=${raw%\"} ;;
        \'*\') raw=${raw#\'}; raw=${raw%\'} ;;
    esac
    printf '%s' "$raw"
}

require_value() {
    name=$1
    value=$(read_value "$name")
    if [ -z "$value" ]; then
        fail "$name es obligatorio."
    fi
}

mode=$(stat -c '%a' "$env_file" 2>/dev/null || printf 'unknown')
case "$mode" in
    400|440|600|640) notice "El archivo de entorno no es público." ;;
    *) fail "Protegé $env_file con permisos 600 o 640 (actual: $mode)." ;;
esac

for name in APP_KEY FRONTEND_URL APP_URL MYSQL_ROOT_PASSWORD DB_PASSWORD TURNSTILE_SECRET_KEY TURNSTILE_SITE_KEY MAIL_FROM SMTP_CONFIG_FILE MERCADO_PAGO_ACCESS_TOKEN MERCADO_PAGO_WEBHOOK_SECRET GEOCODING_USER_AGENT; do
    require_value "$name"
done

app_env=$(read_value APP_ENV)
if [ -n "$app_env" ] && [ "$app_env" != production ]; then
    fail 'APP_ENV debe ser production.'
fi

app_key=$(read_value APP_KEY)
if [ "${#app_key}" -lt 32 ]; then
    fail 'APP_KEY debe tener al menos 32 caracteres aleatorios.'
fi
weak_key=$(printf '%s' "$app_key" | tr '[:upper:]' '[:lower:]')
case "$weak_key" in
    *change-me*|*generate*|*development*|*example*|*gymtrack-local*) fail 'APP_KEY conserva un valor de ejemplo o desarrollo.' ;;
esac

root_password=$(read_value MYSQL_ROOT_PASSWORD)
db_password=$(read_value DB_PASSWORD)
if [ "${#root_password}" -lt 16 ]; then
    fail 'MYSQL_ROOT_PASSWORD debe tener al menos 16 caracteres.'
fi
if [ "${#db_password}" -lt 16 ]; then
    fail 'DB_PASSWORD debe tener al menos 16 caracteres.'
fi
if [ -n "$root_password" ] && [ "$root_password" = "$db_password" ]; then
    fail 'La contraseña root de MySQL debe ser distinta de DB_PASSWORD.'
fi

for name in FRONTEND_URL APP_URL; do
    value=$(read_value "$name")
    case "$value" in
        https://*) ;;
        *) fail "$name debe usar HTTPS." ;;
    esac
    case "$value" in
        *localhost*|*127.0.0.1*) fail "$name no puede apuntar a localhost en producción." ;;
    esac
    case "$value" in
        */) fail "$name no debe terminar en barra." ;;
    esac
done
if [ "$(read_value FRONTEND_URL)" != "$(read_value APP_URL)" ]; then
    fail 'APP_URL y FRONTEND_URL deben compartir el origen público porque Nginx es el único servicio expuesto.'
fi

public_port=$(read_value PUBLIC_HTTP_PORT)
public_port=${public_port:-80}
case "$public_port" in
    *[!0-9]*) fail 'PUBLIC_HTTP_PORT debe ser un número entre 1 y 65535.' ;;
    *)
        if [ "$public_port" -lt 1 ] || [ "$public_port" -gt 65535 ]; then
            fail 'PUBLIC_HTTP_PORT debe estar entre 1 y 65535.'
        fi
        ;;
esac

database=$(read_value DB_DATABASE)
database=${database:-gymtrack}
username=$(read_value DB_USERNAME)
username=${username:-gymtrack_user}
case "$database" in *[!A-Za-z0-9_]*) fail 'DB_DATABASE sólo puede contener letras, números y guion bajo.' ;; esac
case "$username" in *[!A-Za-z0-9_]*) fail 'DB_USERNAME sólo puede contener letras, números y guion bajo.' ;; esac

mail_from=$(read_value MAIL_FROM)
case "$mail_from" in *@*.*) ;; *) fail 'MAIL_FROM no tiene formato de correo.' ;; esac

# El vencimiento evita enlaces indefinidos y también impide valores tan bajos
# que vuelvan impracticable la entrega del correo.
verification_ttl=$(read_value EMAIL_VERIFICATION_TTL_SECONDS)
verification_ttl=${verification_ttl:-86400}
case "$verification_ttl" in
    *[!0-9]*) fail 'EMAIL_VERIFICATION_TTL_SECONDS debe ser un número entero.' ;;
    *)
        if [ "$verification_ttl" -lt 300 ] || [ "$verification_ttl" -gt 604800 ]; then
            fail 'EMAIL_VERIFICATION_TTL_SECONDS debe estar entre 300 y 604800 segundos.'
        fi
        ;;
esac

geocoding_user_agent=$(read_value GEOCODING_USER_AGENT)
case "$geocoding_user_agent" in
    *@*.*) ;;
    *) fail 'GEOCODING_USER_AGENT debe identificar GymTrack e incluir un contacto real.' ;;
esac
case "$geocoding_user_agent" in
    *.local*|*example.com*) fail 'GEOCODING_USER_AGENT no puede usar un contacto ficticio en producción.' ;;
esac

smtp_file=$(read_value SMTP_CONFIG_FILE)
case "$smtp_file" in /*) ;; *) smtp_file="$project_root/$smtp_file" ;; esac
if [ ! -f "$smtp_file" ] || [ ! -s "$smtp_file" ]; then
    fail 'SMTP_CONFIG_FILE debe apuntar a un archivo msmtp no vacío.'
else
    smtp_mode=$(stat -c '%a' "$smtp_file" 2>/dev/null || printf 'unknown')
    case "$smtp_mode" in
        400|440|600|640) notice "La configuración SMTP no es pública." ;;
        *) fail "Protegé SMTP_CONFIG_FILE con permisos 600 o 640 (actual: $smtp_mode)." ;;
    esac
    for smtp_contract in \
        '^[[:space:]]*account[[:space:]]+default([[:space:]:]|$)' \
        '^[[:space:]]*host[[:space:]]+' \
        '^[[:space:]]*from[[:space:]]+' \
        '^[[:space:]]*tls[[:space:]]+on([[:space:]]|$)' \
        '^[[:space:]]*auth[[:space:]]+on([[:space:]]|$)' \
        '^[[:space:]]*user[[:space:]]+' \
        '^[[:space:]]*(password|passwordeval)[[:space:]]+'; do
        if ! grep -Eq "$smtp_contract" "$smtp_file"; then
            fail 'SMTP_CONFIG_FILE no contiene todos los ajustes seguros requeridos.'
            break
        fi
    done
fi

for name in SEED_DEMO_DATA ALLOW_DEMO_DATA_IN_PRODUCTION FEATURE_WHATSAPP FEATURE_GOOGLE_CALENDAR_SYNC FEATURE_ADVANCED_ANALYTICS FEATURE_PERSONAL_RECOMMENDATIONS; do
    value=$(read_value "$name")
    value=${value:-false}
    if [ "$value" != false ]; then
        fail "$name debe permanecer en false para este despliegue estable."
    fi
done

session_secure=$(read_value SESSION_SECURE)
if [ -n "$session_secure" ] && [ "$session_secure" != true ]; then
    fail 'SESSION_SECURE debe ser true.'
fi
session_idle=$(read_value SESSION_IDLE_SECONDS)
session_idle=${session_idle:-1800}
session_absolute=$(read_value SESSION_ABSOLUTE_SECONDS)
session_absolute=${session_absolute:-28800}
case "$session_idle:$session_absolute" in
    *[!0-9:]*) fail 'Los tiempos de sesión deben expresarse en segundos enteros.' ;;
    *)
        if [ "$session_idle" -lt 300 ] || [ "$session_absolute" -lt "$session_idle" ] || [ "$session_absolute" -gt 28800 ]; then
            fail 'La sesión debe usar idle >= 300, absolute >= idle y absolute <= 28800.'
        fi
        ;;
esac

payment_mode=$(read_value PAYMENT_MODE)
payment_mode=${payment_mode:-test}
access_token=$(read_value MERCADO_PAGO_ACCESS_TOKEN)
webhook_secret=$(read_value MERCADO_PAGO_WEBHOOK_SECRET)
if [ "${#access_token}" -lt 20 ]; then
    fail 'MERCADO_PAGO_ACCESS_TOKEN debe parecer completo.'
fi
if [ "${#webhook_secret}" -lt 16 ]; then
    fail 'MERCADO_PAGO_WEBHOOK_SECRET debe tener al menos 16 caracteres.'
fi
case "$payment_mode" in
    test) notice 'Mercado Pago queda explícitamente en modo de prueba.' ;;
    production) ;;
    *) fail 'PAYMENT_MODE sólo admite test o production.' ;;
esac

if [ "$failures" -ne 0 ]; then
    printf '\nPreflight rechazado con %s problema(s). No inicies producción.\n' "$failures" >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    printf 'Docker no está disponible para validar docker-compose.prod.yml.\n' >&2
    exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
    printf 'Docker Compose v2 no está disponible.\n' >&2
    exit 1
fi

docker compose --env-file "$env_file" -f "$project_root/docker-compose.prod.yml" config --quiet

printf '\nPreflight de producción aprobado. No se mostraron secretos.\n'
