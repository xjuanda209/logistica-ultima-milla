# Guía detallada del proyecto

Versión completa de las guías. `CLAUDE.md` (raíz) y `backend/CLAUDE.md` son el resumen; aquí está el detalle y los ejemplos.

---

# Parte 1 — Plataforma de Logística de Última Milla — guía del proyecto

Proyecto universitario (Laboratorio de Software, UTP). Monorepo con tres partes que comparten una API.
Somos 5 personas trabajando en paralelo: **sigue los patrones de este documento** para que todo el código se parezca.
Todos los datos son ficticios.

| Carpeta    | Qué es                                | Tecnología                                         |
| ---------- | ------------------------------------- | -------------------------------------------------- |
| `backend/` | API REST                              | Laravel 13, Sanctum, PHP 8.5, PostgreSQL (Neon)    |
| `web/`     | Aplicación web (todos los roles)      | Angular 21 + Bootstrap 5                           |
| `movil/`   | Aplicación del repartidor             | Ionic 9 + Angular 22 (standalone) + Capacitor      |
| `docs/`    | `modelo_relacional.dbml` (12 tablas)  | Fuente de verdad del modelo de datos               |
| `docs/interfaces/` | Mockups de cada pantalla (INT-xx) y su `README.md` | Referencia obligatoria de la interfaz (ver "Guía de interfaz") |

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
    Validacion/               reglas de validación propias (UnicoSinMayusculas)
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
  Los clientes tienen la misma regla en `Cliente::scopeVisiblesPara()`.
  Un registro ajeno responde 404, no 403. Al usuario de un comercio se le fuerza su `comercio_id` en el
  Form Request (`prepareForValidation`), aunque la petición diga otro.
- **Respuestas**: siempre con API Resources. Un registro: `{ "data": {...} }`. Una lista: `paginate(20)`,
  que produce `{ "data": [...], "meta": {...} }`.
- **Errores**: siempre con este formato (lo arma `ManejadorErrores`, no los controladores):
  ```json
  { "error": { "codigo": "VALIDACION", "mensaje": "Los datos enviados no son válidos.", "campos": { "correo": ["..."] } } }
  ```
  Códigos existentes: `VALIDACION` (422), `NO_AUTENTICADO` (401), `NO_AUTORIZADO` (403), `NO_ENCONTRADO` (404),
  `CREDENCIALES_INVALIDAS` (401; también si la cuenta está desactivada, con el mismo mensaje), `NO_PUEDES_DESACTIVARTE` (409), `NO_PUEDES_CAMBIAR_TU_ROL` (409),
  `ULTIMO_ADMINISTRADOR` (409), `COMERCIO_INACTIVO` (409), `PEDIDO_NO_EDITABLE` (409),
  `TRANSICION_NO_PERMITIDA` (409), `MISMO_REPARTIDOR` (409), `REPARTIDOR_DESACTIVADO` (409), `REPARTIDOR_NO_DISPONIBLE` (409),
  `REPARTIDOR_FUERA_DE_ZONA` (409), `REPARTIDOR_AL_MAXIMO` (409),
  `ERROR_INTERNO` (500).
  Para un error de negocio: `throw new ErrorApi('PEDIDO_NO_EDITABLE', 'El pedido ya fue entregado.', 409);`
- **Validación**: con Form Requests (o `$request->validate()` para filtros simples). Nunca validar a mano.
  Los mensajes salen en español de `lang/es/validation.php` (el idioma está fijo en `config/app.php`);
  si usas una regla o un campo que no está ahí, agrega su mensaje o su nombre legible en `attributes`.
- **CORS**: solo `http://localhost:4200` (web) y `http://localhost:8100` (móvil), en `config/cors.php`.

### Cómo agregar una funcionalidad al backend

Ejemplo: "crear pedidos" en el módulo Pedidos. El código de referencia es `app/Modules/Pedidos`.

