# Pendientes del proyecto

Estado al cierre del plan (`docs/PLAN.md`, partes 0 a E): los cinco módulos están programados y la verificación
final pasa (`php artisan test`: 518 pruebas; `npx ng build`; `ionic build`; `npm run eslint`).
Lo que sigue es lo que **falta probar a mano, ajustar o mejorar**. Cada integrante revisa su módulo (sección 4),
corrige lo que encuentre y actualiza este archivo.

> Importante: las pantallas se programaron siguiendo los mockups, pero **no se compararon una por una en el
> navegador ni en un teléfono** en las partes A a D. Esa comparación es la primera tarea de cada módulo.

## 1. Errores conocidos

| # | Dónde | Qué pasa | Qué hacer |
|---|---|---|---|
| 1 | Web, `ng build` | Aviso: el paquete inicial pesa 600,66 kB y el presupuesto de aviso es 600 kB (se pasa por 661 bytes). No es un error y la app funciona. | Subir el aviso en `web/angular.json` (`budgets` → `maximumWarning`) o aligerar lo que se carga al inicio. |
| 2 | Backend, fotos | PHP trae `upload_max_filesize = 2M`. Una foto de 2 a 5 MB (permitida por la API) falla antes de llegar a Laravel y el mensaje no explica el motivo. | Cada quien sube el valor a `5M` en su `php.ini` (está en el README). Falta un mensaje claro cuando PHP rechaza el archivo. |
| 3 | Backend, fotos | Evidencias y fotos de incidencias se guardan en el disco local (`storage/app/public`). Cada computador tiene las suyas: una foto subida por un compañero se ve rota en el tuyo, aunque el registro exista en Neon. | Aceptarlo para la demo (usar un solo computador como servidor) o pasar a un almacenamiento compartido. |
| 4 | Asistente | En Windows, sin el archivo de certificados, la llamada a la IA falla con `cURL error 60` y la app solo dice "El asistente no está disponible". | Seguir el paso del README (`cacert.pem`). El motivo real queda en `backend/storage/logs/laravel.log`. |
| 5 | Asistente | Con el plan gratuito, Gemini puede tardar más de 30 s o responder 429 (límite de peticiones): se muestra "El asistente no está disponible". | Reintentar, o cambiar a Groq con `IA_PROVEEDOR=groq`. |
| 6 | Móvil | Solo se ha ejecutado en el navegador (`ionic serve`). Cámara, galería y ubicación no se han probado en un teléfono real ni se generó el proyecto Android/iOS. | Probar en un dispositivo; en un teléfono hay que cambiar `apiUrl` y agregar el origen al CORS del backend. |
| 7 | Datos de prueba | El README ya no dice cuántos pedidos ve cada usuario: los seeders de demostración y las pruebas manuales cambian las cantidades en la base compartida. | Ninguna; no confiar en cantidades fijas al probar. |

Corregido en el cierre: el controlador del asistente fijaba un límite de 45 s de ejecución que, al correr las
pruebas, cortaba la suite completa en computadores lentos ("Maximum execution time of 45 seconds exceeded").
Ahora ese límite solo se aplica a las peticiones web.

## 2. Diferencias con los mockups

**Decididas (no son errores):** las de la tabla "Ajustes al modelo" de `docs/interfaces/README.md`. Resumen:

- INT-05 / INT-07: un cliente tiene **una** dirección; no hay tabla de direcciones ni selector de dirección.
- INT-03: un repartidor tiene **una** zona; se quitan los bloques "Estados del pedido" y "Reglas de asignación".
- INT-02 / INT-08: solo fechas de creación y modificación (no se guarda quién modificó).
- INT-13: una sola nota para el comercio, editable (sin historial de notas).
- INT-09 / INT-11 / INT-21: columna "Zona" en singular. INT-02: el formulario conserva el campo nombre.

**Otras diferencias encontradas en el código:**

- **Repartidor en la web:** los mockups lo dejan solo en el móvil; en la web puede entrar y consultar Pedidos e
  Incidencias (solo lectura, solo lo suyo). Decidir si se le deja o se le bloquea el ingreso.
- **INT-11 Repartidores:** la página solo aparece en el menú del operador (como en el mockup), aunque la API
  también se la permite al administrador. Decidir si el administrador la ve.
- **INT-19 web:** la ventana para registrar una incidencia desde el detalle del pedido no permite adjuntar foto;
  en el móvil sí. La API ya acepta la foto.
- **Los círculos rojos numerados** de los mockups son anotaciones y no van en la app.

**Por revisar pantalla por pantalla** (textos, orden de columnas, espaciados, mensajes): todas las INT-xx.
Anota aquí cada diferencia que encuentres y si se corrige o se acepta.

## 3. Mejoras pendientes

1. **Buscador en el selector de cliente** (formulario de pedido, INT-07): hoy es un `<select>` con todos los
   clientes del comercio. Con muchos clientes es incómodo; cambiarlo por un campo que busque con
   `GET /api/clientes?buscar=` (nombre, cédula o teléfono).
