# Plataforma de Logística de Última Milla

Proyecto del Laboratorio de Software, UTP. Todos los datos son ficticios.

| Carpeta    | Qué es                           | Tecnología                                       |
| ---------- | -------------------------------- | ------------------------------------------------ |
| `backend/` | API REST                         | Laravel 13 + Sanctum, PHP 8.5, PostgreSQL (Neon) |
| `web/`     | Aplicación web (todos los roles) | Angular 21 + Bootstrap 5                         |
| `movil/`   | Aplicación del repartidor        | Ionic 9 + Angular 22 + Capacitor                 |
| `docs/`    | Modelo de datos y mockups        | `modelo_relacional.dbml` (se puede pegar en https://dbdiagram.io) e `interfaces/` |

Antes de programar:

- [`CLAUDE.md`](CLAUDE.md): reglas del proyecto y patrón para agregar módulos (detalle en [`docs/guia-detallada.md`](docs/guia-detallada.md)).
- [`docs/PENDIENTES.md`](docs/PENDIENTES.md): errores conocidos, diferencias con los mockups, mejoras pendientes y
  **qué módulo le toca probar y ajustar a cada integrante**.
- [`docs/interfaces/README.md`](docs/interfaces/README.md): mockups de cada pantalla (INT-xx) y ajustes al modelo.

Resumen para arrancar (tres terminales): `php artisan serve` en `backend/`, `npm start` en `web/` e `ionic serve`
en `movil/`. La primera vez sigue los pasos de abajo.

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
php artisan storage:link    # una sola vez: publica las fotos de evidencias e incidencias
php artisan serve
```

> Las fotos se guardan en `backend/storage/app/public` (cada computador tiene las suyas; no van a Git).
> El backend acepta imágenes de hasta 5 MB, pero PHP trae `upload_max_filesize = 2M`: súbelo a `5M`
> en tu `php.ini` (`php --ini` dice dónde está) si vas a probar con fotos grandes.

> **La base de datos ya está creada y con datos.** No ejecutes `migrate --seed` otra vez y **nunca** ejecutes
> `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback` ni `db:wipe`: la base es compartida.
> Cuando alguien agregue una migración nueva se aplica solo con `php artisan migrate`, avisando antes al equipo.

Pruebas (usan SQLite en memoria, no tocan Neon ni llaman a la IA):

```bash
php artisan test
```

### Asistente de IA (gratis)

El asistente (menú "Asistente IA" y botón "Resumir con el asistente" de una incidencia) llama a una IA desde el
backend. Se configura en `backend/.env`, sin tocar código:

```env
IA_PROVEEDOR=gemini      # gemini (por defecto) o groq
IA_MODELO=               # vacío = modelo por defecto del proveedor
IA_API_KEY=              # tu clave; nunca la subas a Git
```

- **Google Gemini (por defecto):** entra a <https://aistudio.google.com/apikey> con una cuenta de Google, pulsa
  "Create API key" y copia la clave en `IA_API_KEY`. El plan gratuito no pide tarjeta. Modelo por defecto:
  `gemini-flash-lite-latest` (el más rápido); para el Flash completo usa `IA_MODELO=gemini-flash-latest`
  (en horas de mucha demanda puede tardar más de 30 s y el asistente dirá que no está disponible).
- **Groq:** entra a <https://console.groq.com/keys>, crea una cuenta gratuita, pulsa "Create API Key" y copia la
  clave en `IA_API_KEY`; pon además `IA_PROVEEDOR=groq`. Modelo por defecto: `llama-3.3-70b-versatile`.

Después de cambiar el `.env`, reinicia `php artisan serve`. Si falta la clave, la IA tarda más de 30 segundos o
el plan gratuito llega a su límite de peticiones, la aplicación muestra "El asistente no está disponible" con
"Reintentar" (el motivo queda en `backend/storage/logs/laravel.log`).

> **Windows: error de certificado SSL (`cURL error 60`).** PHP no trae los certificados raíz. Descarga
> <https://curl.se/ca/cacert.pem>, guárdalo (por ejemplo en `C:\php\cacert.pem`) y en tu `php.ini` deja
> `curl.cainfo = "C:\php\cacert.pem"` y `openssl.cafile = "C:\php\cacert.pem"`. Reinicia `php artisan serve`.

Al asistente solo le llegan datos anonimizados: nunca el nombre, la cédula, el teléfono, el correo ni la
dirección del cliente (los pedidos se identifican por su número). Solo informa: no modifica datos.

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

## Antes de subir cambios

```bash
cd backend && php artisan test && vendor/bin/pint --dirty   # pruebas y formato
cd web     && npx ng build                                  # compila la web
cd movil   && ionic build && npm run eslint                 # compila el móvil y revisa el estilo
```

Los tres deben terminar sin errores. (La web muestra un aviso de tamaño del paquete inicial: está anotado en
`docs/PENDIENTES.md` y no impide compilar.)

## Usuarios de prueba

Todos con la contraseña `password`.

| Rol           | Correo                      | Qué ve                                                    |
| ------------- | --------------------------- | --------------------------------------------------------- |
| Administrador | `admin@logistica.test`      | Todo: Panel, Usuarios, Parametrización, Pedidos, Clientes, Incidencias y Asistente IA |
| Operador      | `operador@logistica.test`   | Todo menos Usuarios y Parametrización; además, Repartidores |
| Comercio      | `comercio@logistica.test`   | Solo lo de "Tienda La Esquina" (pedidos, clientes e incidencias) |
| Comercio      | `comercio2@logistica.test`  | Solo lo de "Farmacia Vida Sana"                           |
| Repartidor    | `repartidor@logistica.test` | Solo sus pedidos asignados. Es el usuario del móvil       |
| Repartidor    | `repartidor2@` a `repartidor5@logistica.test` | Repartidores de demostración, en zonas distintas (el 3 y el 5, no disponibles) |

La web es para administrador, operador y comercio; la app móvil, solo para el repartidor.

## API disponible

| Método | Ruta                 | Roles                             |
| ------ | -------------------- | --------------------------------- |
| POST   | `/api/login`         | público                           |
| POST   | `/api/logout`        | cualquiera con sesión             |
| GET    | `/api/me`            | cualquiera con sesión             |
| GET    | `/api/pedidos`       | todos (cada uno ve los suyos)     |
| GET    | `/api/pedidos/{id}`  | todos (cada uno ve los suyos)     |
| POST   | `/api/pedidos`       | administrador, operador, comercio |
| PUT    | `/api/pedidos/{id}`  | administrador, operador, comercio |
| GET    | `/api/clientes`      | administrador, operador, comercio |
| POST   | `/api/clientes`      | administrador, operador, comercio |
| PUT    | `/api/clientes/{id}` | administrador, operador, comercio |
| GET    | `/api/incidencias`   | todos (según sus pedidos)         |
| GET    | `/api/incidencias/{id}` | todos (según sus pedidos)      |
| POST   | `/api/pedidos/{id}/incidencias` | administrador, operador, repartidor |
| PUT    | `/api/incidencias/{id}/prioridad` | administrador, operador |
| PUT    | `/api/incidencias/{id}/estado` | administrador, operador   |
| PUT    | `/api/incidencias/{id}/nota` | administrador, operador     |
| POST   | `/api/incidencias/{id}/resumen` | administrador, operador  |
| POST   | `/api/asistente/consultas` | administrador, operador       |
| GET    | `/api/panel/resumen` | administrador, operador, comercio |
| GET    | `/api/panel/indicadores` | administrador, operador       |
| GET    | `/api/pedidos/{id}/repartidores` | administrador, operador (candidatos para asignar) |
| PUT    | `/api/pedidos/{id}/repartidor` | administrador, operador (asignar o reasignar) |
| PUT    | `/api/pedidos/{id}/cancelacion` | administrador, operador  |
| PUT    | `/api/pedidos/{id}/estado` | repartidor (recogido, en ruta, entregado, no entregado) |
| POST   | `/api/pedidos/{id}/evidencias` | repartidor                |
| GET    | `/api/repartidores`  | administrador, operador           |
| PUT    | `/api/repartidores/{id}/disponibilidad` | administrador, operador, repartidor (la suya) |
| GET    | `/api/zonas`, `/api/comercios`, `/api/tipos-incidencia` | todos (catálogos) |
| POST, PUT | `/api/zonas`, `/api/tipos-incidencia` (+ `/{id}/estado`) | administrador |
| GET, PUT | `/api/configuracion` | administrador                   |
| GET, POST, PUT | `/api/usuarios` (+ `/{id}/estado`, `/{id}/password`) | administrador |

La lista completa y exacta sale de `php artisan route:list --path=api --except-vendor`; los roles de cada ruta
están en el `routes.php` de cada módulo (`backend/app/Modules/<Módulo>/routes.php`).

Las listas aceptan `?page=2`. La de pedidos filtra con `?estado=`, `?numero=`, `?zona_id=`, `?comercio_id=`,
`?desde=` y `?hasta=` (fecha de creación); la de clientes, con `?buscar=` (nombre, cédula o teléfono) y `?comercio_id=`.
La de incidencias filtra con `?estado=`, `?tipo_incidencia_id=`, `?prioridad=` y `?numero=` (número de pedido).
El usuario de un comercio solo trabaja con lo de su comercio: la API se lo fuerza aunque la petición diga otro.

Una incidencia pasa de `abierta` a `en_revision` y de ahí a `resuelta` (`PUT .../estado` con `estado` y, para
resolver, `resolucion`). El asistente recibe `{ "pregunta": "...", "historial": [...] }` y responde
`{ "data": { "respuesta": "..." } }`; cada consulta queda en `consultas_asistente` como respondida o fallida.

Datos de demostración del módulo de Clientes y Pedidos (8 clientes y 12 pedidos en estado "creado"; es
idempotente y no borra nada): `php artisan db:seed --class=DemoPedidosSeeder`.

Datos de demostración de la operación, para el panel y la app móvil (también idempotente y sin borrar nada):
`php artisan db:seed --class=DemoOperacionSeeder`. Crea `repartidor2@` a `repartidor5@logistica.test` (contraseña
`password`, en zonas distintas; el 3 y el 5 no disponibles), 15 pedidos en estados variados con su historial
(3 quedan asignados a `repartidor@logistica.test`) e incidencias en los no entregados. No crea evidencias.

El panel (`/api/panel/indicadores`) acepta `?desde=`, `?hasta=`, `?zona_id=`, `?comercio_id=` y `?estado=`, y devuelve
los pedidos por estado, el % de entregas exitosas, el tiempo promedio de entrega en minutos y las incidencias por
tipo, zona y repartidor. Un indicador sin base de cálculo llega en `null` ("Sin datos").

Ejemplo:

```bash
curl -X POST http://localhost:8000/api/login -H "Accept: application/json" -H "Content-Type: application/json" -d '{"correo":"comercio@logistica.test","password":"password"}'
curl http://localhost:8000/api/pedidos -H "Accept: application/json" -H "Authorization: Bearer <token>"
```
