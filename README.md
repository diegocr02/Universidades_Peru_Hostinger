# RSU-CNI — Simulador CNI

Proyecto de Responsabilidad Social Universitaria de preparación académica para estudiantes del CNI.

## Arquitectura KISS

Una sola aplicación **Laravel 12 + Blade + Livewire 3 + MariaDB**, con autenticación por sesiones de Laravel. Las cuentas usan el modelo `Usuario` y la tabla `usuarios`. No se emiten tokens Bearer y no se necesita Firebase ni un frontend separado.

- **Presentación:** vistas en `resources/views/`, estilos en `public/css/app.css` y componentes en `app/Livewire/`.
- **Autenticación:** `SessionController` y `AuthService`, usando exclusivamente el guard `web`. Login, logout, regeneración de sesión, CSRF y límite de solicitudes compartido entre HTML y JSON.
- **Acceso:** `auth:web` y `EnsureActiveUser`; se conserva la autorización por roles y propietario de cada módulo.
- **Negocio:** servicios existentes de preguntas, simulacros, intentos y resultados.
- **Persistencia:** modelos/consultas y MariaDB. No cambia el esquema de negocio.

Las rutas `/api/*` se conservan para reutilizar el trabajo de los módulos, pero ahora son **rutas JSON de la misma aplicación web**: se cargan desde `routes/web.php`, comparten la cookie de sesión y requieren CSRF en operaciones de escritura. No son una API pública ni una autenticación independiente. Los componentes Livewire pueden llamar directamente a los servicios.

## Qué incluye esta adaptación

- `/`: bienvenida con enlace al acceso.
- `/login`: formulario de usuario y contraseña.
- `/panel`: componente Livewire que lista simulacros e intentos propios, incluidos puntajes ya registrados.
- `/logout`: cierre de sesión por POST.
- Módulos JSON existentes unificados con la misma sesión; pruebas adaptadas al guard `web`.

**Alcance:** establece la arquitectura y el acceso web. El panel inicial no sustituye las pantallas completas de gestión de preguntas, creación de simulacros y resolución del examen. El recorrido backend de simulacro (asignación, respuestas, cierre y calificación) cuenta con pruebas de integración; consulta `docs/recorrido-backend.md` para sus reglas y límites. Las pantallas completas siguen pendientes.

## Preparación local

Requiere PHP 8.2+, Composer 2 y MariaDB. PHP necesita las extensiones habituales de Laravel, `pdo_mysql` y, para las pruebas, `pdo_sqlite`.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configura la conexión local en `.env`. No publiques ese archivo ni contraseñas.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simulador_cni_validacion
DB_USERNAME=cni_dev
DB_PASSWORD=TU_CONTRASEÑA_LOCAL
SESSION_DRIVER=file
```

**Base nueva y vacía:** revisar primero las migraciones y luego ejecutar `php artisan migrate`. Los seeders existentes crean cuentas de demostración con contraseña conocida; úsalos solo en una base local de prueba. No se ejecutan automáticamente.

**Base con el SQL importado:** no ejecutar `migrate`, `migrate:fresh` ni volver a importar `base-de-datos.sql` sin acordar la sincronización del historial de migraciones. `migrate:fresh` elimina tablas. La adaptación KISS no modifica ni elimina datos ni migraciones existentes; la antigua tabla de tokens se conserva por compatibilidad histórica, pero ya no se usa para autenticar.

```bash
php artisan route:list
php artisan serve
```

Abre `http://127.0.0.1:8000/login`. La aplicación no requiere Node, npm ni Vite: CSS y JavaScript se sirven directamente desde `public/`, y Livewire desde Laravel. También puedes iniciar el servidor con `composer dev`. En Hostinger despliega como sitio PHP, con la carpeta pública apuntando a `public/`.

## Uso de respuestas JSON desde el mismo navegador

Primero ingresa por `/login`. Para llamadas `fetch`, envía `Accept: application/json`; en POST/PUT/DELETE añade `X-CSRF-TOKEN` con el valor de la etiqueta `meta[name="csrf-token"]`. No guardes un token Bearer ni contraseñas en localStorage. Las solicitudes sin sesión reciben 401; las escrituras sin CSRF reciben 419. Desde Blade/Livewire se recomienda usar formularios y acciones en lugar de hacer solicitudes HTTP internas a la propia aplicación.

## Verificación

```bash
composer validate --strict
php artisan view:cache
php artisan test
```

`phpunit.xml` selecciona SQLite en memoria, caché y sesiones de prueba. No uses la base MariaDB compartida para ejecutar tests. El workflow de GitHub repite instalación, validación, compilación de vistas y pruebas.

