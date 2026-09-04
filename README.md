# GymTrack

GymTrack es una plataforma web para descubrir gimnasios y gestionar su operación diaria desde un único sistema. Combina una experiencia pública con mapa y filtros, un área personal para socios y una administración multi-gimnasio protegida por roles y permisos.

El proyecto mantiene el stack original: **Vue 3, PHP, MySQL, Docker, Leaflet y OpenStreetMap**.

La documentación académica y técnica completa, junto con el diagrama de arquitectura y el sistema de identidad visual, está en [`Gymtrack_Documentacion.md`](Gymtrack_Documentacion.md).

## Qué permite hacer

### Experiencia pública

- Explorar gimnasios almacenados en MySQL.
- Buscar y filtrar por nombre, ubicación, categoría y servicios.
- Consultar gimnasios en un mapa interactivo con Leaflet y OpenStreetMap.
- Solicitar ubicación con consentimiento, calcular distancias localmente y ordenar por cercanía sin guardar coordenadas personales.
- Filtrar por ciudad, categoría, servicios y estado, con marcadores agrupados y sincronizados con la lista.
- Usar un panel contraíble en escritorio y un bottom sheet de tres posiciones en móvil.
- Ver la ficha pública, sedes, horarios, contacto y planes disponibles.
- Navegar sin desbordamientos desde móviles de 360 px hasta escritorios amplios.

### Identidad y acceso

- Registro de socios y solicitud de alta para dueños.
- Inicio y cierre de sesión mediante cookie `HttpOnly`.
- Verificación de correo electrónico.
- Recuperación y cambio de contraseña.
- Revocación de sesiones activas.
- Protección CSRF para operaciones autenticadas.
- Roles de socio, empleado, dueño y administrador general.

### Administración

- Navegación administrativa según permisos efectivos.
- Selección segura del gimnasio activo.
- Modo soporte auditado para administradores generales.
- Gestión de información del gimnasio y sus sedes.
- Gestión de socios, empleados y entrenadores.
- Invitaciones de un solo uso con vencimiento.
- Permisos independientes por gimnasio.
- Planes de membresía versionados en UYU o USD.
- Alta, renovación y cambio de estado de membresías.
- Agenda de clases recurrentes con sesiones fechadas, sedes, entrenadores y cupos reales.
- Gestión de reservas, lista de espera con promoción automática y control de asistencia.
- Registro de pagos manuales auditados y estados pendiente, aprobado, rechazado, vencido, reembolsado o cancelado.
- Integración desacoplada con Mercado Pago mediante checkout y webhooks firmados.
- Finanzas con ingresos mensuales, comparación, deuda y agrupaciones reales.
- Reportes Excel y PDF generados desde MySQL con descargas autorizadas y vencimiento.
- Promociones con audiencia, programación, pausa, finalización, imágenes y métricas reales.
- Notificaciones internas y por correo con consentimiento, preferencias, reintentos y prevención de duplicados.
- Verificación administrativa del carné digital del socio mediante un token firmado y de corta duración.
- Historial operativo y trazabilidad administrativa.
- Carga validada de imágenes y documentos fuera del webroot.
- Archivado lógico de gimnasios sin destruir su historial.

### Área del socio

- Resumen personal con próxima clase, membresía, pagos pendientes y progreso mensual.
- Perfil con fotografía y datos personales.
- Preferencias de notificación y objetivo mensual de asistencia.
- Gimnasios y actividades favoritas persistidos en MySQL.
- Historial de asistencia, membresías, pagos y medidas opcionales.
- IMC únicamente orientativo, acompañado por una advertencia no diagnóstica.
- Carné digital con QR firmado, sin datos personales dentro del código.
- Reservas con acceso manual a Google Calendar y descarga de archivo ICS.
- Navegación inferior móvil y acciones rápidas adaptadas al rol.

### Datos de demostración

El proyecto incluye un seeder versionado e idempotente con cinco gimnasios ficticios, perfiles, roles, clases fechadas, reservas, planes, membresías y transacciones de seis meses. Todos los registros quedan identificados como datos demo y pueden eliminarse sin afectar información real.

## Estado funcional

GymTrack 1.0 tiene implementados y conectados a MySQL los flujos principales de autenticación, roles, contexto multi-gimnasio, catálogo, sedes, personas, entrenadores, planes, membresías, clases, reservas, cupos, lista de espera, asistencia, pagos, finanzas, reportes, promociones, notificaciones y experiencia del socio.

