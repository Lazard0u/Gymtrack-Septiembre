# Fase 5 · Tenant y operación comercial

## Alcance terminado

La Fase 5 convierte el contexto administrativo de lectura en una operación
multi-gimnasio acotada y trazable. No cambia el stack ni duplica el panel:
Vue 3 consume la API PHP existente, PDO opera MySQL y Docker conserva el
entorno local.

Quedan funcionales:

- alta y edición de gimnasio con verificación previa a publicación;
- sedes y sincronización de la sede principal con el catálogo público;
- perfiles, estado e invitaciones de socios y empleados;
- permisos específicos de empleados por gimnasio;
- perfiles de entrenador, especialidades, disponibilidad y asociación;
- planes de membresía con versionado al cambiar precio o duración usados;
- alta y transición de membresías con historial y motivo;
- imagen pública validada y almacenada fuera del webroot;
- ficha pública con datos, sedes y planes provenientes de MySQL.

La revisión final también confirma que los importes de planes y membresías
usan la moneda de cada registro, que la etiqueta demo no flota sobre acciones y
que los checkboxes de permisos conservan un objetivo clicable mínimo de 44 px.

No pertenecen a esta fase el CRUD de clases/reservas, checkout, pagos,
finanzas, reportes, calendario ni campañas.

## Migración 005

`database/migrations/005_phase5_tenant_operations.sql` añade:

| Entidad | Propósito |
|---|---|
| `gimnasio_sedes` | Ubicaciones, contacto, horario y sede principal |
| `socio_perfiles` | Número de socio, estado, alta y notas operativas |
| `empleado_perfiles` | Cargo, estado, ingreso y notas |
| `entrenador_perfiles` | Biografía, especialidades, disponibilidad y foto futura |
| `entrenador_gimnasios` | Asociación técnica de un entrenador a uno o más gimnasios |
| `planes_membresia` | Oferta comercial versionada por gimnasio |
| `membresia_historial` | Transiciones con actor, motivo y fecha |
| `invitaciones_gimnasio` | Token hasheado, vencimiento, tipo, perfil y permisos |
| `archivos` | Metadata, hash, scope y clave de almacenamiento |

También amplía `gimnasios` con nombre legal, verificación y archivo lógico, y
`membresias` con plan, número de socio y fecha de actualización. La migración
se validó desde el esquema base, dos veces consecutivas, mediante rollback y
mediante reaplicación. El rollback es destructivo y requiere respaldo.

## Autorización y aislamiento

Todas las rutas `/api/admin/*` de esta fase verifican:

1. sesión activa y correo verificado;
2. rol administrativo permitido;
3. permiso efectivo para el gimnasio;
4. contexto de gimnasio almacenado en sesión;
5. coincidencia entre el ID de ruta y el contexto;
6. scope real/demo y `demo_dataset_id` coherentes.

El administrador general entra en modo soporte con motivo auditado. Un dueño
puede crear otro gimnasio y recibe su asociación de dueño; un empleado no puede
crear tenants. La UI oculta o deshabilita acciones según permisos, pero la
decisión final siempre pertenece a PHP.

Los permisos administrables de un empleado incluyen `finance.read` y
`reports.export`. Se guardan como overrides efectivos por gimnasio: desmarcar
una capacidad también revoca el permiso heredado para ese empleado. Esto
controla el acceso a las rutas, pero no convierte en funcionales los módulos de
Fase 7.

## Contratos API

### Público

| Método | Ruta | Resultado |
|---|---|---|
| `GET` | `/api/public/gyms` | Catálogo publicado del dataset activo |
| `GET` | `/api/public/gyms/{slug}` | Ficha y sedes activas |
| `GET` | `/api/public/gyms/{id}/plans` | Planes activos del gimnasio publicado |
| `GET` | `/api/public/files/{id}` | Sólo imagen pública activa de gimnasio |
| `POST` | `/api/invitations/accept` | Aceptación de un token válido y de un uso |

### Administración

| Dominio | Rutas |
|---|---|
| Gimnasios | `POST /gyms`, `GET/PATCH/DELETE /gyms/{id}`; DELETE archiva |
| Sedes | `GET/POST /gyms/{id}/locations`, `PATCH /gyms/{id}/locations/{locationId}` |
| Socios | `GET/PATCH /members/{userId}` más listado paginado existente |
| Empleados | `GET/PATCH /employees/{userId}` más listado paginado existente |
| Invitaciones | `GET/POST /invitations`, `DELETE /invitations/{id}` |
| Entrenadores | `GET /trainers`, `PATCH /trainers/{profileId}` |
| Planes | `GET/POST /membership-plans`, `PATCH /membership-plans/{id}` |
| Membresías | `POST /memberships`, `PATCH /memberships/{id}/status` y listado existente |
| Archivos | `POST /files`, `GET /files/{id}` |

