# Incremento: tareas y flujo Kanban

## Objetivo

Incorporar el primer flujo vertical de tareas: creación y visualización de tarjetas, movimiento entre columnas con límites WIP y finalización manual autorizada.

## Problema

CoffeeFlow ya crea proyectos con un tablero Kanban y columnas configuradas, pero todavía no dispone de tareas ni de operaciones para administrar tarjetas.

## Por qué

Las tareas son la base necesaria para vincular posteriormente sesiones de enfoque, esfuerzo compartido y gamificación sin mezclar esas responsabilidades en este incremento.

## Alcance autorizado

- Crear tareas dentro de un proyecto, tablero y columna compatibles.
- Mostrar las tareas agrupadas por columna en la vista del proyecto.
- Mover tareas entre columnas del mismo tablero.
- Aplicar límites WIP en el servidor.
- Finalizar tareas únicamente mediante una acción manual y autorizada.
- Mantener `completed_at` y `completed_by` coherentes con el estado de finalización.
- Añadir pruebas funcionales de autorización, aislamiento y reglas de negocio.

## Fuera de alcance

- Subtareas, responsables, checklist, comentarios e historial detallado.
- Drag and drop; el primer incremento puede usar controles accesibles equivalentes.
- Sesiones de enfoque, XP, logros y tiempo real.
- Una definición de terminado configurable.

## Restricciones y decisiones provisionales

- La autorización se aplica en el servidor y exige pertenencia válida al workspace del proyecto.
- Una tarea solo puede usar un tablero y una columna pertenecientes a su proyecto.
- Un límite WIP se valida transaccionalmente al crear o mover una tarea hacia la columna limitada.
- La finalización exige una petición explícita y mueve la tarea a una columna interna `done`; alcanzar estimaciones nunca finaliza una tarea.
- Como los criterios de aceptación completos siguen pendientes, este incremento usa una regla mínima provisional: autorización, coherencia de alcance y existencia de una columna `done`. No se presenta como regla definitiva del producto.

## Estrategia de entrega

- Estrategia: `ask-on-risk`.
- Pronóstico: aproximadamente 650 líneas autorales, sin contar archivos generados.
- Estrategia de encadenamiento: `feature-branch-chain`.
- Orden previsto: KT-01 → KT-02 → KT-03; cada unidad conservará sus pruebas y dependerá de la anterior.
- Límite revisado inicial: `8e665d4`.

## Tareas

### KT-01 — Crear y mostrar tareas

- [x] Añadir persistencia y relaciones del dominio de tareas.
- [x] Añadir autorización, validación y acción de creación.
- [x] Renderizar tarjetas agrupadas por columna con estados accesibles.
- [x] Probar creación, aislamiento por workspace y coherencia proyecto/tablero/columna.

**Ruta:** delegada; múltiples archivos no triviales y lectura preparatoria.

**Criterios de aceptación:**

- Una persona autorizada crea una tarea en una columna del tablero del proyecto.
- Una persona ajena no puede crear ni observar tareas del proyecto.
- No se aceptan identificadores de tablero o columna fuera del proyecto.

**Verificación:** prueba Pest enfocada del flujo de tareas; formateo configurado; build frontend si cambia la página.

**Evidencia:**

- Implementación: `6a2b84e` (`feat(tasks): create and display project tasks`).
- Corrección: `86c0376` (`fix(tasks): prevent implicit completion during creation`).
- RED: la suite falló inicialmente porque no existía `tasks.store`; la regresión posterior falló porque crear en `done` no producía error.
- GREEN: `php artisan test tests/Feature/Tasks/TaskTest.php` — 5 pruebas, 31 aserciones.
- `vendor/bin/pint --test` — correcto.
- `npm run build` — correcto; aviso no bloqueante por `fontaine` opcional ausente.
- Verificación independiente — aprobada sin bloqueantes tras impedir creación directa en `done` y asociar errores accesibles.
- Spot check del padre — 5 pruebas, 31 aserciones.
- RDD — no disponible: el ejecutable `gentle-ai` no existe en el entorno; riesgo tratado como alto y cubierto mediante verificación independiente.
- Alcance adicional confirmado: el cambio mínimo en `ProjectController` es necesario para cargar tarjetas y capacidad de creación; no expande el comportamiento autorizado.
- Conteo acumulado de commits de trabajo: 588 líneas autorales; diff neto de KT-01: 582 líneas.

### KT-02 — Mover tareas y aplicar WIP

- [ ] Implementar movimiento autorizado entre columnas del mismo tablero.
- [ ] Aplicar el límite WIP en el servidor dentro de una transacción.
- [ ] Exponer controles accesibles para mover tarjetas.
- [ ] Probar movimientos válidos, cruces de alcance y rechazo por WIP.

**Ruta:** delegada; lógica concurrente y cambios backend/frontend.

**Criterios de aceptación:**

- Una tarea se mueve únicamente entre columnas compatibles del mismo tablero.
- Una columna que alcanzó su límite WIP rechaza nuevas entradas sin modificar la tarea.
- La interfaz comunica el rechazo sin asumir que el cliente es autoridad.

**Verificación:** prueba Pest enfocada de movimiento/WIP; formateo configurado; build frontend.

**Evidencia:** pendiente.

### KT-03 — Finalizar tareas manualmente

- [ ] Implementar el caso de uso explícito de finalización.
- [ ] Mantener columna, `completed_at` y `completed_by` coherentes.
- [ ] Exponer un control manual accesible.
- [ ] Probar autorización, idempotencia y ausencia de finalización implícita.

**Ruta:** delegada; comportamiento vertical backend/frontend con pruebas.

**Criterios de aceptación:**

- Solo una acción explícita y autorizada finaliza la tarea.
- La tarea finalizada queda en una columna `done` con autor y fecha coherentes.
- Repetir la operación no duplica efectos ni altera la autoría original.

**Verificación:** prueba Pest enfocada de finalización; formateo configurado; build frontend.

**Evidencia:** pendiente.

## Progreso

- KT-01 completada y verificada; KT-02 y KT-03 pendientes.
- Resolución de ejecución: el usuario autorizó `gpt-6-luna` para KT-01 porque el runtime no expone el `gpt-6.1-luna` configurado.
- Primer límite de slice previsto: `f783204` + `6a2b84e` + `86c0376`; todavía no se creó ni publicó ningún PR.
- Próximo paso: ejecutar KT-02 para movimiento de tareas y límites WIP.

