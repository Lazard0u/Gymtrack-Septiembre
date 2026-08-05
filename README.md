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

---

*GymTrack · Proyecto Final · Tecnología Web Aplicada · 2026*