Todas las rutas de la tabla administrativa llevan el prefijo `/api/admin`.
Listados aceptan paginación y filtros permitidos por el repositorio; no se
interpolan nombres de columnas recibidos sin allowlist.

## Reglas de negocio

- Un gimnasio sólo puede publicarse cuando `verificacion_estado=verificado`.
- Archivar es lógico, exige motivo y no se permite con membresías activas. El
  diálogo aclara que el gimnasio sale de la navegación y del catálogo, pero
  conserva el historial; no usa copy de borrado irreversible.
- La sede principal sincroniza dirección, coordenadas, contacto y horario del
  registro público del gimnasio.
- Un correo existente se asocia al gimnasio; no se crea un usuario duplicado.
- Una invitación vence a las 72 horas, guarda SHA-256 del token y sólo puede
  aceptarse una vez. La aceptación tiene rate limiting.
- Un socio sólo puede tener una membresía activa por gimnasio.
- Transiciones válidas: activa → suspendida/vencida, suspendida →
  activa/vencida y vencida → activa.
- Precio y duración de un plan ya utilizado crean una nueva versión y archivan
  la anterior; las membresías históricas conservan su referencia.
- Las tablas formatean `precio` y `precio_pagado` con `moneda` de la misma fila,
  por lo que UYU y USD no se mezclan visualmente.
- No existe aprobación de pagos en esta fase. Crear una membresía es una acción
  administrativa explícita y auditada, no un resultado de checkout.

## Archivos

`FileStorage` desacopla el controlador del almacenamiento. El adaptador inicial
`LocalFileStorage` escribe en `UPLOAD_STORAGE_PATH`, que por defecto está en el
volumen `gymtrack_uploads`. El contenedor prepara el directorio con permisos de
`www-data` antes de iniciar Apache.

Imágenes aceptadas: JPG, PNG o WebP, 64–8000 px por lado y hasta 5 MB. Los
documentos PDF están admitidos por el backend sólo en la categoría privada
`documento`; esta fase no expone todavía una pantalla documental. El nombre
original no define la ruta: se usa una clave aleatoria y se registra MIME,
tamaño y SHA-256.

## Veracidad de la ficha pública

- La ausencia de imagen se representa con un placeholder accesible; no se
  sustituye por una fotografía que parezca pertenecer al gimnasio.
- La ficha y los planes cargan como recursos separados. Si los planes fallan,
  se conservan dirección, sedes, servicios y contacto, con reintento específico
  para planes.
- Un gimnasio abierto ofrece inicio de sesión y consulta real por correo. No se
  muestra una asociación inmediata ni un checkout inexistente.
- Un gimnasio temporalmente cerrado muestra “Inscripciones pausadas” como
  acción deshabilitada y no promete completar el alta.

## Dataset de presentación

El seeder v1.2.0 completa los cinco gimnasios con nombre legal, sede principal
y verificación, crea perfiles mínimos, un entrenador, tres planes y una
membresía trazable. Sólo se ejecuta con `APP_ENV=demo` o
`SEED_DEMO_DATA=true`; producción sigue bloqueada salvo autorización explícita.

`seed:demo --reset` y `seed:demo --remove` consultan primero las claves de
archivos del dataset, eliminan sus filas dentro de la transacción y, después
del commit, retiran únicamente esos binarios. Los registros reales no se
seleccionan por nombre: se filtran mediante `is_demo` y `demo_dataset_id`.

## Evidencia

- `tests/phase5_tenant_operations.sh`: CRUD, aislamiento, token de un uso,
  login invitado, sedes, upload, planes y membresía contra MySQL real.
- Vitest: 12 pruebas, incluidos checkbox con error accesible y acción de fila.
- Playwright: configuración en 360, 390, 768, 1024, 1440 y 1920 px, sin scroll
  horizontal ni errores de consola; Axe sin hallazgos serios o críticos.
- Build Vite y lint de todos los archivos PHP.

Los candidatos completos a eliminación permanecen en `CLEANUP_CANDIDATES.md`.
No se eliminó ninguno durante esta fase.
