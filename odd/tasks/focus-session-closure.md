# Incremento: finalizar e interrumpir sesiones Focus

Permitir cerrar una sesión propia sin perder tiempo verificable: completar exige alcanzar el objetivo guardado; terminar antes registra una interrupción. Reutilizar pausa/reanudación y preservar las protecciones del tablero.

## Problema y propósito

La rama actual permite iniciar Focus, pero no cerrar su ciclo. Pausa/reanudación e intervalos existen en otra rama local. No hay objetivo de duración ni fecha terminal por sesión; tampoco una política implementada que limite el tiempo atribuido después de una suspensión.

## Alcance autorizado

- Reutilizar pausa/reanudación existente, sin duplicar su implementación ni perder controles de edición/archivo.
- Guardar un objetivo por sesión nueva y una fecha terminal; finalizar antes del objetivo produce `interrupted`.
- Añadir finalización e interrupción explícitas con autorización, transacciones e idempotencia.
- Preservar intervalos verificables, excluyendo pausas y periodos sin confirmación.
- Exponer controles accesibles en dashboard y tablero y comprobar el comportamiento.

## Fuera de alcance

- Completar/mover tareas automáticamente, XP, logros, descansos y cancelación.
- Redis, tiempo real, estimaciones, métricas compartidas o configuración de objetivos por usuario en UI.
- Inferir tiempo histórico o migrar la base personal sin autorización específica.
- Publicar, crear PR, integrar ramas remotas o reescribir historia existente.

## Reglas y decisión técnica provisional

| Tema                     | Tratamiento                                                                                                                                                                                                                                                              |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Regla confirmada         | `completed` requiere objetivo alcanzado; cierre anticipado conserva tiempo verificable como `interrupted`.                                                                                                                                                               |
| Transiciones             | Completar solo desde `running`; interrumpir desde `running` o `paused`; ningún estado terminal se reanuda.                                                                                                                                                               |
| Objetivo                 | Snapshot `target_duration_seconds` al iniciar; default técnico configurable de 1500 segundos, no límite fijo de negocio.                                                                                                                                                 |
| Auditoría                | `ended_at` de sesión se escribe una vez con la transición terminal; no altera `started_at` ni autoría.                                                                                                                                                                   |
| Confirmación             | Backend registra `last_confirmed_at` del intervalo; nunca acepta segundos arbitrarios del navegador.                                                                                                                                                                     |
| Recuperación provisional | Ventana configurable de 90 segundos para tolerar demora de contacto; no sumar esa ventana como tiempo perdido.                                                                                                                                                           |
| Contacto fresco          | Heartbeat o comando explícito autorizado puede confirmar hasta la hora del servidor si no se excedió la ventana.                                                                                                                                                         |
| Contacto vencido         | Cerrar el tramo en la última confirmación, sin unir el hueco; requerir reanudación explícita o terminar como interrumpida.                                                                                                                                               |
| Finalización vencida     | Si la confirmación del tramo activo expiró, finalizar siempre como `interrupted`, aunque intervalos anteriores ya hubieran alcanzado el objetivo. La recuperación requiere reanudación explícita; el comando de interrupción también conserva el resultado interrumpido. |
| Navegador oculto         | No equivale a falta de enfoque: permitir trabajo en IDE/pestaña de fondo; confirmar contacto, no inferir atención.                                                                                                                                                       |
| Datos anteriores         | No asignar objetivo retrospectivo: objetivo nulo permite interrumpir, no acreditar un Pomodoro completo. Conservar intervalos cerrados históricos; no inventar duración del tramo abierto sin confirmación.                                                              |

La recuperación anterior es una decisión técnica conservadora y provisional, no una política de producto definitivamente aprobada. Puede pausar/interrumpir ante demoras reales o throttling; la ventana debe ser configurable y el efecto explicarse en UI. Nunca acreditar `last_confirmed_at + grace`: podría completar un objetivo con tiempo no confirmado. Las confirmaciones prueban contacto del cliente, no atención humana.

## Restricciones de integración

