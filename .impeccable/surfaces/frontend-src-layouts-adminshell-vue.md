---
version: 1
slug: "frontend-src-layouts-adminshell-vue"
primary_target: "frontend/src/layouts/AdminShell.vue"
related_targets: []
---

# Fases 4 y 5: Administración y operación por gimnasio

Actualizado: 7 de agosto de 2026

## Resultado

GymTrack dispone de una única superficie administrativa en
`/administracion/resumen`. El panel anterior no se eliminó: quedó desconectado
y registrado en `CLEANUP_CANDIDATES.md`. `/admin` redirige al nuevo shell para
evitar dos paneles en competencia.

El alcance de esta fase es lectura operativa, navegación, contexto, permisos y
trazabilidad. No incluye el CRUD definitivo de personas o gimnasios, la agenda
fechada, pagos avanzados ni generación de archivos.

## Arquitectura

```text
AdminShell
  -> AdminNavigation
  -> GymContextSelector
  -> RouterView por módulo
       -> resumen + actividad
       -> gestión operativa + accesos rápidos
       -> recursos paginados
       -> estados honestos de fases posteriores

Vue store por dominio
  -> cliente Axios con AbortController
  -> /api/admin/*
  -> AuthMiddleware + AuthorizationService
  -> AdminRepository con scope de sesión
  -> MySQL
```

El frontend oculta destinos sin permiso para reducir ruido, pero PHP vuelve a
validar cada petición. El gimnasio no se acepta desde query string en las
lecturas: se obtiene de la sesión activa.

## Rutas y permisos

| Ruta | Capacidad principal | Estado de Fase 4 |
|---|---|---|
| `/administracion/resumen` | acceso administrativo | Resumen real y fallos por widget |
| `/administracion/gestion-operativa` | acceso administrativo | Accesos reales o deshabilitados con fase |
| `/administracion/socios` | `members.read` | Consulta paginada |
| `/administracion/empleados` | `staff.manage` | Consulta paginada |
| `/administracion/entrenadores` | `staff.manage` | Estado pendiente de modelo Fase 5 |
| `/administracion/clases` | `classes.read` | Consulta paginada |
| `/administracion/reservas` | `reservations.read` | Consulta paginada |
| `/administracion/membresias` | `memberships.read` | Consulta paginada |
| `/administracion/pagos` | `payments.read` | Pagos confirmados del esquema actual |
| `/administracion/promociones` | `gym.configure` | Beta detrás de feature flag |
| `/administracion/finanzas` | `finance.read` | Ruta visible, implementación Fase 7 |
| `/administracion/reportes` | `reports.export` | Historial real de `exports`, generación pendiente |
| `/administracion/configuracion` | `gym.configure` | Contexto y permisos de solo lectura |

## API

Endpoints nuevos:

- `GET /api/admin/context`
- `POST /api/admin/context/select`
- `GET /api/admin/summary`
- `GET /api/admin/activity`
- `GET /api/admin/permissions`
- `GET /api/admin/members`
- `GET /api/admin/staff`
- `GET /api/admin/classes`
- `GET /api/admin/reservations`
- `GET /api/admin/memberships`
- `GET /api/admin/payments`
- `GET /api/admin/exports`

Las respuestas nuevas usan este sobre:

```json
{
  "ok": true,
  "error": false,
  "data": {},
  "meta": {},
  "request_id": "uuid"
}
```

Los errores devuelven `ok=false`, código seguro, mensaje, errores de campo y el
mismo `request_id` enviado también en el header `X-Request-ID`. Los parámetros
de ordenamiento se resuelven mediante allowlists; página y tamaño tienen límites
del servidor.

`GET /api/admin/context` es la excepción lógica al requisito de gimnasio
activo: debe funcionar antes de elegirlo para listar únicamente gimnasios
permitidos. Todas las consultas de negocio exigen contexto.

## Administrador general y soporte

Una cuenta `admin_general` no recibe acceso silencioso a todos los datos. Debe:

1. Abrir Administración.
2. Elegir un gimnasio dentro del scope demo o real de su propia cuenta.
3. Escribir un motivo de al menos 8 caracteres.
4. Confirmar la entrada en modo soporte.

