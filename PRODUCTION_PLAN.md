# GymTrack 1.0: auditoría y plan de producción

Fecha de auditoría: 5 de agosto de 2026

Repositorio auditado: `gymtrack-final1`

Alcance original: análisis solamente. Las secciones de estado posteriores registran la implementación aprobada por fases.
Stack que se conserva: Vue 3, PHP, MySQL, Docker, Leaflet y OpenStreetMap.

## 1. Decisión ejecutiva

**Estado actual: NO-GO para producción.** GymTrack compila, pero hoy es un prototipo visual con una parte administrativa incompleta. Los flujos principales no forman un sistema consistente de extremo a extremo. El bloqueo más inmediato es que el login devuelve un token, pero las peticiones autenticadas responden `401` en el entorno Docker actual. También existen contratos incompatibles entre controladores y modelos PHP, datos de demostración presentados como métricas reales, un rol de dueño que no puede acceder a la administración y un esquema sin entidad `gimnasios` ni separación de datos por gimnasio.

La recomendación es evolucionar el monolito actual, no reescribirlo. La ruta más segura es estabilizar primero los contratos existentes, introducir migraciones y tenancy por gimnasio, completar los flujos operativos, y recién después integrar pagos y comunicaciones externas.

### Puntuación de calidad de interfaz

Escala de 0 a 4 por dimensión, según la auditoría de Impeccable:

| Dimensión | Puntaje | Diagnóstico |
|---|---:|---|
| Accesibilidad | 1/4 | Existen etiquetas básicas, pero faltan foco visible consistente, semántica de diálogo, navegación por teclado, anuncios de estado y reducción de movimiento. |
| Rendimiento | 2/4 | El bundle principal es moderado, pero se cargan recursos CDN, imágenes remotas, Leaflet global y animaciones sin control. No hay medición ni presupuesto. |
| Responsive | 1/4 | Hay breakpoints, pero son contradictorios. La cuadrícula de funciones fuerza cuatro columnas en móvil y la administración carece de adaptación completa. |
| Theming | 1/4 | Hay algunos tokens, pero predominan colores directos y sólo existe modo oscuro. Los estados semánticos no están sistematizados. |
| Integridad de implementación | 0/4 | Autenticación real rota, rutas desconectadas, acciones sin evento, endpoints inexistentes y datos demo en paneles productivos. |
| **Total** | **5/20** | **Crítico. Requiere remediación antes de una beta operativa.** |

## 2. Método, evidencia y límites

Se inspeccionaron todos los archivos del repositorio: vistas Vue, router, stores, cliente HTTP, datos demo, controladores, modelos, middleware, router PHP, SQL, Docker y documentación. También se hicieron comprobaciones no destructivas contra los contenedores existentes.

### Comprobaciones ejecutadas

| Comprobación | Resultado |
|---|---|
| `docker compose config --quiet` | Correcto. La sintaxis de Compose es válida. |
| Sintaxis de todos los archivos PHP con PHP 8.2 | Correcta. No hay errores de parseo. |
| `npm ci && npm run build` en contenedor limpio | Correcto. Vite compila 96 módulos; JS 188,45 kB y CSS 41,83 kB antes de gzip. |
| Auditoría npm de producción | 2 vulnerabilidades altas: `axios` 1.16.1 y `postcss` 8.5.16 en los rangos reportados. |
| Prueba real de login y perfil | Login responde éxito, pero el mismo token obtiene `401` en `/api/perfil`. |
| Datos reales del MySQL en ejecución | 2 usuarios; 0 membresías, clases, reservas, pagos, notificaciones y asistencias. |
| Hash del usuario administrador sembrado | No es bcrypt. La contraseña de ejemplo está en texto plano y el login conserva compatibilidad insegura. |
| Captura automatizada a 360, 390, 768, 1024, 1440 y 1920 px | El primer frame muestra el fondo antes del contenido animado. Sirve como evidencia de una primera pintura frágil, no como prueba de que la pantalla quede vacía permanentemente. |

No se recibieron archivos de captura adjuntos accesibles dentro de este turno ni existen capturas raster en el repositorio. Por eso, las observaciones visuales se basan en el código, en el render local temporal y en los problemas descritos en el encargo. Cuando se aporten las capturas originales, debe hacerse una comparación visual adicional sin alterar la prioridad de los fallos funcionales ya confirmados.

## 3. Inventario actual

### Frontend

- Vue 3 con Composition API, Vite, Vue Router, Pinia y Axios.
- Rutas activas: `/`, `/login`, `/registro`, `/dashboard` y `/admin`.
- Vistas no conectadas: `ClassesView.vue`, `admin/AdminDashboard.vue`, `admin/AdminUsers.vue` y `admin/AdminClasses.vue`.
- `HomeView.vue`, `DashboardView.vue` y `AdminView.vue` concentran demasiadas responsabilidades y CSS local.
- Leaflet se carga como script global desde un CDN, no como dependencia encapsulada.
- `demoData.js` contiene gimnasios, clases, crecimiento e ingresos simulados usados por los paneles.
- No existe suite de componentes, documentación de estados, tests, lint ni análisis de tipos.
- `swiper` está declarado pero no se usa. El `counter.js` de plantilla tampoco se usa.

### Backend

- PHP 8 con un router frontal en `backend/index.php`, controladores, modelos PDO y middleware propio.
- Endpoints básicos de autenticación, perfil, clases, reservas, membresías y administración.
- El token es en realidad un ID de sesión PHP que se guarda en `localStorage`.
- El servidor Apache actual no entrega correctamente `Authorization` a PHP; por eso el middleware no recupera el token.
- No hay capa de servicios, transacciones de negocio, respuestas de error uniformes, paginación ni validación declarativa.
- Los controladores de clases, reservas y membresías invocan métodos inexistentes o con firmas incompatibles en sus modelos.

### Base de datos

Tablas existentes: `roles`, `usuarios`, `membresias`, `clases`, `reservas`, `pagos`, `calificaciones`, `notificaciones` y `asistencias`.

Faltan, entre otras, las entidades de gimnasios, vinculación usuario-gimnasio, planes de membresía, sesiones fechadas de clases, lista de espera, eventos de pago, reembolsos, promociones, preferencias, consentimiento, calendario, archivos, trabajos en cola y auditoría. El único SQL es un bootstrap monolítico, no una secuencia de migraciones versionadas. La siembra de roles no es idempotente.

### Docker y configuración

- Un contenedor de Vite en modo desarrollo, Apache/PHP y MySQL.
- Credenciales de base de datos fijas en Compose y también en PHP.
- Puerto MySQL publicado al host, sin necesidad para una despliegue típico.
- No hay healthchecks, espera de disponibilidad, imágenes fijadas por digest, build multi-stage, servidor estático de producción, TLS, backups, rotación de logs ni límites de recursos.
- `depends_on` no garantiza que MySQL esté listo.
- No hay archivos `.env.example` separados por frontend y backend, ni validación de variables al iniciar.
- `display_errors=1` está activo siempre y CORS acepta únicamente `localhost:5173` de forma fija.

### Build, consola, formularios y pruebas existentes

La build Vue termina correctamente, pero sólo compila el grafo alcanzable desde el router. Por eso no descubre los imports incorrectos de las cuatro vistas desconectadas ni `api.delete` inexistente. No hay scripts de test, lint o typecheck, archivos de prueba, configuración CI ni pruebas PHP. La verificación de sintaxis PHP tampoco detecta llamadas a métodos inexistentes.

No se dispuso de un recolector persistente de consola del navegador. Sí se observaron el `401` real después del login y los correspondientes accesos no autorizados en el log del backend. Por inspección determinista, montar `AdminView` alcanza un `ReferenceError` por `period.value`; conectar las vistas administrativas alternativas produciría errores de import/contrato; y activar los endpoints de clases, reservas o membresías alcanza errores de método PHP. Estos casos deben convertirse en smoke tests que fallen en CI, no quedar sujetos a revisar la consola manualmente.

Formularios actuales:

- Login: tiene bloqueo durante envío, pero carece de recuperación, `autocomplete` completo, anuncio accesible del error, rate limit visible y sesión posterior funcional.
- Registro: valida campos básicos y muestra loading, pero no verifica email, términos/consentimiento ni política de contraseña completa; el polling de Turnstile puede sobrevivir al desmontaje y la protección se omite de hecho si falta el secreto.
- Crear membresía y clase en `AdminView`: no tienen errores por campo, confirmación robusta, cancelación, dirty-state, idempotencia ni contratos backend válidos.
- Formularios de las vistas administrativas desconectadas: parecen completos visualmente, pero usan un cliente HTTP incompatible y no pueden guardar de extremo a extremo.
- Ningún formulario remoto tiene un estándar compartido de loading, disabled, éxito, error, reintento, prevención de doble envío o aviso al abandonar cambios.

## 4. Hallazgos priorizados

### Bloqueantes, P0

| ID | Hallazgo | Evidencia | Impacto | Corrección objetivo |
|---|---|---|---|---|
| P0-01 | Autenticación inutilizable en Docker | Login correcto seguido por `401` en `/api/perfil`; Apache no reenvía el header esperado. | Ninguna ruta protegida es confiable. | Configurar Apache, normalizar lectura de headers y migrar a sesión segura o token opaco persistido y rotado. |
| P0-02 | Contratos PHP rotos | Controladores llaman `listarActivas`, `reservar`, `activar`, `actualizarVencidas` y otros métodos inexistentes o con otra firma. | Errores fatales al usar clases, reservas o membresías. | Definir contratos, servicios y tests de integración antes de exponer rutas. |
| P0-03 | Carga administrativa interrumpida | `AdminView.vue` declara `periodo`, pero `cargarReservas()` usa `period.value`. | La carga secuencial falla y deja paneles incompletos. | Corregir contrato de filtro, manejar fallos parciales y probar montaje. |
| P0-04 | No existe modelo multi-gimnasio | No hay tabla `gimnasios`, `gym_id` ni pertenencia por tenant. | Mezcla de datos y autorización imposible para uso real. | Introducir gimnasio, sedes y asignación usuario-rol-gimnasio antes de cargar datos reales. |
| P0-05 | Credencial administrativa insegura | Seed y documentación usan contraseña en texto plano; login la acepta como fallback. | Compromiso inmediato de cuenta privilegiada. | Invalidar credenciales, eliminar fallback, forzar reset y usar `password_hash/password_verify`. |
| P0-06 | Paneles presentan datos inventados | Gimnasios, métricas, ingresos, reservas y progreso vienen de `demoData.js` o valores locales. | Decisiones operativas y financieras falsas. | Sustituir por estados reales con carga, vacío, error y permisos. |
| P0-07 | Roles incompatibles | BD: socio/admin/moderador; UI: socio/super/owner. El dueño intenta `/admin`, reservado al rol 2. | El modelo de acceso requerido no existe. | Migrar a socio, empleado, dueño y admin general con ámbito global o por gimnasio. |
| P0-08 | Pagos sin flujo verificable | La tabla actual sólo guarda monto, método y fecha; no hay checkout, estado, webhook ni idempotencia. | No puede activarse una membresía de forma segura. | Implementar proveedor desacoplado y confirmación servidor a servidor antes de habilitar pagos reales. |

### Prioridad alta, P1

| ID | Hallazgo | Impacto o alcance |
|---|---|---|
| P1-01 | No existen recuperación de contraseña ni validación de correo. | Cuentas no recuperables y direcciones no verificadas. |
| P1-02 | Sesión expuesta en `localStorage`, sin atributos de cookie ni expiración visible. | Robo mediante XSS y revocación deficiente. |
| P1-03 | Turnstile acepta cualquier valor no vacío si falta el secreto. | Protección anti-bot ilusoria. |
| P1-04 | No hay rate limiting, bloqueo gradual ni registro de intentos. | Fuerza bruta y abuso. |
| P1-05 | Reservas sin restricción única, transacción ni bloqueo de cupos. | Sobreventa, duplicados y carreras. |
| P1-06 | La cancelación no comprueba pertenencia ni repone cupo de forma atómica. | Acceso indebido e inconsistencia. |
| P1-07 | Las clases sólo guardan día de semana y hora, no ocurrencias fechadas ni zona horaria. | Reservas, asistencia y Calendar no pueden representar eventos reales. |
| P1-08 | No hay migraciones ni rollback. | Cambios de esquema no reproducibles ni auditables. |
| P1-09 | No hay separación de secretos ni configuración por ambiente. | Riesgo de filtración y despliegues irrepetibles. |
| P1-10 | El backend expone errores de PHP y carece de manejo centralizado. | Filtración de detalles internos y respuestas inestables. |
| P1-11 | No hay CSRF para una futura sesión por cookie ni política CORS configurable. | Mutaciones vulnerables al cambiar al mecanismo recomendado. |
| P1-12 | No hay auditoría administrativa. | Cambios de pagos, roles o membresías no son trazables. |
| P1-13 | Dos dependencias de producción tienen alertas altas. | Superficie de ataque conocida. |
| P1-14 | No hay tests automatizados ni CI. | Cada corrección puede introducir regresiones. |
| P1-15 | El Docker actual es de desarrollo. | No ofrece una operación estable, segura ni observable. |
| P1-16 | La sección de funciones fuerza cuatro columnas con `!important` bajo 480 px. | Desbordamiento y contenido cortado en móvil. |
| P1-17 | Administración no tiene navegación persistente ni responsive completo. | Funciones críticas quedan escondidas en una página extensa. |
| P1-18 | Rutas administrativas dependen sólo del cliente o de IDs rígidos. | Escalada horizontal/vertical de privilegios. |
| P1-19 | Varias vistas desconectadas fallarían al importarse. | Importan `api` por defecto, esperan Axios crudo y llaman `api.delete`, que no existe. |
| P1-20 | OpenStreetMap se muestra sin atribución. | Incumplimiento de la política del proveedor de tiles. |