- Base local: `feature/focus-session-closure`, desde `806daf8`; los cortes de tareas siguen pendientes de publicación/integración independiente.
- Reutilizar backend `6bba827` y UI `36fd1b1` desde `feature/focus-session-pause-resume`; conservar `StartFocusSession` actual con bloqueo/rechazo de tarea archivada.
- No copiar versiones antiguas completas sobre el controlador, rutas o tablero actual; integrar los comportamientos y conservar permisos/errores existentes.
- Preservar sin cambios ni commits el archivo preexistente no rastreado `odd/tasks/focus-session-pause-resume.md`.
- Bloquear sesión propia e intervalos en orden estable; no modificar tareas ni introducir inversión del orden de locks con archivo/inicio.
- Reintentos no duplican intervalos, tiempo ni fecha terminal; la unicidad de sesión activa se libera al terminar.

## Entrega prevista

- Estrategia: `ask-on-risk`; cadena `stacked-to-main` confirmada por el usuario. Cortes locales por comportamiento; publicación no autorizada.
- Pronóstico: aproximadamente 900–1300 líneas autorales, incluidas adaptación, pruebas y tracker. Referencia medida del código reutilizable: 339 líneas de backend y 112 de UI; no contarlas como una funcionalidad nueva ni omitirlas del diff real.
- Organizar cortes por comportamiento verificable, alrededor de 400 líneas cuando sea coherente; no eliminar pruebas ni comprimir código para ajustarlos.
- Registrar cada commit, conteo y resultado observado al cerrar una tarea. Publicación siempre separada y no autorizada aún.

## Tareas

### FC-01 — Reutilizar intervalos, pausa y reanudación

- [x] Integrar modelo/migración de intervalos y acciones existentes sin modificar el tracker anterior.
- [x] Mantener inicio transaccional con protección de archivo y abrir su primer intervalo.
- [x] Integrar pruebas de pausas, reanudación, permisos, repetición y sesiones anteriores sin inventar tiempo.

**Ruta:** delegada; integración multiarchivo entre ramas locales.

**Aceptación:** intervalos únicos/coherentes, tiempo pausado excluido y protecciones de tareas conservadas.

**Checks:** RED de pruebas portadas antes de integración donde corresponda; `php artisan test tests/Feature/Focus/FocusSessionTest.php`, `php artisan test`, `vendor/bin/pint --test`, `git diff --check`.

**Ruta y rollback:** delegada, una unidad backend cohesiva. Revierte únicamente las rutas/acciones de pausa-reanudación, la relación/modelo/migración de intervalos, la apertura atómica del primer intervalo y sus pruebas; no modifica la UI ni el tracker preexistente `odd/tasks/focus-session-pause-resume.md`.

**Evidencia observada:** pruebas portadas primero: RED, 6 errores por rutas ausentes/modelo de intervalos inexistente. Tras integrar: Focus `16/16` (105 aserciones); suite PHP `147/147` (676 aserciones); Pint pasó tras normalizar solo el controlador; `git diff --check` pasó. Se usó SQLite en memoria y `APP_KEY` efímera por proceso.

**Commit/evidencia:** `04490f1` (`feat(focus): add pause and resume with verified intervals`), 323 líneas cambiadas en backend y pruebas (321 agregadas, 2 eliminadas). El tracker es una unidad pasiva separada; la referencia reutilizable en el commit de origen fue de 333 líneas, antes de adaptación y pruebas seleccionadas.

### FC-02 — Guardar objetivos y acotar actividad confirmada

- [x] Añadir objetivo y fecha terminal de sesión, confirmación del intervalo y configuración provisional validada.
- [x] Registrar confirmaciones autenticadas; cerrar tramos vencidos sin sumar huecos ni pausas.
- [x] Cubrir casos frescos/vencidos, datos anteriores, objetivo estable, aislamiento por usuario y concurrencia SQLite.

**Ruta:** delegada; persistencia, acciones y contrato de tiempo backend coordinados.

**Aceptación:** tiempo derivado solo de límites autorizados, sin crédito de gracia ni segundos del cliente; objetivos anteriores no inventados.

**Checks:** RED/GREEN determinista en Focus, suite PHP, Pint y diff check; PostgreSQL aislado para carreras aplicables, con límites de espera.

