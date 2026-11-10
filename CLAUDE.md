# Plataforma de Logística de Última Milla — guía del proyecto

Proyecto universitario (Laboratorio de Software, UTP). Monorepo con tres partes que comparten una API.
Somos 5 personas trabajando en paralelo: **sigue los patrones de este documento** para que todo el código se parezca.
Todos los datos son ficticios.

| Carpeta    | Qué es                                | Tecnología                                         |
| ---------- | ------------------------------------- | -------------------------------------------------- |
| `backend/` | API REST                              | Laravel 13, Sanctum, PHP 8.5, PostgreSQL (Neon)    |
| `web/`     | Aplicación web (todos los roles)      | Angular 21 + Bootstrap 5                           |
| `movil/`   | Aplicación del repartidor             | Ionic 9 + Angular 22 (standalone) + Capacitor      |
| `docs/`    | `modelo_relacional.dbml` (12 tablas)  | Fuente de verdad del modelo de datos               |

Roles: `administrador`, `operador`, `comercio`, `repartidor`.

`backend/CLAUDE.md` tiene además las guías de Laravel Boost; aplican cuando se trabaja dentro de `backend/`.

## Reglas obligatorias

1. **La base de datos de Neon es compartida por 5 personas. NUNCA ejecutes** `migrate:fresh`, `migrate:reset`,
   `migrate:refresh`, `migrate:rollback`, `db:wipe` ni nada que borre tablas o datos.
2. `php artisan migrate --seed` **ya se ejecutó una vez** y no se repite. Para aplicar una migración nueva se usa
   solo `php artisan migrate`, y antes se pide confirmación a la persona.
3. **Nunca edites una migración que ya se ejecutó.** Los cambios al esquema van en una migración nueva,
   y primero se actualiza `docs/modelo_relacional.dbml` y se avisa al equipo.
4. Antes de correr pruebas, verifica que `backend/phpunit.xml` tenga `DB_CONNECTION=sqlite` y
   `DB_DATABASE=:memory:`. Las pruebas jamás deben tocar Neon.
5. No cambies las versiones de Laravel, Angular, Ionic ni PHP. No ejecutes `npm audit fix --force`.
   No agregues dependencias sin aprobación del equipo.
6. No escribas contraseñas, claves ni tokens en archivos que se suben a Git. Los secretos viven solo en
   `backend/.env` (ignorado por Git). La única excepción es la contraseña ficticia `password` de los usuarios de prueba.
7. No hagas `git push` sin que la persona lo pida.
8. Antes de dar algo por terminado, verifica: `php artisan test` (backend), `ng build` (web), `ionic build` (móvil).

## Convenciones

- **Todo en español**: tablas, columnas, clases, variables, rutas, mensajes y pruebas.
- Tablas en plural (`pedidos`, `lineas_pedido`); llaves foráneas `<tabla_en_singular>_id`
  (o `repartidor_id` / `creado_por` cuando apuntan a `usuarios`).
- La tabla de usuarios es `usuarios` (modelo `Usuario`), no `users`. Fechas con `created_at` / `updated_at`.
- **Enums**: columna `string` en la base + enum de PHP en `app/Shared/Enums` + cast en el modelo.
  No se usa el tipo ENUM de PostgreSQL (las pruebas corren en SQLite).
- La API guarda y devuelve fechas en UTC; la web y el móvil las muestran en la hora local del dispositivo.

## Backend (`backend/`)

```
app/
  Shared/
    Enums/                    Rol, EstadoPedido, Prioridad, EstadoIncidencia, ...
    Http/Middleware/CheckRole.php   middleware de roles (alias "rol")
    Errores/                  ManejadorErrores (formato de error) y ErrorApi (errores de negocio)
    Auth/                     AuthController (login, logout, me) y LoginRequest
  Modules/
    Administracion/           Usuario, Zona, Comercio, Configuracion
    Pedidos/                  Cliente, Pedido, LineaPedido, HistorialPedido
    Despacho/                 Evidencia + panel
    Incidencias/              TipoIncidencia, Incidencia
    Asistente/                ConsultaAsistente
database/migrations | factories | seeders
routes/api.php                login/logout/me + carga el routes.php de cada módulo
tests/Feature/                pruebas Pest
```

Cada módulo tiene la misma forma: `Models/`, `Controllers/`, `Resources/` (y `Requests/` cuando haya formularios)
y un `routes.php`.

### Reglas de la API

- **Autenticación**: `POST /api/login` (`correo`, `password`) devuelve `{ token, usuario }`. Las demás rutas van con
  `Authorization: Bearer <token>` y están dentro del grupo `auth:sanctum` de `routes/api.php`.
- **Roles**: cada ruta declara quién puede entrar con el middleware `rol`:
  `Route::get(...)->middleware('rol:administrador,operador');`
- **Visibilidad de datos**: qué pedidos ve cada rol se decide en un solo lugar, `Pedido::scopeVisiblesPara()`.
  Todo lo que dependa de pedidos debe usarlo (`Pedido::query()->visiblesPara($usuario)`), no repetir el filtro.
  Un registro ajeno responde 404, no 403.
- **Respuestas**: siempre con API Resources. Un registro: `{ "data": {...} }`. Una lista: `paginate(20)`,
  que produce `{ "data": [...], "meta": {...} }`.