### Prioridad media, P2

| ID | Hallazgo | Impacto o alcance |
|---|---|---|
| P2-01 | Enlace `#planes` sin sección de destino. | Navegación rota. |
| P2-02 | Cierre de sesión móvil referencia `handleLogout` sin invocarlo. | El usuario cree salir, pero sigue autenticado. |
| P2-03 | `@click.native` persiste en un `router-link` de Vue 3. | Cierre del drawer no confiable. |
| P2-04 | Tarjetas clicables usan `article` sin teclado ni nombre accesible. | Flujo inaccesible para teclado y lectores. |
| P2-05 | Modales sin `role=dialog`, foco atrapado, Escape ni restauración de foco. | Navegación y contexto deficientes. |
| P2-06 | Estados hover, focus, active, loading, disabled, éxito y error son incompletos. | Acciones ambiguas y dobles envíos. |
| P2-07 | No hay página 404 ni manejo global de 401/403/5xx. | Navegación y recuperación deficientes. |
| P2-08 | Home repite los mismos gimnasios en carrusel y mapa/listado. | Ruido visual y falsa abundancia. |
| P2-09 | Hero contiene demasiados mensajes, estadísticas falsas y CTA equivalentes. | Jerarquía débil y primer viewport sobrecargado. |
| P2-10 | Mapas no destruyen siempre la instancia ni sincronizan tarjeta y marcador. | Fugas y estado visual divergente. |
| P2-11 | Tablas no tienen búsqueda, ordenamiento ni paginación. | Operación inviable al crecer datos. |
| P2-12 | Textos y etiquetas mezclan español sin tildes, inglés y jerga. | Menor claridad y calidad percibida. |
| P2-13 | No hay estados vacíos útiles ni skeletons. | Pantallas vacías parecen errores. |
| P2-14 | Transiciones usan `transition: all` y no respetan reducción de movimiento. | Rendimiento, previsibilidad y accesibilidad. |

### Mejora posterior, P3

| ID | Hallazgo | Alcance |
|---|---|---|
| P3-01 | Modo claro o preferencia de tema. | No imprescindible para v1.0 si el contraste oscuro queda validado. |
| P3-02 | Personalización avanzada de dashboard. | Posponer hasta estabilizar roles y datos. |
| P3-03 | Analítica predictiva o inteligencia artificial. | Fuera de v1.0, tal como solicita el alcance. |
| P3-04 | Animaciones expresivas adicionales. | Sólo después de que feedback, foco y rendimiento sean correctos. |

### Aspectos positivos a conservar

- El stack es pequeño y apropiado para un modular monolith inicial.
- PDO ya usa consultas preparadas en gran parte de los modelos.
- Las contraseñas nuevas se generan con bcrypt de costo 12.
- Vue Router y Pinia ya ofrecen puntos claros para centralizar sesión y permisos.
- La estética oscura y el azul de marca pueden convertirse en un sistema sobrio sin perder identidad.
- La build productiva actual es relativamente liviana.

## 5. Auditoría visual y propuesta de sistema

### Lectura de diseño

GymTrack debe sentirse como una herramienta operativa confiable, clara y sobria, no como una landing de demostración. Para la portada se propone densidad 6/10, energía 4/10 y profundidad 3/10. Para los paneles, densidad 4/10, energía 3/10 y profundidad 6/10: más información visible, menos brillo y jerarquía estrictamente funcional.

### Antes, después y razón

| Antes | Después | Por qué |
|---|---|---|
| Hero largo, estadísticas inventadas y dos CTA con destino equivalente. | Promesa breve, una acción primaria “Explorar gimnasios” y una secundaria “Administrar mi gimnasio”; métricas sólo si son verificables. | Reduce carga cognitiva y evita confianza falsa. |
| Muchas tarjetas visualmente idénticas. | Superficies por función: métricas compactas, listas, tablas, paneles y sólo algunas tarjetas destacadas. | La forma comunica el tipo de información. |
| Azul y glow compiten en casi todos los bloques. | Azul reservado para acción primaria, selección y progreso; neutros para estructura; estados con colores semánticos. | La atención vuelve a lo importante. |
| Navegación de dueño mezclada en el dashboard genérico. | Área persistente “Administración” con rutas propias y breadcrumb. | Las tareas operativas dejan de estar escondidas. |
| Cambios de vista que sólo mutan estado local. | Rutas reales, URL compartible, título de página, permisos y carga por vista. | Navegación predecible y recuperable. |
| Modales que aparecen sin gestión de foco. | Diálogos accesibles, Escape, foco inicial, restauración y confirmación contextual. | Seguridad y uso por teclado. |
| Botones instantáneos o inertes. | Estado idle, hover, focus-visible, active, loading, disabled, success y error en cada acción remota. | Previene dobles envíos y comunica resultado. |
| Animaciones de entrada con opacidad y `transition: all`. | Transiciones de transform/opacity de 150 a 250 ms, springs sólo en gestos y fallback completo con reduced motion. | Movimiento rápido, interrumpible y accesible. |

### Sistema visual propuesto

- Tipografía: una sola familia UI autohospedada o system stack; escala mínima 14 px para metadatos y 16 px para cuerpo. Montserrat puede quedar sólo para títulos si se autohospeda y realmente aporta marca.
- Contenedor: máximo 1280 a 1440 px en portada; panel administrativo fluido con rail de navegación y contenido que aproveche pantallas grandes.
- Espaciado: base de 4 px, con pasos 4, 8, 12, 16, 24, 32, 48 y 64.
- Radios: 8 px controles, 12 px tarjetas, 16 px diálogos. Evitar que todos los contenedores parezcan píldoras.
- Bordes: contraste suficiente sobre el fondo y un único nivel de sombra suave. No usar glow como borde.
- Color: tokens `surface`, `surface-raised`, `text`, `text-muted`, `border`, `primary`, `success`, `warning`, `danger`, `info`; contraste WCAG AA validado.
- Formularios: etiqueta persistente, ayuda, error asociado con `aria-describedby`, foco visible, validación al salir o enviar y resumen si hay varios errores.
- Tablas: encabezado persistente en escritorios, acciones en menú contextual, filtros sobre la tabla y adaptación a lista estructurada sólo bajo 768 px.
- Gráficas: título, período, unidad, leyenda, tooltip de teclado o tabla alternativa y estado sin datos. Ninguna barra CSS con números hardcodeados.
- Footer: privacidad, términos, contacto, accesibilidad, estado del servicio y atribuciones.

### Matriz responsive obligatoria

| Ancho | Portada y navegación | Administración y tablas | Mapa y gimnasios | Criterios verificables |
|---:|---|---|---|---|
| 360 px | Header compacto, CTA no se corta, navegación inferior para socio, menú con foco atrapado. | Una columna; acciones primarias visibles; tablas como lista o scroll local declarado. | Bottom sheet en cerrada/media/completa; mapa visible detrás; tarjetas compactas. | Sin overflow horizontal en `documentElement`; targets de al menos 44 px; texto base 16 px. |
| 390 px | Igual a 360 con algo más de separación, sin asumir ancho de iPhone específico. | Formularios de una columna; botones de riesgo no colisionan. | Sheet conserva asa, título, filtros y altura con `dvh`. | Zoom de texto al 200% sin pérdida de acciones. |
| 768 px | Header tablet y secciones de una o dos columnas según contenido. | Sidebar colapsable; tablas aún con scroll interno; modales con margen 24 px. | Mapa superior o división 45/55 según orientación. | Funciona en vertical y horizontal, teclado visible incluido. |
| 1024 px | Navegación completa sólo si cabe sin compresión. | Rail persistente de 240 px, contenido mínimo 0 para no desbordar. | Mapa y panel en un mismo componente, panel contraíble. | Ningún panel se superpone a header, footer o contenido. |
| 1440 px | Contenedor centrado, líneas de texto limitadas y menos espacio vacío artificial. | Sidebar 256 px, grilla de métricas, tablas con densidad cómoda. | Panel 380 a 440 px con scroll interno y mapa flexible. | Finanzas y reportes visibles a un clic. |
| 1920 px | No estirar textos ni tarjetas; usar márgenes laterales y composición asimétrica controlada. | Máximo de lectura para formularios, tabla puede crecer. | Mapa aprovecha espacio; panel no supera 480 px. | No aparecen “islas” de tarjetas ni huecos sin propósito. |

Reglas transversales: `min-width: 0` en hijos flex/grid, imágenes con dimensiones y `object-fit`, `100dvh` para paneles móviles, áreas seguras con `env(safe-area-inset-*)`, prueba de teclado virtual, `prefers-reduced-motion`, foco nunca oculto y ninguna barra horizontal global.

## 6. Inventario de botones, enlaces y acciones

Leyenda: **Sí** funciona con el alcance actual; **Parcial** hace algo local o incompleto; **No** está roto, es decorativo o depende de un contrato inexistente. La dificultad es estimada: B baja, M media, A alta.

