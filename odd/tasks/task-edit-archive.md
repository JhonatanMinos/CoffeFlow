# Incremento: editar y archivar tareas

## Objetivo

Permitir editar y archivar tareas directamente desde las tarjetas del tablero del proyecto.

## Problema

Las tarjetas actuales no ofrecen controles para corregir datos ni retirar tareas del flujo de trabajo. La eliminación física perdería información histórica relacionada con sesiones Focus.

## Por qué

El tablero necesita acciones cotidianas de mantenimiento sin romper la atribución ni la trazabilidad de tareas ya trabajadas.

## Alcance autorizado

- Agregar acciones accesibles `Editar` y `Archivar` en las tarjetas de tareas del tablero.
- Permitir editar los campos de tarea que el modelo y las reglas existentes admitan; validar el conjunto exacto durante la implementación contra esquema y pruebas.
- Archivar tareas conservando sus datos y relaciones históricas; no ejecutar eliminación física.
- Validar autorización y alcance del proyecto en servidor para cada operación.
- Cubrir validación, autorización, archivo y preservación de relaciones con pruebas.

## Fuera de alcance

- Borrado físico, restauración/desarchivo o una vista de archivo, salvo que el código existente ya las incluya y sean necesarias para consistencia.
- Cambios en estados de finalización, asignaciones, permisos de workspace o comportamiento de Focus.
- Operaciones remotas, publicación, PR o merge.

## Restricciones

- Preservar historial y referencias de sesiones Focus; no eliminar tareas ni desvincular relaciones.
- Aplicar políticas/validación de servidor; no confiar en restricciones de UI.
- Mantener compatibilidad con formularios y serialización Inertia existentes.
- No tocar ni incluir en commits el tracker Focus preexistente `odd/tasks/focus-session-pause-resume.md`.

## Estrategia de entrega

- Estrategia: `stacked-to-main`, elegida por el usuario para este feature.
- Rama: `feature/task-edit-archive`, basada en `origin/main`.
- Pronóstico inicial: 400–500 líneas autorales incluyendo pruebas y tracker. TEA-01 cerró con 613 líneas totales (594 inserciones + 19 eliminaciones), 506 autorales sin el tracker; una sola pasada de slicing antes de cualquier PR debe definir los cortes coherentes. No reducir código ni pruebas para alcanzar el presupuesto.
- No hay autorización de acceso remoto; los cortes quedan locales hasta que el usuario autorice otra cosa.

## Tareas

### TEA-01 — Implementar edición y archivo seguros en servidor

- [x] Añadir `archived_at` nullable mediante migración reversible; archivar conserva la fila y sus relaciones.
- [x] Implementar edición limitada a título/descripción y endpoints de edición/archivo con autorización de miembro o superior.
- [x] Añadir pruebas para validación, permisos/alcance, conservación de sesiones, filtrado de tablero, WIP y rechazo con Focus activo.

**Ruta:** delegada; lectura que prepara cambios multiarchivo y escritura de backend en un único trabajador.

**Criterios de aceptación:**

- Solo usuarios autorizados pueden editar o archivar dentro del alcance del proyecto.
- Archivar conserva la fila de tarea y las referencias históricas, incluida la atribución de sesiones Focus.
- Solicitudes inválidas no modifican la tarea; los errores no revelan recursos fuera de alcance.
- Las pruebas de servidor cubren los casos positivos y negativos pertinentes.

**Verificación:** RED antes de implementar si existe runner determinista aplicable; `php artisan test tests/Feature/Tasks/TaskTest.php`, `php artisan test`, `vendor/bin/pint --test` y `git diff --check`.

**Commit:** `3f72ee4` — `feat(tasks): add secure editing and archival` (corte backend local; 594 líneas incluyendo el tracker, 487 autorales de código y pruebas).

### TEA-02 — Integrar controles en tarjetas del tablero

- [x] Añadir controles `Editar` y `Archivar` accesibles a tarjetas, con confirmación antes de archivar.
- [x] Mostrar errores de validación/operación en el contexto del diálogo y mantener la UI coherente tras éxito.
- [x] Actualizar pruebas disponibles y documentación del incremento; el repositorio no configura un runner automatizado de React.

**Ruta:** delegada; integración coordinada de interfaz, pruebas y tracker.

