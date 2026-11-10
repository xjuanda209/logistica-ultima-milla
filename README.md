# Plataforma de Logística de Última Milla

Proyecto del Laboratorio de Software, UTP. Todos los datos son ficticios.

| Carpeta    | Qué es                           | Tecnología                                       |
| ---------- | -------------------------------- | ------------------------------------------------ |
| `backend/` | API REST                         | Laravel 13 + Sanctum, PHP 8.5, PostgreSQL (Neon) |
| `web/`     | Aplicación web (todos los roles) | Angular 21 + Bootstrap 5                         |
| `movil/`   | Aplicación del repartidor        | Ionic 9 + Angular 22 + Capacitor                 |
| `docs/`    | Modelo de datos                  | `modelo_relacional.dbml` (se puede pegar en https://dbdiagram.io) |

Las reglas del proyecto y el patrón para agregar módulos están en [`CLAUDE.md`](CLAUDE.md). Léelo antes de programar.

## Requisitos

- PHP 8.5 con las extensiones `pdo_pgsql`, `pgsql`, `pdo_sqlite` y `sqlite3` activas
  (en `php.ini`, quitar el `;` de `extension=pdo_sqlite` y `extension=sqlite3`; se comprueba con `php -m`).
- Composer, Node.js y npm.
- Ionic CLI: `npm install -g @ionic/cli`.

## 1. Backend (http://localhost:8000)

```bash
cd backend
composer install
cp .env.example .env        # en PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Abre `backend/.env` y escribe en `DB_PASSWORD` la contraseña de Neon (pídesela al equipo; **nunca** la subas a Git).

```bash
php artisan serve
```

> **La base de datos ya está creada y con datos.** No ejecutes `migrate --seed` otra vez y **nunca** ejecutes
> `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback` ni `db:wipe`: la base es compartida.
> Cuando alguien agregue una migración nueva se aplica solo con `php artisan migrate`, avisando antes al equipo.

Pruebas (usan SQLite en memoria, no tocan Neon):

```bash
php artisan test
```

## 2. Web (http://localhost:4200)

```bash
cd web
npm install
npm start
```

## 3. Móvil (http://localhost:8100)

```bash
cd movil
npm install
ionic serve
```

La app móvil es solo para el rol repartidor. Debe abrirse en el puerto 8100 (es el que permite el backend).
Para probarla en un teléfono real hay que cambiar `apiUrl` en `movil/src/environments/environment.ts`
por la IP del computador que corre el backend.

El backend debe estar corriendo para que la web y el móvil funcionen. La dirección de la API está en
`src/environments/environment.ts` de cada proyecto (`http://localhost:8000/api`).

## Usuarios de prueba

Todos con la contraseña `password`.

| Rol           | Correo                      | Qué ve                                                    |
| ------------- | --------------------------- | --------------------------------------------------------- |
| Administrador | `admin@logistica.test`      | Todo (20 pedidos)                                         |
| Operador      | `operador@logistica.test`   | Todo, menos Administración                                |
| Comercio      | `comercio@logistica.test`   | Solo lo de "Tienda La Esquina" (10 pedidos)               |
| Comercio      | `comercio2@logistica.test`  | Solo lo de "Farmacia Vida Sana" (10 pedidos)              |
| Repartidor    | `repartidor@logistica.test` | Solo sus pedidos asignados (14). Es el usuario del móvil  |

## API disponible

| Método | Ruta                 | Roles                             |
| ------ | -------------------- | --------------------------------- |
| POST   | `/api/login`         | público                           |
| POST   | `/api/logout`        | cualquiera con sesión             |
| GET    | `/api/me`            | cualquiera con sesión             |
| GET    | `/api/pedidos`       | todos (cada uno ve los suyos)     |
| GET    | `/api/pedidos/{id}`  | todos (cada uno ve los suyos)     |
| GET    | `/api/clientes`      | administrador, operador, comercio |
| GET    | `/api/incidencias`   | todos (según sus pedidos)         |
| GET    | `/api/panel/resumen` | administrador, operador, comercio |

Las listas aceptan `?page=2`; la de pedidos también `?estado=en_ruta`.

Ejemplo:

```bash
curl -X POST http://localhost:8000/api/login -H "Accept: application/json" -H "Content-Type: application/json" -d '{"correo":"comercio@logistica.test","password":"password"}'
curl http://localhost:8000/api/pedidos -H "Accept: application/json" -H "Authorization: Bearer <token>"
```
