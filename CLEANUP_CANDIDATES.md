# Candidatos para limpieza final

Este inventario registra archivos completos que parecen innecesarios, pero que
no deben eliminarse durante las fases funcionales.

## Política

- Durante las fases funcionales no se eliminan archivos completos por parecer
  obsoletos.
- Dentro de un archivo que ya se esté modificando pueden limpiarse imports,
  variables, estilos y código muerto sólo cuando las pruebas confirmen que no se
  utilizan.
- La eliminación definitiva se evaluará al terminar las fases funcionales y
  antes de la auditoría final de producción.
- Antes de eliminar cualquier candidato se mostrará su contenido o propósito,
  la evidencia de que no se utiliza, el riesgo de quitarlo y la alternativa de
  conservarlo. Se esperará autorización explícita del usuario.

## Candidatos detectados

| Archivo | Estado | Por qué parece candidato | Evidencia pendiente | Decisión |
|---|---|---|---|---|
| `frontend/package.json.bak` | No versionado; preservado | Es una copia anterior de `package.json`: no contiene los scripts de pruebas ni las dependencias actuales y conserva `swiper`, ya retirado de la aplicación activa. | Confirmar con el usuario si tiene valor como respaldo; comprobar que ningún proceso externo lo consume. | Esperar autorización. |
| `frontend/src/data/demoData.js` | Versionado; preservado y desconectado del runtime | Contiene el antiguo catálogo y métricas hardcodeadas. La aplicación de Fase 3 obtiene sus datos desde MySQL y no importa este módulo. | Repetir búsqueda de importaciones y comprobar el bundle al cerrar las fases funcionales. | Esperar autorización. |
| `frontend/src/views/AdminView.vue` | Versionado; preservado y retirado del router en Fase 4 | Era el panel administrativo monolítico. `/admin` ahora redirige al único shell en `/administracion/resumen` y no existe ninguna importación activa de esta vista. | Confirmar que no haya enlaces externos que importen el archivo directamente y revisar historial antes de eliminar. | Esperar autorización. |
| `frontend/src/views/admin/AdminDashboard.vue` | Versionado; preservado y desconectado | Contiene métricas simuladas y enlaces heredados hacia anclas `/admin#...`; nunca estuvo conectado al router aprobado. | Repetir búsqueda de importaciones y comprobar bundle final. | Esperar autorización. |
| `frontend/src/views/admin/AdminUsers.vue` | Versionado; preservado y desconectado | Usa contratos anteriores de usuarios y un modal local que el nuevo panel no consume. | Verificar que ninguna fase posterior decida rescatar lógica concreta antes de eliminar. | Esperar autorización. |
| `frontend/src/views/admin/AdminClasses.vue` | Versionado; preservado y desconectado | Usa contratos anteriores de clases y no participa del nuevo módulo paginado. | Verificar que ninguna fase posterior decida rescatar lógica concreta antes de eliminar. | Esperar autorización. |
| `frontend/src/views/ClassesView.vue` | Versionado; preservado y desconectado | No está registrado en Vue Router ni tiene importaciones activas. | Revisar si la Fase 6 reutilizará alguna estructura antes de decidir. | Esperar autorización. |
| `frontend/src/stores/counter.js` | Versionado; preservado y desconectado | Es el store de contador de la plantilla inicial de Vite y no tiene consumidores. | Repetir búsqueda estática al cierre y confirmar que no se usa en ejemplos o tooling. | Esperar autorización. |

## Registro de decisiones

Todavía no se autorizó ni realizó ninguna eliminación.
