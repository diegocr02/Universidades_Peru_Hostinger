# Despliegue en Hostinger — rama `deploy/hostinger`

Guía para publicar en el hosting la versión de esta rama. Subir a GitHub **no** despliega solo: hay que ejecutar estos pasos en el servidor.

## Qué trae esta entrega

| Cambio | ¿Requiere algo en el servidor? |
|---|---|
| Panel propio de **Villarreal** (área C), con la práctica UNFV, el resumen y el historial | No (solo limpiar cachés) |
| **Historial y "Mi progreso" separados por universidad** (ya no se mezclan intentos al cambiar de universidad) | No |
| Limpieza de vistas sin uso (formulario antiguo de preguntas, inicio de administración y una plantilla provisional) | No |
| Pruebas de calidad (PHPUnit, k6 y Playwright) e informe ATAM | No: son archivos de desarrollo, no se ejecutan en el hosting |
| CI de GitHub activado para `deploy/hostinger` | No |

**Migraciones nuevas: ninguna.** Seeders nuevos: ninguno. El banco UNFV usa el seeder existente `UnfvPreguntasSeeder`; si ya se importó antes, no hace falta repetirlo.

## Pasos en el servidor (SSH, desde la raíz del proyecto Laravel)

```bash
# 1. Respaldo previo de la base (recomendado antes de cualquier actualización)
mysqldump -u USUARIO -p NOMBRE_BASE > ~/respaldo-antes-de-actualizar.sql

# 2. Traer el código
git fetch origin
git status                       # debe estar limpio, sin cambios locales
git pull --ff-only origin deploy/hostinger

# 3. Dependencias de producción (sin paquetes de desarrollo)
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 4. Base de datos: comprobar que no hay migraciones pendientes (no ejecutar migrate:fresh)
php artisan migrate:status
php artisan migrate --force      # sin efecto si no hay pendientes

# 5. Banco UNFV (solo si Villarreal aún no tiene su banco importado; es idempotente)
php artisan db:seed --class=UnfvPreguntasSeeder --force

# 6. Cachés: limpiar las viejas y generar las de producción
php artisan optimize:clear
php artisan optimize
php artisan view:cache
```

Si `storage/` no está enlazado a `public/storage`, ejecutar una vez `php artisan storage:link` (las imágenes de las preguntas lo necesitan).

## `.env` del hosting (revisar, no copiar el local)

```dotenv
APP_ENV=production
APP_DEBUG=false                  # obligatorio: con true se exponen trazas de error
APP_URL=https://tu-dominio
CNI_ALUMNO_AREAS_ENABLED=true    # paneles por universidad
LOG_LEVEL=warning
```

No subir nunca `.env`, `.env.qa` ni `tests/quality/.credenciales-qa.json` (están en `.gitignore`). Si se cambia `.env`, volver a ejecutar `php artisan optimize`.

## Verificación después de desplegar

1. `https://tu-dominio/up` responde **200** (salud de Laravel).
2. El login funciona y cada rol llega a su panel.
3. Con un estudiante de **Villarreal**: se ve el panel con "Examen UNFV", 50 preguntas y el botón para comenzar.
4. Con un estudiante que cambió de universidad: el **historial muestra solo los exámenes de la universidad actual**.
5. Revisar `storage/logs/laravel.log` por errores nuevos.

## Volver atrás (si algo falla)

```bash
git log --oneline -5                       # identificar el commit anterior
git checkout <commit-anterior> -- .        # o: git reset --hard <commit-anterior> (descarta cambios locales)
composer install --no-dev --optimize-autoloader
php artisan optimize:clear && php artisan optimize
```

Esta entrega no cambia el esquema de la base, así que volver atrás no requiere tocar MariaDB.

## Lo que NO se debe hacer en el hosting

- `php artisan migrate:fresh` o `db:wipe`: borran todos los datos.
- `php artisan db:seed` sin `--class`: crea cuentas demo con contraseña conocida.
- Ejecutar k6 contra producción: la prueba de carga es solo para local o staging autorizado.
