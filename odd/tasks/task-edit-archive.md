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
- Pronóstico inicial: 400–500 líneas autorales incluyendo pruebas y tracker. TEA-01 cerró con 613 líneas totales (594 inserciones + 19 eliminaciones), 506 sin el tracker; la pasada de slicing produjo tres cortes locales verificados de 287, 365 y 250 líneas, sin reducir comportamiento ni pruebas.
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

**Commit:** `3f72ee4` — `feat(tasks): add secure editing and archival` (corte backend local; 613 líneas autorales incluyendo el tracker, 506 de código y pruebas; altas más bajas).

### TEA-02 — Integrar controles en tarjetas del tablero

- [x] Añadir controles `Editar` y `Archivar` accesibles a tarjetas, con confirmación antes de archivar.
- [x] Mostrar errores de validación/operación en el contexto del diálogo y mantener la UI coherente tras éxito.
- [x] Actualizar pruebas disponibles y documentación del incremento; el repositorio no configura un runner automatizado de React.

**Ruta:** delegada; integración coordinada de interfaz, pruebas y tracker.

**Implementación:** se añadieron acciones independientes según `canEditTasks`/`canArchiveTasks`; edición envía solo título y descripción y el diálogo de archivo explica que los datos y el historial se conservan. Los errores `title`, `description` y `task` se muestran junto al formulario correspondiente; solo una respuesta exitosa cierra cada diálogo.

**Corrección de accesibilidad:** la revisión independiente detectó que los botones que abrían los diálogos no estaban registrados como triggers Radix, por lo que el foco no se restauraba al cerrar con Escape o Cancelar. Ambos botones ahora usan `DialogTrigger asChild` dentro de su diálogo controlado, habilitando la restauración de foco de Radix sin cambiar el comportamiento de apertura.

**Criterios de aceptación:**

- Las acciones usan los endpoints protegidos por servidor y solo se muestran cuando el usuario puede ejecutarlas.
- La edición refleja los campos permitidos por el modelo; archivar pide confirmación y retira la tarea activa del tablero sin borrarla.
- Los controles son utilizables por teclado y exponen etiquetas/feedback accesibles.

**Verificación:** revisión independiente: `php artisan test` pasó (136 tests, 607 assertions), con `TaskTest.php` 26/26 y 147 assertions; `vendor/bin/pint --test` pasó. Para esta corrección: `npm run types:check` pasó; `npm run build` pasó con aviso no bloqueante por el opcional `fontaine` ausente; `node_modules/.bin/oxfmt --check resources/js/components/tasks/task-card-actions.tsx` pasó; `git diff --check` pasó. No hay runner automatizado de UI; la lectura estructural confirma que cada opener está registrado como `DialogTrigger`, pero no se realizó QA manual de navegador.

**Commit:** `4884365` — `feat(tasks): add task card edit and archive actions` (corte de interfaz TEA-02). `ed11929` — `fix(tasks): restore focus after task dialogs` (corrección de foco y tracker).

### TEA-03 — Completar QA de navegador y concurrencia PostgreSQL

- [x] Verificar edición, confirmación/errores de archivo y navegación por teclado en navegador real con datos aislados.
- [x] Verificar la serialización entre inicio Focus y archivo en PostgreSQL local desechable.

**Ruta:** delegada; ejecución de pruebas y preparación de datos aislados, sin tocar la base personal.

**Estado:** verificación completada con datos aislados tras autorización explícita: PostgreSQL 17.11 y `pdo_pgsql` para PHP 8.4.26 extraídos en el entorno temporal, Playwright Core 1.64.1 y Chromium 148 existente. No se instalaron paquetes ni servicios del host ni se modificaron `.env` o la base personal. Se usaron bases nuevas y un socket PostgreSQL privado sin listener TCP; los servicios QA quedaron detenidos.

**Concurrencia observada:** 6/6 escenarios con procesos PHP independientes y acciones reales en PostgreSQL 17.11: tres ejecuciones con archivo ganador rechazaron el inicio competidor y conservaron cero sesiones; tres con inicio ganador rechazaron el archivo y conservaron una sesión `running` correctamente enlazada. Antes de liberar cada transacción ganadora, `pg_stat_activity` y `pg_blocking_pids` confirmaron que el competidor esperaba el bloqueo de la misma tarea. Las barreras y esperas tuvieron timeout; no se usó solo una inferencia por sleeps. Esto acredita concurrencia entre acciones/procesos, no peticiones HTTP simultáneas.