**Ruta y rollback:** un work unit backend cohesivo. Revertir solo la migración de campos de confirmación, helper/acción/ruta de heartbeat, snapshots y cierres por pausa, y pruebas asociadas; no revertir FC-01 ni el tracker preexistente `odd/tasks/focus-session-pause-resume.md`.

**RED/GREEN observados:** antes de implementar, las nuevas pruebas fallaron al observar objetivo nulo/configuración no validada, heartbeat sin ruta y límites de pausa/reloj incorrectos (9 pruebas: 1 pasó, 4 fallaron y 4 tuvieron error). Tras implementar y normalizar: Focus `26/26` (155 aserciones); suite PHP `157/157` (726 aserciones); `vendor/bin/pint --test` y `git diff --check` pasaron. Pruebas con `APP_KEY` efímera y SQLite en memoria. Intervalos heredados sin confirmación se cierran inmediatamente en `started_at`, aunque estén dentro de la gracia.

**Corrección de configuración tras revisión independiente:** validación del valor crudo de `env()` antes de conversión; acepta defaults y cadenas enteras canónicas positivas, rechaza booleanos, decimales, prefijos numéricos con sufijo y desbordamiento. Prueba de carga real de `config/focus.php` restaurando el entorno: RED observó que valores inválidos se truncaban/aceptaban; GREEN tras corregir: Focus `27/27` (163 aserciones), suite PHP `158/158` (734 aserciones), Pint y diff check pasaron. Corrección verificada independientemente: Focus 27/163, suite PHP 158/734, Pint y diff correctos.

**Corte de corrección:** 97 líneas autorales (93 de código/pruebas y 4 de documentación), aislado del work unit FC-02 anterior. **Commit/evidencia:** `7754197` (`fix(focus): validate raw duration environment values`).

**Alcance de concurrencia:** los comandos adquieren primero el lock de sesión y después el intervalo; la restricción de intervalo abierto duplicado se ejercitó en SQLite. PostgreSQL 17 aislado: cuatro órdenes concurrentes verificados con procesos separados, esperas Lock y pg_blocking_pids: heartbeat→pausa, pausa→heartbeat, doble reanudación y heartbeat vencido→recuperación. Límites y cantidades exactos correctos; duplicado abierto rechazado con 23505. Solo se migró una base QA nueva desechable; servidor propio detenido. No es una prueba exhaustiva ni de cierre terminal. La revisión nativa sigue no disponible mientras no exista `gentle-ai` en el entorno.

**Conteo de corte:** 379 líneas autorales (363 de código/pruebas y 16 de documentación); FC-02 queda bajo el presupuesto orientativo de 400. **Commit/evidencia:** `62a3891` (`feat(focus): confirm verified session activity`).

### FC-03 — Finalizar e interrumpir de forma atómica

- [x] Implementar comandos de cierre y rutas protegidas con resultado terminal idempotente.
- [x] Completar solo una sesión en ejecución con objetivo verificable alcanzado; cerrar antes como interrumpida.
- [x] Conservar tiempo confirmado al interrumpir activa/pausada y liberar la sesión activa sin tocar tareas ni XP.

**Ruta:** delegada; acciones/controlador/rutas y pruebas de transiciones.

**Aceptación:** fecha terminal estable, propiedad validada, terminales no reanudables y solicitudes repetidas sin duplicación.

**Checks:** RED/GREEN de transiciones, permisos, límites y reintentos en Focus; suite PHP, Pint y diff check.

**Ruta y rollback:** un work unit backend cohesivo. Revertir únicamente los endpoints de finalización/interrupción, las acciones de cierre, sus pruebas y esta entrada del tracker; no alterar la pausa/reanudación ni el tracker preexistente de FC-01/02.

**Decisión de cierre vencido:** un `finish` sobre sesión en ejecución solo acredita el tramo vigente cuando su última confirmación está dentro de la gracia. Si expiró, el tramo se cierra en esa última confirmación y el resultado es siempre `interrupted`, incluso cuando el tiempo cerrado de intervalos anteriores ya alcanzaba el objetivo. Se elige recuperación explícita mediante reanudación para evitar certificar concentración durante un hueco no confirmado; no es una política definitiva de producto.

