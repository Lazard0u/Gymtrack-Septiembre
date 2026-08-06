---
name: GymTrack
description: Sistema visual oscuro, preciso y honesto para entrenamiento y operación de gimnasios.
colors:
  canvas: "#0b0e12"
  background-subtle: "#10151b"
  surface-1: "#141a21"
  surface-2: "#19212b"
  surface-raised: "#202a35"
  surface-inverse: "#f3f6f8"
  border-subtle: "#27313d"
  border-strong: "#394757"
  border-glass: "rgba(39, 49, 61, 0.72)"
  text-primary: "#f4f7f9"
  text-secondary: "#aeb9c5"
  text-tertiary: "#7f8b98"
  text-inverse: "#101317"
  text-on-accent: "#ffffff"
  accent: "#0969da"
  accent-hover: "#075fc5"
  accent-pressed: "#0553ae"
  accent-soft: "rgba(45, 140, 255, 0.12)"
  focus: "#79baff"
  info: "#68b6ff"
  success: "#43c17a"
  success-soft: "rgba(67, 193, 122, 0.13)"
  warning: "#f3ad4e"
  warning-soft: "rgba(243, 173, 78, 0.13)"
  danger: "#f36b6b"
  danger-soft: "rgba(243, 107, 107, 0.13)"
  info-soft: "rgba(104, 182, 255, 0.13)"
  status-info-text: "#b8dcff"
  status-success-text: "#a9ebc3"
  status-warning-text: "#ffdc9f"
  status-danger-text: "#ffc0c0"
  status-info-strong: "#9dceff"
  status-success-strong: "#8de0ae"
  status-warning-strong: "#ffd089"
  status-danger-strong: "#ffaaaa"
  overlay: "rgba(3, 6, 10, 0.72)"
  glass: "rgba(11, 14, 18, 0.88)"
  glass-soft: "rgba(11, 14, 18, 0.78)"
typography:
  display:
    fontFamily: "Manrope Variable, Manrope, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "clamp(2.5rem, 7vw, 5.5rem)"
    fontWeight: 730
    lineHeight: 1.08
    letterSpacing: "-0.035em"
  headline:
    fontFamily: "Manrope Variable, Manrope, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "clamp(2rem, 4vw, 3.6rem)"
    fontWeight: 730
    lineHeight: 1.08
    letterSpacing: "-0.035em"
  title:
    fontFamily: "Manrope Variable, Manrope, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "clamp(1.15rem, 2vw, 1.45rem)"
    fontWeight: 730
    lineHeight: 1.08
    letterSpacing: "-0.035em"
  body:
    fontFamily: "Manrope Variable, Manrope, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Manrope Variable, Manrope, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 760
    lineHeight: 1.6
    letterSpacing: "0.14em"
rounded:
  control: "0.5rem"
  card: "0.75rem"
  dialog: "1rem"
  pill: "999px"
spacing:
  1: "0.25rem"
  2: "0.5rem"
  3: "0.75rem"
  4: "1rem"
  5: "1.25rem"
  6: "1.5rem"
  8: "2rem"
  10: "2.5rem"
  12: "3rem"
  16: "4rem"
  20: "5rem"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.text-on-accent}"
    rounded: "{rounded.control}"
    padding: "0 {spacing.5}"
    height: "2.75rem"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
    textColor: "{colors.text-on-accent}"
  button-secondary:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.control}"
    padding: "0 {spacing.5}"
    height: "2.75rem"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.control}"
    padding: "0 {spacing.5}"
    height: "2.75rem"
  card:
    backgroundColor: "{colors.surface-1}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.card}"
    padding: "clamp({spacing.5}, 3vw, {spacing.8})"
  input:
    backgroundColor: "{colors.background-subtle}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.control}"
    padding: "0 {spacing.3}"
    height: "2.75rem"
---

# Design System: GymTrack

## Overview

**Creative North Star: "Calma operativa con energía física"**

La Fase 2 implementa una interfaz oscura, mate y tecnológica que prioriza orientación, legibilidad y estados verificables. La energía aparece en la fotografía deportiva, la escala de los titulares y el azul de las acciones; no en glows, ornamento o datos de demostración.

Principios observables:

- Jerarquía editorial asimétrica en la portada; estructura más sobria y lineal en vistas interiores.
- Azul reservado para acción, foco, enlaces importantes e iconos informativos; el resto se construye con carbones fríos.
- Bordes discretos, superficies tonales y espacio amplio separan contenido sin llenar la pantalla de tarjetas.
- Los estados vacíos explican qué falta y evitan fingir gimnasios, precios o resultados.

