# Despliegue

Tres servicios gratuitos:

| Pieza | Servicio | Carpeta raíz | Build | Publica |
| --- | --- | --- | --- | --- |
| API (Laravel) | Render (Docker, plan Free) | `backend` | `backend/Dockerfile` | contenedor en `$PORT` |
| Web (Angular) | Vercel, proyecto `web` | `web` | `npx ng build` | `dist/web/browser` |
| Móvil (Ionic, como PWA) | Vercel, proyecto `movil` | `movil` | `npx ng build` | `www` |
| Base de datos | Neon, rama `production` | — | — | — |

Orden: **Neon → Render → Vercel (web y movil) → volver a Render para poner `FRONTEND_URLS`**.

> La base de desarrollo (rama `main` de Neon) es compartida: no se toca. Producción usa su propia rama.
> El contenedor solo ejecuta `php artisan migrate --force`: nunca `fresh`, `reset`, `refresh`, `rollback` ni `wipe`.

---

## 1. Neon (rama `production`)

1. En el proyecto de Neon: **Branches → Create branch**.
   - Nombre: `production`. Rama de origen (*parent*): `main`.
   - Si se crea desde el estado actual de `main`, la rama ya trae las tablas y los datos de prueba. Si se prefiere vacía,
     elegir la opción de rama sin datos (*schema only* / *empty*) cuando Neon la ofrezca.
2. En la rama `production`: **Connect** → base `neondb`, rol `neondb_owner`.
   - Desactivar **Connection pooling** (cadena *directa*, sin `-pooler` en el host): las migraciones y las sentencias
     preparadas de PDO funcionan mejor así.
   - Copiar la cadena completa, por ejemplo
     `postgresql://neondb_owner:<contraseña>@ep-xxxx.us-east-2.aws.neon.tech/neondb?sslmode=require`.
     Esa cadena es el valor de `DB_URL` en Render (es secreta: solo en Render, nunca en Git).

## 2. Render (API)

### Opción A: Blueprint (usa `render.yaml` de la raíz del repositorio)

1. **New → Blueprint** → conectar el repositorio de GitHub → rama a desplegar (p. ej. `main`).
2. Render lee `render.yaml` y crea el servicio `logistica-api` (Docker, plan Free, carpeta `backend`, health check `/up`).
3. Pide los valores marcados `sync: false` (ver tabla). Completar y **Apply**.

### Opción B: a mano

**New → Web Service** → repositorio y rama, y:

| Campo | Valor |
| --- | --- |
| Name | `logistica-api` (da la URL `https://logistica-api.onrender.com`) |
| Language / Runtime | Docker |
| Root Directory | `backend` |
| Dockerfile Path | `./Dockerfile` (relativo a `backend`) |
| Docker Build Context | `.` |
| Instance Type | Free |
| Health Check Path | `/up` (en *Advanced*) |

No hay comando de build ni de arranque: los define el `Dockerfile` (`composer install --no-dev` optimizado) y
`docker/iniciar.sh` (`config:cache`, `storage:link`, `migrate --force`, seeders si `SEMBRAR_DEMO=true`, php-fpm + nginx).

### Variables de entorno (Render)

| Variable | Valor | Notas |
| --- | --- | --- |
| `APP_NAME` | `Logistica Ultima Milla` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_KEY` | *(secreto)* | Generarla en local: `cd backend && php artisan key:generate --show` y pegar el resultado completo (`base64:...`). |
| `APP_URL` | `https://logistica-api.onrender.com` | La URL real que muestre Render. Se usa en los enlaces de evidencias. |
| `LOG_CHANNEL` | `stderr` | Los logs salen en la pestaña *Logs* de Render. |
| `LOG_LEVEL` | `info` | |
| `DB_CONNECTION` | `pgsql` | |
| `DB_URL` | *(secreto)* | Cadena directa de la rama `production` de Neon (paso 1). |
| `DB_SSLMODE` | `require` | |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `sync` | La app no usa colas; así no hace falta un *worker*. |
| `FRONTEND_URLS` | `https://<web>.vercel.app,https://<movil>.vercel.app` | Orígenes permitidos (CORS y Sanctum), separados por comas, con `https://` y sin `/` final. Se completa en el paso 4. |
| `SEMBRAR_DEMO` | `false` | `true` solo en un despliegue para cargar los datos ficticios (ver abajo). |
| `IA_PROVEEDOR` | `gemini` o `groq` | |
| `IA_MODELO` | *(vacío)* | Vacío = modelo por defecto del proveedor. |
| `IA_API_KEY` | *(secreto)* | Clave gratuita del proveedor de IA. |

