# RSU-CNI · Módulo de contingencia y respaldo cifrado

## Alcance de la entrega (versión 1.0)

- **Exportación lógica consistente** de tablas InnoDB de MariaDB/MySQL, mediante PDO, sin depender de `mysqldump` o `exec()`.
- **Compresión GZIP** y cifrado autenticado por bloques `libsodium secretstream XChaCha20-Poly1305`, con una clave exclusiva de 32 bytes.
- **Verificación** del archivo cifrado completo y SHA-256; almacenamiento local en `storage/app/private/contingencia/`.
- **Manifiestos JSON** por copia; historial web exclusivo de superadministrador.
- **Envío a uno o dos receptores HTTPS** de otra infraestructura, autenticados por token y con confirmación SHA-256; reintentos programables.
- **Descifrado CLI**, sin restauración automática destructiva; pruebas en base vacía.
- **No modifica las tablas ni los controladores** de preguntas, estudiantes, resultados e intentos.

## LIMITACIONES que deben mencionarse durante la exposición

1. Exporta **tablas InnoDB**, estructura e INSERTs. No exporta archivos subidos, vistas, triggers, procedimientos, funciones ni eventos. El script SQL original del repositorio contiene 14 tablas, sin vistas ni triggers declarados en él. Verificar igualmente la base activa antes de producción.
2. El respaldo se exporta desde la conexión de Laravel. Si existen ALTER TABLE concurrentes, las estructuras podrían no corresponder con el snapshot; evitar mantenimiento del esquema durante el respaldo.
3. Una copia **diaria** puede implicar hasta 24 h de pérdida de cambios (RPO aproximado). Alta disponibilidad, replicación y failover automático **no están implementados**.
4. La verificación criptográfica no sustituye una **prueba de importación y uso** en un servidor alternativo.
5. El proceso utiliza espacio temporal adicional para el `.sql.gz` comprimido; una copia grande puede agotar la cuota del plan compartido. PHP web tiene límites de ejecución; en producción usar el **Cron PHP CLI** del hosting.
6. Hostinger compartido debe permitir: PHP CLI, PDO MySQL, `sodium`, `zlib`, permisos de escritura privados y Cron. Validar antes de activarlo.
7. El código proporciona el receptor para una instancia externa, **pero ese servidor externo y su HTTPS deben instalarse y configurarse**. Si falta, se generarán solo copias locales.

## 1. Instalar los archivos

Usar `INSTALAR_EN_VSCODE.ps1` (opcional si se instala desde ZIP). Al integrar desde GitHub ya se incluyen los archivos nuevos y se agrega `require __DIR__.'/contingencia.php';` al final de `routes/web.php` y añade exclusiones a `.gitignore`.

**No ejecutar `php artisan migrate`** por instalar este módulo: no tiene migraciones y no cambia el esquema original.

Probar desde la raíz del proyecto:

```bash
php artisan config:clear
php artisan route:list --path=administracion/contingencia
php artisan list | grep contingencia
```

En PowerShell, para los comandos usa `php artisan list | Select-String contingencia`.

## 2. Configurar `.env` de forma segura

Generar **localmente** la clave:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

En `.env` privado añadir:

```dotenv
CNI_BACKUP_ENABLED=true
CNI_BACKUP_PANEL_ENABLED=true
CNI_BACKUP_ADMIN_USER=Super_Admin
CNI_BACKUP_KEY=PEGA_AQUI_LA_CLAVE_BASE64_NUEVA
CNI_BACKUP_RETENTION_DAYS=14

CNI_BACKUP_ENDPOINT_A=
CNI_BACKUP_TOKEN_A=
CNI_BACKUP_ENDPOINT_B=
CNI_BACKUP_TOKEN_B=
```

**NO** subir `.env` a GitHub. Guarda una copia de la clave **fuera del servidor** (gestor de contraseñas institucional). Sin ella los respaldos son irrecuperables. La clave no se copia a los receptores externos.

Después de editar `.env`:

```bash
php artisan config:clear
```

Al desplegar una configuración cacheada, reconstruir después con `php artisan config:cache`.

## 3. Prueba manual local, sin servidores externos

```bash
php -m
php artisan contingencia:crear --solo-local
php artisan route:list --path=administracion/contingencia
```

Verificar que existan `pdo_mysql`, `sodium` y `zlib` (para `gzopen`). El comando imprimirá identificador, tablas, filas y SHA-256 del archivo. Los archivos privados quedan en:

```
storage/app/private/contingencia/
  20261112_020000_ab12cd34.cni      <-- SQL comprimido y cifrado
  20261112_020000_ab12cd34.json     <-- estado y SHA-256
```

El nombre mostrado es un ejemplo, no un respaldo real.

La UI se abre con el usuario autorizado en:

```
https://TU_DOMINIO/administracion/contingencia
```

Revisar: Crear respaldo, Verificar, Reenviar y Descargar cifrado. **No se muestra a docentes o estudiantes**. La ruta no depende de la bandera de administración académica y exige rol `admin` + usuario `Super_Admin` (modificable por env).

## 4. Preparar el receptor en otra instancia independiente

El receptor **no debe quedar en el mismo Hostinger que queremos proteger** si buscamos tolerancia a fallas. Se necesita una segunda instancia de hosting, VPS o servicio web con PHP y HTTPS.