**Aislamiento y evidencia:** migración normal en una base PostgreSQL nueva y desechable, sin reset ni acceso a SQLite personal; resultados JSON y reporte en los artefactos temporales `artifacts/concurrency/`. Los seis procesos hijos finalizaron y el cluster privado quedó detenido.

**Navegador observado:** 10/10 escenarios en Chromium 148: login real y controles de propietario; apertura por teclado, contención de foco y restauración exacta tras Escape/Cancelar; validación nativa y del servidor con diálogo abierto; edición persistida y valores reabiertos; cancelación y confirmación de archivo conservando tarea e historial; rechazo de archivo con sesión activa; invitado sin controles de mantenimiento; diálogos dentro del viewport móvil 390×844. Sin errores de consola/página ni solicitudes externas.

**Datos y artefactos de navegador:** base PostgreSQL desechable separada de la usada para concurrencia; filas de sesiones completadas/activas y sus vínculos permanecieron idénticos. La tarea archivada se conservó y la tarea con sesión activa no cambió. Reporte, resultados, prueba de base y seis capturas en los artefactos temporales `artifacts/browser/`. Navegador, servidor HTTP propio y cluster privado detenidos al finalizar.

**Incidencias y límites:** se corrigieron fallos del harness (clave temporal inválida, residuo de sintaxis shell, selector global ambiguo y observación de POST con `_method=PUT`); no se alteró el producto para pasar pruebas. La evidencia cubre Chromium, no una auditoría exhaustiva multibrowser/accesibilidad. Se observó un detalle de contenido: el error obligatorio del título muestra `validation.required`. El proyecto ya configuraba locale/fallback `es` sin archivos de idioma propios en la base; no se amplió el alcance a traducciones globales ni se corrigió ese mensaje. Queda como seguimiento de baja severidad, sin fallos funcionales en los escenarios solicitados.

### TEA-04 — Preparar cortes locales revisables

- [x] Crear cortes independientes de edición backend, archivo backend e interfaz, conservando la historia original.
- [x] Verificar cada corte con sus pruebas y documentar límites y conteos autorales.

**Ruta:** delegada; construcción secuencial en un único worktree temporal y ramas nuevas. No publicar ni reescribir la rama original.

**Resultado tras una pasada de slicing:**

| Corte | Rama local | Commit | Altas + bajas, incluido tracker |
| --- | --- | --- | --- |
| Edición | `feature/task-edit-archive-01-edit` | `653e5e4` | 287 + 0 = 287 |
| Archivo | `feature/task-edit-archive-02-archive` | `44aea79` | 339 + 26 = 365 |
| Interfaz | `feature/task-edit-archive-03-ui` | `21b3105` | 243 + 7 = 250 |

**Dependencias:** `f491ae3 -> 653e5e4 -> 44aea79 -> 21b3105`. La interfaz depende de ambos cortes de servidor. El worktree temporal conserva los tres commits sin reescribir la rama original. No hay ramas publicadas ni PR de este incremento.

**Verificación observada:** edición: suite independiente 130 tests/564 assertions; archivo: suite 136 tests/607 assertions. Verificación independiente del corte final: 136 tests/607 assertions, tipos, build, Pint y diff check pasaron. El árbol completo versionado coincide exactamente con `8777612`, excluyendo únicamente este tracker; el backend del segundo corte coincide con el final. Binarios y dependencias locales se reutilizaron sin instalaciones ni cambios de `.env` o de la base personal.

**Incidencias del entorno resueltas:** el symlink inicial de `vendor` resolvía clases de la rama original; una copia local y comprobación por reflexión aislaron las clases del worktree. Se copiaron assets ignorados para disponer del manifiesto Vite en pruebas de servidor (no acredita fidelidad visual). El typecheck inicial del corte UI falló por Wayfinder ausente; el build lo regeneró y el typecheck independiente posterior pasó. El build mantiene avisos no bloqueantes de `fontaine` opcional y tiempos de plugins. No se afirma prueba de navegador ni concurrencia PostgreSQL.

## Progreso

