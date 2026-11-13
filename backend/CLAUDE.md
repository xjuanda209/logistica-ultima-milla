<laravel-boost-guidelines>
# Guías de Laravel (backend) — versión corta

Las reglas del proyecto (base de datos compartida, estructura, formato de errores, CRUD) están en `../CLAUDE.md`
y mandan sobre este archivo. El texto completo de las guías de Boost está en `../docs/guia-detallada.md` (Parte 2).
Si `php artisan boost:update` regenera este archivo, vuelve a dejar esta versión corta.

## Base

- Laravel sobre PHP 8.5. Usa las APIs de la versión instalada: `composer show --direct` (PHP) y `package.json` (JS).
- Sigue las convenciones del código existente: mira los archivos hermanos antes de crear o editar uno.
- Nombres descriptivos; reutiliza componentes existentes antes de escribir uno nuevo.
- No crees carpetas base nuevas ni cambies dependencias sin aprobación.
- No crees scripts de verificación ni uses tinker si una prueba cubre el caso. Las pruebas son lo importante.
- Crea archivos de documentación solo si la persona lo pide.
- Si existe `.ai/rules`, abre `.ai/rules/index.md` y lee las reglas que cubran los archivos a tocar.
- Si hay skills del dominio en `**/skills/**`, actívalas al trabajar en ese dominio.

## Boost (servidor MCP, si está conectado)

- `search-docs` antes de cambios que dependan de APIs o versiones del ecosistema Laravel. Consultas amplias por tema,
  sin el nombre del paquete (`['rate limiting', 'routing']`) y con `packages` para acotar.
- `database-schema` para ver tablas antes de migraciones o modelos; `database-query` solo para lecturas.
- `get-absolute-url` antes de compartir una URL; `browser-logs` para errores del navegador.

## Artisan y tinker

- Crea archivos con `php artisan make:...` (`make:class` para clases genéricas) y siempre con `--no-interaction`.
- `php artisan route:list --path=api --except-vendor` para ver rutas; `php artisan config:show app.name` para configuración.
- Tinker solo para depurar, con comillas simples: `php artisan tinker --execute 'Usuario::count();'`.
  No crees registros con tinker: usa pruebas con factories.
- **Nunca** los comandos prohibidos de `../CLAUDE.md` (`migrate:fresh`, `reset`, `refresh`, `rollback`, `db:wipe`).

## PHP

- Llaves siempre en las estructuras de control, aunque tengan una sola línea.
- Promoción de propiedades en el constructor; sin constructores vacíos.
- Tipos de retorno y de parámetros explícitos: `function esVisible(Usuario $usuario, ?string $ruta = null): bool`.
- Casos de enum en TitleCase.
- PHPDoc antes que comentarios en línea; formas de arreglo (`array{...}`) en el PHPDoc.

## Laravel

- API con Eloquent API Resources, siguiendo la convención del proyecto (sin versionado de rutas).
- Modelo nuevo: crea también su factory (y seeder si hace falta).
- En pruebas, crea modelos con factories y revisa si ya hay un estado (`->administrador()`) antes de armarlo a mano.
- Faker: sigue lo que use el archivo (`fake()` o `$this->faker`).

## Pint

- Si tocaste PHP: `vendor/bin/pint --dirty --format agent` antes de terminar. No uses `--test`.

## Pest

- Prueba nueva: `php artisan make:test --pest NombreTest` (sin el prefijo `Feature/`).
- Corre lo más angosto que cubra el cambio: `php artisan test --compact tests/Feature/ZonasTest.php` o `--filter=nombre`.
  Vuelve a correr la prueba tras cada cambio. Antes de terminar, la suite completa: `php artisan test`.
- No borres pruebas sin aprobación.
</laravel-boost-guidelines>