`PORT` lo pone Render; no se define.

### Datos de demostración

Si la rama `production` quedó vacía: poner `SEMBRAR_DEMO=true`, desplegar (**Manual Deploy → Deploy latest commit**),
revisar en *Logs* que terminó el `db:seed`, y volver a `SEMBRAR_DEMO=false`. Corre `DatabaseSeeder`, `DemoPedidosSeeder`
y `DemoOperacionSeeder`; los tres son idempotentes, así que repetirlo no duplica datos. Usuarios de prueba: `README.md`.

### Comprobar

- `https://logistica-api.onrender.com/up` → página "Application up".
- `POST https://logistica-api.onrender.com/api/login` con `correo` y `password` → `{ token, usuario }`.

### Límites del plan Free

- El servicio se duerme tras ~15 min sin tráfico; la primera petición después tarda cerca de un minuto.
- El disco del contenedor no es persistente: las fotos de evidencias e incidencias (`storage/app/public`) se pierden en
  cada redespliegue o reinicio. Los registros en la base sí se conservan.

## 3. Vercel (dos proyectos)

La URL de la API está compilada en `web/src/environments/environment.prod.ts` y
`movil/src/environments/environment.prod.ts` (`https://logistica-api.onrender.com/api`). Si Render asignó otra URL,
cambiarla en **los dos** archivos y hacer commit antes de desplegar. Vercel no necesita variables de entorno.

Cada carpeta tiene su `vercel.json` (instalación, build, carpeta de salida y la reescritura de todas las rutas a
`index.html`, para que funcione recargar en `/pedidos/12`).

### Proyecto `web`

**Add New → Project** → importar el repositorio:

| Campo | Valor |
| --- | --- |
| Project Name | p. ej. `logistica-web` |
| Root Directory | `web` |
| Framework Preset | Other (lo demás lo toma de `web/vercel.json`) |
| Install Command | `npm ci` |
| Build Command | `npx ng build` |
| Output Directory | `dist/web/browser` |
| Node.js Version (Settings → General) | 22.x o superior |

### Proyecto `movil`

Otro **Add New → Project** con el mismo repositorio:

| Campo | Valor |
| --- | --- |
| Project Name | p. ej. `logistica-movil` |
| Root Directory | `movil` |
| Framework Preset | Other (lo demás lo toma de `movil/vercel.json`) |
| Install Command | `npm ci` |
| Build Command | `npx ng build` (equivale a `ionic build`) |
| Output Directory | `www` |
| Node.js Version | 22.x o superior |

La app del repartidor se abre desde el navegador del celular. Vercel sirve por HTTPS, que el navegador exige para usar
la cámara y la ubicación; `@ionic/pwa-elements` muestra la interfaz de la cámara en el navegador.

## 4. Cerrar el ciclo

1. Copiar las dos URL de producción de Vercel (p. ej. `https://logistica-web.vercel.app` y `https://logistica-movil.vercel.app`).
2. En Render → **Environment** → `FRONTEND_URLS=https://logistica-web.vercel.app,https://logistica-movil.vercel.app` → **Save**
   (Render redespliega solo; el arranque vuelve a correr `config:cache`).
3. Entrar a la web y al móvil con un usuario de prueba. Si el navegador muestra un error de CORS, revisar que
   `FRONTEND_URLS` tenga exactamente esas URL, con `https://` y sin `/` al final.

> Las URL de *preview* de Vercel (una por rama o commit) no están en `FRONTEND_URLS`, así que no pueden llamar a la API;
> usar la URL de producción de cada proyecto.

## Probar la imagen en local (opcional)

Con Docker Desktop encendido, desde `backend/` y **sin** apuntar a Neon (base SQLite descartable):

```sh
docker build -t logistica-api .
docker run --rm -p 8080:8080 -e APP_KEY=base64:... -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=/tmp/prueba.sqlite -e SEMBRAR_DEMO=true logistica-api
```

Luego abrir `http://localhost:8080/up`. (`migrate --force` crea el archivo SQLite si no existe.)