Las imágenes implementadas son `gymtrack-hero-training.webp` (1536×1024, horizontal, preparación de una barra) y `gymtrack-owner-studio.webp` (1122×1402, vertical, planificación operativa). Ambas son fotografía realista, fría y de bajo ruido visual, llevan texto alternativo contextual y se recortan con `object-fit: cover`. El hero fija el foco horizontal al 61 % y aplica un scrim oscuro desde el 58 % de la altura para sostener la leyenda. Son material de marca, no evidencia de gimnasios publicados. El símbolo SVG conserva sus gradientes metálicos blanco/celeste como excepción de identidad.

## Colors

La paleta normativa está en el frontmatter y corresponde a las variables de `tokens.css`.

- **Primario:** `accent`, `accent-hover` y `accent-pressed` forman la secuencia de acción; `focus` es un celeste separado y más luminoso.
- **Informativo:** `info` identifica cejas, iconos, enlaces del mapa y detalles de marca. No sustituye al azul de CTA.
- **Neutrales:** `canvas` es el fondo; `background-subtle` alterna secciones; `surface-1`, `surface-2` y `surface-raised` construyen profundidad tonal. Texto y bordes tienen tres y dos niveles, respectivamente.
- **Semánticos:** `success`, `warning`, `danger` e `info` alimentan alertas, badges y toasts. Sus fondos suaves implementados usan cada color al 13 % de opacidad.
- **Translúcidos:** `glass` y `glass-soft` se limitan al header y a controles sobre el mapa; `overlay` pertenece a diálogos y drawers.

**La regla del azul escaso.** No usar azul para rellenar superficies decorativas grandes: expresa acción, selección, foco, progreso o información puntual.

**La regla sin glow.** La implementación no usa halos luminosos. Los gradientes funcionales existentes son el scrim fotográfico y el shimmer del skeleton; el gradiente del logo pertenece al activo de marca.

## Typography

Manrope Variable, cargada localmente mediante `@fontsource-variable/manrope`, es la única familia. Su carácter es compacto y funcional: cuerpo de lectura abierto, titulares pesados y cejas técnicas.

- **Display:** `h1` global fluido, peso 730; el hero móvil usa una variante mayor (`clamp(2.65rem, 14vw, 4.25rem)`).
- **Headline:** `h2` global fluido, peso 730. El cierre de portada amplía su máximo a `4rem`.
- **Title:** `h3` global fluido; títulos compactos de componentes usan entre `1.1rem` y `1.35rem`.
- **Body:** base de `1rem/1.6`; descripciones secundarias suelen bajar a `0.875–0.9rem`.
- **Label:** cejas en mayúsculas, peso 760 y tracking de `0.14em`; badges y metadatos usan `0.7–0.75rem`.

**La regla de titular corto.** Los encabezados principales tienen anchos deliberados de 10–18 caracteres; no reducir la fuente para acomodar copy largo.

## Layout

El ritmo parte de 4 px y usa los once pasos del frontmatter. Los bloques de sección tienen padding vertical fluido desde 64 px hasta 120 px. El header mide 72 px. El contenedor se limita a 1240 px (`77.5rem`): tiene gutters de 16 px por lado bajo 768 px y de 24 px desde 768 px.

Comportamiento en los anchos de prueba:

- **360 px:** contenido de 328 px; hero, beneficios, bento, CTA y vistas de dueños van a una columna; acciones principales se apilan; navegación en drawer. El mapa mide al menos 544 px de alto y la lista se vuelve bottom sheet.
- **390 px:** mismas reglas móviles con 358 px de contenido; no aparece una composición alternativa.
- **768 px:** contenido de 720 px. Entra el gutter amplio; el header aún usa drawer y el hero sigue a una columna. Beneficios pasan a tres columnas, capacidades a dos; el explorador vuelve a mapa + panel lateral.
- **1024 px:** contenido de 976 px. Entran navegación y acciones de escritorio, hero de dos columnas y bento de tres columnas. Los cortes usan 48 rem y 64 rem; no hay breakpoint intermedio específico.
- **1440 px:** contenedor fijo de 1240 px, con 100 px libres a cada lado; se mantienen las composiciones de escritorio.
- **1920 px:** el mismo contenedor de 1240 px deja 340 px por lado. Tipos y espaciado fluido ya alcanzaron sus topes; el contenido no se estira.