El mapa público, la geolocalización opcional, las distancias, los filtros, los favoritos y el orden por cercanía están operativos. La ubicación del visitante se procesa sólo en el navegador. Google Calendar funciona mediante enlace manual e ICS; la sincronización automática con OAuth no forma parte del flujo estable.

Funciones beta opcionales, desactivadas por defecto:

- WhatsApp: existe un adaptador que falla de forma segura; falta configurar un proveedor real.
- Sincronización automática con Google Calendar: funcionan el enlace manual y el ICS; falta OAuth bidireccional.
- Analítica avanzada: funcionan las métricas financieras y operativas estables; no se ofrecen predicciones.
- Recomendaciones personalizadas: funcionan favoritos y preferencias; no hay recomendaciones automatizadas.

Ninguna función beta confirma acciones que no se hayan realizado ni bloquea los flujos estables.

## Tecnologías

| Capa | Tecnología |
|---|---|
| Frontend | Vue 3, Vite, Pinia, Vue Router, Axios |
| Backend | PHP 8.2, PDO, API REST |
| Base de datos | MySQL 8 |
| Mapas | Leaflet y OpenStreetMap |
| Infraestructura | Docker y Docker Compose |
| Pruebas | Vitest, Playwright y scripts de contratos API |

## Estructura

```text
Gymtrack-Agosto/
├── backend/          API, controladores, modelos, servicios y seeders
├── database/         esquema inicial y migraciones versionadas
├── frontend/         aplicación Vue, componentes, vistas y pruebas E2E
├── tests/            contratos y pruebas de integración del backend
├── docker-compose.yml
└── .env.example
```

## Instalación local

### Requisitos

- Docker con Docker Compose.
- Git.

### Inicio rápido

```bash
git clone https://github.com/Lazard0u/Gymtrack-Agosto.git
cd Gymtrack-Agosto
cp .env.example .env
docker compose up -d --build
docker compose ps
```

Servicios disponibles:

| Servicio | Dirección |
|---|---|
| Frontend | http://localhost:5173 |
| Backend | http://localhost:8080 |
| MySQL | localhost:3306 |

### Verificación de correo

Las cuentas nuevas se crean con el correo pendiente. PHP genera un token aleatorio de un solo uso, guarda únicamente su hash y envía un enlace construido desde `FRONTEND_URL`. El enlace vence de forma predeterminada en 24 horas y las rutas protegidas permanecen cerradas hasta completar la verificación.

En desarrollo, `MAIL_TRANSPORT=log` funciona como un buzón local privado y permite inspeccionar el último mensaje con `php console.php auth:mail:latest correo@ejemplo.test`. En producción, PHPMailer construye el correo HTML y su alternativa de texto; `MAIL_TRANSPORT=mail` lo entrega al agente SMTP configurado en el contenedor.

```dotenv
FRONTEND_URL=https://aplicacion.tu-dominio.com
MAIL_TRANSPORT=mail
MAIL_FROM=no-reply@tu-dominio.com
MAIL_FROM_NAME=GymTrack
EMAIL_VERIFICATION_TTL_SECONDS=86400
```

Los secretos y credenciales SMTP no se escriben en estas variables: producción recibe la configuración de `msmtp` mediante `SMTP_CONFIG_FILE`, fuera de Git.

PHPMailer se instala de forma reproducible desde `backend/composer.lock`. Docker ejecuta `composer install` al iniciar el backend local y durante la construcción de la imagen de producción. Para instalarlo fuera de Docker:

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

Para enviar directamente con una cuenta personal de Gmail durante la presentación, activá la verificación en dos pasos y generá una contraseña de aplicación. Guardá esa contraseña únicamente en el `.env` local:

```dotenv
MAIL_TRANSPORT=smtp
MAIL_FROM=tu-cuenta@gmail.com
MAIL_FROM_NAME=GymTrack
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=tu-cuenta@gmail.com
SMTP_PASSWORD=contraseña-de-aplicación
SMTP_ENCRYPTION=tls
```

`MAIL_FROM` y `SMTP_USERNAME` deben coincidir, salvo que Gmail tenga configurado un alias autorizado. Después de cambiar el entorno, recreá el backend con `docker compose up -d --force-recreate backend`. Nunca uses la contraseña normal de la cuenta ni publiques el `.env`.

La base se crea automáticamente al iniciar con un volumen nuevo. En bases existentes deben aplicarse, en orden, las migraciones de `database/migrations/`.

La agenda operativa requiere la migración `006_class_schedule_booking_attendance.sql`; pagos, finanzas y reportes requieren `007_payments_finance_reports.sql`; promociones, calendario y notificaciones requieren `008_phase9_calendar_promotions_notifications.sql`; el área personal del socio requiere `009_phase10_member_experience.sql`; y el país, los estados operativos y la geocodificación de gimnasios requieren `010_gimnasios_mapa_sincronizado.sql`.