- **Errores**: siempre con este formato (lo arma `ManejadorErrores`, no los controladores):
  ```json
  { "error": { "codigo": "VALIDACION", "mensaje": "Los datos enviados no son válidos.", "campos": { "correo": ["..."] } } }
  ```
  Códigos existentes: `VALIDACION` (422), `NO_AUTENTICADO` (401), `NO_AUTORIZADO` (403), `NO_ENCONTRADO` (404),
  `CREDENCIALES_INVALIDAS` (401), `USUARIO_INACTIVO` (403), `ERROR_INTERNO` (500).
  Para un error de negocio: `throw new ErrorApi('PEDIDO_NO_EDITABLE', 'El pedido ya fue entregado.', 409);`
- **Validación**: con Form Requests (o `$request->validate()` para filtros simples). Nunca validar a mano.
- **CORS**: solo `http://localhost:4200` (web) y `http://localhost:8100` (móvil), en `config/cors.php`.

### Cómo agregar una funcionalidad al backend

Ejemplo: "crear pedidos" en el módulo Pedidos. El código de referencia es `app/Modules/Pedidos`.

1. **Modelo de datos**: si hace falta una tabla o columna, actualiza el DBML, crea una migración nueva
   (`php artisan make:migration ...`) y el modelo en `app/Modules/<Modulo>/Models` con `$table`, `#[Fillable]`,
   `casts()` y relaciones. Si el modelo se usa en pruebas, crea su factory en `database/factories`
   y enlázala con `#[UseFactory(...)]`.
2. **Request**: `app/Modules/<Modulo>/Requests/CrearPedidoRequest.php` con las reglas de validación.
3. **Controlador**: `app/Modules/<Modulo>/Controllers`. Métodos cortos; filtra con `visiblesPara()`;
   devuelve un Resource.
4. **Resource**: `app/Modules/<Modulo>/Resources`. Solo los campos que el cliente necesita; relaciones con `whenLoaded`.
5. **Ruta**: en `app/Modules/<Modulo>/routes.php`, con su middleware `rol:...`.
6. **Pruebas** en `tests/Feature`: caso feliz, validación, 401 sin token, 403 con rol no permitido
   y que un comercio/repartidor no vea ni modifique datos ajenos.
7. `vendor/bin/pint --dirty` y `php artisan test`.

Todo cambio de estado de un pedido debe insertar una fila en `historial_pedido` (solo se inserta, nunca se edita).

## Web (`web/src/app`)

```
core/        api.service.ts      única puerta a la API (get/post/put/delete)
             auth.service.ts     sesión: token y usuario en localStorage, como signals
             auth.interceptor.ts agrega el token; ante un 401 cierra sesión
             guards.ts           authGuard, invitadoGuard, rolGuard
             menu.ts             opciones del menú y roles de cada una
             modelos.ts          interfaces de lo que devuelve la API
             etiquetas.ts        textos y colores de estados; mensajeDeError()
layout/      barra superior + menú lateral según el rol
shared/      componentes reutilizables (paginacion)
features/    login, panel, pedidos, clientes, incidencias, administracion, despacho
```

### Cómo agregar una página a la web

1. Agrega las interfaces en `core/modelos.ts` (deben coincidir con el Resource del backend).
2. Crea el componente en `features/<modulo>/` (standalone, con signals `datos`, `cargando`, `error`).
   Copia la forma de `features/clientes/clientes.ts`. Usa `ApiService`, nunca `HttpClient` directo,
   y `mensajeDeError()` para mostrar errores.
3. Agrega la opción en `core/menu.ts` con sus roles.
4. Agrega la ruta en `app.routes.ts` con `canActivate: [rolGuard]` y `data: { roles: rolesDe('/ruta') }`.
5. `ng build`.

Estilos con clases de Bootstrap; no agregar librerías de componentes.

## Móvil (`movil/src/app`)

Mismo `core/` que la web (ApiService, AuthService, interceptor, guards, modelos, etiquetas).
Pantallas: `login/`, `tabs/` (pestañas), `pedidos/` (Mis pedidos y detalle), `perfil/`.

### Cómo agregar una pantalla al móvil

1. Interfaces en `core/modelos.ts`.
2. Página en su carpeta, archivo `*.page.ts`, clase terminada en `Page`; componentes importados desde `@ionic/angular`.
   Copia la forma de `pedidos/pedidos.page.ts` (carga los datos en `ionViewWillEnter`).
3. Regístrala en `app.routes.ts` (dentro de `tabs` si debe mostrar las pestañas) y, si es una pestaña nueva,
   agrega el botón en `tabs/tabs.page.ts`.
4. `npm run eslint` e `ionic build`.

## Comandos

| Parte   | Levantar                      | Verificar                                      |
| ------- | ----------------------------- | ---------------------------------------------- |
| Backend | `php artisan serve` (:8000)   | `php artisan test`, `vendor/bin/pint --dirty`  |
| Web     | `npm start` (:4200)           | `npx ng build`                                 |
| Móvil   | `ionic serve` (:8100)         | `ionic build`, `npm run eslint`                |

Usuarios de prueba y pasos de instalación: ver `README.md`.