**RED/GREEN observados:** RED inicial de las pruebas de FC-03: 13 pruebas, 0 pasaron, 13 errores; 12 llegaron a observar que faltaban las rutas `finish`/`interrupt` y una detectó un tipo de fecha incompatible en el helper de prueba, corregido antes de GREEN. Tras implementar, Focus closure `15/15` (66 aserciones), Focus `42/42` (229 aserciones), suite PHP `173/173` (800 aserciones), Pint `--test` y `git diff --check` pasaron. `APP_KEY` efímera y SQLite en memoria; sin migración a base persistente. La verificación de PostgreSQL corresponde a FC-02 y no demuestra concurrencia de cierre; review nativo no disponible.

**Conteo de corte y commit/evidencia:** `ff1eef8` (`feat(focus): finish and interrupt verified sessions`), 435 líneas autorales (435 agregadas, 0 eliminadas). Un solo corte cohesivo quedó 35 líneas sobre el presupuesto orientativo: acciones compartidas, rutas y sus pruebas terminales forman una unidad que no conviene separar. Recomendar `size:exception` si se propone como PR; no se creó ni publicó un PR.

### FC-04 — Integrar controles y verificar el ciclo completo

- [ ] Reutilizar controles de pausa/reanudación y añadir cierre/interrupción y confirmaciones periódicas en ambas superficies.
- [ ] Mostrar objetivo/tiempo autorizado y errores/recuperación con controles accesibles; no enviar duración contable del cliente.
- [ ] Comprobar el ciclo en navegador y mantener los flujos de edición/archivo de tareas.

**Ruta:** delegada; integración React/Inertia y verificación independiente.

**Aceptación:** errores visibles, interacción de teclado, estado recuperable tras contactos vencidos y ausencia de efectos laterales en tareas/privacidad.

**Checks:** Focus y suite PHP; `npm run types:check`, `npm run build`, Pint y diff check; Chromium aislado y comprobaciones PostgreSQL pertinentes. No presentar sus pruebas como auditoría exhaustiva o revisión nativa.

**Commit/evidencia:** pendiente.

**RED/GREEN observados:** antes del cambio de contrato, la tanda de pruebas de Dashboard, Project y heartbeat dio RED (27 pruebas: 24 pasaron y 3 fallaron; dos fallos confirmaron props/campos ausentes y un fixture de URL sin workspace produjo 403, corregido antes de GREEN). Después del cambio, la misma tanda pasó `27/27` (208 aserciones).

**Corte backend y evidencia:** `f96708c` (`feat(focus): expose confirmed session state`), 174 líneas autorales. La consulta compartida serializa solo la sesión activa del propietario, el objetivo nullable, los segundos de intervalos confirmados y los límites del intervalo vigente; heartbeat devuelve el total confirmado del servidor. RED/GREEN incluye pausa y sesión antigua sin objetivo ni intervalo actual, tiempo confirmado de intervalos cerrados/abiertos y exclusión de otra persona.

**Corte de interfaz y evidencia:** `4150921` (`feat(focus): integrate session controls across pages`), 574 líneas cambiadas (542 agregadas, 32 eliminadas). Un solo corte cohesivo reutiliza el hook y los controles accesibles en Dashboard y tablero; incluye polling limitado con CSRF, recuperación explícita y cierre terminal comunicado por el servidor. Si se propone como PR, recomendar `size:exception`: separar hook/componente de ambas integraciones produciría cortes incompletos. No se creó ni publicó un PR.

**Verificación observada:** Focus `42/42` (232 aserciones); Dashboard + Project `16/16` (149 aserciones); suite PHP `175/175` (845 aserciones); `npm run types:check`, `npm run build`, `vendor/bin/pint --test` y `git diff --check` pasaron. Build advierte que `fontaine` opcional no está instalado, pero termina exitosamente. `npm run check` se reejecutó después de normalizar los archivos UI y este tracker: sigue fallando solo por formato pendiente en cinco rutas preexistentes y ajenas al alcance (`.atl/.skill-registry.cache.json`, `.atl/skill-registry.md`, `odd/tasks/kanban-tasks.md`, `odd/tasks/task-edit-archive.md`, `resources/js/components/tasks/task-card-actions.tsx`); no se modificaron.

