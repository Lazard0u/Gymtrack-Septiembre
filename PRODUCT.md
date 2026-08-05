# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

GymTrack sirve a dos audiencias principales confirmadas:

- Socios que quieren descubrir gimnasios, consultar clases, administrar reservas y membresías, y revisar su actividad.
- Dueños y equipos de gimnasios que necesitan operar socios, clases, membresías, cobros y reportes desde una sola aplicación.

El rol administrador general existe en el producto actual y debe conservarse. La definición definitiva de socio, empleado, dueño y administrador general se resolverá en fases posteriores.

## Product Purpose

GymTrack busca convertir la operación diaria de un gimnasio y la experiencia del socio en flujos digitales claros y verificables. La versión 1.0 será exitosa cuando los recorridos principales funcionen con datos reales, permisos efectivos y estados completos, sin depender de contenido de demostración.

## Positioning

La propuesta confirmada es una plataforma web para conectar descubrimiento, membresía, clases, reservas y operación del gimnasio dentro del mismo producto. El sistema multigimnasio definitivo todavía no está implementado y no debe presentarse como terminado.

## Operating Context

- Superficie pública para descubrir el producto y, cuando existan datos reales, gimnasios disponibles.
- Superficie autenticada para socios.
- Superficie administrativa existente para la operación actual.
- Uso responsive desde teléfonos de 360 px hasta escritorios de 1920 px.
- Mapas basados en Leaflet y OpenStreetMap con atribución visible.

## Capabilities and Constraints

- Stack obligatorio: Vue 3, PHP, MySQL, Docker, Leaflet y OpenStreetMap.
- No se reescribe el proyecto desde cero ni se cambia el stack.
- La Fase 2 se limita al sistema visual, la página de inicio, rutas públicas y responsive.
- No se modifica el mecanismo de autenticación aprobado en la Fase 1.
- No se implementan anticipadamente pagos, nuevos roles, administración completa, calendario, promociones, WhatsApp, inteligencia artificial ni tenancy definitivo.
- Los datos de demostración no son evidencia y no deben aparecer como datos reales.
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
- No hay estadísticas públicas, precios, planes comerciales, testimonios ni listado público real de gimnasios confirmados. No deben fabricarse.

## Product Principles

1. La interfaz dice la verdad sobre el estado del producto y de los datos.
2. Toda acción visible tiene destino, estado y respuesta comprensible.
3. La autorización pertenece al backend; la UI comunica, no decide seguridad.
4. Responsive, accesibilidad y rendimiento son criterios funcionales.
5. La sofisticación visual nunca oculta un flujo incompleto.

## Accessibility & Inclusion

La superficie web debe admitir navegación por teclado, lectores de pantalla, foco visible, contraste WCAG AA, `prefers-reduced-motion`, zoom al 200 %, targets táctiles de al menos 44 px y los anchos 360, 390, 768, 1024, 1440 y 1920 px.

Los hechos de producto de este archivo fueron inferidos exclusivamente del brief aprobado, `PRODUCTION_PLAN.md` y el repositorio actual, tal como autorizó el usuario al pedir continuar con la Fase 2.