| Página | Componente/archivo | Texto o acción | Qué debería hacer | Qué hace actualmente | ¿Funciona? | Ruta o modal necesario | Endpoint necesario | Tabla MySQL | Rol | Prioridad | Dif. |
|---|---|---|---|---|---|---|---|---|---|---|---:|
| Inicio | `HomeView.vue` | Logo GymTrack | Volver al inicio | Va a `/` | Sí | `/` | No | No | Público | Media | B |
| Inicio | `HomeView.vue` | Funciones | Desplazar a funciones | Va a `#funciones` | Sí | `/#funciones` | No | No | Público | Media | B |
| Inicio | `HomeView.vue` | Planes | Mostrar planes reales | Apunta a un ID inexistente | No | `/#planes` o `/planes` | `GET /api/public/planes` | `membership_plans` | Público | Alta | M |
| Inicio | `HomeView.vue` | Gimnasios | Desplazar al buscador/mapa | Va a `#gimnasios` | Sí | `/#gimnasios` | `GET /api/public/gyms` | `gyms` | Público | Alta | M |
| Inicio | `HomeView.vue` | Owners | Ir a propuesta para dueños | Va a `#owners` | Sí | `/#owners` | No | No | Público | Media | B |
| Inicio | `HomeView.vue` | Dashboard | Abrir panel del usuario | Va a `/dashboard` | Parcial | `/app/inicio` | `GET /api/me/summary` | Varias | Autenticado | Alta | M |
| Inicio | `HomeView.vue` | Salir, escritorio | Cerrar sesión y revocar credencial | Ejecuta store y redirección | Parcial | `/login` | `POST /api/auth/logout` | `user_sessions` | Autenticado | Bloqueante | M |
| Inicio | `HomeView.vue` | Iniciar sesión | Abrir login | Va a `/login` | Sí | `/login` | No | No | Público | Alta | B |
| Inicio | `HomeView.vue` | Registrarse | Abrir alta de socio | Va a `/registro` | Parcial | `/registro` | `POST /api/auth/register` | `users`, `email_verifications` | Público | Alta | M |
| Inicio | `HomeView.vue` | Menú hamburguesa | Abrir/cerrar menú accesible | Alterna drawer sin foco ni Escape | Parcial | Drawer móvil | No | No | Público | Alta | M |
| Inicio | `HomeView.vue` | Cerrar menú | Cerrar y restaurar foco | Sólo cambia booleano | Parcial | Drawer móvil | No | No | Público | Alta | B |
| Inicio | `HomeView.vue` | Salir, móvil | Cerrar sesión | No invoca `handleLogout()` | No | `/login` | `POST /api/auth/logout` | `user_sessions` | Autenticado | Bloqueante | B |
| Inicio | `HomeView.vue` | Buscar gimnasios | Abrir explorador público | Envía al registro | No | `/gimnasios` | `GET /api/public/gyms` | `gyms` | Público | Alta | M |
| Inicio | `HomeView.vue` | Soy dueño de gimnasio | Iniciar alta/claim de gimnasio | Baja a la sección y luego comparte registro de socio | No | `/para-gimnasios` y `/onboarding/gym` | `POST /api/owner-applications` | `gyms`, `gym_users` | Dueño | Alta | A |
| Inicio | `HomeView.vue` | Detectar ubicación | Pedir permiso, centrar y ordenar | Sólo cambia un mensaje; ignora coordenadas | No | Estado inline y mapa | `GET /api/public/gyms?lat&lng` | `gyms` | Público | Alta | M |
| Inicio | `HomeView.vue` | Tarjeta de gimnasio | Seleccionar gimnasio y marcador | Abre modal con datos demo | Parcial | `/gimnasios/:slug` o modal | `GET /api/public/gyms/{id}` | `gyms`, `class_sessions` | Público | Alta | M |
| Inicio | `HomeView.vue` | Ver | Mostrar detalle real del gimnasio | Abre el mismo modal demo | Parcial | `/gimnasios/:slug` | `GET /api/public/gyms/{id}` | `gyms` | Público | Alta | M |
| Inicio | `HomeView.vue` | Tarjeta de función | Expandir detalle por teclado/táctil | Click de `article`, no accesible por teclado | Parcial | Acordeón accesible | No | No | Público | Media | B |
| Inicio | `HomeView.vue` | Crear gimnasio demo | Empezar onboarding de dueño | Va al registro genérico | No | `/onboarding/gym` | `POST /api/gyms` | `gyms`, `gym_users` | Dueño | Alta | A |
| Inicio | `HomeView.vue` | Cerrar detalle de gimnasio | Cerrar modal y restaurar foco | Cierra por click | Parcial | Modal accesible | No | No | Público | Media | B |
| Inicio | `HomeView.vue` | Registrarme como socio | Alta y selección de plan/gimnasio | Va al registro genérico | Parcial | `/registro?gym=:id` | `POST /api/auth/register` | `users`, `memberships` | Público | Alta | M |
| Inicio | `HomeView.vue` | Soy dueño, dentro de modal | Iniciar onboarding de negocio | Va al mismo registro de socio | No | `/onboarding/gym` | `POST /api/owner-applications` | `gyms`, `gym_users` | Dueño | Alta | A |
| Login | `LoginView.vue` | Iniciar sesión | Autenticar, rotar sesión y volver al destino | Backend valida, pero rutas posteriores dan 401 | No | `/login` | `POST /api/auth/login` | `users`, `user_sessions`, `login_attempts` | Público | Bloqueante | A |
| Login | `LoginView.vue` | Regístrate | Abrir registro | Va a `/registro` | Sí | `/registro` | No | No | Público | Media | B |
| Login | Falta | Olvidé mi contraseña | Solicitar enlace no enumerativo | No existe | No | `/recuperar` | `POST /api/auth/password/forgot` | `password_resets` | Público | Alta | M |
| Registro | `RegisterView.vue` | Crear cuenta | Validar, registrar y enviar verificación | Crea socio; Turnstile puede ser aparente | Parcial | `/registro` y `/verificar-email` | `POST /api/auth/register` | `users`, `email_verifications`, `consents` | Público | Alta | A |
| Registro | `RegisterView.vue` | Inicia sesión | Abrir login | Va a `/login` | Sí | `/login` | No | No | Público | Media | B |
| Dashboard | `DashboardView.vue` | Menú móvil | Abrir navegación y gestionar foco | Alterna sidebar/overlay | Parcial | Drawer app | No | No | Autenticado | Alta | M |
| Dashboard | `DashboardView.vue` | Inicio, Mis Gimnasios, Reservas, Membresías, Progreso, Explorar; Dashboard, Mi Gimnasio, Socios, Clases, Pagos, Gimnasios, Owners y Reportes | Cambiar a rutas reales según rol | Sólo cambia `activeNav`; para owner/admin casi no cambia contenido | No | `/app/*`, `/administracion/*`, `/plataforma/*` | Endpoints por módulo | Varias | Por rol | Bloqueante | A |
| Dashboard | `DashboardView.vue` | Cerrar sesión | Revocar sesión y volver al login | Intenta logout actual | Parcial | `/login` | `POST /api/auth/logout` | `user_sessions` | Autenticado | Bloqueante | M |
| Dashboard socio | `DashboardView.vue` | Ver todos / Ver mapa / Explorar más | Navegar al explorador | Muta estado local | Parcial | `/app/gimnasios` | `GET /api/gyms` | `gyms`, `favorites` | Socio | Alta | M |
| Dashboard socio | `DashboardView.vue` | Filtro por ciudad | Filtrar resultados reales | Filtra datos demo locales | Parcial | Query `?city=` | `GET /api/gyms?city=` | `gyms` | Socio | Alta | M |
| Dashboard socio | `DashboardView.vue` | Ver gimnasio / Ver clases | Abrir gimnasio o agenda | Abre modal con clases demo | Parcial | `/app/gimnasios/:id` | `GET /api/gyms/{id}/sessions` | `gyms`, `class_sessions` | Socio | Alta | M |
| Dashboard socio | `DashboardView.vue` | Unirme / Cancelar membresía | Iniciar checkout o solicitar baja | Activa/cancela una membresía demo local | No | Modal de plan/checkout/baja | `POST /api/checkouts`, `POST /api/memberships/{id}/cancel` | `memberships`, `payments` | Socio | Bloqueante | A |
| Dashboard socio | `DashboardView.vue` | Reservar otra clase | Abrir sesiones disponibles | Muta a exploración demo | No | `/app/clases` | `GET /api/class-sessions` | `class_sessions` | Socio | Alta | M |
| Dashboard socio | `DashboardView.vue` | Ver detalle de reserva | Mostrar sesión, QR y acciones | Abre modal demo | Parcial | `/app/reservas/:id` | `GET /api/reservations/{id}` | `reservations`, `class_sessions` | Socio | Alta | M |
| Dashboard socio | `DashboardView.vue` | Cancelar reserva demo | Cancelar dentro de política y promover espera | Sólo elimina/cambia estado local | No | Confirmación contextual | `DELETE /api/reservations/{id}` | `reservations`, `waitlist_entries` | Socio | Alta | A |
| Dashboard socio | `DashboardView.vue` | Comprar otra | Abrir planes disponibles | Muta a exploración demo | No | `/app/membresias` | `GET /api/gyms/{id}/plans` | `membership_plans` | Socio | Alta | M |
| Dashboard dueño | `DashboardView.vue` | Administrar gimnasio | Abrir administración de su gimnasio | Va a `/admin`, pero el rol 3 no está autorizado | No | `/administracion/resumen` | `GET /api/admin/summary` | Varias | Dueño | Bloqueante | A |
| Dashboard dueño | `DashboardView.vue` | Nueva clase | Abrir formulario | Botón sin evento | No | `/administracion/clases/nueva` | `POST /api/admin/class-sessions` | `classes`, `class_sessions` | Dueño/empleado autorizado | Alta | M |
| Dashboard dueño | `DashboardView.vue` | Agregar socio | Abrir alta o invitación | Botón sin evento | No | `/administracion/socios/nuevo` | `POST /api/admin/members` | `users`, `gym_users` | Dueño/empleado autorizado | Alta | M |
| Dashboard dueño | `DashboardView.vue` | Ver reservas | Abrir listado filtrable | Botón sin evento | No | `/administracion/reservas` | `GET /api/admin/reservations` | `reservations` | Dueño/empleado autorizado | Alta | M |
| Dashboard dueño | `DashboardView.vue` | Gestión operativa | Abrir administración | Va a `/admin`, actualmente restringido al rol incorrecto | No | `/administracion/resumen` | `GET /api/admin/summary` | Varias | Dueño | Bloqueante | A |
| Administración activa | `AdminView.vue` | Inicio / Volver al Dashboard | Navegar | Enlaces funcionan | Sí | `/`, `/dashboard` | No | No | Admin actual | Media | B |
| Administración activa | `AdminView.vue` | Activar/Desactivar socio | Cambiar acceso en su gimnasio con confirmación | Llama endpoint global sin tenant | Parcial | Confirmación | `PATCH /api/admin/members/{id}/status` | `gym_users`, `audit_logs` | Dueño/admin general | Alta | A |
| Administración activa | `AdminView.vue` | Perfil | Abrir ficha completa | Sólo carga perfil en estado/alerta parcial | No | `/administracion/socios/:id` | `GET /api/admin/members/{id}` | `users`, `memberships` | Dueño/empleado autorizado | Alta | M |
| Administración activa | `AdminView.vue` | Crear membresía | Asignar plan y condiciones | Contrato backend incompatible | No | Formulario o drawer | `POST /api/admin/memberships` | `membership_plans`, `memberships` | Dueño/empleado autorizado | Bloqueante | A |
| Administración activa | `AdminView.vue` | Crear clase | Crear plantilla/sesiones | Contrato controlador-modelo roto | No | `/administracion/clases/nueva` | `POST /api/admin/classes` | `classes`, `class_sessions` | Dueño/empleado autorizado | Bloqueante | A |
| Administración activa | `AdminView.vue` | Ver inscriptos | Listar reservas de una sesión | Depende de modelo de reservas roto | No | Drawer de sesión | `GET /api/admin/class-sessions/{id}/reservations` | `reservations`, `users` | Dueño/entrenador/empleado | Alta | M |
| Administración activa | `AdminView.vue` | Cancelar clase | Cancelar sesión, avisar y liberar reservas | Endpoint/contrato no implementado de extremo a extremo | No | Confirmación con motivo | `POST /api/admin/class-sessions/{id}/cancel` | `class_sessions`, `reservations`, `notifications` | Dueño/empleado autorizado | Alta | A |
| Administración activa | `AdminView.vue` | Hoy / Semana | Filtrar por fecha de la sesión | Usa variable equivocada y filtra fecha de creación de reserva | No | Query `?from&to` | `GET /api/admin/reservations` | `class_sessions`, `reservations` | Staff | Bloqueante | A |
| Administración activa | `AdminView.vue` | Cancelar reserva | Cancelar con permisos, cupo y aviso | Modelo no valida pertenencia ni transacción | No | Confirmación | `DELETE /api/admin/reservations/{id}` | `reservations`, `waitlist_entries` | Staff | Bloqueante | A |
| Clases desconectada | `ClassesView.vue` | Reservar | Reservar sesión real o entrar a espera | Vista no tiene ruta y cliente API incompatible | No | `/app/clases` | `POST /api/class-sessions/{id}/reservations` | `reservations`, `waitlist_entries` | Socio | Bloqueante | A |
| Admin desconectado | `AdminDashboard.vue` | Volver | Volver al dashboard | Ruta de la vista no existe | No | `/administracion/resumen` | No | No | Dueño/admin | Alta | M |
| Admin desconectado | `AdminDashboard.vue` | Ver todos / Gestionar socios | Abrir socios | Apunta a `/admin/usuarios`, inexistente | No | `/administracion/socios` | `GET /api/admin/members` | `users`, `gym_users` | Dueño/staff | Alta | M |
| Admin desconectado | `AdminDashboard.vue` | Perfil | Abrir socio | Botón sin evento | No | `/administracion/socios/:id` | `GET /api/admin/members/{id}` | `users` | Dueño/staff | Alta | M |
| Admin desconectado | `AdminDashboard.vue` | Gestionar | Gestionar membresías pendientes | Botón sin evento | No | `/administracion/membresias` | `GET /api/admin/memberships` | `memberships` | Dueño/staff | Alta | M |
| Admin desconectado | `AdminDashboard.vue` | Crear clase | Abrir alta de clase | Apunta a ruta inexistente | No | `/administracion/clases/nueva` | `POST /api/admin/classes` | `classes`, `class_sessions` | Dueño/staff | Alta | M |
| Admin desconectado | `AdminDashboard.vue` | Exportar datos | Exportar según contexto y permisos | Muestra un `alert` placeholder | No | Modal de exportación | `POST /api/admin/exports` | `export_jobs`, `audit_logs` | Dueño/admin general | Alta | A |
| Usuarios desconectada | `AdminUsers.vue` | Buscar / Limpiar | Filtrar socios paginados | Vista sin ruta; endpoint esperado no existe | No | `/administracion/socios` | `GET /api/admin/members?q=` | `users`, `gym_users` | Dueño/staff | Alta | M |
| Usuarios desconectada | `AdminUsers.vue` | Gestionar / Cerrar | Abrir/cerrar ficha de membresía | Modal local, pero datos/cliente incompatibles | No | Drawer accesible | `GET /api/admin/members/{id}` | `users`, `memberships` | Dueño/staff | Alta | M |
| Usuarios desconectada | `AdminUsers.vue` | Activar/Suspender membresía | Cambiar estado con reglas y auditoría | Métodos de modelo requeridos no existen | No | Confirmación | `POST /api/admin/memberships/{id}/activate` o `/suspend` | `memberships`, `audit_logs` | Dueño/staff | Bloqueante | A |
| Clases admin desconectada | `AdminClasses.vue` | Nueva clase / Cancelar | Abrir/cerrar formulario | Vista no tiene ruta | No | `/administracion/clases/nueva` | No | No | Dueño/staff | Alta | M |
| Clases admin desconectada | `AdminClasses.vue` | Guardar | Crear o editar clase/sesión | Cliente y firmas backend incompatibles | No | Formulario | `POST/PUT /api/admin/classes/{id}` | `classes`, `class_sessions` | Dueño/staff | Bloqueante | A |
| Clases admin desconectada | `AdminClasses.vue` | Editar | Cargar formulario | Sólo local; flujo no conectable hoy | No | `/administracion/clases/:id/editar` | `GET /api/admin/classes/{id}` | `classes` | Dueño/staff | Alta | M |
| Clases admin desconectada | `AdminClasses.vue` | Eliminar | Archivar con validación de reservas | Llama `api.delete` inexistente y ruta inexistente | No | Confirmación | `DELETE /api/admin/classes/{id}` | `classes`, `class_sessions` | Dueño | Alta | A |