Cada migración dispone de una reversión controlada en su archivo `.down.sql`. Antes de aplicar o revertir migraciones en una base existente, creá y verificá un respaldo.

## Mapa y geocodificación

La portada y la sección Gimnasios consultan el mismo catálogo público de MySQL. Sólo aparecen gimnasios verificados con estado activo o cierre temporal y coordenadas válidas. Los registros inactivos, en borrador o archivados quedan fuera tanto de la lista como de los marcadores.

Administración permite buscar una dirección bajo demanda, revisar el resultado en Leaflet y corregir el marcador arrastrándolo antes de guardar. También acepta coordenadas manuales cuando el proveedor no responde. Configuración local:

```dotenv
GEOCODING_ENABLED=true
GEOCODING_PROVIDER_URL=https://nominatim.openstreetmap.org/search
GEOCODING_USER_AGENT="GymTrack/1.0 (correo-real@tu-dominio.com)"
GEOCODING_CACHE_DAYS=30
```

La implementación usa un proxy PHP con caché, limita las consultas externas a una por segundo y no ofrece autocompletado. Para una instalación con tráfico significativo debe configurarse un proveedor geográfico contratado o una instancia propia mediante `GEOCODING_PROVIDER_URL`.

## Configurar pagos

Sin credenciales la aplicación permite consultar planes, pagos y finanzas, pero no muestra un checkout funcional. Para habilitar Mercado Pago agregá en `.env`:

```dotenv
APP_URL=https://api.tu-dominio.com
PAYMENT_PROVIDER=mercado_pago
PAYMENT_MODE=test
MERCADO_PAGO_ACCESS_TOKEN=
MERCADO_PAGO_WEBHOOK_SECRET=
```

El webhook debe apuntar a `https://api.tu-dominio.com/api/webhooks/mercado-pago`. Usá credenciales de prueba con `PAYMENT_MODE=test` y credenciales productivas sólo después de configurar HTTPS y validar la firma. Volver a la URL de éxito no aprueba pagos: GymTrack consulta al proveedor y procesa únicamente un webhook firmado.

## Cuenta administrativa

Las contraseñas no se guardan en Git. Para crear o regenerar la cuenta administrativa inicial:

```bash
docker compose exec \
  -e ADMIN_EMAIL=admin@tu-dominio.com \
  -e ADMIN_INITIAL_PASSWORD='una-clave-larga-y-unica' \
  backend php seed_admin.php
```

## Activar el modo demostración

Configurá valores locales en `.env`:

```dotenv
APP_ENV=demo
SEED_DEMO_DATA=true
DEMO_DATASET_NAME=gymtrack-presentation
DEMO_USER_PASSWORD=elige-una-clave-local-segura
ALLOW_DEMO_DATA_IN_PRODUCTION=false
```

Después recreá el backend y ejecutá el seeder:

```bash
docker compose up -d --force-recreate backend
docker compose exec -T backend php console.php seed:demo
```

Cuentas creadas:

| Rol | Correo |
|---|---|
| Socio | `socio.demo@gymtrack.local` |
| Empleado | `empleado.demo@gymtrack.local` |
| Dueño | `dueno.demo@gymtrack.local` |
| Administrador general | `admin.demo@gymtrack.local` |

Todas utilizan el valor local de `DEMO_USER_PASSWORD`. La contraseña se almacena con `password_hash` y nunca se imprime ni se versiona.

Comandos disponibles:

```bash
# Crear o actualizar sin duplicados
docker compose exec -T backend php console.php seed:demo

# Regenerar el dataset y las contraseñas
docker compose exec -T backend php console.php seed:demo --reset

# Consultar el estado
docker compose exec -T backend php console.php seed:demo --status

# Eliminar exclusivamente los datos demo
docker compose exec -T backend php console.php seed:demo --remove
```

El seeder bloquea su ejecución accidental en producción salvo autorización explícita. Los datos demo son ficticios y nunca deben utilizarse como información real.

## Feature flags

Las integraciones incompletas están desactivadas por defecto:

```dotenv
FEATURE_WHATSAPP=false
FEATURE_GOOGLE_CALENDAR_SYNC=false
FEATURE_ADVANCED_ANALYTICS=false
FEATURE_PERSONAL_RECOMMENDATIONS=false
```

Promociones y notificaciones internas o por correo son funciones estables y no dependen de una bandera beta.

## Despliegue de producción

