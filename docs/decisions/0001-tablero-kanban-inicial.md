# ADR 0001: tablero Kanban inicial

## Estado

Provisional, hasta que se aprueben reglas completas de configuración de tableros y límites WIP.

## Decisión

Cada proyecto Kanban nuevo crea atómicamente un tablero predeterminado con cuatro columnas:

1. `Pendiente` (`todo`)
2. `En progreso` (`in_progress`)
3. `En revisión` (`review`)
4. `Terminado` (`done`)

Las columnas comienzan sin límite WIP. Las categorías `backlog` y `cancelled` están disponibles en el modelo, pero no se crean inicialmente. Las migraciones generan la misma configuración para proyectos existentes.

## Motivo

Esta configuración permite entregar un recorrido Kanban mínimo sin inventar límites WIP ni reglas de transición. Los nombres son editables en una fase posterior; las categorías internas permanecen estables para reglas de negocio y analítica.