No se encontraron `href="#"` literales. El equivalente funcional aparece en destinos inexistentes, mutaciones de estado sin vista asociada y botones sin evento. Todos los formularios remotos deberán adquirir bloqueo por petición, prevención de doble envío, error de campo, error global, confirmación de éxito y recuperación tras fallo.

## 7. Arquitectura objetivo sin cambiar el stack

### Principio general

Mantener un **modular monolith**: una SPA Vue 3 y una API PHP desplegadas juntas, con MySQL como fuente de verdad. Dentro del backend, separar por módulos y no por una colección plana de controladores:

```text
backend/
  public/index.php
  src/
    Auth/          Admin/         Gyms/          Members/
    Classes/       Reservations/  Memberships/   Payments/
    Finance/       Promotions/    Notifications/ Calendar/
    Shared/
      Http/ Middleware/ Validation/ Database/ Security/ Files/ Jobs/
  routes/
  database/migrations/
  database/seeders/
  tests/Unit/
  tests/Integration/
```

Cada módulo debe tener controlador HTTP delgado, servicio de aplicación, repositorio PDO, DTO/validador y políticas de autorización. Las operaciones con varios cambios usan una única transacción MySQL. Los IDs públicos deben ser UUID/ULID o identificadores opacos; los autoincrementales pueden mantenerse internamente.

### Sesión y autenticación recomendadas

Para esta SPA desplegada en el mismo origen, usar sesión servidor a servidor con cookie `HttpOnly`, `Secure`, `SameSite=Lax`, nombre no predeterminado, expiración inactiva y absoluta, rotación al iniciar sesión y al elevar privilegios. No almacenar el ID en `localStorage`. Añadir token CSRF por sesión para toda mutación y CORS por allowlist cuando exista un origen separado.

Si el despliegue exige bearer tokens, usar tokens opacos aleatorios cuyo hash se guarda en `user_sessions`, con expiración, rotación y revocación; nunca usar `session_id` como API token. El frontend debe resolver `GET /api/me` al arrancar y no confiar en la presencia local de un valor.

Flujos obligatorios:

1. Registro con email normalizado, consentimiento versionado y Turnstile sólo si hay secreto válido.
2. Verificación de email mediante token aleatorio de un solo uso almacenado como hash y con vencimiento.
3. Login con respuesta no enumerativa, rate limit por IP y cuenta, registro de intentos y backoff.
4. Recuperación de contraseña no enumerativa, enlace de un solo uso, invalidación de sesiones y aviso de seguridad.
5. Logout de sesión actual y opción de cerrar todas las sesiones.
6. Reautenticación para cambios de email, pagos manuales, reembolsos y roles privilegiados.

### Modelo de roles y permisos

| Capacidad | Socio | Empleado | Dueño | Admin general |
|---|:---:|:---:|:---:|:---:|
| Ver/editar su perfil y preferencias | Sí | Sí | Sí | Sí |
| Explorar gimnasios, comprar, reservar y cancelar lo propio | Sí | Opcional como socio | Opcional como socio | Sólo soporte autorizado |
| Consultar socios del gimnasio | No | Con permiso | Sí | Sí, con motivo auditado |
| Gestionar clases, reservas y asistencia | No | Con permiso granular | Sí | Sí |
| Gestionar entrenadores y empleados | No | No por defecto | Sí | Sí |
| Gestionar membresías y pagos manuales | No | Con permiso financiero | Sí | Sí |
| Ver finanzas y exportar | No | Sólo permiso explícito | Sí | Sí |
| Reembolsar | No | Permiso reforzado | Sí, con reautenticación | Sí, auditado |
| Configurar un gimnasio | No | No por defecto | Sí | Sí |
| Crear/suspender gimnasios y administrar plataforma | No | No | No | Sí |

Los roles de empleado y dueño viven en `gym_user_roles` y siempre tienen `gym_id`. `admin_general` es global y excepcional. Los permisos de empleado se asignan como capacidades, por ejemplo `members.read`, `classes.write`, `attendance.write`, `payments.manual`, `finance.read`. Un entrenador es un perfil operativo asociado a un usuario o registro invitado, no un rol global independiente. Toda query administrativa recibe el `gym_id` autorizado desde middleware; no acepta libremente el tenant enviado por el cliente.

### Navegación y rutas frontend

```text
Público
  /
  /gimnasios
  /gimnasios/:slug
  /planes
  /para-gimnasios
  /login
  /registro
  /verificar-email
  /recuperar
  /restablecer/:token

Socio
  /app/inicio
  /app/gimnasios
  /app/clases
  /app/reservas
  /app/membresias
  /app/pagos
  /app/progreso
  /app/favoritos
  /app/notificaciones
  /app/perfil
  /app/preferencias

Administración
  /administracion/resumen
  /administracion/operacion
  /administracion/socios
  /administracion/empleados
  /administracion/entrenadores
  /administracion/clases
  /administracion/reservas
  /administracion/membresias
  /administracion/pagos
  /administracion/promociones
  /administracion/finanzas
  /administracion/reportes
  /administracion/configuracion

Plataforma
  /plataforma/resumen
  /plataforma/gimnasios
  /plataforma/usuarios
  /plataforma/pagos
  /plataforma/auditoria
  /plataforma/configuracion
```

El router usa metadatos de `requiresAuth`, `allowedRoles` y `requiredPermission` para UX, pero PHP vuelve a validar cada petición. Deben existir páginas 401, 403, 404 y estado de mantenimiento.

### Navegación administrativa visible

La sidebar se llama **Administración** y contiene exactamente: Resumen, Gestión operativa, Socios, Empleados, Entrenadores, Clases, Reservas, Membresías, Pagos, Promociones, Finanzas, Reportes y Configuración. Finanzas y Reportes permanecen en primer nivel, nunca dentro de “Más”. En tablet se transforma en rail y en móvil en drawer; la ruta y el título siguen visibles.

El resumen incluye accesos rápidos, con permiso y estado real:

- Registrar socio.
- Crear clase.
- Registrar pago manual.
- Revisar pagos pendientes.
- Gestionar membresías.
- Consultar reservas.
- Crear promoción.
- Descargar Excel.
- Descargar PDF.

Cada acceso abre una ruta o drawer específico, no un `alert`. Los accesos financieros deben exigir permiso; un pago manual debe registrar operador, razón y método.

### Navegación del socio

En móvil, barra inferior con Inicio, Explorar, Reservas, Progreso y Perfil; “Más” puede agrupar membresías, pagos, favoritos, notificaciones y preferencias. En escritorio, sidebar compacta. El inicio prioriza próxima clase, estado de membresía, pago pendiente y objetivo mensual, seguido por promociones relevantes. El carné/QR queda disponible en un toque y nunca contiene datos sensibles en texto claro.

## 8. Modelo de datos y migraciones

No editar el bootstrap existente como única estrategia. Crear migraciones incrementales, tabla `schema_migrations`, transacciones donde MySQL lo permita, respaldos previos y scripts de verificación. Los nombres siguientes son propuestos y pueden adaptarse a la convención actual.

| Dominio | Tablas nuevas o cambios principales | Restricciones e índices esenciales |
|---|---|---|
| Identidad | `users` evolucionada desde `usuarios`, `user_sessions`, `email_verifications`, `password_resets`, `login_attempts` | Email normalizado único; tokens sólo como hash; expiración e índices por usuario/fecha. |
| Tenancy | `gyms`, `gym_locations`, `gym_user_roles`, `roles`, `permissions`, `role_permissions` | Un rol por usuario/gimnasio/tipo; slug único; lat/lng; zona horaria IANA; índices por `gym_id`. |
| Personal | `employee_profiles`, `trainer_profiles`, `trainer_gyms` | Estado, especialidades y vínculo opcional a `user_id`; aislamiento por gimnasio. |
| Planes | `membership_plans`, `memberships`, `membership_status_history` | Plan versionado, precio/moneda, fechas, estado; una membresía activa compatible por usuario/gimnasio. |
| Clases | `class_types`, `classes`, `class_schedules`, `class_sessions` | Sesión con inicio/fin UTC y timezone origen; capacidad no negativa; estado; entrenador y sede. |
| Reservas | `reservations`, `waitlist_entries`, `reservation_events` | `UNIQUE(session_id,user_id)`; posición única por sesión; estados explícitos; timestamps. |
| Asistencia | `attendances`, `member_qr_tokens` | Una asistencia por sesión/socio; actor, método, hora; QR corto, rotatorio y revocable. |
| Pagos | `payments`, `payment_events`, `payment_refunds`, `webhook_events`, `idempotency_keys` | `external_reference` único; proveedor+ID externo único; evento externo único; importes decimal y moneda. |
| Promociones | `promotions`, `promotion_gyms`, `promotion_audiences`, `promotion_media`, `promotion_metrics` | Vigencia, estado, reglas, descuento y alcance; archivo validado. |
| Notificaciones | `notification_preferences`, `consents`, `campaigns`, `message_deliveries`, `internal_notifications`, `outbox_jobs` | Dedupe key única por campaña/destinatario/canal; intentos, próxima ejecución y estado. |
| Calendario | `calendar_connections`, `calendar_event_links` | Tokens OAuth cifrados, proveedor+usuario únicos; reserva+proveedor único; ID remoto. |
| Socio | `favorites`, `member_goals`, `member_measurements`, `member_preferences`, `profile_files` | Favorito único; medidas opcionales y privadas; objetivo por período. |
| Operación | `audit_logs`, `exports`, `file_uploads` | Actor, tenant, acción, entidad, before/after seguro, IP, request ID; expiración de archivos. |

### Estrategia para datos actuales

1. Inventariar y respaldar antes de migrar.
2. Crear un gimnasio “principal” temporal sólo para asociar los datos legítimos existentes, nunca para ocultar demos.
3. Mapear `moderador` a `empleado` únicamente tras revisar cada usuario; no inferir que es dueño.
4. Crear `admin_general` y migrar administradores conocidos después de forzar cambio de contraseña.
5. Convertir clases recurrentes en plantillas y generar `class_sessions` futuras con un horizonte configurable.
6. Migrar membresías y pagos sólo si existe evidencia operativa; los datos de demo deben eliminarse o marcarse fuera de producción.
7. Añadir claves foráneas, `NOT NULL`, únicos e índices después de limpiar inconsistencias.
8. Validar totales, huérfanos, duplicados y pertenencia por gimnasio antes y después.

## 9. Contrato de API propuesto

Todas las respuestas usan JSON uniforme: `data`, `meta`, `error.code`, `error.message`, `error.fields`, `request_id`. Fechas ISO 8601; importes como decimal serializado o unidades menores, nunca float; paginación con límite máximo; filtros allowlist; ordenamiento allowlist. Las mutaciones aceptan `Idempotency-Key` cuando crean valor financiero, reserva o notificación.

### Identidad y perfil

