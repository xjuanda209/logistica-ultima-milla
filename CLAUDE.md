# Plataforma de Logística de Última Milla — guía corta

Proyecto universitario (UTP), 5 personas en paralelo: **copia los patrones existentes**. Datos ficticios. Detalle y ejemplos de todo lo de aquí: `docs/guia-detallada.md` (léela solo si este resumen no alcanza).

| Carpeta | Qué es | Tecnología |
| --- | --- | --- |
| `backend/` | API REST | Laravel 13, Sanctum, PHP 8.5, PostgreSQL (Neon) |
| `web/` | Web (todos los roles) | Angular 21 + Bootstrap 5 |
| `movil/` | App del repartidor | Ionic 9 + Angular 22 (standalone) + Capacitor |
| `docs/` | `modelo_relacional.dbml` (12 tablas, fuente de verdad) e `interfaces/` (mockups INT-xx + `README.md`) | |

Roles: `administrador`, `operador`, `comercio`, `repartidor`. Usuarios de prueba e instalación: `README.md`.
Levantar: backend `php artisan serve` (:8000) · web `npm start` (:4200) · móvil `ionic serve` (:8100).

## Reglas obligatorias

1. **Neon es compartida. NUNCA** `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback`, `db:wipe` ni nada que borre tablas o datos.
2. `migrate --seed` ya se ejecutó y no se repite. Una migración nueva se aplica solo con `php artisan migrate` y **con confirmación de la persona** (igual los seeders).
3. **Nunca edites una migración ya ejecutada**: migración nueva, y antes se actualiza `docs/modelo_relacional.dbml`.
4. Antes de correr pruebas, verifica en `backend/phpunit.xml`: `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`.
5. No cambies versiones de Laravel, Angular, Ionic ni PHP; no `npm audit fix --force`; no agregues dependencias sin aprobación.
6. Secretos solo en `backend/.env`. Única excepción: la contraseña ficticia `password` de los usuarios de prueba.
7. No hagas `git push` sin que la persona lo pida.
8. Antes de terminar: `php artisan test` y `vendor/bin/pint --dirty` (backend), `npx ng build` (web), `ionic build` y `npm run eslint` (móvil).

## Convenciones

- **Todo en español** (tablas, columnas, clases, rutas, mensajes, pruebas). Tablas en plural; llaves `<singular>_id` (`repartidor_id` / `creado_por` hacia `usuarios`). Modelo `Usuario`, tabla `usuarios`.
- Enums: columna `string` + enum PHP en `app/Shared/Enums` + cast. Sin ENUM de PostgreSQL (las pruebas van en SQLite).
- La API guarda y devuelve fechas en UTC; web y móvil las muestran en hora local.

## Backend (`backend/app`)

```
Shared/   Enums/, Http/Middleware/CheckRole.php (alias "rol"), Errores/ (ManejadorErrores, ErrorApi),
          Validacion/ (UnicoSinMayusculas), Auth/ (login, logout, me)
Modules/  Administracion (Usuario, Zona, Comercio, Configuracion) · Pedidos (Cliente, Pedido, LineaPedido,
          HistorialPedido) · Despacho (Evidencia, panel) · Incidencias (TipoIncidencia, Incidencia) · Asistente
          Cada módulo: Models/, Controllers/, Resources/, Requests/ y routes.php (lo carga routes/api.php)
          Fuera de app/: database/migrations|factories|seeders y tests/Feature (Pest)
```
- **Auth**: `POST /api/login` (`correo`, `password`) → `{ token, usuario }`; el resto con `Bearer` dentro del grupo `auth:sanctum`.
- **Roles**: cada ruta con `->middleware('rol:administrador,operador')`.
- **Visibilidad**: solo en `Pedido::scopeVisiblesPara()` y `Cliente::scopeVisiblesPara()`; no repitas el filtro. Registro ajeno = 404. Al comercio se le fuerza su `comercio_id` en `prepareForValidation`.
- **Respuestas**: API Resources. Uno: `{ "data": {...} }`; lista: `paginate(20)` → `{ "data": [...], "meta": {...} }`.
- **Errores** (los arma `ManejadorErrores`, no los controladores):
  `{ "error": { "codigo": "VALIDACION", "mensaje": "Los datos enviados no son válidos.", "campos": { "correo": ["..."] } } }`
  Códigos: `VALIDACION` 422, `NO_AUTENTICADO` 401, `NO_AUTORIZADO` 403, `NO_ENCONTRADO` 404, `CREDENCIALES_INVALIDAS` 401, `ERROR_INTERNO` 500 y, con 409, `NO_PUEDES_DESACTIVARTE`, `NO_PUEDES_CAMBIAR_TU_ROL`, `ULTIMO_ADMINISTRADOR`, `COMERCIO_INACTIVO`, `PEDIDO_NO_EDITABLE`, `TRANSICION_NO_PERMITIDA`, `MISMO_REPARTIDOR`, `REPARTIDOR_DESACTIVADO`, `REPARTIDOR_NO_DISPONIBLE`, `REPARTIDOR_FUERA_DE_ZONA`, `REPARTIDOR_AL_MAXIMO`, `FALTA_EVIDENCIA`, `FALTA_INCIDENCIA`, `PEDIDO_NO_ADMITE_EVIDENCIAS`, `PEDIDO_NO_ADMITE_INCIDENCIAS`.
  Regla de negocio: `throw new ErrorApi('PEDIDO_NO_EDITABLE', 'El pedido ya fue entregado.', 409);` y agrega aquí el código nuevo.
