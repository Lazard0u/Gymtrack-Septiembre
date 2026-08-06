# GymTrack · Sistema de Gestión de Gimnasio

> Vue 3 · PHP · MySQL / MariaDB · Docker  
> Tecnología Web Aplicada · Año lectivo 2026

**Integrantes:** Leandro González · Santiago Cáceres · Máximo Díaz · Emilio Escobar · Magdalena Belmonti · Hiliana Pereira

---

## Estructura del proyecto

```
gymtrack/
├── docker-compose.yml          ← Levanta todo el entorno con un solo comando
├── database/
│   └── gymtrack_database.sql   ← Esquema completo de la base de datos
├── backend/                    ← API REST en PHP (PDO + MVC)
└── frontend/                   ← SPA en Vue 3 + Vite + Pinia
```

---

## Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y corriendo
- Git

---

## Cómo levantar el entorno

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/gymtrack.git
cd gymtrack

# 2. Crear la configuración local y cambiar sus secretos
cp .env.example .env

# 3. Levantar todos los contenedores
docker compose up -d --build

# 4. Verificar que todo esté corriendo
docker compose ps
```

Con eso tenés disponible:

| Servicio   | URL / Puerto                  |
|------------|-------------------------------|
| Frontend   | http://localhost:5173         |
| Backend    | http://localhost:8080         |
| Base de datos | localhost:3306             |

> **Nota:** La base de datos se crea automáticamente al levantar un volumen nuevo. En una base existente, aplicá en orden los archivos de `database/migrations/`.

---

## Configuración y cuenta administrativa

Las credenciales se definen en `.env`, que no debe versionarse. El repositorio no incluye una contraseña administrativa conocida. Para crear o rotar la cuenta inicial:

```bash
docker compose exec \
  -e ADMIN_EMAIL=admin@tu-dominio.com \
  -e ADMIN_INITIAL_PASSWORD='una-clave-larga-y-unica' \
  backend php seed_admin.php
```

## Modo demostración para presentaciones

El modo demostración usa un seeder versionado (`v1.0.0`) y es opt-in: no se ejecuta al iniciar Docker ni durante una
migración. Los cinco gimnasios y sus usuarios se guardan en MySQL, están
marcados con `is_demo = true` y pertenecen al dataset indicado por
`DEMO_DATASET_NAME`. La interfaz muestra la etiqueta discreta **Datos de
demostración** mientras el dataset está activo.

### Activarlo y crear los cinco gimnasios

1. Aplicá las migraciones si trabajás sobre una base ya existente:

   ```bash
   docker compose exec -T db sh -lc \
     'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
     < database/migrations/002_phase3_roles_demo_context.sql
   ```

2. Definí en tu `.env` una contraseña local de al menos 12 caracteres. No la
   agregues a Git:

   ```dotenv
   APP_ENV=demo
   SEED_DEMO_DATA=true
   DEMO_DATASET_NAME=gymtrack-presentation
   DEMO_USER_PASSWORD=elige-una-clave-local-segura
   ALLOW_DEMO_DATA_IN_PRODUCTION=false
   ```

3. Recreá el backend para cargar el entorno y ejecutá el seeder:

   ```bash
   docker compose up -d --force-recreate backend
   docker compose exec -T backend php console.php seed:demo
   ```

El comando crea o actualiza, sin duplicar registros, `GymTrack Centro`, `Norte
Fitness Club`, `Titan Training`, `Punto Activo` y `Arena Functional Gym`. Sus
direcciones, teléfonos, correos, imágenes y escenarios son ficticios. La Fase 3
sólo incorpora la estructura mínima de roles y contexto de gimnasio; el CRUD y
el detalle operativo completo corresponden a la Fase 5. Por ese motivo, el
panel administrativo de una cuenta demo es de sólo lectura: permite presentar
datos, roles y navegación, pero oculta y bloquea altas, ediciones y
cancelaciones. Sus consultas tampoco mezclan cuentas reales con el dataset.

### Credenciales demo

Las cuentas son:

| Rol | Correo |
|---|---|
| Socio | `socio.demo@gymtrack.local` |
| Empleado | `empleado.demo@gymtrack.local` |
| Dueño | `dueno.demo@gymtrack.local` |
| Administrador general | `admin.demo@gymtrack.local` |

Todas usan el valor actual de `DEMO_USER_PASSWORD`. La contraseña se almacena
con `password_hash`/bcrypt y nunca se imprime ni se versiona. Para regenerarla,
cambiá la variable y ejecutá `seed:demo --reset`.

### Administrar o eliminar el dataset

```bash
# Crear o actualizar de forma idempotente
docker compose exec -T backend php console.php seed:demo

# Regenerar los registros y hashes del dataset
docker compose exec -T backend php console.php seed:demo --reset

# Mostrar estado, cantidades y correos (nunca la contraseña)
docker compose exec -T backend php console.php seed:demo --status

