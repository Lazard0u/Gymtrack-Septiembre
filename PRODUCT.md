# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

GymTrack sirve a dos audiencias principales confirmadas:

- Socios que quieren descubrir gimnasios, consultar clases, administrar reservas y membresías, y revisar su actividad.
- Dueños y equipos de gimnasios que necesitan operar socios, clases, membresías, cobros y reportes desde una sola aplicación.

La Fase 5 opera los roles socio, empleado, dueño y administrador general sobre asociaciones explícitas a gimnasio, con perfiles de socio, empleado y entrenador y permisos efectivos por tenant.

## Product Purpose

GymTrack busca convertir la operación diaria de un gimnasio y la experiencia del socio en flujos digitales claros y verificables. La versión 1.0 será exitosa cuando los recorridos principales funcionen con permisos efectivos y estados completos. Para presentaciones existe un dataset MySQL opt-in, aislado y siempre identificado; no reemplaza datos reales ni criterios de producción.

## Positioning

La propuesta confirmada es una plataforma web para conectar descubrimiento, membresía, clases, reservas y operación del gimnasio dentro del mismo producto. La identidad y la operación comercial básica ya admiten contexto multigimnasio, sedes, personas, entrenadores, planes y membresías con aislamiento desde PHP. Clases, reservas y pagos todavía no deben presentarse como flujos terminados.

## Operating Context

- Superficie pública para descubrir el producto y consultar gimnasios publicados desde MySQL.
- Superficie autenticada con contexto diferente para socios, empleados, dueños y administración general.
- Superficie administrativa única en `/administracion`, con navegación por
  gimnasio, permisos efectivos, consultas operativas y modo soporte auditado.
- Uso responsive desde teléfonos de 360 px hasta escritorios de 1920 px.
- Mapas basados en Leaflet y OpenStreetMap con atribución visible.

## Capabilities and Constraints

- Stack obligatorio: Vue 3, PHP, MySQL, Docker, Leaflet y OpenStreetMap.
- No se reescribe el proyecto desde cero ni se cambia el stack.
- La Fase 5 agrega el tenant operativo y los CRUD comerciales principales, sin
  anticipar clases y reservas de Fase 6 ni pagos y finanzas de Fase 7.
- No se implementan anticipadamente pagos, calendario, WhatsApp ni inteligencia artificial.
- Los datos de demostración sólo existen en MySQL mediante un seeder versionado, idempotente y opt-in; llevan `is_demo`/`demo_dataset_id` y una etiqueta visible.
- El seeder demo nunca se ejecuta automáticamente ni puede activarse en producción sin una autorización excepcional explícita.
- Cuando falta un endpoint, la interfaz debe comunicar un estado vacío honesto.
- La cuenta administradora actual debe conservarse.

## Brand Commitments

- Nombre y marca: GymTrack.
- Conservar el símbolo existente y la identidad moderna y tecnológica.
- Superficie oscura con azul y celeste como colores de marca.
- El azul se reserva principalmente para acciones, selección, enlaces importantes, progreso y foco.
- Reducir glow, bordes luminosos, gradientes, animaciones lentas y decoración sin función.
- Voz clara, directa y profesional en español.

## Evidence on Hand

- Repositorio funcional con frontend, backend, base, Docker y autenticación de Fase 1.
- Logo existente en `frontend/src/assets/gymtrack-mark.svg`.
- Capturas del estado visual de Fase 1 guardadas durante la auditoría local.
- Plan aprobado en `PRODUCTION_PLAN.md` y brief detallado de Fase 2 proporcionado por el usuario.
- Hay cinco gimnasios ficticios de presentación almacenados en MySQL. No constituyen evidencia comercial, estadísticas, precios ni testimonios reales.

## Product Principles

1. La interfaz dice la verdad sobre el estado del producto y de los datos.
2. Toda acción visible tiene destino, estado y respuesta comprensible.
3. La autorización pertenece al backend; la UI comunica, no decide seguridad.
4. Responsive, accesibilidad y rendimiento son criterios funcionales.
5. La sofisticación visual nunca oculta un flujo incompleto.

## Accessibility & Inclusion

La superficie web debe admitir navegación por teclado, lectores de pantalla, foco visible, contraste WCAG AA, `prefers-reduced-motion`, zoom al 200 %, targets táctiles de al menos 44 px y los anchos 360, 390, 768, 1024, 1440 y 1920 px.

Los hechos de producto de este archivo incluyen el alcance terminado de la
Fase 5: tenant operativo, administración visible, contexto obligatorio,
personas, sedes, planes, membresías trazables y acceso global auditado.
