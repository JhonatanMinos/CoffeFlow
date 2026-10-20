# Incremento: pausar y reanudar sesiones Focus

## Objetivo

Permitir pausar y reanudar la sesión activa propia, registrando intervalos de concentración verificables por el servidor.

## Problema

El MVP permite iniciar una sesión, pero aún no puede detener temporalmente el cómputo de enfoque ni continuarla sin contar el descanso como trabajo.

## Por qué

La pausa debe excluirse del tiempo efectivo. Cambiar solo el estado de la sesión perdería la duración activa y haría imposible recuperarla de forma consistente.

## Alcance autorizado

- Registrar intervalos de actividad de una sesión con inicio y fin autorizados por el servidor.
- Crear el primer intervalo atómicamente al iniciar una sesión.
- Pausar la sesión activa propia cerrando el intervalo vigente.
- Reanudar la sesión pausada propia creando un nuevo intervalo.
- Mantener la unicidad de una sesión `running` o `paused` por usuario y validar transiciones con concurrencia.
- Exponer controles accesibles de pausa y reanudación desde el dashboard y el tablero de tareas.
- Añadir pruebas de transición, alcance, repetición y consistencia de intervalos.

## Fuera de alcance

- Completar, cancelar o interrumpir sesiones; descansos; XP y logros.
- Presentar una política de recuperación tras suspensión, desconexión o alteración del reloj.
- Mostrar cronómetro de cuenta regresiva o animación de taza.
- Introducir sincronización en tiempo real, Redis o colas.

## Decisión técnica provisional

Persistir intervalos en una tabla `focus_session_intervals` con `focus_session_id`, `started_at` y `ended_at` nullable. Inicio y reanudación abren un intervalo; pausa lo cierra. Todas las marcas de tiempo se generan en el backend. Esta representación permite derivar tiempo efectivo como suma de intervalos y evita contar pausas; no define cómo resolver intervalos abiertos luego de una suspensión o falla del cliente. La política de recuperación continúa pendiente y este incremento no la presume.

## Restricciones

- Cada transición se ejecuta en una transacción y bloquea la sesión objetivo antes de validar el estado.
- Solo el propietario puede operar sobre su sesión; errores no deben revelar la existencia de sesiones ajenas.
- Los estados terminales no se reanudan; este incremento solo admite `running -> paused` y `paused -> running`.
- La base de datos debe preservar como máximo un intervalo abierto por sesión cuando el motor soporte índice parcial/condicional.
- No confiar en duración, segundos ni hora reportados por el navegador.

## Estrategia de entrega

- Estrategia: `stacked-to-main` (elegida por el usuario); integrar los cortes en orden y retargetear la siguiente PR tras cada merge.
- Pronóstico inicial: 400–500 líneas autorales, incluidos tests y este documento, excluidos archivos generados. Corte 1 (FS-03 backend + pruebas): 339 líneas autorales; corte 2 (FS-04 UI/prueba + tracker): 228 líneas estimadas (112 en código/prueba + 116 del tracker), menor a 400. Revisar el conteo final antes de preparar una PR.
- Base: `origin/main` en `f491ae3`; rama `feature/focus-session-pause-resume`.

## Tareas

### FS-03 — Persistir transiciones e intervalos

- [x] Crear el modelo y almacenamiento de intervalos; registrar el intervalo inicial al iniciar la sesión.
- [x] Implementar pausa y reanudación autorizadas, transaccionales y protegidas ante solicitudes repetidas o concurrentes.
- [x] Cubrir intervalos, transiciones válidas/inválidas, propiedad, unicidad y errores de concurrencia con pruebas.

**Ruta:** delegada; cambios coordinados en persistencia, acciones, rutas y pruebas backend.

**Criterios de aceptación:**

- Al iniciar/reanudar se abre un solo intervalo activo con timestamp del servidor.
- Al pausar se cierra el intervalo activo; repetir pausa no crea ni altera otro intervalo.
- Una sesión pausada se reanuda con un nuevo intervalo, sin incluir el lapso pausado en trabajo efectivo.
- No es posible operar una sesión de otra persona ni crear otra sesión activa en paralelo.
- Los endpoints solo aceptan la transición válida para el estado actual y verifican propiedad.

**Verificación:** prueba RED/GREEN en `php artisan test tests/Feature/Focus/FocusSessionTest.php`, suite `php artisan test` y `vendor/bin/pint --test`. La prueba de base usa SQLite; PostgreSQL real y concurrencia HTTP multi-proceso se reportan por separado si siguen sin entorno.

**Commit:** `6bba827` (`feat(focus): pause and resume active sessions`).

