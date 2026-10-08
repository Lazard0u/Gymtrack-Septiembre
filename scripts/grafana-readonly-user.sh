#!/usr/bin/env bash
# Crea (o actualiza) el usuario MySQL de solo lectura que usa Grafana.
# Sólo ve tablas sin contraseñas, sesiones ni datos de contacto.
# Uso: GRAFANA_DB_PASSWORD='...' scripts/grafana-readonly-user.sh
set -euo pipefail
: "${GRAFANA_DB_PASSWORD:?Definí GRAFANA_DB_PASSWORD}"
cd "$(dirname "$0")/.."
docker compose exec -T -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD:-root}" db mysql -uroot <<SQL
CREATE USER IF NOT EXISTS 'grafana_ro'@'%' IDENTIFIED BY '${GRAFANA_DB_PASSWORD}';
ALTER USER 'grafana_ro'@'%' IDENTIFIED BY '${GRAFANA_DB_PASSWORD}';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.gimnasios TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.gimnasio_sedes TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.clases TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.sesiones_clase TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.reservas TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.asistencias TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.membresias TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.planes_membresia TO 'grafana_ro'@'%';
GRANT SELECT ON ${DB_DATABASE:-gymtrack}.pagos TO 'grafana_ro'@'%';
SQL
echo "Usuario grafana_ro listo. En Grafana: MySQL, host db:3306, usuario grafana_ro."