El despliegue estable utiliza imágenes separadas para PHP/Apache y Vue/Nginx, un único origen público, servicios internos no expuestos, contenedores de sólo lectura, volúmenes persistentes, comprobación de migraciones y workers independientes.

1. Creá un archivo `.env.production` fuera de Git con permisos `600` o `640`.
2. Configurá URLs HTTPS, secretos únicos, Turnstile, correo SMTP, MySQL y Mercado Pago.
3. Guardá la configuración de `msmtp` en un archivo privado y asigná su ruta a `SMTP_CONFIG_FILE`.
4. Ejecutá el preflight. Si falta una condición de seguridad, el despliegue se detiene.
5. Construí e iniciá la composición de producción.

```bash
chmod 600 .env.production /ruta/privada/msmtprc
scripts/production-preflight.sh .env.production
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d
```

El archivo de producción exige `SEED_DEMO_DATA=false`, `ALLOW_DEMO_DATA_IN_PRODUCTION=false`, cookies seguras, credenciales no triviales y todas las funciones beta desactivadas. El servicio `schema-check` rechaza el arranque si falta una migración requerida. La terminación TLS debe situarse delante de Nginx y conservar `X-Forwarded-Proto=https`.

## Pruebas

```bash
# Componentes frontend
docker compose exec -T frontend npm run test:run

# Build de producción
docker compose exec -T frontend npm run build

# Sintaxis PHP
docker compose exec -T backend sh -lc \
  'find /var/www/html -name "*.php" -print0 | xargs -0 -n1 php -l'

# Integración de la operación multi-gimnasio
DEMO_USER_PASSWORD='valor-definido-en-tu-entorno' \
  sh tests/tenant_operations.sh

# Clases, cupos, reservas, espera, asistencia e idempotencia
DEMO_USER_PASSWORD='valor-definido-en-tu-entorno' \
  sh tests/schedule_booking_attendance.sh

# Pagos, finanzas, exportaciones, webhook e aislamiento
DEMO_USER_PASSWORD='valor-definido-en-tu-entorno' \
  sh tests/payments_finance_reports.sh

# Catálogo geográfico y datos reales del mapa
sh tests/map_geolocation.sh

# Calendario, promociones, consentimiento y notificaciones
DEMO_USER_PASSWORD='valor-definido-en-tu-entorno' \
  sh tests/promociones_notificaciones.sh

# Perfil, preferencias, favoritos, progreso y carné digital
DEMO_USER_PASSWORD='valor-definido-en-tu-entorno' \
  sh tests/miembro_http.sh

# Contratos de producción y seguridad
php tests/production_infrastructure_contracts.php
php tests/security_http_status_contracts.php
php tests/security_concurrency_contracts.php

# Auditoría de dependencias utilizadas en runtime
docker compose exec -T frontend npm audit --omit=dev --audit-level=high

# Navegación, responsive, accesibilidad y consola
docker run --rm --network host \
  -v "$PWD/frontend:/work" \
  -v gymtrack-final1_frontend_node_modules:/work/node_modules \
  -w /work \
  -e VITE_API_PROXY_TARGET=http://127.0.0.1:8080 \
  -e DEMO_USER_PASSWORD='valor-definido-en-tu-entorno' \
  mcr.microsoft.com/playwright:v1.62.1-noble \
  npx playwright test
```

## Seguridad

- Consultas preparadas mediante PDO.
- Contraseñas almacenadas con `password_hash`.
- Cookies de sesión `HttpOnly`, `SameSite` y `Secure` en producción.
- Protección CSRF y autorización comprobada en PHP.
- Aislamiento de datos por gimnasio y defensa contra IDOR.
- Tokens de invitación y recuperación almacenados como hash y de un solo uso.
- Validación de MIME, tamaño y dimensiones en archivos subidos.
- Archivos privados fuera del webroot.
- Rate limiting en flujos sensibles.
- Auditoría con identificadores de solicitud.
- Consentimiento versionado y baja publicitaria mediante token de un solo uso.
- Carnés digitales firmados, acotados por alcance y con vencimiento breve.
- Referencias externas únicas, idempotencia y eventos de pago.
- Firma HMAC y consulta directa al proveedor antes de aprobar webhooks.
- Secretos únicamente mediante variables de entorno.

Para un despliegue real deben configurarse terminación HTTPS, copias de seguridad verificadas, monitoreo externo, rotación de secretos y credenciales únicas de producción.

## Equipo

Leandro González · Santiago Cáceres · Máximo Díaz · Emilio Escobar · Magdalena Belmonti · Hiliana Pereira

---

GymTrack · Proyecto final de Tecnología Web Aplicada · 2026
