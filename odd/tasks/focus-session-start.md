# Increment: inicio de sesiones Focus

## Objetivo

Permitir que cada usuario inicie una sesión de enfoque libre o vinculada a una tarea accesible, sin permitir más de una sesión activa por usuario.

## Problema

El flujo Kanban permite administrar tareas, pero todavía no existe persistencia ni operación para registrar el inicio de una sesión individual de enfoque.

## Por qué

Las sesiones individuales son la base para medir esfuerzo verificable y, en una fase posterior, animar el temporizador y agregar aportes en tareas compartidas.

## Alcance autorizado

- Crear una sesión en estado `running` desde el servidor.
- Permitir sesiones libres (`task_id = null`) y sesiones ligadas a tareas del workspace activo.
- Validar en servidor autenticación, membresía y acceso a la tarea.
- Garantizar en la base de datos como máximo una sesión `running` o `paused` por usuario.
- Mostrar el estado activo y controles de inicio accesibles en el dashboard y el tablero de tareas.
- Añadir pruebas de aislamiento, vinculación, sesiones libres e idempotencia/concurrencia aplicable.

## Fuera de alcance

- Pausar, reanudar, completar, interrumpir o cancelar sesiones.
- Contabilizar duración efectiva, recuperación tras suspensión o política de reloj.
- Descansos, animación de taza, XP, logros, estadísticas o tiempo real.
- Redis, colas o nuevos servicios.

## Restricciones y decisiones provisionales

- El backend genera el estado inicial y el timestamp; no se acepta duración ni tiempo transcurrido del cliente.
- La unicidad activa debe imponerse con índice único parcial/condicional sobre `user_id` para estados `running` y `paused`, no solo mediante preconsulta de aplicación.
- Una tarea opcional se resuelve dentro del workspace actual y requiere autorización en servidor.
- La migración e índice deben funcionar con PostgreSQL y con SQLite de pruebas; cualquier limitación del motor de pruebas se documenta, no se disimula.

## Estrategia de entrega

- Estrategia: `stacked-to-main` (elegida por el usuario).
- Pronóstico inicial: 250–400 líneas autorales; conteo observado acumulado en código: 620 adiciones + 30 eliminaciones = 650 líneas.
- Slices apilados: PR-1 FS-01 persistence/start (`0cfc2aa`, 312 líneas); PR-2 FS-02 UI/active state (`c609397`, 342 líneas); PR-3 tracker ODD pasivo (este documento, 129 líneas). Cada corte queda bajo 400 líneas y depende del anterior.
- Los límites de PR son acumulativos: PR-1 termina en `0cfc2aa`; PR-2 en `c609397`; PR-3 agrega únicamente el tracker. Total por límites de commit: 654 líneas; el diff neto del árbol de código es 650 porque algunas líneas añadidas en commits previos se editaron luego.
- La estrategia `stacked-to-main` permite integrar cada unidad por orden; tras integrar una PR, la siguiente se retargetea/rebasea para mostrar solo su slice.
- Rama de trabajo: `feature/focus-session-start`, creada desde `main` `86b9547`.

## Tareas

### FS-01 — Persistir e iniciar sesiones

- [x] Crear tabla y modelo de sesiones Focus con usuario, tarea nullable, estado e inicio del servidor.
- [x] Añadir operación de inicio con autorización, alcance de workspace y unicidad activa.
- [x] Probar inicio libre, inicio vinculado, denegación de tarea ajena y conflicto por segunda sesión activa.

**Ruta:** delegada; investigación preparatoria y cambios backend multiarchivo.

**Criterios de aceptación:**

- Una persona autenticada inicia una sesión libre con `task_id = null`.
- Una persona puede vincular solo una tarea autorizada dentro del workspace activo.
- Iniciar una segunda sesión `running` mientras exista otra `running`/`paused` no crea una fila adicional.
- La base de datos protege la unicidad ante peticiones concurrentes.

**Verificación:** `php artisan test tests/Feature/Focus/FocusSessionTest.php`; prueba de migración/índice en SQLite y comprobación de DDL compatible con PostgreSQL.

**Evidencia:**