PHP valida el gimnasio, rota el ID de sesión y CSRF, y escribe
`admin.context.selected` en `audit_logs`. Los intentos denegados también se
registran. El cambio heredado `/api/me/gym-context` rechaza administradores para
evitar saltarse el motivo.

## Migración 004

`audit_logs` registra actor, gimnasio, acción, entidad, resultado, motivo,
before/after, IP hasheada, request ID y scope demo. `exports` prepara el
seguimiento de trabajos XLSX/PDF sin fingir que ya genera archivos.

La migración es idempotente y `004_phase4_administration.down.sql` es el
rollback destructivo controlado. El rollback no toca usuarios, gimnasios ni
datos operativos, pero elimina auditoría y exportaciones; exige respaldo.

## Estados y responsive

- Los encabezados administrativos presentan título y descripción una sola vez;
  no agregan cejas que repitan la sección o el contexto ya visible.
- Indicadores independientes: `ready`, `unavailable` o `error`. `unavailable`
  muestra `No disponible`; `error` identifica que la consulta falló.
- A 360 px los indicadores listos conservan dos columnas; cualquier fallback
  ocupa la fila completa para que su explicación mantenga ancho de lectura.
- Recursos: loading, empty, error, retry, filtros, orden y paginación.
- Tablas de escritorio se convierten en tarjetas etiquetadas en móvil.
- Sidebar permanente desde 1024 px y drawer con focus trap bajo ese ancho.
- Selector de gimnasio visible en escritorio y móvil.
- El modo soporte tiene un único badge junto al selector; el motivo se presenta
  como detalle del estado, no como una segunda insignia.
- Las acciones de fases futuras conservan su condición legible y expresan el
  disabled mediante superficie, color y cursor, sin opacidad global.
- La actividad reciente separa actor y entidad del resultado y la fecha para
  que los metadatos sigan siendo escaneables en móvil.
- La etiqueta demo no se superpone con contenido móvil.

Las capturas de 360, 390, 768, 1024, 1440 y 1920 px están en
`frontend/artifacts/phase4/`.

## Contrato de interfaz incorporado en Fase 5

- Socios, empleados, entrenadores, planes y membresías mantienen tablas densas
  y abren acciones focalizadas en drawers. Los importes usan la moneda declarada
  por cada fila; UYU es sólo el fallback cuando el contrato no trae `moneda`.
- Los permisos editables de empleados incluyen `finance.read` y
  `reports.export`. Activarlos modifica capacidades efectivas por gimnasio,
  pero no presenta como terminadas finanzas ni exportaciones de Fase 7.
- `AppCheckbox` conserva el control nativo, un label clicable de al menos 44 px,
  `required` real y error asociado mediante `aria-describedby`.
- La etiqueta “Datos de demostración” participa del flujo del documento: no es
  fija y no se superpone con navegación, formularios o acciones.
- Archivar significa retirar de navegación y catálogo, no borrar. El diálogo
  comunica la conservación del historial, exige motivo auditado y explica el
  bloqueo por membresías activas.
- La ficha pública no ofrece asociación ni checkout ficticios. Muestra inicio de
  sesión o contacto real, “Inscripciones pausadas” cuando corresponde, placeholder
  honesto si falta imagen y un error de planes independiente del error de ficha.

Las capturas finales de configuración para los seis anchos están en
`frontend/artifacts/phase5/`.

## Pruebas realizadas

- Lint PHP completo y migración 004 repetida.
- API sin sesión, CSRF, motivo de soporte, request ID y respuesta uniforme.
- RBAC de empleado, dueño y administrador general.
- Intento de seleccionar gimnasio fuera de alcance.
- Paginación y filtros del servidor.
- Vitest y build Vite.
- Playwright por roles, deep links, consola, overflow en seis anchos y axe.
- Contratos y flujos de regresión de Fases 1 a 3.

## Pendiente por diseño

- Fase 6: agenda fechada, cupos, reservas y asistencia completos.
- Fase 7: estados de pago, finanzas, gráficas, Excel y PDF.
- Fase 9: promociones y notificaciones funcionales.

La interfaz no muestra confirmaciones de estas operaciones mientras sigan
pendientes.