1. Copiar `docs/contingencia/receptor-externo/receptor.php` al sitio HTTPS externo como `public_html/cni-receptor.php`.
2. Copiar `docs/contingencia/receptor-externo/backup-receptor-config.example.php` a `backup-receptor-config.php` **en la carpeta padre de `public_html`**, no dentro de la web pública.
3. Editar ese archivo privado con `token` secreto generado con `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` y `directorio` en una ruta **fuera** de `public_html`.
4. Comprobar que el webserver permite el método PUT y reenvía `Authorization` a PHP. Evitar logs que contengan el token.
5. En el `.env` de **Laravel (Hostinger)** configurar:

```dotenv
CNI_BACKUP_ENDPOINT_A=https://otro-dominio.example/cni-receptor.php
CNI_BACKUP_TOKEN_A=TOKEN_ALEATORIO_DEL_RECEPTOR
```

Para una segunda instancia repetir con `_B` y un **token diferente**. Los receptores solo almacenan `.cni` cifrados. No necesitan la clave de descifrado.

**IMPORTANTE:** la recepción de la copia **no actualiza automáticamente una base MariaDB secundaria**. Mantenerla cifrada es más seguro; la restauración está separada y debe validarse antes de activarse como contingencia.

## 5. Cron de Hostinger — automatización real

En hPanel comprobar acceso a **Tareas programadas / Cron Jobs** y la ruta real donde está `artisan`. Hostinger puede tener PHP CLI en un binario específico que se debe confirmar en hPanel/SSH.

Cron diario recomendado para generar y enviar (2:00 a.m. hora de Lima):

```bash
cd /RUTA/REAL/LARAVEL && /RUTA/PHP/CLI artisan contingencia:crear
```

Cron adicional para reintentar la entrega cuando vuelva la conexión (ej. cada hora):

```bash
cd /RUTA/REAL/LARAVEL && /RUTA/PHP/CLI artisan contingencia:reenviar
```

Si Hostinger programa en UTC: `02:00 America/Lima = 07:00 UTC` en todo el año. Verificar la zona horaria del panel. Para Linux cron podría ser `0 7 * * *` y `0 * * * *` respectivamente; en hPanel los horarios pueden configurarse en campos separados.

**No activar a ciegas otro `schedule:run`**: el repositorio ya define una tarea cada minuto para cerrar intentos vencidos. Este módulo utiliza Crons dedicados y no necesita modificar `routes/console.php`.

## 6. Restauración de prueba (obligatoria antes de producción)

1. En una máquina segura, con el repositorio y la **clave original**, descarga el archivo cifrado y su manifiesto, o utiliza la copia local.
2. Verifica y descifra mediante CLI:

```bash
php artisan contingencia:descifrar 20261112_020000_ab12cd34 /RUTA/PRIVADA/cni-restauracion.sql.gz
```

3. Importa en una **base de datos nueva, vacía, NO productiva**, tras revisar el contenido del SQL. Por ejemplo, en un entorno Linux con cliente MariaDB:

```bash
gzip -dc /RUTA/PRIVADA/cni-restauracion.sql.gz | mariadb -h HOST_PRUEBA -u USUARIO_PRUEBA -p BASE_VACIA
```

4. Verifica tablas, registros de prueba, inicio de sesión y consultas críticas. Borra de forma segura el SQL descifrado después de validar; conserva la copia cifrada y la clave.

No ejecutes este SQL contra producción ni uses `migrate:fresh`; contiene `CREATE TABLE`/`INSERT` y requiere BD vacía. Para Windows usa una ruta absoluta válida y evita `public/`.

## 7. Pruebas sugeridas para la exposición

| Prueba | Resultado esperado |
|---|---|
| Ejecutar `contingencia:crear --solo-local` | Se crea `.cni` y `.json` en carpeta privada, sin actualizar las tablas |
| Abrir `/administracion/contingencia` como admin autorizado | Muestra historial y botones |
| Abrir esa URL como estudiante | Respuesta 403 |
| Descargar y verificar una copia | Archivo cifrado auténtico, SHA-256 correcto |
| Corromper una copia SOLO de prueba | La verificación falla |
| Simular caída del destino externo | Copia local preservada, estado de entrega `error` |
| Recuperar el destino y ejecutar `contingencia:reenviar` | Copia recibida y SHA-256 confirmado |
| Descifrar e importar en base vacía | Los datos y relaciones se recuperan |

## 8. Protección de datos

Los archivos pueden contener información personal de alumnos menores de edad. Utilizar datos ficticios para una demostración pública, acceso por roles, HTTPS y tokens distintos por destino. No compartir copias SQL sin cifrar, tokens, contraseñas o claves. Para uso real se debe revisar cumplimiento de la normativa peruana de protección de datos aplicable.

## 9. Publicación en GitHub del equipo

**No se realizaron commits automáticamente.** Después de verificar el código:

```bash
git status
git add app config routes resources/views/admin/contingencia .gitignore
git add docs/contingencia
git commit -m "feat: respaldo cifrado y contingencia para Hostinger compartido"
git push -u origin feature/contingencia-backups
```

Crear Pull Request para la rama de integración que utilice el equipo; no hacer cambios directos en producción sin revisión.