1. **Modelo de datos**: si hace falta una tabla o columna, actualiza el DBML, crea una migración nueva
   (`php artisan make:migration ...`) y el modelo en `app/Modules/<Modulo>/Models` con `$table`, `#[Fillable]`,
   `casts()` y relaciones. Si el modelo se usa en pruebas, crea su factory en `database/factories`
   y enlázala con `#[UseFactory(...)]`.
2. **Request**: `app/Modules/<Modulo>/Requests/CrearPedidoRequest.php` con las reglas de validación.
3. **Controlador**: `app/Modules/<Modulo>/Controllers`. Métodos cortos; filtra con `visiblesPara()`;
   devuelve un Resource. Carga las relaciones con `with()` y solo con las columnas que usa el Resource
   (`->with(['comercio:id,nombre', 'zona:id,nombre'])`): fuera de producción una relación sin cargar lanza
   error (`Model::preventLazyLoading`, en `AppServiceProvider`), así no se cuelan consultas N+1.
4. **Resource**: `app/Modules/<Modulo>/Resources`. Solo los campos que el cliente necesita; relaciones con `whenLoaded`.
5. **Ruta**: en `app/Modules/<Modulo>/routes.php`, con su middleware `rol:...`.
6. **Pruebas** en `tests/Feature`: caso feliz, validación, 401 sin token, 403 con rol no permitido
   y que un comercio/repartidor no vea ni modifique datos ajenos.
7. `vendor/bin/pint --dirty` y `php artisan test`.

Todo cambio de estado de un pedido debe insertar una fila en `historial_pedido` (solo se inserta, nunca se edita).
El canal del evento sale de `Canal::deLaPeticion($request)`: la app móvil manda el encabezado `X-Canal: movil`;
sin encabezado es `web`. En un evento `edicion` el `detalle` es un JSON con los campos cambiados
(`HistorialPedido::cambios()`); en los demás eventos es texto libre.
El número del pedido (`PED-0021`: continúa después del más alto que exista) lo genera `Pedido::siguienteNumero()`; ver `PedidoController::store`.

## Web (`web/src/app`)

```
core/        api.service.ts       única puerta a la API (get/post/put/delete)
             auth.service.ts      sesión: token y usuario en localStorage, como signals
             auth.interceptor.ts  agrega el token; 401 → login con "Tu sesión venció"; 403 → "Acceso denegado";
                                  sin respuesta → franja "Sin conexión"
             guards.ts            authGuard, invitadoGuard, rolGuard
             menu.ts              opciones del menú y roles de cada una
             modelos.ts           interfaces de lo que devuelve la API
             etiquetas.ts         textos y colores de estados (badges); mensajeDeError(), camposDeError(),
                                  mensajeDeFormulario(), codigoDeError()
             mensajes.service.ts  toasts de éxito / error / acceso denegado (INT-22)
             catalogos.service.ts zonas, comercios y tipos de incidencia en memoria para los <select>
             titulo-pagina.ts     el `title` de cada ruta → barra superior y pestaña del navegador
layout/      barra lateral (menú según el rol) + barra superior (título, correo, rol, cerrar sesión)
shared/      componentes reutilizables: ver "Guía de interfaz"
features/    login, panel, pedidos, clientes, incidencias, administracion (usuarios, parametrizacion),
             en-construccion
estilos/     _variables.scss (tema: variables de Bootstrap) y _base.scss (lo que Bootstrap no expone)
```

### Cómo agregar una página a la web

1. Abre su mockup en `docs/interfaces/mockups/INT-xx-*.png` y sus notas y ajustes en `docs/interfaces/README.md`.
2. Agrega las interfaces en `core/modelos.ts` (deben coincidir con el Resource del backend).
3. Crea el componente en `features/<modulo>/` (standalone, con signals `datos`, `cargando`, `error`).
   Copia la forma de `features/clientes/clientes.ts` y arma la pantalla con los componentes de `shared/`
   (ver "Guía de interfaz"). Usa `ApiService`, nunca `HttpClient` directo, y `mensajeDeError()` para los errores.
