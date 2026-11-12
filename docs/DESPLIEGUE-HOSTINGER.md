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

**Migraciones:** esta entrega no agrega migraciones respecto de `0e0728c`. Un servidor en una versión anterior puede tener pendientes las de noviembre de 2026; `php artisan migrate:status` lo indica. Los bancos de San Marcos y UNFV se cargan con sus seeders (paso 5), que son idempotentes.

## Cómo está montado el servidor (comprobado el 12/11/2026)

```
~/domains/universidades-simulador-examen.roolvex.com/
├── laravel-app/   ← repositorio Git (rama deploy/hostinger): app, vendor, .env, storage
└── public_html/   ← COPIA de laravel-app/public/ (no es un enlace)
    ├── index.php  ← propio del servidor: apunta a ../laravel-app (NO sobrescribir)
    └── storage → laravel-app/storage/app/public (enlace simbólico)
```

- **SSH:** `ssh -p 65002 u167778766@31.170.166.52` (llave SSH registrada en hPanel → Acceso SSH).
- **PHP CLI 8.3**, Composer 2.9. El hosting **bloquea `proc_open`**: `composer install` muestra un error en el script `package:discover`. Las dependencias sí se instalan; después ejecutar a mano `php artisan package:discover` y `composer dump-autoload --no-dev --optimize --no-scripts`.
- **`git pull` no actualiza `public_html`**: hay que copiar `public/` (paso 6).
- **Cron:** el hosting no permite `crontab` por SSH; las tareas se crean en hPanel → Avanzado → Cron Jobs (ver "Tarea programada").

## Pasos en el servidor (SSH, desde `laravel-app/`)

```bash
cd ~/domains/universidades-simulador-examen.roolvex.com/laravel-app

# 1. Respaldo previo (base + código + public_html) en ~/respaldos-cni/FECHA/
R=~/respaldos-cni/$(date +%Y%m%d-%H%M%S); mkdir -p "$R"
mysqldump -u USUARIO -p NOMBRE_BASE | gzip > "$R/base.sql.gz"
tar -czf "$R/laravel-app.tar.gz" --exclude=./vendor -C . .
tar -czf "$R/public_html.tar.gz" -C ../public_html .

# 2. Mantenimiento y código
php artisan down --retry=60
git fetch origin
git status                       # debe estar limpio; si no, revisar antes de seguir
git pull --ff-only origin deploy/hostinger

# 3. Dependencias (ver nota de proc_open arriba)
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan package:discover
composer dump-autoload --no-dev --optimize --no-scripts

# 4. Base de datos (nunca migrate:fresh)
php artisan migrate:status
php artisan migrate --force

# 5. Bancos de preguntas (idempotentes: omiten las que ya existen)
php artisan db:seed --class=BancoUnmsmSeeder --force
php artisan db:seed --class=UnfvPreguntasSeeder --force

# 6. Archivos públicos → public_html (sin tocar index.php, .htaccess ni storage; no borra nada)
rsync -a --checksum --exclude=/index.php --exclude=/storage --exclude=/.htaccess public/ ../public_html/

# 7. Cachés y salida del mantenimiento
php artisan optimize:clear
php artisan optimize
php artisan view:cache
php artisan up
```

Antes del paso 6 se puede ver qué copiará con `rsync -a --dry-run --itemize-changes --checksum …` (mismas exclusiones).

## Tarea programada (cron)

Laravel cierra cada minuto los exámenes vencidos (`intentos:cerrar-vencidos`). Sin cron, un examen vencido se cierra y califica cuando alguien vuelve a abrirlo. Crear en hPanel → Avanzado → Cron Jobs, cada minuto:

```
cd /home/u167778766/domains/universidades-simulador-examen.roolvex.com/laravel-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Comprobar con `php artisan schedule:list`.

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

## Registro de despliegues

| Fecha | Versión | Notas |
|---|---|---|
| 12/11/2026 | `b82dbcc` (desde `c4b12e8`) | 3 migraciones (columnas `area_unmsm` y `puntaje_actitudinal`, `codigo` de preguntas a 80). Bancos San Marcos (390) y UNFV (150, 153 imágenes). 37 archivos copiados a `public_html`. Verificado: `/up`, login, CSS e imágenes en 200 y 0 errores en el log. Respaldo: `~/respaldos-cni/20261112-073414/`. Estado anterior del servidor en `git stash`. Cron pendiente de crear |

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
