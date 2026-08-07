# GymTrack

GymTrack es una plataforma web para descubrir gimnasios y gestionar su operación diaria desde un único sistema. Combina una experiencia pública con mapa y filtros, un área personal para socios y una administración multi-gimnasio protegida por roles y permisos.

El proyecto mantiene el stack original: **Vue 3, PHP, MySQL, Docker, Leaflet y OpenStreetMap**.

## Qué permite hacer

### Experiencia pública

- Explorar gimnasios almacenados en MySQL.
- Buscar y filtrar por nombre, ubicación, categoría y servicios.
- Consultar gimnasios en un mapa interactivo con Leaflet y OpenStreetMap.
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
- Historial operativo y trazabilidad administrativa.
- Carga validada de imágenes y documentos fuera del webroot.
- Archivado lógico de gimnasios sin destruir su historial.

### Datos de demostración

El proyecto incluye un seeder versionado e idempotente con cinco gimnasios ficticios, perfiles, roles, clases, planes y membresías. Todos los registros quedan identificados como datos demo y pueden eliminarse sin afectar información real.

## Estado funcional

Los flujos de autenticación, roles, contexto multi-gimnasio, catálogo público, sedes, personas, entrenadores, planes, membresías e invitaciones están implementados y conectados a MySQL.

Los siguientes módulos pertenecen a las próximas etapas y no deben interpretarse como operaciones terminadas:

- Clases, cupos, reservas, lista de espera y asistencia.
- Pagos con Mercado Pago, webhooks y reembolsos.
- Finanzas, reportes y exportaciones.
- Sincronización con Google Calendar.
- Promociones y notificaciones multicanal.
- Perfil avanzado, progreso y carné digital del socio.

WhatsApp, sincronización automática con Google Calendar, analítica avanzada y recomendaciones personalizadas permanecen detrás de feature flags y se consideran funciones beta opcionales.

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

La base se crea automáticamente al iniciar con un volumen nuevo. En bases existentes deben aplicarse, en orden, las migraciones de `database/migrations/`.

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
FEATURE_PROMOTIONS_BETA=false
```

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
  sh tests/phase5_tenant_operations.sh

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
- Secretos únicamente mediante variables de entorno.

Para un despliegue real deben configurarse HTTPS, `APP_KEY`, `SESSION_SECURE=true`, correo transaccional, copias de seguridad y credenciales únicas de producción.

## Equipo

Leandro González · Santiago Cáceres · Máximo Díaz · Emilio Escobar · Magdalena Belmonti · Hiliana Pereira

---

GymTrack · Proyecto final de Tecnología Web Aplicada · 2026