**Implementación:** se añadieron acciones independientes según `canEditTasks`/`canArchiveTasks`; edición envía solo título y descripción y el diálogo de archivo explica que los datos y el historial se conservan. Los errores `title`, `description` y `task` se muestran junto al formulario correspondiente; solo una respuesta exitosa cierra cada diálogo.

**Criterios de aceptación:**

- Las acciones usan los endpoints protegidos por servidor y solo se muestran cuando el usuario puede ejecutarlas.
- La edición refleja los campos permitidos por el modelo; archivar pide confirmación y retira la tarea activa del tablero sin borrarla.
- Los controles son utilizables por teclado y exponen etiquetas/feedback accesibles.

**Verificación:** `npm run types:check` pasó; `npm run build` pasó con aviso no bloqueante: falta el opcional `fontaine` para optimizar fallbacks de fuentes; `php artisan test tests/Feature/Tasks/TaskTest.php` pasó (26 tests, 147 assertions); `vendor/bin/pint --test` pasó; `git diff --check` pasó. No hay runner automatizado de UI configurado ni se afirma QA manual de navegador.

**Commit:** pendiente de verificación y commit de trabajo.

## Progreso

- La exploración previa identificó `TaskController`, `TaskPolicy`, `Task`, migración de tareas, `ProjectController` y `TaskTest` como superficies afectadas; TEA-01 confirmó e implementó ese alcance.
- TEA-01 agrega edición PUT (solo `title` y `description`) y archivo DELETE lógico. Archivo es idempotente y rechaza sesiones enlazadas `running`/`paused`; Focus start y archive bloquean la misma fila de tarea dentro de transacciones para serializar la carrera en bases con soporte de row locks.
- Las tareas archivadas desaparecen del tablero activo y no consumen WIP en creación, movimiento ni finalización. Editar, mover, completar e iniciar Focus sobre una tarea archivada se rechaza en el límite de acción/servicio.
- La página del proyecto ahora expone `canEditTasks` y `canArchiveTasks` para TEA-02; las Policy siguen usando el umbral de miembro ya aplicado a creación/finalización.
- RED observado: los tests nuevos fallaron antes de la implementación por rutas ausentes y columna `archived_at` inexistente. GREEN observado después de migración y acciones.
- Verificación final observada: `TaskTest.php` 26/26, `FocusSessionTest.php` 10/10, suite completa 136/136, `vendor/bin/pint --test` pasó y `git diff --check` pasó.
- La base de pruebas usa SQLite en memoria: confirma invariantes funcionales, no la serialización real entre procesos concurrentes ni la ejecución PostgreSQL de la migración.
- El tracker Focus modificado preexistente se preservó en esta rama como archivo no rastreado y debe permanecer fuera de los commits de este feature.
- El native review y el estado RDD se consultarán de manera no mutante antes de cualquier flujo de revisión; no se inventará resultado PASS.
- El frontend solo tiene `types:check`, `build` y `check`; `package.json` no incluye Vitest, Playwright ni otro runner de UI. Las pruebas existentes de `TaskTest` ya verifican los props de permiso enviados por servidor y pasaron (26 tests, 147 assertions).
- TEA-01 es un único commit cohesivo por encima del umbral: 506 líneas autorales; la estrategia `stacked-to-main` permanece y requerirá una pasada honesta de slicing antes de cualquier PR. No reescribir historia ni omitir pruebas.

## Próximo paso

Continuar con TEA-02 usando `canEditTasks`/`canArchiveTasks`. Publicación y PR quedan pendientes de autorización explícita.

## Relevant Files

- `app/Http/Controllers/Tasks/TaskController.php` — operaciones actuales de tareas.
- `app/Policies/TaskPolicy.php` — autorización de tareas.
- `app/Models/Task.php` y migración de tareas — persistencia y relaciones.
- `app/Actions/Tasks/UpdateTask.php` / `ArchiveTask.php` — mutaciones transaccionales de edición y archivo.
- `database/migrations/2026_10_14_000000_add_archived_at_to_tasks_table.php` — estado lógico de archivo reversible.
- `app/Http/Controllers/Projects/ProjectController.php` — serialización del tablero.
- `resources/js/pages/projects/show.tsx` — tarjetas del tablero.
- `tests/Feature/Tasks/TaskTest.php` — pruebas de tareas.