- `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `POST /api/auth/logout-all`.
- `POST /api/auth/email/resend`, `POST /api/auth/email/verify`.
- `POST /api/auth/password/forgot`, `POST /api/auth/password/reset`.
- `GET /api/me`, `PATCH /api/me`, `POST /api/me/photo`.
- `GET/PATCH /api/me/preferences`, `GET /api/me/sessions`, `DELETE /api/me/sessions/{id}`.

### Gimnasios, personal y socios

- Público: `GET /api/public/gyms`, `GET /api/public/gyms/{slug}`, `GET /api/public/gyms/{id}/plans`, `GET /api/public/gyms/{id}/sessions`.
- Admin: CRUD de `/api/admin/gyms/{gymId}`, `/members`, `/employees`, `/trainers` y endpoints de invitación/estado.
- Plataforma: `GET/POST/PATCH /api/platform/gyms` y suspensión auditada.

### Clases, reservas y asistencia

- CRUD administrativo de `/api/admin/classes` y `/api/admin/class-sessions`.
- `GET /api/class-sessions` con fecha, gimnasio, actividad, entrenador, cupos y distancia.
- `POST /api/class-sessions/{id}/reservations`, `DELETE /api/reservations/{id}`.
- `POST/DELETE /api/class-sessions/{id}/waitlist` y lectura de posición.
- `POST /api/admin/class-sessions/{id}/attendance` y `POST /api/attendance/scan`.

### Membresías, pagos y finanzas

- `GET /api/me/memberships`, `GET /api/me/payments`, `POST /api/checkouts`.
- `POST /api/payments/mercadopago/webhook` sin sesión, pero con firma y allowlist de evento.
- `POST /api/admin/payments/manual`, `POST /api/admin/payments/{id}/refund`.
- `GET /api/admin/payments`, `/finance/summary`, `/finance/revenue-series`, `/finance/breakdowns`, `/reports`.
- `POST /api/admin/exports` y `GET /api/admin/exports/{id}` con descarga firmada y temporal.

### Promociones, notificaciones y calendario

- CRUD y transiciones `/api/admin/promotions`, más `/schedule`, `/pause`, `/finish`.
- `GET /api/me/notifications`, `POST /api/me/notifications/{id}/read`.
- `GET/PATCH /api/me/notification-preferences` y `POST /api/me/consents/withdraw`.
- `GET /api/reservations/{id}/calendar.ics`, `GET /api/reservations/{id}/google-calendar-url`.
- `POST /api/calendar/google/connect`, callback OAuth, disconnect y sync por reserva.

## 10. Módulos funcionales obligatorios

### 10.1 Gestión de gimnasios, socios, empleados y entrenadores

El dueño puede crear y editar nombre legal/comercial, descripción, dirección, coordenadas, contacto, imágenes, zona horaria, horarios, servicios y estado de publicación. La creación inicial pasa por borrador, verificación y publicación. Los empleados se invitan por email y reciben permisos por gimnasio. Los entrenadores pueden tener especialidades, biografía, foto, disponibilidad y asignaciones de sesión. No se elimina físicamente una persona con historial; se desactiva o archiva.

La ficha de socio agrega membresía actual, reservas, asistencia, pagos, deuda, notas operativas restringidas y registro de cambios. La búsqueda debe soportar nombre, email, teléfono y número de socio, con paginación del servidor.

### 10.2 Clases, reservas, cupos, espera y asistencia

Separar la plantilla “Yoga intermedio, martes 18:00” de la sesión real “2026-08-11T21:00:00Z”. La generación de sesiones respeta la zona horaria del gimnasio y excepciones. Reservar ejecuta en una transacción:

1. Bloquear la sesión o contador de capacidad.
2. Comprobar estado, ventana de reserva, membresía válida y duplicado.
3. Si hay cupo, crear reserva confirmada y actualizar contador derivable.
4. Si no hay cupo y se acepta espera, crear posición única en lista.
5. Guardar evento y notificación en outbox dentro de la misma transacción.

Al cancelar, promover de forma atómica a la primera persona elegible y notificarla. Definir política de cancelación tardía, no-show y liberación de cupo. La asistencia acepta lista manual y QR rotatorio; guarda quién registró el ingreso.

### 10.3 Membresías

Planes por gimnasio con nombre, duración, precio, moneda, acceso, límites, renovación y política de cancelación. Estados internos: `pending_payment`, `active`, `paused`, `past_due`, `cancelled`, `expired`. El historial es append-only. Pausas, extensiones y ajustes manuales requieren permiso, motivo y auditoría. La membresía sólo se activa por pago aprobado verificado o por un pago manual autorizado.

### 10.4 Pagos reales

Definir una interfaz PHP estable:

```text
PaymentProvider
  createCheckout(CheckoutRequest): CheckoutResult
  getPayment(externalPaymentId): ProviderPayment
  refund(RefundRequest): RefundResult
  verifyWebhook(headers, query, rawBody): VerifiedWebhook