La portada alterna fondos `canvas` y `background-subtle`, grids asimétricos y secciones con bordes horizontales. Las vistas públicas interiores reutilizan `AppPageHeader` y un cuerpo acotado. `StatusView` es una excepción heredada: usa fondo literal `#05070a`, panel de 560 px y espaciado fijo de 24 px; no es el patrón a replicar.

## Elevation & Depth

La profundidad es tonal antes que flotante. Los bordes de 1 px separan la mayoría de superficies; el blur aparece solo cuando el contenido debe seguir visible detrás.

- **Baja:** `0 1px 2px rgba(0, 0, 0, 0.2)` en tarjetas.
- **Media:** `0 14px 36px rgba(0, 0, 0, 0.25)` en el marco del explorador.
- **Alta:** `0 26px 72px rgba(0, 0, 0, 0.38)` en hero visual, bottom sheet, drawer, diálogo y toast.
- **Vidrio:** header sticky con blur de 16 px; etiqueta sobre el mapa con blur de 10 px.

**La regla mate por defecto.** Una superficie normal se distingue con tono y borde; la sombra alta se reserva para capas superpuestas o imágenes protagonistas.

## Shapes

La geometría es suavemente técnica: controles de 8 px, tarjetas de 12 px y capas grandes de 16 px. Píldoras de 999 px se usan solo en badges, indicadores y etiquetas compactas. Icon buttons son cuadrados de 44 px con radio de control; las imágenes grandes usan radio de diálogo. No hay tarjetas completamente redondas ni radios arbitrarios fuera de escala.

## Components

### Identity surfaces

- Login, registro, solicitud de dueño, recuperación, restablecimiento,
  verificación y sesiones reutilizan `AuthShell`: una superficie interior
  sobria, legible y sin métricas decorativas ni testimonios inventados.
- El formulario conserva una sola acción primaria por paso. Loading bloquea el
  doble envío; errores de campo, respuesta global, confirmación y reintento se
  anuncian con componentes semánticos existentes.
- En móvil el contenido ocupa el ancho disponible sin panel lateral obligatorio;
  en escritorio el marco se centra y limita la longitud de lectura. No se usa
  animación ambiental en flujos sensibles.
- El selector de gimnasio comunica el contexto efectivo devuelto por PHP. La UI
  puede ocultar destinos sin permiso, pero nunca sustituye la autorización del
  backend.

### Buttons and links

- `AppButton` y `AppLinkButton` comparten geometría: 44 px por defecto, 52 px grande y 36 px pequeño; padding horizontal de 20, 24 y 12 px respectivamente.
- Primario azul: hover oscurece y eleva 1 px; active usa `accent-pressed` y vuelve al eje. Secundario es una superficie tonal con borde fuerte. Ghost es transparente y adquiere superficie al hover. `AppButton` también implementa danger, loading con spinner, block y disabled.
- El disabled global reduce opacidad a 0.52 y elimina el cursor de acción. La variante `sm` de 36 px es una excepción real al objetivo táctil de 44 px y se usa en acciones compactas de escritorio.

### Cards, fields and feedback

- `AppCard`: superficie 1, borde sutil, radio de 12 px, sombra baja y padding fluido de 20–32 px. Solo `interactive` eleva 2 px y refuerza el borde al hover.
- Input, select y textarea: fondo sutil, borde fuerte, radio de 8 px, label a `0.875rem`; hint terciario y error rojo. Input expone hover; el foco visible lo aporta la regla global. `aria-invalid` y `aria-describedby` conectan errores y ayudas.
- Alertas y badges tienen tonos info/success/warning/danger. Empty state centra icono, título, explicación y acción; error state usa `role="alert"` y retry. Skeleton y spinner representan carga. Toast combina superficie elevada, punto semántico y `aria-live="polite"`.

### Navigation and overlays

- Header sticky translúcido: marca a la izquierda, enlaces al centro y acciones a la derecha desde 1024 px. Bajo ese ancho se conserva solo la marca y un icon button que abre drawer lateral de hasta 384 px.
- Drawer y dialog usan overlay oscuro, borde fuerte, sombra alta, cierre explícito y focus trap. El dialog se limita a 544 px y `90dvh` de alto.
- Footer: marca + dos grupos de enlaces; bajo 768 px mantiene dos columnas y desplaza la marca a una fila completa.

### Administración operativa

