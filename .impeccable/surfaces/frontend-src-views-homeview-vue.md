---
version: 1
slug: "frontend-src-views-homeview-vue"
primary_target: "frontend/src/views/HomeView.vue"
related_targets: ["frontend/src/views/GymsView.vue","frontend/src/views/PlansView.vue","frontend/src/views/ForGymsView.vue"]
---

# Superficie pública GymTrack

## Alcance y modo

- Rutas: `/`, `/gimnasios`, `/planes`, `/para-gimnasios` y páginas legales.
- Modo principal: Persuade. La persona debe entender el producto, elegir un recorrido y actuar.

## Audiencia, trabajo y acciones

- Socio: comprender que puede encontrar gimnasios y gestionar su actividad. Acción principal: explorar gimnasios.
- Dueño: comprender el alcance operativo sin confundirlo con una función ya terminada. Acción secundaria: conocer la propuesta para gimnasios.

## Prueba y contenido

- El mapa real con OpenStreetMap demuestra el mecanismo de exploración.
- La ausencia de un endpoint público se representa con un estado vacío, nunca con gimnasios, distancias, precios o métricas inventadas.
- El contenido describe capacidades objetivo sin afirmar que los módulos de fases posteriores ya estén disponibles.

## Dirección elegida

Calma operativa con energía física: fondo carbón frío, superficies mate, azul reservado para acción y foco, tipografía sans de peso funcional, fotografía de entrenamiento real y controles precisos. Se retiran glows, tarjetas repetidas y densidad de demo. La composición alterna hero asimétrico, explorador unificado, listas agrupadas, bento de funciones, FAQ y cierre compacto.

## Momento memorable

El explorador une mapa y estado de disponibilidad dentro de un mismo marco en escritorio. En móvil se convierte en mapa con bottom sheet de tres posiciones, manteniendo visible la geografía y la atribución.

## Restricciones

- Mantener logo, azul/celeste, tema oscuro y stack actual.
- No alterar autenticación ni implementar fases posteriores.
- Cero rutas rotas, datos simulados o contenido inicialmente invisible por animación.
- Movimiento corto y motivado por feedback o cambio de estado, con reducción de movimiento.

## Decisiones abiertas

- El endpoint público y el contenido comercial definitivo de planes siguen pendientes de fases posteriores.
- Las fotografías producidas para esta fase son material visual de marca, no evidencia de gimnasios registrados.