4. Agrega la opción en `core/menu.ts` con sus roles (si la opción ya existe y apunta a "En construcción",
   cambia su `loadComponent` en `app.routes.ts`).
5. Agrega la ruta en `app.routes.ts` con `title` (el título del mockup, sale en la barra superior),
   `canActivate: [rolGuard]` y `data: { roles: rolesDe('/ruta') }`.
6. `npx ng build` y compara la pantalla con el mockup.

Estilos con clases de Bootstrap y las variables del tema; no agregar librerías de componentes.

## Guía de interfaz

**Dónde están los mockups**: `docs/interfaces/mockups/` (una imagen por pantalla, de `INT-01-login.png` a
`INT-22-mensajes.png`, más la navegación `00a`/`00b`). `docs/interfaces/README.md` tiene la paleta, los colores
de los badges, el menú por rol, las notas de cada pantalla y la tabla **"Ajustes al modelo"**.
Los círculos rojos numerados son anotaciones del documento: **no** van en la app.

**Regla**: cada pantalla nueva sigue su mockup INT-xx (colores, distribución, textos, botones y mensajes) y
aplica los "Ajustes al modelo" del README. Si el mockup choca con el modelo de 12 tablas, manda el modelo
(por ejemplo: un cliente tiene una sola dirección; una sola zona por repartidor; sin "creado por" en usuarios).
Antes de dar la pantalla por terminada, compárala con su mockup.

**Tema**: los colores, la fuente (Inter) y los radios están en `web/src/estilos/_variables.scss` como variables
de Bootstrap. No escribas colores a mano ni CSS encima de Bootstrap: usa sus clases (`btn-primary`,
`btn-outline-secondary`, `text-body-secondary`, `bg-primary-subtle`…) e íconos de Bootstrap Icons (`bi-…`).

**Qué componente usar** (`web/src/app/shared/`; cada archivo explica su uso al inicio):

| Necesito… | Usa |
| --- | --- |
| Estado de pedido, prioridad, estado de incidencia o activo/inactivo | `<app-badge-estado tipo="…" [valor]="…" />` con `tipo` = `pedido` (por defecto), `prioridad`, `incidencia` o `activo`. Textos y colores solo en `core/etiquetas.ts` |
| Un bloque blanco con título y botones a la derecha | `<app-tarjeta titulo="…">` + `<button acciones …>` |
| Una lista: encabezados en mayúsculas, carga, vacío, error con "Reintentar" y paginación | `<app-tabla>` (las `<tr>` las escribe la página) |
| Un campo con etiqueta, `*` y el error en rojo debajo | `<app-campo etiqueta para [obligatorio] [errores]>` + `[class.is-invalid]` en el control |
| Formulario de crear o editar | `<app-modal>`; si el mockup lo pone debajo de la tabla (INT-02), en una `<app-tarjeta>` |
| Preguntar "¿seguro?" antes de desactivar, cancelar… | `<app-confirmacion>` |
| Avisar que algo salió bien o mal | `MensajesService.exito()` / `.error()` (toasts de INT-22) |
| Una regla de negocio impidió una acción de la lista | aviso `alert alert-warning` dentro de la tarjeta (INT-02) |
| Nada que mostrar | `<app-estado-vacio>` (la tabla ya lo trae) |
| Cifra del panel / gráfico de barras | `<app-indicador>` / `<app-barras>` (muestran "Sin datos" solos) |
| Zonas, comercios o tipos de incidencia en un `<select>` | `CatalogosService` (no pidas `/zonas` en cada pantalla) |
| Página cuyo módulo aún no existe | ruta con `loadComponent: enConstruccion` y `data.modulo` |

**Mensajes**: siempre en español y sin textos técnicos. Los errores de un formulario van dentro del formulario
(debajo de cada campo y, si no son de un campo, arriba en rojo); el resultado de una acción, en un toast.
"Sin conexión", "Acceso denegado" y "Tu sesión venció" ya los muestran el interceptor y el `rolGuard`:
no los repitas en las páginas.

## Cómo hacer un CRUD completo