- Implementación: `c9a6d0f` (`feat(focus): start authorized focus sessions`).
- Corrección de alcance: `3f0c33a` (`fix(focus): scope task existence validation`).
- Corrección de ID cero: `0cfc2aa` (`fix(focus): reject zero task identifiers`).
- RED inicial: 5 pruebas fallaron por ausencia de ruta/modelo; GREEN: 5 pruebas, 12 aserciones.
- RED de lookup acotado: 5 pruebas pasaron y 1 falló porque la tarea inexistente respondía con error de validación; GREEN: 6 pruebas, 17 aserciones.
- RED de `task_id = 0`: 6 pasaron y 1 falló porque el cero se interpretaba como sesión libre; GREEN final: 7 pruebas, 19 aserciones.
- `vendor/bin/pint --test` y `git diff --cached --check` correctos.
- Spot check del padre: 7 pruebas, 19 aserciones.
- Verificación independiente: sin hallazgos restantes; revisó lookup de tarea, unicidad e aislamiento de la prueba ante constraint error.
- La migración e índice parcial se ejercitan con SQLite; PostgreSQL real, concurrencia HTTP entre procesos y rollback de migración siguen pendientes.
- RDD no disponible: `gentle-ai` no está instalado; el riesgo se trató como alto con verificación independiente.
- Límite de FS-01: 312 líneas añadidas en la rama hasta este cierre; mantener el grupo como unidad vertical con sus pruebas.

### FS-02 — Exponer inicio y sesión activa

- [x] Mostrar inicio libre y sesión activa en dashboard.
- [x] Añadir control accesible para iniciar desde tarjetas de tareas.
- [x] Probar props/visibilidad y errores de sesión activa en los flujos expuestos.

**Ruta:** delegada; cambios coordinados en React/Inertia, rutas/controladores y pruebas.

**Criterios de aceptación:**

- El usuario puede iniciar libremente desde su espacio personal.
- El usuario puede iniciar vinculando una tarea accesible.
- La interfaz muestra que ya hay una sesión activa y no presenta controles que sugieran que puede iniciar otra.
- La interfaz no calcula ni envía segundos como autoridad contable.

**Verificación:** pruebas Pest enfocadas, `npm run build`, `npm run check`, `npm run types:check` y `vendor/bin/pint --test`.

**Evidencia:**

- Implementación: `c609397` (`feat(focus): expose session start controls`).
- RED: 7 pruebas pasaron y 2 fallaron porque faltaba `activeFocusSession.id`; GREEN enfocado: 9 pruebas, 68 aserciones.
- `php artisan test` — 127 pruebas, 550 aserciones; `vendor/bin/pint --test`, `npm run build` y `npm run types:check` correctos.
- `npm run check` falló por formato preexistente en `.atl/.skill-registry.cache.json`, `.atl/skill-registry.md` y `odd/tasks/kanban-tasks.md`; no se alteraron esos archivos y los TSX tocados pasan su verificación dirigida.
- Verificación independiente FS-02: sin bloqueantes; confirmó alcance por usuario, formularios accesibles, estado activo y retorno a la página de origen.
- Spot check del padre: `npm run types:check` correcto.
- Slice FS-02 contra el límite FS-01: 310 adiciones + 32 eliminaciones = 342 líneas autorales, excluidos archivos generados.
- Candidato acumulado de código desde `86b9547`: 620 adiciones + 30 eliminaciones = 650 líneas autorales, excluidos archivos generados; suma de slices por límites de commit: 654 líneas.

## Progreso

- Exploración de solo lectura completada; no hay archivos Focus existentes.
- El repositorio usa SQLite `:memory:` en pruebas; se debe comprobar que el índice condicional se crea correctamente allí y en PostgreSQL.
- El comando `gentle-ai` no está disponible; la revisión nativa RDD queda no disponible y no se sustituye por aprobación inventada.
- FS-01 completada en `c9a6d0f`, `3f0c33a` y `0cfc2aa`.
- FS-02 implementada y verificada en `c609397`; el usuario eligió `stacked-to-main`. El candidato se divide en tres cortes: FS-01, FS-02 y tracker pasivo.

## Riesgos

- PostgreSQL real y concurrencia HTTP multi-proceso no se probaron en esta ejecución; verificar cuando haya un entorno disponible antes de producción.
- La revisión global `npm run check` sigue señalando tres archivos ajenos a este incremento; no se corrigieron fuera de alcance.
- Esta entrega solo registra inicio; no resuelve suspensión, fin ni recuperación de sesión, decisiones expresamente diferidas.

## Relevant Files

- `app/Actions/Tasks/` — referencia de operaciones transaccionales y WIP.
- `app/Policies/TaskPolicy.php` — referencia de autorización de tareas.
- `resources/js/pages/dashboard.tsx` — destino probable para inicio libre y estado activo.
- `resources/js/pages/projects/show.tsx` — destino probable para inicio ligado desde tarjetas.
- `tests/Feature/Tasks/TaskTest.php` — referencia de pruebas de alcance.
