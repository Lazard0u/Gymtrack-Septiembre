# GymTrack · Backend

API REST desarrollada en PHP 8.2 siguiendo el patrón MVC.

## Estructura

```
backend/
├── index.php                   ← Punto de entrada único, headers CORS, autoload
├── config/
│   └── Database.php            ← Conexión PDO a MySQL (Singleton)
├── controllers/
│   ├── AuthController.php      ← Registro, login, logout
│   └── PerfilController.php    ← Ver y actualizar perfil
├── models/
│   └── Usuario.php             ← Operaciones sobre la tabla usuarios
├── middleware/
│   └── AuthMiddleware.php      ← Verificación de sesión y roles
└── routes/
    └── api.php                 ← Tabla de rutas de la API
```

## Endpoints principales

| Método | Ruta | Acceso | Descripción |
|--------|------|--------|-------------|
| POST | `/api/auth/registro` | Público | Registrar nuevo socio |
| POST | `/api/auth/login` | Público | Iniciar sesión |
| POST | `/api/auth/logout` | Público | Cerrar sesión |
| GET | `/api/perfil` | Autenticado | Ver perfil propio |
| PUT | `/api/perfil` | Autenticado | Actualizar nombre y teléfono |
| GET/POST/PUT/DELETE | `/api/clases/*` | Según rol | Consultar y gestionar clases |
| GET/POST/DELETE | `/api/reservas/*` | Autenticado | Consultar, crear y cancelar reservas |
| GET/POST | `/api/membresia/*` | Según rol | Consultar y administrar membresías |
| GET/POST/PUT | `/api/admin/*` | Administrador | Operación administrativa actual |

## Ejemplos de uso

### Registro
```json
POST /api/auth/registro
{
  "nombre": "Leandro González",
  "email": "leandro@gmail.com",
  "password": "mipass123",
  "telefono": "099123456"
}
```

### Login
```json
POST /api/auth/login
{
  "email": "leandro@gmail.com",
  "password": "mipass123"
}
```
La respuesta incluye una cookie de sesión HttpOnly y, por compatibilidad transitoria, el identificador que puede enviarse como Bearer:
```
Authorization: Bearer <token>
```

## Seguridad implementada
- **BCRYPT** con factor de coste 12 para contraseñas
- **PDO con prepared statements** en todas las consultas (anti SQL Injection)
- Cookie HttpOnly con `SameSite=Lax`; `Secure` se activa por variable de entorno en HTTPS
- **session_regenerate_id(true)** al hacer login (anti session fixation)
- Mismo mensaje de error para email inexistente y contraseña incorrecta (anti enumeración)
- Contraseñas heredadas que no sean hashes reconocidos se desactivan mediante migración
- Reservas protegidas por transacción, bloqueo de fila e índice único

## Configuración

Copiá `.env.example` a `.env`, rotá los secretos y no los subas al repositorio. `TURNSTILE_ENABLED` sólo debe estar desactivado en desarrollo controlado.