El módulo de referencia es **Administración**. El ejemplo más corto es **Zonas**; cópialo y cambia los nombres.
Usuarios muestra los casos menos comunes (campos que dependen de otro, regla de negocio, activar/desactivar).

| Pieza       | Archivo de ejemplo                                                        |
| ----------- | ------------------------------------------------------------------------- |
| Request     | `backend/app/Modules/Administracion/Requests/GuardarZonaRequest.php`      |
| Controlador | `backend/app/Modules/Administracion/Controllers/ZonaController.php`       |
| Resource    | `backend/app/Modules/Administracion/Resources/ZonaResource.php`           |
| Rutas       | `backend/app/Modules/Administracion/routes.php`                           |
| Pruebas     | `backend/tests/Feature/ZonasTest.php`                                     |
| Página web  | `web/src/app/features/administracion/zonas.ts` y `zonas.html`             |

### 1. Backend

Rutas de un CRUD (no se usa `Route::apiResource`; cada ruta se escribe, con `{id}` numérico):

```php
Route::middleware('rol:administrador')->group(function (): void {
    Route::get('/zonas', [ZonaController::class, 'index']);
    Route::post('/zonas', [ZonaController::class, 'store']);
    Route::put('/zonas/{id}', [ZonaController::class, 'update'])->whereNumber('id');
    Route::put('/tipos-incidencia/{id}/estado', [TipoIncidenciaController::class, 'cambiarEstado'])->whereNumber('id');
});
```

- **Un solo Request para crear y editar**: `Guardar<Cosa>Request`. La regla de único ignora el propio registro con
  `Rule::unique('usuarios', 'correo')->ignore($this->route('id'))` (al crear no hay `id` y no ignora nada).
  Para nombres que no deben distinguir mayúsculas ("Centro" = "centro") usa
  `new UnicoSinMayusculas('zonas', 'nombre', $this->route('id'), 'Ya existe una zona con ese nombre.')`
  (`app/Shared/Validacion`).
  Si un campo solo aplica al crear, agrégalo con `if ($this->isMethod('POST'))` (ver `GuardarUsuarioRequest`).
  Un campo obligatorio según otro: `required_if:rol,comercio`.
- **Controlador**: `index` con `orderBy(...)->paginate(20)`; `store` con `Modelo::query()->create($request->validated())`
  (responde 201 solo); `update` con `findOrFail($id)` + `update($request->validated())` (un id inexistente da 404 solo).
  Siempre `$request->validated()`, nunca `$request->all()`. Lo que el cliente no debe decidir (por ejemplo `activo`
  al crear) lo pone el controlador, no el Request.
- **Activar / desactivar** no es un `DELETE`: es `PUT /<cosas>/{id}/estado` con `{ "activo": true|false }`,
  validado con `CambiarActivoRequest`. No se borran registros que otras tablas referencian.
- **Reglas de negocio** (lo que no es validar un campo) van en el controlador con `ErrorApi` y código propio:
  `throw new ErrorApi('NO_PUEDES_DESACTIVARTE', 'No puedes desactivar tu propia cuenta: …', 409);`
  Agrega el código nuevo a la lista de "Reglas de la API".
- **Catálogo para un `<select>`**: la lista acepta `?por_pagina=` (1 a 100) y la web la pide con `por_pagina: 100`
  a través de `CatalogosService` (ver `ZonaController::index`). No crees otra ruta "sin paginar".
- **Catálogos ya disponibles para cualquier rol** (solo lectura): `GET /zonas` (con `?con_repartidores=1`,
  administrador y operador reciben además los repartidores activos de cada zona), `GET /comercios` (el comercio
  solo recibe el suyo) y `GET /tipos-incidencia` (con `?activos=1` trae solo los activos: úsalo en los
  formularios). Si la lectura y la escritura tienen roles distintos, van en dos grupos de rutas
  (ver `Administracion/routes.php`) y las pruebas de 403 cubren solo las rutas de escritura.
- Si el CRUD depende de pedidos, filtra con `visiblesPara()` y recuerda el `historial_pedido`.