- **Validación**: Form Requests; mensajes en `lang/es/validation.php` (agrega ahí reglas y `attributes` nuevos).
- **Relaciones**: `with()` solo con las columnas del Resource; una relación sin cargar lanza error (`preventLazyLoading`).
- **Estados e historial**: todo cambio de estado pasa por `Pedido::cambiarEstado()` (transiciones solo en `EstadoPedido::siguientes()`), que inserta en `historial_pedido` (nunca se edita); quién puede recibir un pedido, solo en `Usuario::motivoParaNoRecibir()`. Canal: `Canal::deLaPeticion($request)` (`X-Canal: movil`; sin encabezado, `web`). En `edicion` el detalle es JSON (`HistorialPedido::cambios()`). Número de pedido: `Pedido::siguienteNumero()`.
- **CORS**: solo `localhost:4200` y `localhost:8100`.

## Cómo hacer un CRUD (referencia: Zonas, en Administración; Usuarios para casos menos comunes)

Copia: `GuardarZonaRequest.php`, `ZonaController.php`, `ZonaResource.php`, `Administracion/routes.php`, `tests/Feature/ZonasTest.php`, `web/.../administracion/zonas.ts|html`.

1. **Datos**: DBML → migración nueva → modelo (`$table`, `#[Fillable]`, `casts()`, relaciones) → factory con `#[UseFactory]`.
2. **Rutas** escritas una a una (sin `apiResource`), `{id}` con `whereNumber`. Lectura y escritura con roles distintos = dos grupos.
3. **Request** único `Guardar<Cosa>Request`: único con `Rule::unique(...)->ignore($this->route('id'))` o `UnicoSinMayusculas`; campos solo al crear con `if ($this->isMethod('POST'))`.
4. **Controlador** corto: `index` con `orderBy()->paginate(20)` (acepta `?por_pagina=` 1–100 para catálogos); `store` con `create($request->validated())`; `update` con `findOrFail($id)`. Nunca `$request->all()`.
5. **Activar/desactivar**: `PUT /<cosas>/{id}/estado` con `{ "activo": bool }` (`CambiarActivoRequest`). No se borran registros.
6. **Pruebas**: listar (estructura y orden), crear, validación (`error.codigo` y `error.campos.<campo>.0`), editar (+404), cada regla de negocio (código y que el dato no cambió), permisos con `dataset` (401 y 403 por rol), datos ajenos (404).
7. **Web**: interfaz en `core/modelos.ts` igual al Resource; componente standalone con signals `datos`, `meta`, `cargando`, `error` y, para el formulario, `modalAbierto`, `editando`, `guardando`, `errorFormulario`, `errores`; `guardar()` hace `post`/`put`, luego `mensajes.exito()` y recarga (`catalogos.invalidar()` si es catálogo); si falla, `mensajeDeFormulario(error)` y `camposDeError(error)`. Opción en `core/menu.ts` y ruta en `app.routes.ts` con `title`, `rolGuard` y `data: { roles: rolesDe('/ruta') }`.

## Web (`web/src/app`) y guía de interfaz

`core/` (api.service = única puerta a la API, auth, interceptor, guards, menu, modelos, etiquetas, mensajes, catalogos, titulo-pagina) · `layout/` · `shared/` · `features/` (login, panel, pedidos, clientes, incidencias, administracion, en-construccion) · `estilos/_variables.scss`.

- **Mockups**: `docs/interfaces/mockups/INT-xx-*.png`; notas, paleta y **"Ajustes al modelo"** en `docs/interfaces/README.md`. Cada pantalla sigue su mockup; si choca con el modelo de 12 tablas, manda el modelo. Los círculos rojos numerados son anotaciones: no van en la app.
- **Tema**: clases de Bootstrap y variables de `_variables.scss`; sin colores a mano ni librerías de componentes; íconos `bi-…`. Botones principales `#0B6E6E`; el naranja `#F2A33A` solo en el logo y la franja del menú activo.
- **Componentes de `shared/`** (cada archivo explica su uso al inicio):
  `<app-badge-estado tipo="pedido|prioridad|incidencia|activo" [valor]>` (textos y colores solo en `core/etiquetas.ts`) · `<app-tarjeta titulo>` + `<button acciones>` · `<app-tabla>` (carga, vacío, error, paginación; las `<tr>` las pone la página) · `<app-campo etiqueta para [obligatorio] [errores]>` + `[class.is-invalid]` · `<app-modal>` · `<app-confirmacion>` · `<app-estado-vacio>` · `<app-indicador>` / `<app-barras>` · `CatalogosService` para zonas, comercios y tipos de incidencia en `<select>` · ruta `enConstruccion` con `data.modulo`.
- **Mensajes** en español, sin textos técnicos: errores del formulario dentro del formulario; resultado de una acción en toast (`MensajesService`); regla de negocio que impide una acción de la lista: `alert alert-warning` en la tarjeta. "Sin conexión", "Acceso denegado" y "Tu sesión venció" ya los muestra el interceptor: no los repitas.
- Usa `ApiService` (nunca `HttpClient`) y `mensajeDeError()`. La validación real es la del backend.

## Móvil (`movil/src/app`)

Mismo `core/` que la web. Pantallas: `login/`, `tabs/`, `pedidos/`, `perfil/`. Página nueva: `*.page.ts`, clase `...Page`, componentes de `@ionic/angular`, datos en `ionViewWillEnter` (copia `pedidos/pedidos.page.ts`); regístrala en `app.routes.ts` (y en `tabs/tabs.page.ts` si es pestaña).