```

`MercadoPagoProvider` será el primer adaptador; el dominio no debe conocer campos específicos salvo en un bloque JSON de metadata no sensible. Flujo:

1. El usuario elige un plan. PHP valida plan, precio, moneda, gimnasio y elegibilidad.
2. PHP crea un `payment` interno `created`, `external_reference` UUID/ULID único y clave de idempotencia; un único checkout abierto por intención lógica.
3. El adaptador crea el checkout en modo prueba o producción según configuración, envía la referencia y guarda el ID externo.
4. El frontend redirige o monta Checkout. La URL de éxito sólo muestra “Estamos confirmando tu pago”. Nunca aprueba nada.
5. El webhook valida `x-signature`, `x-request-id`, timestamp y secreto. Se persiste el evento crudo saneado con clave única antes de procesar.
6. PHP consulta la API de Mercado Pago por el ID notificado y compara cuenta, `live_mode`, referencia, monto y moneda.
7. Una transacción bloquea pago y membresía, aplica una transición válida, inserta `payment_event` y activa la membresía sólo si el estado confirmado es aprobado.
8. Eventos repetidos devuelven éxito sin repetir efectos. Eventos fuera de orden se guardan y se reconcilian con el estado remoto.
9. Reembolsos parciales/totales usan la misma idempotencia, guardan actor y eventos; la política decide el efecto sobre la membresía.
10. Una tarea de reconciliación consulta pagos pendientes antiguos y alerta diferencias.

Estados internos mínimos: `created`, `pending`, `in_process`, `approved`, `rejected`, `cancelled`, `expired`, `refunded`, `partially_refunded`, `chargeback`. Mantener mapping versionado del proveedor. Credenciales separadas: `MP_ACCESS_TOKEN_TEST`, `MP_WEBHOOK_SECRET_TEST`, `MP_ACCESS_TOKEN_LIVE`, `MP_WEBHOOK_SECRET_LIVE`, `PAYMENTS_MODE`; nunca enviarlas a Vue ni registrarlas en logs.

La documentación oficial actual de Mercado Pago exige validar la firma secreta del webhook y recomienda URLs distintas para prueba y producción. Tras confirmar la recepción, la aplicación debe recuperar el recurso completo desde la API del proveedor: [Webhooks de Mercado Pago](https://www.mercadopago.com.uy/developers/en/docs/your-integrations/notifications/webhooks). La idempotencia debe existir tanto internamente como en el header del proveedor cuando su operación lo admita.

### 10.5 Finanzas y reportes

La fuente de verdad son pagos aprobados, reembolsos y ajustes manuales auditados. No multiplicar socios por un precio supuesto. El resumen debe ofrecer:

- Ingresos del mes y de los últimos seis meses agrupados en la zona horaria seleccionada.
- Comparación con período anterior de igual duración, valor absoluto y porcentaje; manejar divisor cero.
- Conteos e importes de aprobados, pendientes, rechazados, vencidos y reembolsados.
- Deuda total basada en membresías vencidas/cuotas exigibles, no en pagos pendientes abandonados.
- Desglose por gimnasio, plan/membresía y método de pago.
- Tabla de transacciones con búsqueda, filtros por fecha/estado/gimnasio/método, ordenamiento allowlist y paginación servidor.
- Exportación Excel y PDF generadas en backend con el mismo filtro y zona horaria que la pantalla.

Endpoints de series devuelven buckets con `period_start`, `gross`, `refunds`, `net`, `currency` y `count`. No mezclar monedas; mostrar selector o reportes separados. Índices mínimos: `(gym_id,status,approved_at)`, `(membership_id)`, `(provider,external_id)` y `(created_at)`. Los reportes pesados se generan como jobs; la descarga expira y queda auditada. Excel debe usar una librería PHP mantenida como PhpSpreadsheet; PDF puede usar Dompdf o mPDF después de una prueba de fuentes, tablas largas y consumo de memoria.

### 10.6 Google Calendar e ICS

Ofrecer dos niveles:

- **Sin conexión:** botón “Agregar a Google Calendar” que abre la URL prellenada y botón “Descargar ICS”. Ambos se generan desde la sesión/reserva real.
- **Con conexión:** OAuth con el menor scope aplicable, tokens cifrados en servidor, conexión revocable y sincronización create/update/delete.

El evento incluye título, inicio/fin, zona horaria IANA del gimnasio, dirección, entrenador, descripción, enlace a la reserva y `UID` estable. ICS usa CRLF, escaping correcto, `DTSTAMP`, `UID`, `SEQUENCE`, `STATUS` y `VTIMEZONE` o tiempos UTC. RFC 5545 exige un identificador persistente y globalmente único y define el tratamiento de zonas horarias: [RFC 5545](https://www.rfc-editor.org/info/rfc5545/).

Guardar `reservation_id`, proveedor, calendar ID, remote event ID, checksum/versión y última sincronización con restricción única. Crear es idempotente; actualizar incrementa versión; cancelar marca/cancela el evento según política. Una cola reintenta fallos transitorios y comunica al usuario. Google requiere OAuth y acceso de escritura para crear eventos y usa `start.dateTime/end.dateTime` para eventos con hora: [guía oficial de Google Calendar](https://developers.google.com/workspace/calendar/api/guides/create-events).

### 10.7 Promociones y notificaciones

Promoción con borrador, programada, activa, pausada y finalizada. Incluye gimnasios, audiencia, ventana temporal, imagen, copy, tipo/valor de descuento, condiciones y URL. La audiencia se materializa al programar o se calcula con una versión de reglas auditable. Resultados: entregados, abiertos cuando el canal lo permita, clics, conversiones y bajas, sin atribuir causalidad falsa.

Canales mediante interfaces `NotificationChannel`: interno, email y WhatsApp opcional. WhatsApp no se integra directamente en el dominio; un adaptador futuro implementa plantillas aprobadas y credenciales propias.

Requisitos operativos:

- Consentimiento versionado por finalidad y canal; publicidad separada de mensajes transaccionales.
- Preferencias editables y baja de marketing en un paso.
- `outbox_jobs` creado en la transacción del evento de negocio.
- `message_deliveries` con `pending`, `sending`, `sent`, `failed`, `cancelled`, contador, próximo intento y error seguro.
- Clave de deduplicación única por campaña, usuario, canal y versión.
- Retries exponenciales con jitter, dead-letter y reenvío manual auditado.
- Email con dominio autenticado, plantillas texto/HTML, enlace de baja y rebotes.
- Notificación interna persistente, contador no leído y marcado idempotente.

### 10.8 Mapa y geolocalización

Encapsular Leaflet en un componente Vue que crea y destruye su instancia, carga la biblioteca como dependencia y conserva atribución visible. La implementación actual desactiva la atribución, algo incompatible con la [política oficial de tiles de OpenStreetMap](https://operations.osmfoundation.org/policies/tiles/). Para tráfico productivo debe evaluarse un proveedor de tiles OSM con SLA y URL configurable; los servidores estándar son best effort y no admiten precarga masiva.

Flujo:

1. Mostrar ubicación predeterminada configurable, por ejemplo la ciudad seleccionada, mientras carga.
2. Solicitar geolocalización sólo tras una acción clara; explicar el beneficio.
3. En permiso concedido, colocar marcador del usuario, calcular distancia Haversine y ordenar si el usuario lo pide.
4. En permiso rechazado/no disponible/timeout, mantener mapa útil y ofrecer ciudad/dirección manual.
5. Filtros por actividad, horario, precio, servicios y distancia se reflejan en URL.
6. Marcadores agrupados a zoom bajo. Hover/foco de tarjeta destaca marcador; click de marcador selecciona y lleva la tarjeta a vista.
7. Botón accesible para volver a centrar, con estados buscando/error/listo.

Escritorio: un componente dividido, panel lateral de 380 a 440 px contraíble, scroll interno y mapa flexible sin overlays externos. Móvil: mapa de fondo y bottom sheet con posiciones cerrada, media y completa, asa, snap accesible también por botones, tarjetas compactas y respeto a safe areas. Nunca anidar scrolls sin límite ni ocultar la atribución.

### 10.9 Perfil y funciones del socio

- Foto validada y procesada, datos personales y contacto verificado.
- Favoritos de gimnasio y actividad; horarios preferidos.
- Preferencias por canal y finalidad.
- Objetivo mensual medible, progreso basado en asistencias reales.
- Próxima clase, reservas, membresías, pagos pendientes e historial.
- Promociones relevantes según consentimiento y gimnasio.
- Carné digital y QR rotatorio de corta vida.
- Historial opcional de altura, peso y medidas con control de privacidad y eliminación.
- IMC sólo como cálculo orientativo, acompañado por una advertencia no diagnóstica; no usarlo para decisiones automáticas.
- Acciones rápidas: reservar, mostrar carné, pagar pendiente, ver próxima clase y contactar al gimnasio.

La inteligencia artificial queda documentada para una versión posterior. No debe desviar trabajo de identidad, reservas, pagos, seguridad ni calidad de datos de v1.0.

## 11. Seguridad y confiabilidad

### Controles obligatorios

| Área | Estado actual | Objetivo v1.0 |
|---|---|---|
| Validación backend | Manual e incompleta | Esquemas por endpoint; rechazo de campos desconocidos; límites de longitud, formato, rango y estado. |
| SQL | PDO preparado en muchas consultas | Preparadas en todas; filtros/orden allowlist; usuario DB sin privilegios de root. |
| Contraseñas | Bcrypt para nuevas, fallback plano | `password_hash/password_verify`, rehash progresivo, mínimo razonable, contraseñas comprometidas opcional, sin credencial demo. |
| Autorización | ID de rol exacto y sin tenant | Políticas por capacidad y gimnasio en PHP; denegar por defecto; tests de acceso cruzado. |
| CSRF | No implementado | Token sincronizado para sesión por cookie; comprobar origen en mutaciones sensibles. |
| XSS | `htmlspecialchars` al guardar y Vue para render | Guardar datos crudos validados; escapar según contexto al salir; CSP; sanitizar HTML permitido. |
| Archivos | No existe flujo | MIME por contenido, tamaño/dimensiones, nombre generado, storage fuera de webroot, antivirus opcional, variantes de imagen y URLs temporales. |
| Rate limiting | No existe | Login, registro, reset, email, búsqueda/geocoding, checkout, webhook y exportación; Redis no es obligatorio para una sola instancia, pero el backend debe abstraer el store. |
| Sesiones/cookies | Session ID en `localStorage` | Cookie HttpOnly/Secure/SameSite, rotación, expiración, revocación, lista de sesiones y no cachear respuestas privadas. |
| Webhooks | No existen | Firma, tolerancia temporal, consulta remota, dedupe, orden eventual, raw body limitado y logs sin secretos. |
| Tenant | No existe | `gym_id` obligatorio y aplicado desde contexto autorizado; admin general con acceso explícito y auditado. |
| Auditoría | No existe | Append-only para roles, configuración, pagos, reembolsos, membresías, exportes, archivos y accesos de soporte. |
| Secretos | Hardcodeados | Secret manager o variables inyectadas; `.env.example` sin valores; rotación y comprobación al iniciar. |
| Errores | `display_errors=1` | Mensaje público genérico, código estable y request ID; detalle sólo en log estructurado. |
| Transacciones | Operaciones aisladas | Reservas/cupos/espera, pago/membresía, reembolso, outbox y cambios administrativos multi-tabla atómicos. |
| Idempotencia | No existe | Tabla de claves con actor, endpoint, hash de request, respuesta y expiración; únicos de negocio. |

Añadir headers `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options`, `Referrer-Policy` y una política de permisos que permita geolocalización sólo al propio origen. No cargar Leaflet ni fuentes desde CDN sin una política explícita, integridad y fallback; preferir assets empaquetados/autohospedados. Los logs deben ser JSON con request ID, usuario/tenant cuando corresponda y redacción de email, tokens, cabeceras de autorización, credenciales, cuerpos de webhook sensibles y datos de pago.

### Separación de datos por gimnasio

Regla de oro: una ruta operativa resuelve primero el gimnasio autorizado, luego toda consulta incluye ese `gym_id`. Los repositorios no exponen métodos administrativos sin tenant salvo en el módulo exclusivo de plataforma. Deben existir tests que intenten leer, modificar, exportar y reservar recursos de otro gimnasio con IDs válidos. El admin general selecciona un gimnasio de forma explícita, ve una señal visual de “modo soporte” y deja audit log con motivo.

### Backups, observabilidad y recuperación

- Backup cifrado automático de MySQL, retención documentada y restauración probada trimestralmente.
- Métricas de latencia, tasa 4xx/5xx, login fallido, reservas fallidas, cupos inconsistentes, webhooks, jobs, pagos pendientes y exportes.
- Alertas por pagos aprobados sin membresía, webhooks muertos, cola acumulada, errores 5xx y backup fallido.
- Health endpoints separados: liveness sin dependencias y readiness con MySQL/migraciones.
- Runbooks de caída de proveedor de pago, cola, correo, mapas y base de datos.
- Objetivos iniciales: 99,5% disponibilidad mensual, RPO 24 h máximo al comienzo y RTO 4 h, a ajustar con negocio.

## 12. Plan de implementación en 11 fases

Las prioridades usadas son: **Bloqueante**, **Alta**, **Media** y **Mejora posterior**. Ninguna fase se considera terminada sólo porque la UI existe; debe aprobar sus criterios funcionales, autorización, datos, estados y pruebas.

### Fase 1. Errores bloqueantes y botones sin funcionamiento

**Objetivo:** conseguir una base honesta y ejecutable, donde lo visible funcione o esté claramente deshabilitado.

- Problemas: auth `401`, contratos controller/model, `period.value`, logout móvil, rutas falsas, imports API incompatibles, datos demo mezclados y acciones inertes.
- Tareas: **Bloqueante** corregir transporte de sesión/header, consolidar cliente HTTP, alinear contratos PHP y pruebas; **Alta** conectar o retirar temporalmente vistas/rutas inaccesibles; **Alta** inventariar cada acción de la tabla anterior en tickets; **Alta** retirar métricas falsas de flujos productivos; **Media** 404/401/403 y manejo global de errores.
- Archivos probables: `docker-compose.yml`, configuración Apache nueva, `backend/index.php`, `backend/middleware/AuthMiddleware.php`, rutas/controladores/modelos actuales, `frontend/src/services/api.js`, store de auth, router y todas las vistas activas.
- Componentes: `AppButton`, `AsyncState`, `ErrorBanner`, `ConfirmDialog`, route guards.
- Rutas: conservar las actuales mientras se crean aliases hacia `/app` y `/administracion`; añadir páginas de error.
- Endpoints: estabilizar `/api/auth/*`, `/api/perfil`, clases, reservas y membresías existentes antes de expandir.
- Tablas/migraciones: `schema_migrations`, `user_sessions`, corrección segura del admin y únicos urgentes de reserva.
- Dependencias: actualizar Axios/PostCSS dentro de compatibilidad; eliminar Swiper si sigue sin uso.
- Riesgos: romper sesiones existentes o interpretar datos demo como reales. Mitigar con backup, entorno de staging y feature flags.
- Pruebas: login/perfil/logout, guard de frontend, cada endpoint actual, montaje de cada vista, smoke de todos los botones y regresión del build.
- Criterios de aceptación: login real funciona tras reiniciar contenedores; logout invalida la sesión; ninguna llamada válida termina en fatal; ningún botón visible carece de destino/estado; demo está etiquetada y aislada; 0 vulnerabilidades altas conocidas sin aceptación documentada.

#### Estado de ejecución de la Fase 1 — 5 de agosto de 2026

**Implementado y verificado:** transporte de sesión por cookie HttpOnly y Bearer compatible; logout idempotente e invalidación real; autorización administrativa desde PHP; contratos de clases, reservas y membresías alineados; reserva/cancelación transaccional con prevención de duplicados; rutas 403/404; guard de frontend que valida el perfil; errores de red normalizados; `periodo` administrativo corregido; estados de carga y bloqueo en acciones activas; acciones aún no disponibles deshabilitadas y rotuladas; datos demo identificados; enlaces falsos retirados; mapa con atribución OSM; correcciones bloqueantes de overflow a 360 y 1440 px; migración de integridad idempotente y registrada; semillas sin credenciales conocidas; variables sensibles trasladadas a entorno; build, contratos, smoke API, cookie, roles y auditoría de dependencias aprobados.

**Decisiones conscientes para fases siguientes:** el reemplazo total de la sesión compatible por cookie same-origin con CSRF pertenece a la Fase 3; las vistas administrativas antiguas que no están registradas en el router permanecen fuera del bundle activo hasta la Fase 4; los datos demostrativos del dashboard están aislados y avisados, y se sustituirán por datos multi-gimnasio reales en las fases 4–7; el sistema tipográfico, la matriz responsive completa de seis anchos y la eliminación de dependencias visuales sin uso se resolverán en la Fase 2.

### Fase 2. Sistema visual, página de inicio y responsive

**Objetivo:** establecer un lenguaje visual consistente y corregir la experiencia pública en los seis anchos.

- Problemas: CSS anidado accidentalmente, cuatro columnas móviles, hero sobrecargado, navegación rota, repetición de gimnasios, modales inaccesibles, falta de estados y exceso de glow.
- Tareas: **Alta** tokens y componentes base; **Alta** refactor de Home con datos reales o estados vacíos; **Alta** menú móvil accesible; **Alta** corrección integral a 360/390/768/1024/1440/1920; **Media** footer legal; **Media** motion reducido.
- Archivos probables: `App.vue`, `HomeView.vue`, hoja global nueva, `components/ui/*`, router y assets.
- Componentes: botones, inputs, cards funcionales, dialog, drawer, toast, skeleton, empty/error state, header/footer.
- Rutas: `/`, `/gimnasios`, `/planes`, `/para-gimnasios`, 404.
- Endpoints: `GET /api/public/gyms`, planes y sesiones destacadas; todos toleran vacío.
- Tablas/migraciones: `gyms` y `membership_plans` pueden empezar en lectura; no crear contenido falso.
- Dependencias: preferir CSS/Vue nativo; iconos SVG consistentes si se incorpora una librería pequeña; fuentes autohospedadas.
- Riesgos: rediseño amplio antes de fijar datos. Usar contratos mock sólo en tests/story fixtures, no en runtime productivo.
- Pruebas: snapshots/component tests, axe, teclado, contraste, zoom 200%, viewport matrix, ausencia de overflow, Lighthouse como señal no única.
- Criterios de aceptación: sin scroll horizontal; ninguna sección cortada/superpuesta; navegación y diálogo operables por teclado; CTA con destinos diferentes y reales; first paint legible incluso sin animación; estados completos.

#### Estado de ejecución de la Fase 2 — 5 de agosto de 2026

**Implementado:** sistema de tokens semánticos y estilos base; Manrope autohospedada; librería única de iconos Tabler; componentes reutilizables para botones, campos, tarjetas, badges, alertas, diálogos, drawer, toast, skeleton, vacío, error, spinner y encabezado; portada pública reestructurada; navegación responsive; footer con destinos reales; rutas `/gimnasios`, `/planes`, `/para-gimnasios`, `/privacidad`, `/terminos`, `/accesibilidad` y `/contacto`; Leaflet cargado como dependencia local y mapa/lista unificados con panel contraíble en escritorio y bottom sheet de tres posiciones en móvil; imágenes WebP locales; títulos por ruta y carga diferida de vistas secundarias.

**Integridad de producto:** la portada dejó de consumir `demoData.js`; no publica gimnasios, precios, distancias, planes ni métricas inventadas. Cuando el endpoint público todavía no existe, muestra un estado vacío explícito. El dashboard demostrativo heredado continúa aislado y rotulado hasta las fases 4–7. La cuenta administrativa se conserva con hash seguro; esta fase no modifica el mecanismo de autenticación ni implementa recuperación, roles nuevos o lógica completa de geolocalización.

**Verificado:** build productivo; pruebas unitarias de estados, formularios y overlays; foco atrapado, cierre con `Escape` y restauración de foco; navegación móvil; auditoría axe sin violaciones serias o críticas; contraste AA de acciones y marca; capturas y ausencia de desbordamiento horizontal a 360, 390, 768, 1024, 1440 y 1920 px. Swiper y las cargas CDN de Google Fonts/Leaflet fueron retiradas. La documentación de producto, dirección por superficie y sistema visual queda versionada junto al código.

**Resuelto después en la Fase 3:** el catálogo de presentación ya consume cinco gimnasios identificados desde MySQL y los cuatro roles reciben contexto mínimo. La geolocalización, distancias y el CRUD operativo definitivo siguen reservados para sus fases correspondientes.

### Fase 3. Autenticación, recuperación y roles

**Objetivo:** identidad segura y modelo de permisos requerido.

- Problemas: sesión expuesta, roles incompatibles, falta recuperación/verificación, Turnstile aparente, rate limit ausente.
- Tareas: **Bloqueante** sesiones seguras y CSRF; **Bloqueante** migración de roles socio/empleado/dueño/admin general; **Alta** verificación y reset; **Alta** rate limits/lockouts; **Alta** middleware de tenant/capacidad; **Media** sesiones activas y logout global.
- Archivos probables: auth store/router/views, `AuthController`, middleware, nuevos servicios/repositorios de identidad, mailer y configuración.
- Componentes: login, registro, forgot/reset, verificación, selector de contexto, Forbidden/SessionExpired.
- Rutas: `/login`, `/registro`, `/verificar-email`, `/recuperar`, `/restablecer/:token`.
- Endpoints: todos los de identidad y `/api/me` definidos en la sección 9.
- Tablas/migraciones: `roles`, `permissions`, `role_permissions`, `gym_user_roles`, `user_sessions`, `email_verifications`, `password_resets`, `login_attempts`, `consents`.
- Dependencias: mailer PHP mantenido; no inventar criptografía.
- Riesgos: bloquear usuarios existentes. Crear herramienta de migración, reset forzado y soporte auditado.
- Pruebas: enumeración, token expirado/usado, CSRF, fijación de sesión, brute force, matriz de roles y acceso cruzado.
- Criterios de aceptación: PHP deniega por defecto; frontend no decide autorización; correo verificado según política; reset invalida sesiones; las cuatro identidades sólo ven sus rutas y datos.

#### Estado de ejecución parcial de la Fase 3 — 5 de agosto de 2026

**Alcance aprobado para la presentación:** usuarios, roles, permisos y asociaciones mínimas con gimnasio. Se migraron los roles estables socio/administrador general/empleado/dueño, la matriz de permisos y la relación usuario–gimnasio. Login y perfil devuelven rol y contextos desde PHP; las cuentas existentes se conservan. El CRUD completo de gimnasios no fue implementado.

**Dataset controlado:** la migración versionada agrega `demo_datasets`, `gimnasios` y marcadores `is_demo`/`demo_dataset_id`. `seed:demo`, `--reset`, `--remove` y `--status` crean cinco gimnasios y cuatro usuarios de forma transaccional e idempotente; rechazan colisiones con registros reales, requieren contraseña de entorno y bloquean producción por defecto. La interfaz lee el catálogo desde la API/MySQL, muestra “Datos de demostración” y no usa `demoData.js`. Las lecturas administrativas demo están aisladas de registros reales y el panel de presentación es de sólo lectura hasta el CRUD de la Fase 5.

**Módulos beta:** WhatsApp, sincronización automática con Google Calendar, analítica avanzada, recomendaciones personalizadas y promociones de presentación están apagados por feature flags. Cuando se habilitan, sólo explican alcance funcional y pendiente; no muestran confirmaciones ni acciones falsas.

**Pendiente dentro de la Fase 3 completa:** recuperación de contraseña, validación de correo, CSRF definitivo, rate limiting/lockout y gestión de sesiones. Estos puntos siguen siendo necesarios antes de producción aunque no bloqueen la presentación controlada solicitada.

### Fase 4. Administración y gestión operativa

**Objetivo:** hacer visible y navegable la operación diaria.

- Problemas: página única extensa, navegación inexistente, accesos rápidos inertes, finanzas/reportes escondidos y estados no compartibles.
- Tareas: **Alta** shell Administración y rutas; **Alta** resumen y gestión operativa; **Alta** selector seguro de gimnasio; **Alta** quick actions; **Media** breadcrumbs, filtros persistentes y command palette opcional.
- Archivos probables: nuevo layout administrativo, router, `views/admin/*`, componentes de sidebar, page header, tablas y permisos.
- Componentes: `AdminShell`, `AdminSidebar`, `QuickActions`, `MetricCard`, `DataTable`, `FilterBar`, `AuditSummary`.
- Rutas: todas las de `/administracion/*`; finanzas y reportes en primer nivel.
- Endpoints: `/api/admin/summary`, contadores operativos, actividad reciente y permisos.
- Tablas/migraciones: `audit_logs`, `exports`; usa entidades de fases siguientes en estados vacíos.
- Dependencias: ninguna librería grande necesaria; decidir tabla accesible antes de sumar una.
- Riesgos: construir dashboards sobre endpoints aún no listos. Cada widget debe fallar de forma aislada y no inventar fallback.
- Pruebas: navegación por rol, deep links, sidebar móvil, permisos por acceso rápido, fallos parciales y empty states.
- Criterios de aceptación: Administración es visible para roles autorizados; cada sección se alcanza en máximo un clic desde sidebar; quick actions abren flujos reales; Finanzas y Reportes están a un clic.

### Fase 5. Gimnasios, socios, empleados y membresías

**Objetivo:** establecer el tenant y las entidades comerciales principales.

- Problemas: gimnasio inexistente en BD, datos repetidos demo, personal sin modelo, membresías sin plan/gimnasio/historial.
- Tareas: **Bloqueante** migración tenant; **Alta** CRUD de gimnasio y sedes; **Alta** socios/invitaciones; **Alta** empleados/permisos; **Alta** entrenadores; **Alta** planes y membresías; **Media** fotos y documentos seguros.
- Archivos probables: módulos PHP `Gyms`, `Members`, `Staff`, `Memberships`; vistas admin y socio; repositorios y validadores.
- Componentes: formularios de gimnasio/plan, tablas de personas, ficha de socio, selector de permiso, uploader.
- Rutas: administración de gimnasios, socios, empleados, entrenadores y membresías; detalles públicos del gimnasio.
- Endpoints: CRUD listados en sección 9 con filtros/paginación.
- Tablas/migraciones: `gyms`, `gym_locations`, `gym_user_roles`, perfiles de empleado/entrenador, `membership_plans`, `memberships`, historial y archivos.
- Dependencias: procesador de imágenes PHP; almacenamiento abstraído local/S3-compatible sin cambiar aplicación.
- Riesgos: fuga entre gimnasios, duplicar usuarios por email, migrar moderadores incorrectamente.
- Pruebas: aislamiento tenant, uniques, permisos, upload malicioso, paginación, desactivación con historial y migración reversible.
- Criterios de aceptación: dueño sólo opera sus gimnasios; un usuario puede pertenecer a más de uno con rol distinto; membresías y planes son trazables; no hay datos demo en producción.

### Fase 6. Clases, reservas, cupos y asistencia

**Objetivo:** completar el flujo reserva-asistencia con consistencia bajo concurrencia.

- Problemas: clase sin fecha real, métodos rotos, duplicados, sobreventa, cancelación insegura y lista de espera ausente.
- Tareas: **Bloqueante** plantillas/sesiones; **Bloqueante** transacción de reserva/cupo; **Alta** espera/promoción; **Alta** cancelaciones y notificaciones; **Alta** asistencia/QR; **Media** políticas no-show.
- Archivos probables: módulos `Classes`, `Reservations`, `Attendance`, workers/outbox; vistas de agenda, reserva y operación.
- Componentes: calendario/lista de sesiones, selector de cupo, waitlist badge, reserva detail, QR, roster de asistencia.
- Rutas: `/app/clases`, `/app/reservas/:id`, administración de clases/reservas/operación.
- Endpoints: sesiones, reservas, espera y asistencia definidos en sección 9.
- Tablas/migraciones: `class_types`, `classes`, `class_schedules`, `class_sessions`, `reservations`, `waitlist_entries`, eventos, asistencias y tokens QR.
- Dependencias: librería pequeña de QR auditada; fechas manejadas en PHP/MySQL con IANA y UTC.
- Riesgos: condiciones de carrera, DST, QR compartido, promociones dobles desde espera.
- Pruebas: concurrencia con último cupo, reserva doble, cancelación simultánea, transición de espera, DST, política tardía, QR expirado y autorización.
- Criterios de aceptación: nunca se supera capacidad; una persona no duplica reserva/espera; cada sesión tiene instante inequívoco; cancelar repone/promueve atómicamente; asistencia queda auditada.

### Fase 7. Pagos, finanzas y reportes

**Objetivo:** cobrar y reportar dinero real con trazabilidad e idempotencia.

- Problemas: tabla de pagos mínima, métricas ficticias, sin proveedor/webhook/reembolso/exportación.
- Tareas: **Bloqueante** dominio e interfaz de proveedor; **Bloqueante** Mercado Pago test + webhook firmado; **Bloqueante** activación segura; **Alta** reembolsos y reconciliación; **Alta** pagos manuales; **Alta** finanzas/transacciones; **Alta** Excel/PDF; **Media** alertas operativas.
- Archivos probables: módulos `Payments`, `Finance`, `Reports`, adaptador Mercado Pago, worker, vistas checkout/pagos/finanzas/reportes.
- Componentes: plan checkout, payment status, transaction table, filter bar, real charts, refund/manual payment dialogs, export center.
- Rutas: socio pagos/membresías y administración pagos/finanzas/reportes.
- Endpoints: checkout, webhook, pagos, reembolsos, resúmenes, series, breakdowns y exports.
- Tablas/migraciones: `payments`, `payment_events`, `payment_refunds`, `webhook_events`, `idempotency_keys`, `exports`, vínculos a membresía/gimnasio.
- Dependencias: SDK PHP oficial de Mercado Pago fijado y revisado; PhpSpreadsheet; PDF elegido tras benchmark; librería de gráficas Vue accesible y mantenida.
- Riesgos: doble cobro, webhook falso/fuera de orden, mezcla de moneda, reembolso inconsistente, exportación pesada.
- Pruebas: sandbox completo, firmas inválidas, replay, evento duplicado/fuera de orden, callback de éxito sin webhook, montos distintos, concurrencia, refund parcial/total, Excel/PDF y permisos.
- Criterios de aceptación: regreso de checkout nunca aprueba; sólo confirmación remota válida activa; external reference única; replay no duplica efectos; totales reconcilian con transacciones; exportes coinciden con filtros.

### Fase 8. Mapa y geolocalización

**Objetivo:** exploración real, útil y conforme a OSM en escritorio y móvil.

- Problemas: coordenadas demo, permiso ignorado, sin distancia/clusters/sincronización, overlays y atribución oculta.
- Tareas: **Alta** componente Leaflet con lifecycle; **Alta** geolocalización/fallback; **Alta** distancia/orden/filtros; **Alta** clusters y sincronización; **Alta** split panel/bottom sheet; **Media** proveedor de tiles configurable.
- Archivos probables: `GymMap.vue`, `GymResultsPanel.vue`, `MobileMapSheet.vue`, composable de geolocalización, vistas públicas/app y configuración.
- Componentes: mapa, marcador accesible, cluster, filtros, tarjeta compacta, recenter y sheet.
- Rutas: `/gimnasios` y `/app/gimnasios` con query persistente.
- Endpoints: `/api/public/gyms` con bounding box, lat/lng, radio, filtros, sort y cursor/página.
- Tablas/migraciones: lat/lng/zona horaria en sedes; índices geográficos si la versión MySQL lo admite, con fallback por bounding box.
- Dependencias: Leaflet empaquetado, plugin de clustering mantenido; proveedor OSM configurable.
- Riesgos: privacidad de ubicación, límites de tiles/geocoding, mapas ocultos que requieren `invalidateSize`, scroll atrapado.
- Pruebas: permiso granted/denied/timeout, ubicación predeterminada, distancias, sync bidireccional, sheet en tres posiciones, teclado, seis anchos y atribución visible.
- Criterios de aceptación: mapa siempre útil sin permiso; orden por cercanía correcto; no hay superposición/overflow; panel y mapa son una unidad; atribución y política respetadas.

### Fase 9. Calendario, promociones y notificaciones

**Objetivo:** conectar reservas y campañas con canales confiables y consentimiento.

- Problemas: Calendar ausente, promociones inexistentes, notificaciones mínimas sin canal/estado/retry/preferencias.
- Tareas: **Alta** URL Google + ICS; **Media** OAuth/sync bidireccional; **Alta** CRUD/estado de promociones; **Alta** preferencias/consentimiento; **Alta** interno+email/outbox; **Media** interfaz WhatsApp sin proveedor obligatorio.
- Archivos probables: módulos `Calendar`, `Promotions`, `Notifications`, workers, plantillas y vistas.
- Componentes: calendar actions, connection settings, promotion editor, audience builder, delivery report, notification center/preferences.
- Rutas: detalle de reserva, preferencias, notificaciones y administración/promociones.
- Endpoints: Calendar, promociones, preferencias y entregas definidos en sección 9.
- Tablas/migraciones: conexiones/eventos Calendar; promociones/targets/media; consents/preferences/campaigns/deliveries/internal/outbox.
- Dependencias: cliente OAuth Google, generador ICS compatible RFC 5545, mailer; adaptador WhatsApp sólo al elegir proveedor.
- Riesgos: tokens OAuth, duplicados, cambios de zona horaria, spam, retirada de consentimiento, jobs repetidos.
- Pruebas: UID estable, DST, update/cancel, OAuth revocado, dedupe, retry/dead-letter, baja inmediata, audiencia/tenant y plantillas.
- Criterios de aceptación: ICS se importa con hora/dirección correctas; sync no duplica; campaña respeta consentimiento; cada entrega tiene estado e historial; reintentos no duplican mensajes.

### Fase 10. Perfil, preferencias y funciones del socio

**Objetivo:** convertir el área de socio en una herramienta diaria completa.

- Problemas: perfil mínimo, progreso/membresía/reservas demo, sin favoritos, carné, medidas ni preferencias completas.
- Tareas: **Alta** home socio real; **Alta** perfil/foto/preferencias; **Alta** favoritos; **Alta** objetivos/asistencia/progreso; **Alta** carné/QR; **Media** medidas e IMC orientativo; **Alta** navegación móvil inferior.
- Archivos probables: módulos `Members/Profile`, vistas `/app/*`, componentes de resumen y stores por dominio.
- Componentes: next class, membership/payment status, goal progress, attendance history, favorites, profile form, photo upload, digital card, measurement log.
- Rutas: todas las rutas de socio enumeradas en sección 7.
- Endpoints: `/api/me/*`, favoritos, objetivos, medidas, asistencias, membresías, pagos y promociones relevantes.
- Tablas/migraciones: favoritos, objetivos, medidas, preferencias, archivos, QR; reutiliza reservas/pagos/asistencia.
- Dependencias: procesamiento de imagen/QR ya elegidos.
- Riesgos: privacidad de medidas, cálculo de progreso inconsistente, navegación saturada.
- Pruebas: propietario de datos, upload, preferencias, objetivo por mes/timezone, IMC con límites y copy no diagnóstico, QR revocado, barra inferior/safe area.
- Criterios de aceptación: ningún dato del socio es demo; próxima clase y progreso concuerdan con sesiones/asistencia; datos opcionales pueden omitirse/borrarse; acciones prioritarias están a un toque.

### Fase 11. Seguridad, pruebas y producción

**Objetivo:** demostrar que GymTrack puede operarse, desplegarse y recuperarse con seguridad.

- Problemas: sin CI/tests, Docker dev, secretos fijos, errores expuestos, sin backup/monitoring/runbooks.
- Tareas: **Bloqueante** revisión de seguridad y tenant; **Bloqueante** CI y suites críticas; **Alta** imágenes productivas/healthchecks/env; **Alta** backups/restauración; **Alta** logs/métricas/alertas; **Alta** performance/accesibilidad; **Alta** staging, migración y rollback; **Media** documentación operativa.
- Archivos probables: Dockerfiles productivos, Compose de producción o manifiesto equivalente, config Apache, CI, tests, `.env.example`, scripts de migración/backup y documentación.
- Componentes: error boundary, maintenance/offline states, status surfaces administrativos.
- Rutas/endpoints: health, readiness y administración/auditoría; no exponer diagnóstico sensible públicamente.
- Tablas/migraciones: cierre de constraints/índices, política de retención, partición/archivo si volumen lo requiere.
- Dependencias: PHPUnit, runner de tests Vue (Vitest), Vue Test Utils, Playwright para E2E y herramienta de análisis estático PHP; fijar versiones y revisar licencias.
- Riesgos: falsos positivos, migración prolongada, rollback de esquema/datos, secretos en historial o logs.
- Pruebas: unitarias, integración MySQL real, contratos API, E2E por rol, concurrencia, seguridad, accesibilidad, visual responsive, carga, backup restore y smoke posdeploy.
- Criterios de aceptación: pipeline verde obligatorio; 0 P0/P1 abiertos sin aceptación del responsable; restore demostrado; secretos fuera del repo; producción sin dev server/display_errors; alertas y rollback probados; checklist de salida firmado.

## 13. Estrategia de pruebas

### Pirámide mínima

- Unitarias PHP: validadores, políticas, mappers de estados, cálculos financieros, recurrencia y zonas horarias.
- Integración PHP/MySQL: repositorios, migraciones, transacciones, isolation tenant, reservas concurrentes, pagos/eventos.
- Componentes Vue: formularios, estados async, tablas, diálogos, drawer, bottom sheet y gráficas con vacío/error.
- Contratos: OpenAPI como fuente revisable; comprobar que cliente y backend comparten métodos, paths y shapes.
- E2E: registro-verificación-login-reset; socio compra/reserva/cancela; empleado asiste; dueño administra/exporta; admin general soporta con auditoría.
- Seguridad: OWASP ASVS proporcional, CSRF/XSS/IDOR/upload/rate limit/session fixation/webhook replay.
- Accesibilidad: axe automatizado más recorrido manual de teclado y lector en flujos críticos.
- Responsive/visual: 360, 390, 768, 1024, 1440 y 1920; zoom 200%; contenido largo; español; errores; teclado virtual.
- Rendimiento: p95 de endpoints, consultas N+1, índices, bundle, LCP/INP/CLS y carga de tablas/exportes.

### Casos que deben bloquear un release

1. Acceso a datos de otro gimnasio cambiando un ID.
2. Membresía activada por URL de retorno sin verificación remota.
3. Pago/webhook/reintento que produce dos efectos.
4. Reserva que supera cupo o se duplica bajo concurrencia.
5. Reset reutilizable o sesión no revocada.
6. Exportación que incluye otro tenant o ignora filtros.
7. Campaña enviada tras retirar consentimiento o duplicada por retry.
8. Error 5xx que expone stack, SQL o secreto.

## 14. Variables de entorno propuestas

Publicar `.env.example` sin valores reales y validar presencia/formato al arrancar.

```text
APP_ENV, APP_URL, APP_KEY, APP_TIMEZONE
FRONTEND_URL, CORS_ALLOWED_ORIGINS
DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
SESSION_NAME, SESSION_SECURE, SESSION_IDLE_TTL, SESSION_ABSOLUTE_TTL
TURNSTILE_SITE_KEY, TURNSTILE_SECRET_KEY
MAIL_TRANSPORT, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM
PAYMENTS_PROVIDER, PAYMENTS_MODE
MP_ACCESS_TOKEN_TEST, MP_WEBHOOK_SECRET_TEST
MP_ACCESS_TOKEN_LIVE, MP_WEBHOOK_SECRET_LIVE
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
FILES_DISK, FILES_PATH, FILES_MAX_BYTES
OSM_TILE_URL, OSM_ATTRIBUTION, GEOCODING_PROVIDER
LOG_LEVEL, LOG_CHANNEL
```

Vue sólo recibe variables explícitamente públicas: URL base si no es same-origin, Turnstile site key y flags no sensibles. Ningún token de proveedor usa prefijo `VITE_`.

## 15. Checklist de producción

### Producto y datos

- Todos los botones de la sección 6 están en Sí o fueron retirados con decisión aprobada.
- No se importa `demoData.js` en bundles de producción.
- Términos, privacidad, consentimiento, contacto y soporte están publicados.
- Política de cancelación, reembolso, espera y vencimiento está acordada con negocio.
- Roles y permisos tienen dueño funcional y matriz firmada.

### Ingeniería

- Migraciones forward y rollback ensayadas con copia real anonimizada.
- Imágenes inmutables; frontend construido, no Vite dev; Apache/PHP configurado para producción.
- Healthchecks, graceful shutdown, timeouts, límites de cuerpo y recursos.
- Dependencias sin vulnerabilidades altas sin excepción documentada.
- OpenAPI, runbooks, diagrama de datos y decisiones de arquitectura actualizados.

### Seguridad

- Todas las credenciales iniciales rotadas; no hay secrets en repo, imagen, JS o logs.
- HTTPS/HSTS, cookies seguras, CSRF, CSP, rate limiting y headers comprobados.
- Pruebas IDOR/tenant y permisos ejecutadas por rol.
- Webhook firmado, idempotente y reconciliado; refund reforzado.
- Uploads, exportes y datos de salud/medidas revisados por privacidad.

### Operación

- Backup y restauración demostrados; responsables y ventanas definidos.
- Dashboards y alertas operativos; request ID visible en errores de soporte.
- Staging usa proveedores sandbox y datos no productivos.
- Plan de migración, rollback y comunicación de incidente aprobado.
- Smoke tests posdeploy y observación reforzada durante las primeras 24 horas.

## 16. Orden recomendado y dependencias críticas

```text
F1 estabilidad
  -> F3 identidad y roles
      -> F5 tenancy, personas y membresías
          -> F6 sesiones, reservas y asistencia
              -> F7 pagos y finanzas
              -> F9 calendario y notificaciones
          -> F10 experiencia del socio
  -> F4 shell administrativa, en paralelo sólo después del contrato de roles

F2 sistema visual puede avanzar tras F1, usando contratos de datos acordados.
F8 mapa requiere gimnasios/sedes de F5.
F11 endurecimiento acompaña todas las fases y cierra el release.
```

La fase 11 no significa postergar seguridad y tests: cada fase incorpora sus controles. La fase final verifica el sistema completo, operación y recuperación.

## 17. Estimación relativa y hitos de aprobación

No es responsable fijar fechas sin conocer equipo, calidad de datos, proveedor de hosting, decisiones legales/comerciales y cobertura deseada. Sí puede estimarse tamaño relativo:

| Fase | Tamaño | Hito demostrable |
|---|---:|---|
| 1 | M | Sesión y contratos actuales estables; inventario de acciones cerrado. |
| 2 | M | Portada y sistema responsive aprobados en seis anchos. |
| 3 | A | Identidad y matriz de acceso pasan tests de seguridad. |
| 4 | M | Administración visible, navegable y con estados reales. |
| 5 | A | Primer gimnasio opera socios, personal, planes y membresías aislados. |
| 6 | A | Reserva concurrente, espera y asistencia completas. |
| 7 | A | Primer pago sandbox reconciliado y reporte exportado. |
| 8 | M | Exploración geolocalizada usable en desktop/móvil. |
| 9 | A | ICS/Google y primera campaña consentida sin duplicados. |
| 10 | M | Jornada completa del socio sin datos simulados. |
| 11 | A | Go-live review, restore y rollback aprobados. |

## 18. Definición de versión 1.0 terminada

GymTrack 1.0 está terminado cuando un socio puede verificar su cuenta, recuperar acceso, elegir un gimnasio/plan, pagar, recibir una membresía sólo tras confirmación válida, reservar una sesión con cupo, entrar a espera, cancelar, agregar la clase al calendario, registrar asistencia, ver progreso e historial; y cuando un dueño puede operar su gimnasio, personal, clases, reservas, membresías, promociones, pagos, finanzas y reportes sin ver datos de otro gimnasio. El administrador general puede gestionar la plataforma con acceso explícito y auditado.

Además, el sistema debe superar los criterios de seguridad, responsive, accesibilidad, pruebas, despliegue y recuperación de este documento. Hasta entonces, la denominación correcta es entorno de desarrollo o staging, no producción 1.0.

## 19. Decisiones que requieren aprobación antes de implementar

1. Mecanismo final de sesión: se recomienda cookie HttpOnly same-origin.
2. Política comercial de planes, renovaciones, cancelaciones, espera y reembolsos.
3. Si cada dueño conecta su propia cuenta Mercado Pago o la plataforma centraliza cobros; cambia onboarding, conciliación y responsabilidad.
4. Proveedor de email y, si se habilita, proveedor oficial de WhatsApp.
5. Proveedor de tiles/geocoding OSM con capacidad/SLA para producción.
6. Retención y privacidad de fotos, medidas, auditoría, logs y exportes.
7. Hosting, dominio, backups, RPO/RTO y responsables de incidentes.
8. Alcance de Google OAuth en v1.0: ICS/URL son obligatorios; sincronización OAuth puede liberarse detrás de feature flag si no bloquea reservas.

Este plan propone resolver primero la integridad de los flujos y luego la sofisticación visual. Las tres guías de diseño invocadas influyen en la jerarquía, responsive, accesibilidad, estados y movimiento propuestos; ninguna justifica ocultar problemas de datos o permisos detrás de una interfaz pulida.