# Eliminar exclusivamente los registros del dataset seleccionado
docker compose exec -T backend php console.php seed:demo --remove
```

En desarrollo también se pueden pasar las variables sólo al comando con
`docker compose exec -e ...`, sin guardarlas en `.env`. El seeder exige
`APP_ENV=demo` o `SEED_DEMO_DATA=true`. Si detecta `APP_ENV=production`, muestra
una advertencia y bloquea la creación salvo que exista una autorización
deliberada con `ALLOW_DEMO_DATA_IN_PRODUCTION=true`.

Los datos demo nunca deben usarse en producción: son ficticios, comparten una
contraseña controlada por el presentador y representan estados diseñados para
probar la interfaz, no información contractual ni transacciones reales. La
protección por entorno y el etiquetado no sustituyen ese aislamiento.

### Funciones estables y beta

En el alcance actual son estables el inicio de sesión, cierre de sesión,
perfiles básicos, roles, permisos mínimos, contexto de gimnasio, lectura del
catálogo desde MySQL, filtros y navegación/mapa de presentación. Los CRUD
operativos, pagos y módulos de fases posteriores no se presentan como
operaciones terminadas.

Sólo se muestran funciones beta cuando su feature flag está activa. Cada bloque
explica qué funciona y qué falta, y no ofrece acciones que confirmen operaciones
inexistentes:

| Función beta | Feature flag | Disponible hoy | Pendiente |
|---|---|---|---|
| WhatsApp | `FEATURE_WHATSAPP` | Información del canal | Adaptador, consentimiento, envíos y reintentos |
| Google Calendar automático | `FEATURE_GOOGLE_CALENDAR_SYNC` | Descripción del alcance | OAuth y sincronización crear/actualizar/cancelar |
| Analítica avanzada | `FEATURE_ADVANCED_ANALYTICS` | Presentación del módulo | Métricas operativas reales y comparativas |
| Recomendaciones personalizadas | `FEATURE_PERSONAL_RECOMMENDATIONS` | Presentación del módulo | Modelo y señales verificadas |
| Promociones | `FEATURE_PROMOTIONS_BETA` | Escenario demo identificado | CRUD, programación, audiencia y resultados |

Las flags están en `false` por defecto. La aplicación completa no se etiqueta
como beta.

---

## Stack tecnológico

| Capa       | Tecnología                          |
|------------|-------------------------------------|
| Frontend   | Vue 3 · Vite · Pinia · Vue Router · Axios |
| Backend    | PHP 8.2 · PDO · MVC · API REST      |
| Base de datos | MySQL 8.0                        |
| Entorno    | Docker · Docker Compose             |

---

## Comandos útiles

```bash
# Detener los contenedores
docker compose down

# Ver logs del backend
docker compose logs backend

# Ver logs de la base de datos
docker compose logs db

# Acceder a MySQL desde la terminal
docker compose exec db sh -lc 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

## Verificación de la Fase 1

```bash
php tests/phase1_contracts.php
TEST_EMAIL='socio@ejemplo.com' TEST_PASSWORD='clave-local' sh tests/api_smoke.sh
docker compose config --quiet
docker compose run --rm frontend npm run build
```

## Verificación de la Fase 2

```bash
# Componentes, formularios y overlays
docker compose exec -T frontend npm run test:run

# Build productivo
docker compose exec -T frontend npm run build

# Responsive, teclado, consola y accesibilidad en el contenedor oficial
docker volume create gymtrack_playwright_modules
docker run --rm --ipc=host \
  -v "$PWD/frontend:/app" \
  -v gymtrack_playwright_modules:/app/node_modules \
  -w /app mcr.microsoft.com/playwright:v1.62.1-noble \
  sh -lc 'npm ci --silent && npm run test:e2e'
```

Las capturas de referencia para 360, 390, 768, 1024, 1440 y 1920 px se guardan en `frontend/artifacts/phase2/`.

## Verificación de la Fase 3

```bash
# Seeder, aislamiento, idempotencia, roles y login por API
DEMO_USER_PASSWORD='la-clave-definida-en-tu-entorno' sh tests/phase3_demo_seed.sh

# Componentes y build
docker compose exec -T frontend npm run test:run
docker compose exec -T frontend npm run build

# Flujos de los cuatro roles y responsive contra la API real
docker volume create gymtrack_phase3_playwright_modules
docker run --rm --ipc=host \
  --add-host=host.docker.internal:host-gateway \
  -e VITE_API_PROXY_TARGET=http://host.docker.internal:8080 \
  -e DEMO_USER_PASSWORD='la-clave-definida-en-tu-entorno' \
  -v "$PWD/frontend:/app" \
  -v gymtrack_phase3_playwright_modules:/app/node_modules \
  -w /app mcr.microsoft.com/playwright:v1.62.1-noble \
  sh -lc 'npm ci --silent && npm run test:e2e'
```

Las capturas de esta fase se guardan en `frontend/artifacts/phase3/`. El test no
incluye ninguna contraseña fija: la recibe únicamente desde el entorno.

---

*GymTrack · Proyecto Final · Tecnología Web Aplicada · 2026*