- `/administracion` usa un shell B2B persistente: sidebar de 256 px desde
  1024 px, header compacto con contexto, y drawer con focus trap bajo ese ancho.
- La densidad visual es mayor que en marketing, pero conserva Manrope, la
  paleta mate, radios 8/12/16 y azul escaso. Los títulos de página usan
  `1.8rem-2.8rem`; no heredan el display de portada ni se preceden con cejas
  que repitan el título o el contexto ya visible.
- Los indicadores ocupan una fila de cinco en escritorio y dos columnas en
  móvil, incluso a 360 px; un fallback puede ocupar la fila completa. Un dato
  ausente se etiqueta `No disponible`, mientras que un fallo de consulta se
  comunica como error. Ninguno se reemplaza por cero ni por una tendencia
  inventada.
- Las tablas tienen header tonal, ordenamiento accesible, filtros persistentes
  y paginación de servidor. Bajo 768 px cada fila se transforma en una tarjeta
  con labels visibles, sin scroll horizontal.
- Acciones rápidas distinguen enlaces operativos de botones deshabilitados. Un
  botón futuro incluye su fase o condición con texto plenamente legible; el
  estado deshabilitado no reduce la opacidad del bloque completo ni ofrece
  hover o apariencia activa.
- El modo soporte usa warning sin dominar la pantalla y exige motivo en un
  diálogo. Su badge permanece visible junto al selector de gimnasio.
- No hay animación en navegación frecuente o tablas. Drawer, diálogo, hover y
  feedback reutilizan únicamente los tokens de 140 y 220 ms.

### Explorer and disclosure

- En escritorio, `GymExplorer` une mapa real de Leaflet/OpenStreetMap y panel lateral de 320–400 px dentro de un marco de 576 px de alto. El panel se puede colapsar y el mapa recalcula su tamaño.
- Bajo 768 px, el panel es un bottom sheet con posiciones cerrada (72 px visibles), media (240 px) y completa. Conserva parte del mapa visible salvo al expandirse por completo; la atribución de OpenStreetMap está configurada en el mapa. La carga usa skeleton; el fallo muestra retry; la falta de datos muestra un estado vacío honesto.
- FAQ usa `details/summary`; el signo `+` rota 45° al abrir.

### Motion and accessibility

- Tokens de movimiento: 140 ms para feedback rápido, 220 ms para transiciones normales; curvas `cubic-bezier(0.22, 1, 0.36, 1)` y `cubic-bezier(0.2, 0, 0, 1)`. Hero entra una vez en 600/700 ms; drawer, dialog, toast, botones, FAQ y explorer se mueven solo por entrada o cambio de estado. Spinner gira en 700 ms y skeleton recorre 1.4 s.
- Con `prefers-reduced-motion: reduce`, el scroll suave se desactiva y toda animación/transición baja globalmente a 0.01 ms con una sola iteración.
- El foco global es un outline celeste de 3 px con offset de 3 px. Hay labels, regiones y estados ARIA, focus trap en overlays, texto alternativo en fotografías, títulos jerárquicos, helper text asociado y atribución visible de OpenStreetMap. El elemento `html` parte de 320 px de ancho mínimo y evita overflow horizontal.

## Do's and Don'ts

### Do

- **Do** reutilizar variables de `tokens.css` y los componentes `App*`; el frontmatter describe esos valores, no una paleta paralela.
- **Do** mantener el azul escaso, el fondo mate, el ritmo de 4 px y los radios 8/12/16 antes de introducir una variante.
- **Do** diseñar primero los estados loading, empty, error, disabled, hover, active y focus con copy directo en español.
- **Do** validar cada extensión en 360, 390, 768, 1024, 1440 y 1920 px, con teclado, reduced motion y zoom de 200 %.
- **Do** usar fotografía deportiva realista con encuadre funcional, dimensiones declaradas, lazy loading fuera del primer viewport y alt contextual.

### Don't

- **Don't** inventar datos, precios, sedes o métricas para llenar estados vacíos.
- **Don't** copiar los literales ni las clases de acción sin estilo de `StatusView`; es una excepción heredada, no un nuevo primitive.
- **Don't** agregar glows, gradientes decorativos, animaciones ambientales lentas o sombras altas en tarjetas normales.
- **Don't** convertir cada bloque en card ni usar azul como fondo de sección: primero usar ritmo, borde y cambio tonal.
- **Don't** crear nuevos breakpoints, radios o pasos de spacing si las escalas implementadas resuelven el caso.