**Resultado observado:** prueba Focus 15/15 (103 assertions), suite completa 133/133 (585 assertions), `vendor/bin/pint --test` y `git diff --check` pasaron; verificación independiente reprodujo sesiones preexistentes sin backfill histórico. PostgreSQL real y concurrencia HTTP multiproceso no verificados.

### FS-04 — Exponer los controles de pausa y reanudación

- [x] Añadir controles accesibles que alternen pausa/reanudación según el estado activo en dashboard y tablero.
- [x] Presentar errores de transición sin perder la página de origen; cubrir el retorno y el error del servidor con la prueba Feature disponible.

**Ruta:** delegada; cambios coordinados en React/Inertia y pruebas.

**Criterios de aceptación:**

- La UI ofrece solo la acción válida para la sesión activa propia.
- Los controles funcionan con teclado y tecnologías de asistencia, y muestran errores de servidor de forma comprensible.
- La UI no calcula ni envía segundos como autoridad contable.

**Verificación:** `php artisan test tests/Feature/Focus/FocusSessionTest.php`, `npm run types:check`, `npm run build` y `vendor/bin/pint --test`.

**Commit:** `36fd1b1` (`feat(focus): add pause controls to active sessions`).

**Resultado observado:** prueba Focus 15/15 (104 assertions), suite completa 133/133 (586 assertions), `npm run types:check`, `npm run build`, `vendor/bin/pint --test` y `git diff --check` pasaron. El repositorio no configura Vitest, Playwright, Jest ni Testing Library; por eso no hay prueba automatizada de render/UI. La prueba Feature verifica que una transición inválida vuelve al `Referer` y expone el error esperado. El build avisó que `fontaine` es opcional para optimizar fallbacks de fuentes.

## Progreso

- Exploración completada sobre `origin/main` `f491ae3`; el modelo actual solo guarda estado e inicio de sesión.
- La política de recuperación tras suspensión sigue explícitamente pendiente; no se incorporará en este incremento.
- La revisión nativa `gentle-ai` no está instalada en el entorno; no se inventará resultado PASS.
- FS-03 implementado: migración con índice único parcial para intervalos abiertos; inicio abre el intervalo dentro de la transacción y pausa/reanudación validan y bloquean la sesión propia.
- Verificación observada tras corrección de compatibilidad: prueba Focus 15/15 (103 assertions), suite completa 133/133 (585 assertions), `vendor/bin/pint --test` y `git diff --check` pasaron.
- Verificación independiente reprodujo sesiones Focus preexistentes: pausar legacy no inventa intervalos ni tiempo histórico; reanudar crea uno nuevo desde hora de servidor.
- FS-03 quedó en el primer corte apilado con 339 líneas autorales (333 altas + 6 bajas), bajo el presupuesto de 400. El tracker se actualiza en el siguiente corte junto con FS-04.
- FS-04 agrega controles POST accesibles en dashboard y tablero, muestra el error de transición del servidor y no envía duración/hora del navegador; solo renderiza pausar para `running` o reanudar para `paused`.
- La prueba de transición inválida confirma redirect al `Referer` y mensaje comprensible; no hay infraestructura de tests React configurada en `package.json`.
- FS-04 quedó en el segundo corte apilado con 230 líneas autorales (214 altas + 16 bajas), menor a 400.
- La rama del primer corte `feature/focus-session-pause-resume-fs03` se publicó en `JhonatanMinos/CoffeFlow`.
- La creación de la PR FS-03 fue rechazada por GitHub con `403 Resource not accessible by integration`; no se reintentó ni se creó PR. GitHub ofreció `https://github.com/JhonatanMinos/CoffeFlow/pull/new/feature/focus-session-pause-resume-fs03` para crearla manualmente.

## Próximo paso

FS-03 y FS-04 están implementados, verificados y comprometidos localmente en dos cortes menores a 400 líneas. La rama del primer corte está publicada, pero la integración GitHub no permitió crear la PR (403); se requiere crearla manualmente o resolver permisos antes de continuar con el corte 2. No publicar FS-04 antes de integrar FS-03. La estrategia elegida sigue siendo `stacked-to-main`.

## Relevant Files

- `app/Models/FocusSession.php` — modelo actual de sesión.
- `app/Actions/Focus/StartFocusSession.php` — inicio existente, que debe crear el intervalo inicial.
- `app/Http/Controllers/Focus/FocusSessionController.php` — controlador de inicio.
- `database/migrations/2026_10_10_000000_create_focus_sessions_table.php` — estado e índice de unicidad activa.
- `resources/js/pages/dashboard.tsx` — inicio libre y estado activo.
- `resources/js/pages/projects/show.tsx` — inicio ligado desde tareas.
- `tests/Feature/Focus/FocusSessionTest.php` — pruebas de inicio y unicidad.
