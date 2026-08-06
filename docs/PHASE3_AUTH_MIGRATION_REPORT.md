# Informe previo de migración de identidad - Fase 3

Fecha de corte: 2026-08-06
Rama: `gymtrack-v1-fase-3`
Respaldo: `/home/gordo/.codex/backups/gymtrack/gymtrack-pre-phase3-auth-20260806.sql`

## Línea base verificada

- Build Vue y 8 pruebas unitarias: correctos.
- Contratos PHP de Fase 1: correctos dentro de la imagen del backend.
- Smoke API de login, perfil, autorización, logout y revocación: correcto.
- Seeder demo ejecutado dos veces, eliminado y recreado sin duplicados: correcto.
- E2E de línea base: no se ejecutaron porque el contenedor de desarrollo no incluía Chromium. El cierre se validó con la imagen oficial de Playwright.
- Cuentas reales preservadas: `admin@gmail.com` y `usuario@gmail.com`.
- Dataset demo preservado: cinco gimnasios, cuatro usuarios y cinco asociaciones.

## Incompatibilidades que debe resolver la migración

| Área | Estado anterior | Compatibilidad adoptada |
| --- | --- | --- |
| Sesión | `session_id()` se devuelve como token y se guarda en `localStorage`; también se acepta Bearer | Cookie HttpOnly exclusiva, registro de sesión en MySQL y eliminación de Bearer |
| Cookies | Sin expiración absoluta ni control central de producción | `SameSite=Lax`, `Secure` obligatorio en producción, expiración inactiva y absoluta |
| Revocación | Solo archivo de sesión PHP actual | Tabla `user_sessions`, revocación actual, individual y global |
| CSRF | No existe | Token ligado a sesión para todas las mutaciones autenticadas |
| Roles | `socio`, `administrador_general`, `empleado`, `dueno`; controles por IDs 1-4 | `socio`, `empleado`, `dueño`, `admin_general`; autorización por nombre y capacidad |
| Multigimnasio | Asociación mínima sin contexto activo de sesión | Contexto validado contra `usuario_gimnasio_roles`, nunca contra un `gym_id` libre del cliente |
| Permisos | Cinco permisos agregados y matriz rígida por rol | Capacidades del brief y excepciones granulares por empleado/gimnasio |
| Verificación | No existe | Tokens aleatorios, hash SHA-256 en MySQL, vencimiento y un solo uso |
| Recuperación | No existe | Solicitud no enumerativa, token de un solo uso y revocación de sesiones al cambiar contraseña |
| Registro | Un solo `nombre`, contraseña de 8 caracteres y sin consentimientos | Nombre y apellido, política de 12 caracteres, términos/privacidad obligatorios y marketing opcional |
| Turnstile | Se puede desactivar sin distinguir producción | Desactivación explícita solo fuera de producción; producción falla si falta configuración |
| Rate limiting | No existe | Persistencia por acción, IP anonimizada y cuenta normalizada, bloqueo progresivo y limpieza CLI |
| Auditoría | No existe | Eventos de seguridad y reporte de migración de roles |

## Reporte de cuentas y roles previo

| Cuenta | Rol anterior | Destino | Resolución |
| --- | --- | --- | --- |
| `admin@gmail.com` | `administrador_general` | `admin_general` | Automática; la cuenta se conserva |
| `usuario@gmail.com` | `socio` | `socio` | Automática; la cuenta se conserva |
| `socio.demo@gymtrack.local` | `socio` | `socio` | Automática, dataset demo |
| `empleado.demo@gymtrack.local` | `empleado` | `empleado` | Automática, ámbito de gimnasio |
| `dueno.demo@gymtrack.local` | `dueno` | `dueño` | Automática, ámbito de gimnasio |
| `admin.demo@gymtrack.local` | `administrador_general` | `admin_general` | Automática, dataset demo |

No se detectaron cuentas con rol `moderador` ni roles desconocidos en este corte. La migración deja una tabla de reporte y el CLI permite revisar o resolver futuras ambigüedades sin asignar privilegios por defecto.

## Decisiones de seguridad

- Las sesiones anteriores dejan de ser autoridad al no existir en `user_sessions`; el usuario debe autenticarse otra vez.
- Los administradores reales heredados quedan marcados para revisar/rotar su contraseña mediante el comando seguro de credenciales. Los usuarios demo se regeneran desde `DEMO_USER_PASSWORD` y no almacenan claves en el repositorio.
- Las cuentas existentes se consideran verificadas para no bloquear usuarios previamente admitidos. Las nuevas cuentas deben verificar su correo.
- El transporte `log` para correos solo se admite en desarrollo, demo y test. Producción requiere un transporte real configurado.
- El rollback es explícito y está destinado a una ventana controlada con respaldo. El archivo `down` no se ejecuta automáticamente.

## Resultado posterior a la migración

- Migración `003` aplicada sobre la base existente y ejecutada dos veces sobre
  una base temporal limpia sin duplicar columnas, índices, capacidades ni
  tablas.
- Rollback `003` ejecutado en la base temporal: eliminó únicamente las
  estructuras de identidad de esta migración y restauró los nombres de roles
  anteriores.
- Ninguna cuenta real ni demo fue eliminada. Las credenciales reales locales se
  rotaron fuera del repositorio y las demo siguen proviniendo exclusivamente de
  `DEMO_USER_PASSWORD`.
- Cookie HttpOnly, CSRF, verificación, recuperación, revocación, expiración,
  rate limiting y cambio de contexto fueron probados contra PHP/MySQL reales.
- La prueba de aislamiento confirmó que un socio no puede leer por ID una clase
  de otro gimnasio activo y que el backend ignora un `gimnasio_id` agregado a
  la URL.
- La suite final de navegador aprobó 19 de 19 recorridos en Chromium oficial,
  incluidos los cuatro roles demo, responsive y errores de consola.