2. **Número de pedido** (`Pedido::siguienteNumero()` y `PedidoController::conNumeroUnico()`): el consecutivo se
   calcula leyendo todos los números existentes y, si dos personas crean a la vez, se reintenta hasta 5 veces;
   al quinto choque responde `ERROR_INTERNO`. Mejorar con una secuencia o un contador con bloqueo, y devolver
   un error claro (409) si aun así no se logra.
3. **Restablecer contraseña**: solo lo hace el administrador desde Usuarios; no hay "olvidé mi contraseña" ni
   cambio de contraseña por el propio usuario.
4. **Fotos en almacenamiento compartido** (ver error 3) y aviso claro cuando el archivo supera el límite de PHP.
5. **Móvil sin conexión**: hoy muestra "Sin conexión" con Reintentar; no guarda cambios para enviarlos después.
6. **Móvil en dispositivo real**: generar el proyecto con Capacitor, permisos de cámara y ubicación, y prueba completa.
7. **Panel**: exportar indicadores (CSV o PDF) y gráfica de evolución por día.
8. **Asistente**: la conversación vive solo en la página (se pierde al recargar); en la base se guarda solo si
   cada consulta fue respondida o fallida, no el texto.
9. **Notificaciones**: nadie recibe aviso cuando se le asigna un pedido o se registra una incidencia; hay que
   recargar la lista.
10. **Tamaño del paquete inicial de la web** (ver error 1).
11. **Pruebas del frontend**: la web y el móvil solo tienen la prueba de ejemplo que trae Angular; todo lo verificado está en el backend.
12. **Despliegue**: no hay ambiente publicado; CORS solo acepta `localhost:4200` y `localhost:8100`.

## 4. Reparto de módulos entre 5 personas

Cada persona **prueba su módulo de punta a punta** con los usuarios de prueba, lo compara con sus mockups,
corrige y deja sus pruebas Pest al día. Los nombres los asigna el equipo.

| Persona | Módulo | Pantallas | Backend (`app/Modules`) y pruebas | Frontend |
|---|---|---|---|---|
| 1 | **Diseño y administración** | INT-01, 02, 03, 22, navegación 00a | `Shared/` (Auth, errores, roles) y `Administracion` · `AuthTest`, `AccesoPorRolTest`, `UsuariosTest`, `ZonasTest`, `TiposIncidenciaTest`, `ConfiguracionTest` | `web`: `core/`, `layout/`, `shared/`, `features/login`, `features/administracion` |
| 2 | **Pedidos y clientes** | INT-05, 06, 07, 08 (datos) | `Pedidos` · `ClientesTest`, `PedidosTest`, `CrearPedidoTest`, `EditarPedidoTest`, `ListaPedidosTest`, `DetallePedidoTest` | `web`: `features/pedidos`, `features/clientes` |
| 3 | **Despacho y panel** | INT-04, 08 (botones), 09, 10, 11 | `Despacho` (asignar, cancelar, repartidores, panel) · `MaquinaEstadosTest`, `AsignarPedidoTest`, `CancelarPedidoTest`, `RepartidoresTest`, `PanelTest` | `web`: `features/despacho`, `features/panel` |
| 4 | **App móvil del repartidor** | INT-15 a 21, 00b (e INT-01, 19 y 22 en móvil) | `Despacho/EntregaController` (estados y evidencias) · `EntregaRepartidorTest`, `EvidenciasEIncidenciasMovilTest` | todo `movil/` |
| 5 | **Incidencias y asistente de IA** | INT-12, 13, 14, 19 (web) | `Incidencias` y `Asistente` · `IncidenciasTest`, `AsistenteTest` | `web`: `features/incidencias`, `features/asistente` |

Mejoras sugeridas para empezar: persona 1 → 3 y 10 · persona 2 → 1 y 2 · persona 3 → 7 y la decisión de INT-11 ·
persona 4 → 5 y 6 · persona 5 → 8 y la foto de INT-19 web.

**Qué probar en cada módulo (lista mínima):**

1. Entrar con cada rol que usa el módulo y con uno que **no** debería (debe salir "Acceso denegado" o no aparecer en el menú).
2. Recorrer cada pantalla junto a su mockup: textos, columnas, badges, mensajes de error y estados vacíos.
3. Provocar cada regla de negocio (por ejemplo: asignar a un repartidor al máximo, marcar entregado sin
   evidencia, resolver una incidencia sin resolución) y confirmar el mensaje en español.
4. Con `comercio@` y `comercio2@`, confirmar que ninguno ve datos del otro.
5. Anotar en este archivo lo encontrado y, al terminar, correr la verificación del README ("Antes de subir cambios").

**Zonas compartidas (avisar al equipo antes de tocarlas):** `docs/modelo_relacional.dbml` y las migraciones,
`Pedido::cambiarEstado()` y `EstadoPedido::siguientes()`, los `scopeVisiblesPara()`, `web/src/app/core` y
`shared/`, y `lang/es/validation.php`. La base de Neon es de todos: nunca `migrate:fresh` ni parecidos.
