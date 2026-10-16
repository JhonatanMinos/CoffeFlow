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

| Tema | Tratamiento |
| --- | --- |
| Regla confirmada | `completed` requiere objetivo alcanzado; cierre anticipado conserva tiempo verificable como `interrupted`. |
| Transiciones | Completar solo desde `running`; interrumpir desde `running` o `paused`; ningún estado terminal se reanuda. |
| Objetivo | Snapshot `target_duration_seconds` al iniciar; default técnico configurable de 1500 segundos, no límite fijo de negocio. |
| Auditoría | `ended_at` de sesión se escribe una vez con la transición terminal; no altera `started_at` ni autoría. |
| Confirmación | Backend registra `last_confirmed_at` del intervalo; nunca acepta segundos arbitrarios del navegador. |
| Recuperación provisional | Ventana configurable de 90 segundos para tolerar demora de contacto; no sumar esa ventana como tiempo perdido. |
| Contacto fresco | Heartbeat o comando explícito autorizado puede confirmar hasta la hora del servidor si no se excedió la ventana. |
| Contacto vencido | Cerrar el tramo en la última confirmación, sin unir el hueco; requerir reanudación explícita o terminar como interrumpida. |
| Navegador oculto | No equivale a falta de enfoque: permitir trabajo en IDE/pestaña de fondo; confirmar contacto, no inferir atención. |
| Datos anteriores | No asignar objetivo retrospectivo: objetivo nulo permite interrumpir, no acreditar un Pomodoro completo. Conservar intervalos cerrados históricos; no inventar duración del tramo abierto sin confirmación. |

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

**Alcance de concurrencia:** los comandos adquieren primero el lock de sesión y después el intervalo; la restricción de intervalo abierto duplicado se ejercitó en SQLite. PostgreSQL y carreras concurrentes reales quedan pendientes; SQLite no demuestra su comportamiento. No se ejecutó migración contra una base persistente. La revisión nativa sigue no disponible mientras no exista `gentle-ai` en el entorno.

**Conteo de corte:** 379 líneas autorales (363 de código/pruebas y 16 de documentación); FC-02 queda bajo el presupuesto orientativo de 400. **Commit/evidencia:** pendiente de registrar tras crear el commit.

### FC-03 — Finalizar e interrumpir de forma atómica

- [ ] Implementar comandos de cierre y rutas protegidas con resultado terminal idempotente.
- [ ] Completar solo una sesión en ejecución con objetivo verificable alcanzado; cerrar antes como interrumpida.
- [ ] Conservar tiempo confirmado al interrumpir activa/pausada y liberar la sesión activa sin tocar tareas ni XP.

**Ruta:** delegada; acciones/controlador/rutas y pruebas de transiciones.

**Aceptación:** fecha terminal estable, propiedad validada, terminales no reanudables y solicitudes repetidas sin duplicación.

**Checks:** RED/GREEN de transiciones, permisos, límites y reintentos en Focus; suite PHP, Pint y diff check.

**Commit/evidencia:** pendiente.

### FC-04 — Integrar controles y verificar el ciclo completo

- [ ] Reutilizar controles de pausa/reanudación y añadir cierre/interrupción y confirmaciones periódicas en ambas superficies.
- [ ] Mostrar objetivo/tiempo autorizado y errores/recuperación con controles accesibles; no enviar duración contable del cliente.
- [ ] Comprobar el ciclo en navegador y mantener los flujos de edición/archivo de tareas.

**Ruta:** delegada; integración React/Inertia y verificación independiente.

**Aceptación:** errores visibles, interacción de teclado, estado recuperable tras contactos vencidos y ausencia de efectos laterales en tareas/privacidad.

**Checks:** Focus y suite PHP; `npm run types:check`, `npm run build`, Pint y diff check; Chromium aislado y comprobaciones PostgreSQL pertinentes. No presentar sus pruebas como auditoría exhaustiva o revisión nativa.

**Commit/evidencia:** pendiente.

## Progreso y próximo paso

- Exploración y diseño acotados completados; usuario confirmó la regla de objetivo antes de completar.
- FC-01 implementado y verificado localmente; no se aplicaron migraciones a una base personal.
- Modalidad `stacked-to-main` confirmada. FC-01 verificado independientemente: Focus 16 pruebas/105 aserciones; suite PHP 147/676; Pint y diff correctos. FC-02 backend implementado y verificado; PostgreSQL concurrente y review nativo siguen pendientes. Siguiente paso: FC-03 (finalización e interrupción).
- Native CLI `gentle-ai` no estaba instalado; volver a consultar estado sin cambiar preferencias y registrar indisponibilidad si persiste.

## Verificación independiente de FC-01

- Código `04490f1`; tracker inicial `5dfe6be`. Sin hallazgos bloqueantes; comprobación estructural del padre y diff check correctos.
- Verificador independiente reprodujo Focus 16/105 y suite PHP 147/676; Pint y diff check correctos.
- Pendiente: pruebas PostgreSQL de concurrencia y revisión nativa (CLI no disponible). SQLite no demuestra carreras PostgreSQL.
- Cobertura adicional para FC-02/03: reanudación por propietario ajeno, estados terminales y rechazo de intervalo abierto duplicado.
- Cortes locales previstos: comportamiento FC-01 `806daf8..04490f1` (323 líneas); documentación `04490f1..5dfe6be` (124 líneas). No hay PR publicados.