### 2. Pruebas (un archivo por recurso en `tests/Feature`)

Lista mínima, la misma de `ZonasTest.php`:

1. Listar: estructura (`data`, `meta`) y orden.
2. Crear: caso feliz (201) y que quedó en la base.
3. Validación: obligatorio, límites y **único**, comprobando `error.codigo = VALIDACION` y el mensaje en
   `error.campos.<campo>.0`.
4. Editar: caso feliz, conservar su propio valor único, chocar con el de otro registro y 404 si no existe.
5. Cada regla de negocio: su código de error y que el dato **no cambió**.
6. Permisos con un `dataset` de `[método, ruta]`: 401 sin token y 403 con cada rol no permitido, comprobando
   además que no se guardó nada.
7. Si aplica: que un comercio/repartidor no ve ni modifica datos ajenos (404).

Usa factories (crea la del modelo si no existe) y `Sanctum::actingAs(Usuario::factory()->administrador()->create())`.

### 3. Web (tarjeta + tabla + formulario en modal)

1. Interfaz en `core/modelos.ts`, igual al Resource.
2. Componente con estos signals — lista: `datos`, `meta`, `cargando`, `error`;
   formulario: `modalAbierto`, `editando` (`null` = crear), `guardando`, `errorFormulario`, `errores`;
   y un objeto `formulario` normal enlazado con `[(ngModel)]`.
3. `guardar()` decide `post` o `put` según `editando()`. Si sale bien: cierra el modal, avisa con
   `mensajes.exito('Zona Centro creada correctamente.')` y recarga la lista (si es un catálogo, además
   `catalogos.invalidar('zonas')`). Si falla: `errorFormulario.set(mensajeDeFormulario(error))` y
   `errores.set(camposDeError(error))`.
4. En la plantilla, la tarjeta con la tabla, el modal y cada campo:
   ```html
   <app-tarjeta titulo="Zonas y cobertura">
     <button acciones type="button" class="btn btn-primary" (click)="abrirFormulario()">+ Nueva zona</button>
     <app-tabla [columnas]="columnas" [cargando]="cargando()" [error]="error()" [vacia]="datos().length === 0"
                textoVacio="Todavía no hay zonas." [meta]="meta()" (cambiarPagina)="cargar($event)" (reintentar)="cargar(1)">
       @for (zona of datos(); track zona.id) {
         <tr><td>{{ zona.nombre }}</td> ... </tr>
       }
     </app-tabla>
   </app-tarjeta>

   @if (modalAbierto()) {
     <app-modal [titulo]="editando() ? 'Editar zona' : 'Nueva zona'" (cerrar)="cerrarFormulario()">
       <form (ngSubmit)="guardar()">
         <app-campo etiqueta="Nombre" para="zona-nombre" [obligatorio]="true" [errores]="errores()['nombre']">
           <input id="zona-nombre" name="nombre" class="form-control" [class.is-invalid]="errores()['nombre']"
                  [(ngModel)]="formulario.nombre" />
         </app-campo>
       </form>
     </app-modal>
   }
   ```
   La clave de `errores()` es el nombre del campo en la API (`comercio_id`, no `comercio`).
5. La validación de verdad es la del backend: en la web solo ayudas (`required`, `maxlength`), nunca reglas de negocio.
6. Menú y ruta como en "Cómo agregar una página a la web". Si el mockup junta varios bloques en una página
   (como Parametrización, INT-03), cada bloque es su propio componente y la página los compone
   (ver `features/administracion/parametrizacion.ts`).

### 4. Antes de terminar

`vendor/bin/pint --dirty`, `php artisan test`, `npx ng build`, y probar a mano crear, editar y un error de validación.

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

---

# Parte 2 — Guías de Laravel Boost (backend, texto completo)

### Sección: foundation rules

## Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

### Sección: boost rules

## Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

### Sección: php rules

## PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

### Sección: deployments rules

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

### Sección: laravel/core rules

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Sección: pint/core rules

## Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

### Sección: pest/core rules

## Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