**Pendiente:** aceptación manual en Chromium (no ejecutada; queda para QA del padre) y review nativa no disponible (`gentle-ai` no está instalado). Pruebas PHP usaron `APP_KEY` efímera y SQLite en memoria; Wayfinder generado por el build permanece ignorado. El tracker preexistente no rastreado `odd/tasks/focus-session-pause-resume.md` no se modificó ni se staged.

**Corrección acotada basada en QA independiente (retest Chromium pendiente):** la primera pasada del padre en Chromium aprobó 16 verificaciones funcionales, pero encontró dos defectos: el heartbeat de una sesión anterior sin intervalo abierto no devolvía el mismo contrato que la ruta normal, y el aviso de red mostraba el texto técnico `Failed to fetch`. El fixture de QA configuró objetivo de 5 s y gracia de 90 s, envió primero un heartbeat real y luego aceleró timers con un fixture vencido; los procesos quedaron detenidos/limpios. Aún no se repitió Chromium contra esta corrección.

**RED/GREEN de la corrección:** el nuevo test de ruta HTTP falló primero (1 prueba, 5 aserciones; el total histórico esperado de 75 s faltaba en el JSON), y luego pasó (1/1, 8 aserciones). La recuperación ahora conserva los 75 s históricos y `targetDurationSeconds: null`, devuelve `status: paused` y timestamps de intervalo nulos, sin crear un intervalo. El hook solo expone excepciones de heartbeat tipadas/localizadas; errores de red inesperados se traducen a texto de recuperación en español, manteniendo mensajes HTTP específicos.

**Verificación de corrección:** Focus `43/43` (240 aserciones), suite PHP `176/176` (853 aserciones), `npm run types:check`, `npm run build`, check/lint Vite+ limitado al hook y `git diff --check` pasaron. Build conserva el aviso opcional de `fontaine`. Pint limitado a los archivos PHP cambiados pasó; `vendor/bin/pint --test` global falla solo por el diff preexistente y ajeno en `app/Http/Controllers/Projects/ProjectController.php`, preservado intacto. El diff observado son cuatro cambios de espacio en cierres `fn (` → `fn(`; no se afirma quién lo produjo ni se normalizó.

**Commit de corrección:** `82a335e` (`fix(focus): keep heartbeat recovery responses consistent`), 49 líneas cambiadas (44 agregadas, 5 eliminadas). Archivos comprometidos: acción heartbeat, hook de controles y test de contrato; ninguna ruta ajena fue staged.

## Progreso y próximo paso

- Exploración y diseño acotados completados; usuario confirmó la regla de objetivo antes de completar.
- FC-01 implementado y verificado localmente; no se aplicaron migraciones a una base personal.
- Modalidad `stacked-to-main` confirmada. FC-01 verificado independientemente: Focus 16 pruebas/105 aserciones; suite PHP 147/676; Pint y diff correctos. FC-02 backend implementado y verificado; PostgreSQL concurrente de FC-02 verificado; review nativo no disponible. FC-03 backend cerrado y probado localmente; revisión nativa no disponible. FC-04 integración de código está en commits `f96708c` y `4150921`; falta aceptación manual en Chromium y review nativa si la herramienta queda disponible.
- Native CLI `gentle-ai` no estaba instalado; volver a consultar estado sin cambiar preferencias y registrar indisponibilidad si persiste.

## Verificación independiente de FC-01

- Código `04490f1`; tracker inicial `5dfe6be`. Sin hallazgos bloqueantes; comprobación estructural del padre y diff check correctos.
- Verificador independiente reprodujo Focus 16/105 y suite PHP 147/676; Pint y diff check correctos.
- Pendiente: pruebas PostgreSQL de concurrencia y revisión nativa (CLI no disponible). SQLite no demuestra carreras PostgreSQL.
- Cobertura adicional para FC-02/03: reanudación por propietario ajeno, estados terminales y rechazo de intervalo abierto duplicado.
- Cortes locales previstos: comportamiento FC-01 `806daf8..04490f1` (323 líneas); documentación `04490f1..5dfe6be` (124 líneas). No hay PR publicados.
