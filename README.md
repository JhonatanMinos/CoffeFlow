# CoffeeFlow

CoffeeFlow combina sesiones de enfoque, gestión de proyectos y progreso personal para equipos de software. La aplicación usa Laravel 13, React 19, TypeScript e Inertia.js.

## Estado actual

Este repositorio contiene el primer incremento de Fundamentos:

- autenticación con Laravel Fortify;
- espacios de trabajo personales y de equipo;
- membresías con roles `owner`, `admin`, `manager`, `member` y `guest`;
- invitaciones, cambio de espacio y autorización mediante Policies;
- creación atómica del usuario, su espacio personal y la membresía propietaria.
- proyectos Kanban con creación autorizada, listado paginado y aislamiento por espacio;
- tablero inicial creado atómicamente con columnas de estado estables.

Los tableros, tareas, sesiones de enfoque y gamificación todavía no están implementados.

La política inicial permite ver proyectos a cualquier miembro del espacio y crearlos a los roles `owner`, `admin` y `manager`. Los permisos específicos por proyecto se incorporarán cuando exista colaboración a ese nivel.

## Desarrollo local

Requisitos: PHP 8.3 o posterior, Composer, Node.js y PostgreSQL. Copia `.env.example` a `.env`, configura la conexión y ejecuta:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run dev
```

La cuenta creada por el seeder es `test@example.com` y su contraseña de desarrollo es `password`.

## Verificación

```bash
composer test
npm run check
npm run types:check
npm run build
```

Las pruebas usan SQLite en memoria para aislar cada caso. Las restricciones específicas de PostgreSQL deberán cubrirse además con pruebas de integración cuando se incorporen sesiones de enfoque y su índice único parcial.
