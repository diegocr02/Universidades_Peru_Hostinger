# RSU-CNI — Administración del banco de preguntas y simulacros

## Alcance implementado (PR)
- La cuenta con rol **`admin`** actúa como superadministrador académico. Por KISS no se creó un cuarto rol `superadmin` incompatible con el enum actual. Las cuentas `docente` y `estudiante` no acceden al panel.
- Login `/login` → `/administracion` si el usuario es `admin`.
- Desde el panel se crean **carreras por área A-E**, asignaturas (`areas`) y temas (`temas`).
- `/administracion/preguntas` permite consultar y desactivar preguntas. La carga y actualización desde la interfaz se hacen mediante `/administracion/importar-excel`; el formulario manual antiguo ya no forma parte de la aplicación.
- El importador conserva las imágenes del Excel en `storage/app/public/banco`. El banco base UNI también se carga desde [JSON](banco-uni-json.md).
- `/administracion/simulacros` publica un examen general de área o exclusivo de una carrera. Distribuye cantidades entre asignaturas del banco; comprueba que existan suficientes preguntas válidas antes de publicar.
- Migración **aditiva** `2026_10_22_000002_add_carrera_id_to_simulacros_table.php`: campo nullable con FK; se conservan simulacros históricos y perfiles generales.
- La pantalla del alumno filtra los exámenes por área y carrera; el servicio backend también rechaza intentos de carreras no autorizadas. `intentos.carrera_id` conserva el perfil de carrera cuando se inicia.
- El flag `CNI_ALUMNO_AREAS_ENABLED=false` sigue protegiendo la experiencia del estudiante hasta activar el recorrido en la instancia.

## Activación protegida

El panel nuevo **permanece desactivado por defecto** al fusionar el PR: `CNI_ADMIN_ACADEMICO_ENABLED=false`. El login del administrador sigue enviando al panel anterior. Cuando se aplique la migración y se compruebe el banco en una copia de desarrollo, configurar `CNI_ADMIN_ACADEMICO_ENABLED=true` y ejecutar `php artisan config:cache`. Esto habilita `/administracion` y la redirección desde el login para el rol `admin`. Para revertir, dejarlo en `false` y limpiar o recompilar la configuración.

## Preparación para instancia Ubuntu (con respaldo)
1. Integrar a `develop` solamente después de aprobar las pruebas y la revisión del equipo.
2. En servidor con repo limpio, `git pull --ff-only origin develop` y `composer install --no-dev --prefer-dist --optimize-autoloader`.
3. Revisar `php artisan migrate:status`, **hacer respaldo verificado de MariaDB**, aplicar exclusivamente la nueva migración:
   ```bash
   php artisan migrate --path=database/migrations/2026_10_22_000002_add_carrera_id_to_simulacros_table.php --force
   php artisan storage:link
   php artisan optimize:clear
   php artisan config:cache
   php artisan view:cache
   ```
4. Verificar que `public/storage` sea un enlace simbólico correcto y que PHP tenga permisos de escritura en `storage/app/public/preguntas`.
5. Después de comprobar la migración, activar `CNI_ADMIN_ACADEMICO_ENABLED=true` y ejecutar `php artisan config:cache`. Entrar con un usuario **real de rol `admin`**, no ejecutar `UsuarioSeeder` en producción (contiene contraseñas demo).
6. Registrar carreras, asignaturas y temas. Registrar y revisar preguntas con imágenes. Crear perfiles generales por área o especializados por carrera usando cuotas válidas.
7. Probar un alumno de ingeniería y otro de humanidades, ambos con carrera seleccionada. **Solo luego de validar la experiencia completa**, cambiar `CNI_ALUMNO_AREAS_ENABLED=true` en el `.env` de la instancia y ejecutar `php artisan config:cache`.

## Compatibilidad y restricciones conocidas
- La API JSON original `/api/preguntas` sigue aceptando `imagen` como ruta de texto para clientes existentes; las **subidas** validadas se realizan mediante la interfaz administrativa HTML.
- El rol `docente` mantiene la API de gestión de preguntas/simulacros existente para no romper integraciones previas. La interfaz de administración queda restringida a `admin`. Si el proyecto exige exclusividad absoluta del superadministrador, habrá que restringir también esos endpoints históricos previa coordinación.
- Carreras y asignaturas se crean de manera explícita; no se insertan datos académicos ficticios en producción.
- Las preguntas ya utilizadas no se editan (historial inmutable); la baja es lógica.
- Un simulacro general de área sigue disponible para alumnos del área aunque aún no hayan indicado carrera; un simulacro específico exige que la carrera coincida. Esta combinación conserva el flujo previo.
- La interfaz del banco utiliza el importador Excel. La API preexistente conserva sus formatos de alternativas y niveles.
- No hay cambio de `main` ni despliegue automático al hosting por este PR.
