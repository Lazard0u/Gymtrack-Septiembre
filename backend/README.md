# GymTrack · Backend

API REST desarrollada en PHP 8.2 siguiendo el patrón MVC.

## Estructura

```
backend/
├── index.php                   ← Punto de entrada único, headers CORS, autoload
├── config/
│   └── Database.php            ← Conexión PDO a MySQL (Singleton)
├── controllers/
│   ├── AuthController.php      ← Registro, login, correo y contraseñas
│   ├── MeController.php        ← Perfil de sesión, contexto y revocación
│   ├── PerfilController.php    ← Ver y actualizar perfil
│   ├── PublicGymController.php ← Catálogo público de sólo lectura
│   └── SystemController.php    ← Dataset activo y feature flags públicas
├── models/
│   ├── Usuario.php             ← Usuarios, rol y contextos de gimnasio
│   ├── Gimnasio.php            ← Lectura pública mínima de gimnasios
│   └── SystemContext.php       ← Estado seguro del entorno de presentación
├── middleware/
│   └── AuthMiddleware.php      ← Sesión, CSRF, correo, permisos y tenant
├── services/                   ← Sesión, tokens, correo, rate limit y autorización
└── routes/
    └── api.php                 ← Tabla de rutas de la API
```

## Endpoints principales

| Método | Ruta | Acceso | Descripción |
|--------|------|--------|-------------|
| POST | `/api/auth/registro` | Público | Registrar nuevo socio |
| POST | `/api/auth/registro-dueno` | Público | Solicitar alta de dueño |
| POST | `/api/auth/login` | Público | Iniciar sesión |
| POST | `/api/auth/logout` | Sesión + CSRF | Cerrar sesión actual |
| POST | `/api/auth/email/verify` | Público | Consumir token de verificación |
| POST | `/api/auth/email/resend` | Público | Reenviar verificación sin enumerar cuentas |
| POST | `/api/auth/password/forgot` | Público | Solicitar recuperación no enumerativa |
| POST | `/api/auth/password/reset` | Público | Restablecer con token de un uso |
| POST | `/api/auth/password/change` | Sesión + CSRF | Cambiar contraseña autenticada |
| GET | `/api/auth/session` | Público | Consultar sesión sin generar 401 en navegación pública |
| GET | `/api/me` | Sesión | Usuario, rol, capacidades y gimnasio activo |
| GET | `/api/me/sessions` | Sesión verificada | Listar sesiones activas |
| DELETE | `/api/me/sessions/{uuid}` | Sesión + CSRF | Revocar otra sesión |
| POST/DELETE | `/api/me/gym-context` | Sesión + CSRF | Cambiar o limpiar contexto activo |
| GET | `/api/perfil` | Autenticado | Ver perfil propio |
| PUT | `/api/perfil` | Autenticado | Actualizar nombre y teléfono |
| GET | `/api/public/gimnasios` | Público | Listar gimnasios publicados desde MySQL |
| GET | `/api/system/context` | Público | Consultar dataset demo y feature flags no sensibles |
| GET/POST/PUT/DELETE | `/api/clases/*` | Según rol | Consultar y gestionar clases |
| GET/POST/DELETE | `/api/reservas/*` | Autenticado | Consultar, crear y cancelar reservas |
| GET/POST | `/api/membresia/*` | Según rol | Consultar y administrar membresías |
| GET/POST/PUT | `/api/admin/*` | Administrador | Operación administrativa actual |

## Ejemplos de uso

### Registro
```json
POST /api/auth/registro
{
  "nombre": "Leandro",
  "apellido": "González",
  "email": "leandro@example.test",
  "password": "una-clave-local-de-12-o-mas",
  "password_confirmation": "una-clave-local-de-12-o-mas",
  "terms_accepted": true,
  "privacy_accepted": true,
  "marketing_accepted": false
}
```

### Login
```json
POST /api/auth/login
{
  "email": "leandro@example.test",
  "password": "una-clave-local-de-12-o-mas"
}
```
La respuesta instala una cookie HttpOnly y devuelve el contexto de usuario y un
token CSRF. No devuelve token de autenticación y no existe compatibilidad
Bearer.

## Seguridad implementada
- **BCRYPT** con factor de coste 12 para contraseñas
- **PDO con prepared statements** en todas las consultas (anti SQL Injection)
- Cookie HttpOnly con `SameSite=Lax`; `Secure` es obligatorio en producción
- Sesiones opacas persistidas en MySQL, rotación al cambiar de gimnasio y revocación individual/global
- CSRF obligatorio para `POST`, `PUT`, `PATCH` y `DELETE` autenticados
- Mismo mensaje de error para email inexistente y contraseña incorrecta (anti enumeración)
- Tokens aleatorios con sólo el hash SHA-256 almacenado, vencimiento y un único uso
- Rate limiting persistente para login, registro, correo y recuperación
- Capacidades por rol y filtro de gimnasio activo desde la sesión, no desde parámetros del cliente
- Eventos de seguridad sin contraseñas, tokens ni secretos
- Reservas protegidas por transacción, bloqueo de fila e índice único

## Configuración

Copiá `.env.example` a `.env`, rotá los secretos y no los subas al repositorio.
`APP_KEY`, `SESSION_*`, `TURNSTILE_*`, `MAIL_*` y `FRONTEND_URL` configuran
identidad. `TURNSTILE_ENABLED=false` y `MAIL_TRANSPORT=log` sólo son válidos en
desarrollo, demo o test; producción falla de forma cerrada si falta la
protección o el transporte real.

Los roles canónicos son `socio`, `empleado`, `dueño` y `admin_general`. Usá
`php console.php roles:report`, `roles:ambiguous` y `roles:resolve` para auditar
una migración sin depender de IDs rígidos. `auth:cleanup` elimina límites y
tokens vencidos; debe programarse como tarea de mantenimiento.

## Dataset demo

La consola `php console.php seed:demo [--reset|--remove|--status]` gestiona un
dataset idempotente y aislado mediante `is_demo` y `demo_dataset_id`. La creación
requiere `APP_ENV=demo` o `SEED_DEMO_DATA=true`, además de una
`DEMO_USER_PASSWORD` de 12 caracteres o más. En producción se bloquea salvo
autorización explícita. Consultá el README principal para el procedimiento y las
cuentas disponibles.
