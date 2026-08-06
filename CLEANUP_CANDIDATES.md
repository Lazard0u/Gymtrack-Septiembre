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

## Registro de decisiones

Todavía no se autorizó ni realizó ninguna eliminación.