Los cambios se proponen mediante Pull Request a `develop`; no se modifica `main` directamente.

## Recorrido del simulacro

`php artisan test --filter=RecorridoSimulacroTest` comprueba el recorrido usando SQLite en memoria. El inicio asigna preguntas, la finalización califica y los resultados se consultan con la misma sesión web. No requiere nuevas migraciones ni seeders.

El cierre de intentos vencidos está definido cada minuto en Laravel Scheduler; al desplegar hay que activar el scheduler. En desarrollo puede mantenerse `php artisan schedule:work` en otra terminal. Las preguntas y configuraciones ya usadas no se editan; se crean nuevas versiones para proteger los intentos existentes.

## Banco de preguntas desde Excel

El banco base UNI se edita en `database/data/uni_preguntas.json` y se carga con `php artisan db:seed --class=BancoUniSeeder --force`. Consulta [el formato JSON y las reglas de importación](docs/banco-uni-json.md).

El banco del colegio (`Banco_preguntas_completo.xlsx`, hoja «Banco de preguntas») se carga con:

```bash
php artisan migrate
php artisan storage:link
php artisan banco:importar "C:\ruta\Banco_preguntas_completo.xlsx" --simulacros
```

- Cada pregunta se identifica por su código (`AM_1`, `LE_12`…), así que el comando se puede repetir cada vez que se actualice el Excel sin duplicar nada.
- Cada curso (Álgebra, Física, Lenguaje…) queda como asignatura propia, para poder sortear por curso.
- Las imágenes de preguntas y alternativas se guardan en `storage/app/public/banco` y el examen las muestra desde `/storage/banco/…`.
- La columna «Respuesta correcta» se guarda en `alternativas.es_correcta`; el examen nunca la envía al navegador.
- Las preguntas del Excel que aún no tienen respuesta también salen en el examen, pero no suman ni restan puntos hasta completar su respuesta y volver a importar.
- `--simulacros` crea o actualiza «Simulacro CNI · Área A» … «Área E»: 100 preguntas cada uno, 180 minutos, con la distribución por curso de `ImportarBancoExcel::DISTRIBUCION` (20 de Habilidades comunes y el resto según el área).
- Para probar cada área en local: `php artisan db:seed --class=EstudiantesPorAreaDemoSeeder` crea `estudiante_a_demo` … `estudiante_e_demo`.
- El Excel no se sube al repositorio: el repositorio es público y el archivo contiene las respuestas.

## Nuevo flujo del estudiante: despliegue seguro (PR #18)

El flujo de primera elección de cinco áreas, panel personalizado y exámenes por área ya está integrado en el código, pero **permanece apagado por defecto** al integrarse en `develop`:

```dotenv
CNI_ALUMNO_AREAS_ENABLED=false
```

Así, las instalaciones que todavía utilizan el esquema o catálogo anterior mantienen el login y `/panel` clásicos. Las rutas nuevas devuelven 404 mientras esté desactivado. No se han cambiado las reglas de login del docente.

Antes de activar, **en copia de desarrollo con respaldo**:

1. `php artisan migrate:status` y `php artisan cni:auditar-areas`; si hay códigos distintos de A-E, detenerse y acordar la migración de datos. El catálogo legado de cuatro áreas NO debe transformarse automáticamente.
2. Una vez corregido el catálogo sin pérdida de relaciones históricas, aplicar la nueva migración `usuarios.area_postulacion_id` con `php artisan migrate --force`. Si la base se creó desde un SQL importado, sincronizar cuidadosamente el historial de migraciones antes de usar `migrate` (ver advertencia anterior).
3. Cargar y aprobar los exámenes reales con asociaciones en `area_postulacion_simulacro` y cuotas de preguntas en `simulacro_area`. Los cinco exámenes demo no son oficiales.
4. Validar en navegador el ingreso de alumno nuevo, selección única, panel, examen, resultados y un segundo inicio de sesión. Probar docentes y administradores también.
5. **Solo entonces** modificar la configuración local de esa instalación a `CNI_ALUMNO_AREAS_ENABLED=true` y aplicar `php artisan config:clear` (o regenerar config cache según despliegue). Si se detecta un problema, regresar a `false` y limpiar caché: no cambia ni elimina registros por sí solo.

GitHub Actions ejecuta la suite completa tanto en SQLite como en MariaDB 11.4 **temporales**. Una CI verde prueba el código, no el estado de la MariaDB del equipo. Consultar `docs/validacion-mariadb-areas.md`.