- La exploración previa identificó `TaskController`, `TaskPolicy`, `Task`, migración de tareas, `ProjectController` y `TaskTest` como superficies afectadas; TEA-01 confirmó e implementó ese alcance.
- TEA-01 agrega edición PUT (solo `title` y `description`) y archivo DELETE lógico. Archivo es idempotente y rechaza sesiones enlazadas `running`/`paused`; Focus start y archive bloquean la misma fila de tarea dentro de transacciones para serializar la carrera en bases con soporte de row locks.
- Las tareas archivadas desaparecen del tablero activo y no consumen WIP en creación, movimiento ni finalización. Editar, mover, completar e iniciar Focus sobre una tarea archivada se rechaza en el límite de acción/servicio.
- La página del proyecto ahora expone `canEditTasks` y `canArchiveTasks` para TEA-02; las Policy siguen usando el umbral de miembro ya aplicado a creación/finalización.
- RED observado: los tests nuevos fallaron antes de la implementación por rutas ausentes y columna `archived_at` inexistente. GREEN observado después de migración y acciones.
- Verificación final observada: `TaskTest.php` 26/26, `FocusSessionTest.php` 10/10, suite completa 136/136, `vendor/bin/pint --test` pasó y `git diff --check` pasó.
- La base de pruebas usa SQLite en memoria: confirma invariantes funcionales, no la serialización real entre procesos concurrentes ni la ejecución PostgreSQL de la migración.
- El tracker Focus modificado preexistente se preservó en esta rama como archivo no rastreado y debe permanecer fuera de los commits de este feature.
- Native CLI ausente; el estado RDD permanece desconocido y no se afirma PASS nativo.
- La verificación independiente registró la suite completa con 136 tests y 607 assertions (TaskTest: 26 tests, 147 assertions), además de Pint; la revisión encontró que los diálogos no registraban el botón opener como trigger Radix y no podían restaurar el foco. La corrección queda limitada al registro de `DialogTrigger asChild` en ambos botones.
- El frontend solo tiene `types:check`, `build` y `check`; `package.json` no incluye Vitest, Playwright ni otro runner de UI. Las pruebas existentes de `TaskTest` ya verifican los props de permiso enviados por servidor y pasaron (26 tests, 147 assertions).
- TEA-01 original conserva sus 506 líneas de código/pruebas; TEA-04 resolvió la entrega con nuevas ramas de edición, archivo e interfaz por debajo de 400 líneas cada una, sin reescribir esa historia ni omitir pruebas.
- Tras autorización explícita del usuario, se aplicó únicamente la migración de `archived_at` a SQLite local. El tracker Focus `odd/tasks/focus-session-pause-resume.md` sigue preexistente y sin rastrear, excluido de commits.
- Lectura independiente final: ambos botones registran `DialogTrigger asChild`; el estado final pasó `oxfmt --check`, `npm run types:check`, `npm run build` y `git diff --check`. Los cambios residuales del componente son fragmentos y formato sin cambios semánticos adicionales.
- `php artisan migrate --path=database/migrations/2026_10_14_000000_add_archived_at_to_tasks_table.php` pasó; la consulta posterior de estado confirmó `[6] Ran` y la columna existe. No se ejecutaron otras migraciones ni seeders.
- Verificación de datos antes/después: usuarios 2, tareas 1, sesiones Focus 1, sesiones enlazadas 1 y enlaces válidos 1, sin cambios; tareas archivadas 0. La consulta de tablero con `whereNull('archived_at')` devolvió la tarea existente. `php artisan test tests/Feature/Tasks/TaskTest.php` pasó nuevamente (26 tests, 147 assertions).

## Próximo paso

El código, la migración local, los tres cortes y TEA-03 están verificados: 10 escenarios de navegador y 6 de concurrencia PostgreSQL. Queda como seguimiento el mensaje `validation.required` visible al rechazar un título vacío; no se implementó una corrección de traducciones. La publicación futura debe seguir el orden edición, archivo e interfaz; integrar y retargetear cada corte según `stacked-to-main`. PR y acceso remoto siguen pendientes de autorización explícita.

## Relevant Files

- `app/Http/Controllers/Tasks/TaskController.php` — operaciones actuales de tareas.
- `app/Policies/TaskPolicy.php` — autorización de tareas.
- `app/Models/Task.php` y migración de tareas — persistencia y relaciones.
- `app/Actions/Tasks/UpdateTask.php` / `ArchiveTask.php` — mutaciones transaccionales de edición y archivo.
- `database/migrations/2026_10_14_000000_add_archived_at_to_tasks_table.php` — estado lógico de archivo reversible.
- `app/Http/Controllers/Projects/ProjectController.php` — serialización del tablero.
- `resources/js/pages/projects/show.tsx` — tarjetas del tablero.
- `tests/Feature/Tasks/TaskTest.php` — pruebas de tareas.
