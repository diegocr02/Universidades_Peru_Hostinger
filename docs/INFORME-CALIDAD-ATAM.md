# Informe de calidad y evaluación ATAM — Simulador CNI

**Fecha de ejecución:** 12/11/2026 · **Entorno:** equipo local Windows 11, PHP 8.4.25, MariaDB local, Chrome instalado.
**Código evaluado:** rama `deploy/hostinger` del repositorio `diegocr02/Universidades_Peru_Hostinger`, en el commit que incorpora este informe (base anterior: `0e0728c`).
El repositorio `JIMMY1012102/rsu-simulador-cni` no se auditó directamente.

> Convención de estados usada en todo el informe:
> **✅ Implementado y comprobado** · **🟡 Implementado, no comprobado** · **⏳ Pendiente de implementación** · **❌ Error detectado**

**Resultado en una línea:** el sistema es **seguro, íntegro y funcional** (PHPUnit 242/242 en SQLite y en MariaDB; Playwright 8/8). Con 15 estudiantes a la vez **no tuvo errores y cumplió la meta de rapidez** (p95 de 0,18 s frente a una meta de 2 s) una vez activado OPcache en el servidor local, como lo tiene el hosting.

> **Actualización 12/11/2026 (tarde):** se volvieron a ejecutar las tres herramientas. Cambios: OPcache en el servidor QA (k6 pasó de p95 3,09 s a 0,18 s), 8 pruebas PHPUnit nuevas (diagnóstico y Centro de contingencia) y 3 escenarios Playwright nuevos del Centro de contingencia. Las cifras anteriores se conservan como referencia en la sección 7.

---

## 1. Introducción y objetivo

Obtener evidencias reales para evaluar la arquitectura del Simulador CNI con el método ATAM (Architecture Tradeoff Analysis Method). Se ejecutaron tres herramientas sobre el código real:

- **PHPUnit:** seguridad, integridad de datos y regresiones del backend.
- **k6:** rendimiento con 15 estudiantes concurrentes.
- **Playwright:** recorrido completo del estudiante en un navegador real.

Las **metas** (p95 < 2 s, < 1 % de errores) son criterios de evaluación propuestos, **no resultados**. Los resultados obtenidos se informan por separado.

## 2. Arquitectura real identificada

| Capa | Elemento real |
|---|---|
| Presentación | Blade (paneles por universidad: UNDC, UNI, Villarreal, San Marcos y panel general) y Livewire 3 (`app/Livewire/Examen.php`, pantalla del examen) |
| Control | Controladores web por rol (`Alumno/`, `Docente/`, `Admin/`) y API JSON (`Api/IntentoController`) en `routes/modules/*.php` bajo `/api` |
| Negocio | Servicios: `IntentoService` (iniciar, responder, finalizar, cerrar vencidos), `ResultadoService` (calificación), `SimulacroService`, `AuthService` y `Alumno/*` (paneles y examen por universidad) |
| Datos | MariaDB con migraciones Laravel; bancos de preguntas en JSON importados por seeders |
| Seguridad | Guard `web` por sesión, CSRF, `EnsureActiveUser`, `SoloAdministrador`, control de propietario en intentos y resultados, límite de login (10 por minuto por usuario e IP) |
| Integridad | Transacciones con `lockForUpdate`; `guardarRespuesta` rechaza un intento no en curso, una pregunta ajena al intento, una alternativa de otra pregunta y una respuesta fuera de tiempo |
| Disponibilidad | Módulo de contingencia (respaldos cifrados y envío externo), **desactivado por defecto** |

Estilo arquitectónico: **monolito modular por capas** (MVC más capa de servicios), con renderizado en el servidor y un componente reactivo (Livewire) para el examen.

## 3. Herramientas encontradas (auditoría previa)

| Herramienta | Estado encontrado |
|---|---|
| PHPUnit 11.5.57 | Instalado. `phpunit.xml` usa SQLite en memoria (aislado de MariaDB). 230 pruebas aprobadas antes de los cambios |
| k6 v2.3.0 | El binario estaba instalado. **No existía ningún script** |
| Playwright | Existía `tests/e2e/browser.mjs` (librería `playwright` 1.56.1). **No estaba instalado en local** y está **bloqueado para ejecutarse solo en CI** |
| CI/CD | `tests.yml` (SQLite y MariaDB), `examen-independiente.yml`, `e2e-browser.yml`. Se disparaban en `develop`, `main` y `OFICIAL#1`, **no en `deploy/hostinger`** (corregido, ver sección 10) |

## 4. Pruebas que ya existían

- **Autenticación** (`AuthModuleTest`, 14 pruebas): login y logout por web y JSON, regeneración de sesión, CSRF, cuenta inactiva, límite de intentos y bearer rechazado.
- **Roles y acceso:** `AccesoPanelOficialTest`, `SeccionesDocenteTest`, `AdministracionAcademicaTest`.
- **Resultados ajenos:** `test_un_alumno_no_ve_resultados_ajenos`, `test_otro_estudiante_no_lee_ni_modifica_intentos_o_resultados_ajenos`.
- **Examen:** `ExamenServiceTest`, `ExamenPantallaTest`, `ExamenIndependienteTest`, `ExamenUniTest`, `ExamenUnmsmTest` (inicio, reanudación, finalización, envío automático al vencer, pregunta ajena al sorteo).
- **Calificación:** `ResultadoServiceTest`, `CalificacionUniTest`, `test_fallo_al_calificar_no_deja_finalizacion_ni_resultados_parciales`.
- **Tiempo:** `test_si_el_tiempo_termino_no_guarda_y_envia_automaticamente`, `test_respuesta_fuera_de_tiempo_se_rechaza_y_el_cierre_si_persiste`.

**Huecos detectados:** no había prueba de *alternativa de otra pregunta* y la de *responder un intento finalizado manualmente* era solo parcial. k6 no existía. La E2E existente está desactualizada (sección 8).

## 5. Nuevas pruebas implementadas

| Archivo | Qué verifica |
|---|---|
| `tests/Feature/IntegridadRespuestasTest.php` (4 pruebas) | El servicio y la API rechazan una alternativa de otra pregunta con 422 sin guardar nada. Un intento finalizado no acepta respuestas (422), conserva su respuesta y su puntaje, y finalizar de nuevo no recalifica |
| `tests/Feature/HistorialPorUniversidadTest.php` (3 pruebas) | Cada panel y la gráfica de progreso muestran solo intentos de su universidad |
| `tests/performance/cni-load.js` | k6: 15 estudiantes, login con CSRF y sesión, 4 fases, umbrales p95 < 2 s y < 1 % de errores. **El resultado se explica en lenguaje simple** (cifras, metas con CUMPLE o NO CUMPLE y conclusión) |
| `tests/quality/e2e/estudiante.spec.js` y `playwright.config.js` | Playwright (`@playwright/test` 1.56.1): 5 escenarios **divididos en pasos con nombre** (por ejemplo, "Recarga la página: la respuesta sigue guardada en el servidor"), capturas y reporte HTML |
| `tests/quality/ejecutar-pruebas.ps1` | **Un solo comando** que ejecuta las tres herramientas, enciende y apaga el servidor QA y termina con un **resumen final** (APROBADO, META NO CUMPLIDA o FALLO y qué significa) |
| `tests/quality/preparar-qa.php` | Prepara la base QA: 15 cuentas k6 y 2 cuentas E2E con una clave aleatoria guardada en un archivo ignorado por git. Se bloquea fuera de `APP_ENV=qa` y `simulador_cni_qa` (comprobado) |

**Entorno QA aislado:**
- Base `simulador_cni_qa` (nueva y vacía, solo `migrate` y seeders, nunca `migrate:fresh`).
- `.env.qa` (ignorado por git).
- Servidor en `127.0.0.1:8001`.

La base de trabajo `simulador_cni_oficial` no se modificó. No se cambió ninguna regla de negocio para que las pruebas pasaran.

## 6. Resultados reales de PHPUnit

| Ejecución | Pruebas | Aprobadas | Fallidas | Aserciones | Tiempo | Evidencia |
|---|---|---|---|---|---|---|
| SQLite en memoria (`php artisan test`) | **242** | **242** | **0** | 10.631 | 28,5 s | `docs/evidencias-calidad/phpunit-salida.txt` |
| **MariaDB real** (base desechable `simulador_cni_phpunit`, creada y borrada para la prueba) | **242** | **242** | **0** | 10.631 | 60,2 s | `docs/evidencias-calidad/phpunit-mariadb-salida.txt` |

Con la ejecución en MariaDB, los bloqueos de fila (`lockForUpdate`) y las transacciones se ejercitan en el motor real, no solo en SQLite.

Las pruebas nuevas no detectaron defectos en las reglas existentes: las validaciones de `IntentoService` se comportan como dice el código.

## 7. Resultados reales de k6

Comando: `k6 run tests/performance/cni-load.js` contra `http://127.0.0.1:8001/alumno/panel` (servidor QA local, `php artisan serve` **con OPcache**, cargado desde `tests/quality/php-qa/opcache.ini`).

Etapas: 15 s → 5 usuarios, 15 s → 15, 30 s sostenidos con 15 y 10 s de bajada (unos 70 s).

| Métrica | **15 usuarios concurrentes** | Referencia: 1 usuario (20 s) |
|---|---|---|
| Solicitudes atendidas | 693 (663 paneles más 30 de login) | 21 |
| Solicitudes por segundo | **9,89** | 1,04 |
| Tiempo promedio | **0,06 s** | 0,06 s |
| Más rápida / más lenta | 0,02 s / **0,32 s** | 0,03 s / 0,22 s |
| **p95** | **0,18 s** | **0,09 s** |
| Solicitudes con error | **0,0 %** | 0,0 % |
| Verificaciones funcionales (login y panel correcto) | **100 %** | 100 % |

Evidencia: `docs/evidencias-calidad/k6-15-usuarios.txt` y `k6-1-usuario-referencia.txt`.

| Meta propuesta | Resultado |
|---|---|
| 95 % de solicitudes en menos de 2 s | ✅ **Cumple** (p95 = 0,18 s) |
| Menos del 1 % de solicitudes fallidas | ✅ **Cumple** (0 %) |

**Antes y después de OPcache** (mismo equipo, mismo script, mismo servidor de desarrollo):

| | Sin OPcache (primera medición) | Con OPcache |
|---|---|---|
| Tiempo de una petición sola | ~0,25 s | ~0,02 s |
| p95 con 15 usuarios | **3,09 s** ❌ (entre 3,04 y 3,25 s en tres ejecuciones) | **0,18 s** ✅ |
| Solicitudes por segundo | 3,64 | 9,89 |
| Errores | 0 % | 0 % |

**Interpretación:**
- **Causa del primer resultado:** sin OPcache, PHP recompilaba Laravel en cada petición (~0,25 s). Como `php artisan serve` en Windows **atiende una petición a la vez**, los 15 estudiantes hacían cola y el p95 subía a ~3 s.
- **Corrección:** se activó OPcache **solo para el servidor QA** (sin tocar el `php.ini` global). El hosting de producción ya lo tiene activo (`opcache.enable=On`), así que la prueba local ahora se parece más a producción.
- **Sigue siendo una medición local:** el hosting de producción **no se midió con k6** (no está autorizado). Un servidor real (LiteSpeed con varios procesos PHP) atiende peticiones en paralelo; la medición formal debe hacerse en staging.

**Nota:** cuando una meta no se cumple, k6 termina con la línea `ERRO … thresholds … crossed`. No es un fallo del script, sino el aviso de k6 de que la meta no se alcanzó.

**Corrección durante la prueba:** la primera ejecución dio 59 % de verificaciones porque k6 borra las cookies entre iteraciones por defecto. Se corrigió en el script (`noCookiesReset: true`); era un error del script, no de la aplicación.

## 8. Resultados reales de Playwright

Comando: `npx playwright test` en `tests/quality` (Chrome local, contra QA :8001). **Pasó 8 de 8** (5 del estudiante y 3 del Centro de contingencia, unos 14 s en total).

Evidencia: `docs/evidencias-calidad/playwright-salida.txt`, reporte HTML local en `tests/quality/playwright-report/index.html` y capturas en `tests/quality/test-results/`.

| Escenario | Pasos comprobados | Resultado |
|---|---|---|
| 1 · Autenticación | Abrir el login → escribir las credenciales de prueba → llegar a `/alumno/panel` con su nombre y "Examen UNFV" | ✅ |
| 2 · Simulacro | Botón del panel → examen de 50 preguntas → marcar una alternativa (el contador sube) → **recargar: la respuesta sigue guardada en el servidor** → "Finalizar examen" → confirmar → resultados | ✅ |
| 3 · Resultados | "Resultados de tu examen" con **Puntaje total** → el mismo intento aparece en su historial con sus respondidas y el enlace correcto | ✅ |
| 4 · Seguridad | Abrir el resultado de otro estudiante → **HTTP 404** y ninguna calificación visible | ✅ |
| 4b · Seguridad | Abrir `/alumno/panel` sin sesión → redirige a `/login` | ✅ |
| C1 · Contingencia | `Super_Admin` inicia sesión → ve "Centro de contingencia" en el menú → el panel dice "Listo para pruebas" con el botón habilitado | ✅ |
| C2 · Contingencia | "Generar respaldo" → confirmar → aparece la fila con el archivo `.cni` → "Verificar" confirma **SHA-256 y autenticidad** | ✅ |
| C3 · Seguridad | Un estudiante no ve el enlace y, si escribe `/administracion/contingencia`, recibe **HTTP 403** | ✅ |

Los escenarios C1–C3 están en `tests/quality/e2e/contingencia.spec.js`. El panel permite 2 respaldos cada 10 minutos; la prueba limpia ese contador en la base QA antes de empezar para poder repetirse.

**Verificación en la base QA tras la E2E:** un intento con 1 respuesta incorrecta y 49 en blanco quedó `finalizado` con puntaje **−1,125**, que coincide con la escala (incorrecta = −1,125, en blanco = 0).

**E2E existente (`tests/e2e/browser.mjs`): ❌ desactualizada (análisis estático, no ejecutada).** Espera el encabezado "Panel del administrador", la ruta `/administracion/preguntas/nueva` (eliminada en `0e0728c`) y una lista de simulacros en el panel del área C, y nada de eso existe ya. Fallaría en su workflow de CI (`develop`). No se modificó; sus escenarios quedan cubiertos por `tests/quality`.

## 9. Evaluación ATAM

### 9.1 Escenarios de calidad y resultado

| Atributo | Escenario (estímulo → respuesta esperada) | Herramienta | Resultado |
|---|---|---|---|
| Seguridad | Un estudiante pide el resultado de otro → se rechaza | PHPUnit y Playwright | ✅ 404, sin datos |
| Seguridad | Petición sin sesión o sin CSRF → se rechaza | PHPUnit y Playwright | ✅ |
| Seguridad | Estudiante o docente entran a administración → 403 | PHPUnit | ✅ |
| Integridad | Alternativa de otra pregunta → 422 sin persistir | PHPUnit (nuevo) | ✅ |
| Integridad | Responder un intento finalizado → 422; la nota no cambia | PHPUnit (nuevo) | ✅ |
| Integridad | Fallo al calificar → sin resultados parciales | PHPUnit | ✅ |
| Integridad | Transacciones y bloqueos en el motor real | PHPUnit en MariaDB | ✅ 242/242 |
| Confiabilidad funcional | Recorrido login → examen → envío → resultado | Playwright y PHPUnit | ✅ |
| Rendimiento | 15 estudiantes simultáneos → p95 < 2 s, < 1 % de errores | k6 | ✅ p95 0,18 s · ✅ 0 % de errores (servidor local con OPcache) |
| Mantenibilidad | Un cambio no rompe funciones → la batería lo detecta | PHPUnit (242) y CI | ✅ local y ✅ CI de GitHub en `deploy/hostinger` (SQLite y MariaDB en verde) |
| Disponibilidad | Pérdida de la base → existe un respaldo cifrado y verificable | Playwright (C2) y PHPUnit (`ContingenciaPanelTest`) | ✅ Respaldos cifrados **activos en producción**; generar y verificar comprobado en QA · 🟡 restauración y destinos externos no probados |
| Disponibilidad | Revisar el estado del servidor en producción | `php artisan cni:diagnostico` | ✅ Disponible en el hosting (solo lectura) |

### 9.2 Puntos sensibles

- **Modelo de concurrencia y OPcache del servidor PHP:** determinan directamente el p95 bajo carga. En el servidor local de un solo proceso, activar OPcache bajó el p95 con 15 usuarios de 3,09 s a 0,18 s.
- **Bloqueos de base de datos** (`lockForUpdate`): garantizan la integridad, pero serializan las operaciones del mismo alumno o de la misma plantilla.
- **Sesión y caché en archivos:** E/S de disco en cada petición; con muchos usuarios conviene base de datos o Redis.
- **Clasificación de exámenes por prefijo de nombre** (`App\Support\UniversidadExamen`): separa los historiales por universidad. Renombrar una plantilla sin actualizar los prefijos ocultaría intentos.

### 9.3 Compromisos (*tradeoffs*)

- **Seguridad e integridad frente a rendimiento:** transacciones, bloqueos, validación de propiedad en cada petición y CSRF aumentan la latencia, pero dan la integridad comprobada.
- **Límite de login frente a pruebas de carga:** protege de la fuerza bruta. Como combina usuario e IP, no bloquea a alumnos distintos en un aula con IP compartida.
- **Renderizado en servidor (Blade y Livewire) frente a escalabilidad:** es simple de mantener, pero cada interacción del examen es una petición al servidor.

### 9.4 Riesgos

| # | Riesgo | Evidencia | Estado |
|---|---|---|---|
| R1 | El p95 superaba 2 s con 15 usuarios en el servidor local sin OPcache; no se midió producción | k6 (sección 7) | **Mitigado en local** (p95 0,18 s con OPcache) · producción pendiente de medir en staging |
| R2 | La E2E antigua está desactualizada y falla en CI de `develop` | Sección 8 | Abierto (cubierta por `tests/quality`) |
| R3 | La rama de despliegue no ejecutaba CI | `.github/workflows/tests.yml` | **Mitigado:** ahora se ejecuta al hacer push a `deploy/hostinger` |
| R4 | Concurrencia no probada en MariaDB | `phpunit.xml` | **Mitigado:** 242/242 en MariaDB real |
| R5 | La disponibilidad no tenía pruebas ni respaldos activos | `config/contingencia.php` | **Mitigado en parte:** respaldos cifrados activos en producción y probados en QA; falta un destino externo (otro servidor) y una prueba de restauración |
| R6 | `APP_DEBUG=true` en el `.env` local | `.env` | Verificar `APP_DEBUG=false` en el hosting (ver `docs/DESPLIEGUE-HOSTINGER.md`) |

### 9.5 No riesgos (decisiones comprobadas como adecuadas)

Control de propietario en resultados; validación de alternativas y de estado del intento; CSRF en todos los formularios; envío automático al vencer el tiempo; calificación coherente con la escala; separación del historial por universidad.

## 10. Recomendaciones

1. **Rendimiento:** medir en un **entorno de staging** igual a producción (PHP-FPM con varios workers, `APP_DEBUG=false`, `php artisan optimize`). Se usa el mismo script: `$env:BASE_URL="https://staging…"; $env:K6_ENTORNO_AUTORIZADO="1"; k6 run tests/performance/cni-load.js`.
2. **CI:** ya aplicado y comprobado: `tests.yml` corre en `deploy/hostinger` y su primera ejecución (run 43175141851) terminó en verde.
3. **E2E:** retirar o actualizar `tests/e2e/browser.mjs` y llevar `tests/quality` al CI.
4. **Disponibilidad:** monitorear `/up` (incluido en Laravel) con un servicio externo y probar periódicamente la restauración del módulo de contingencia.
5. **Prefijos de exámenes:** guardar la universidad como columna del simulacro en lugar de deducirla del nombre (requiere migración; se propone, no se aplicó).

## 11. Conclusiones de la evaluación arquitectónica

- **Seguridad, integridad y confiabilidad funcional:** se cumplen con evidencia reproducible: PHPUnit 242/242 en SQLite y en MariaDB, y Playwright 8/8.
- **Rendimiento:** **se cumple la meta de p95 < 2 s** con 15 usuarios en el servidor local (0,18 s) y **sin ningún error**. La primera medición (3,09 s) se debía a que faltaba OPcache en el servidor de desarrollo: era un **punto sensible de despliegue y no de diseño**, y se corrigió sin cambiar el código de la aplicación. La conclusión sobre producción **queda pendiente** de medir en staging.
- **Disponibilidad:** mejoró: los respaldos cifrados están activos en producción y su generación y verificación están comprobadas en QA. Falta probar la restauración y configurar un destino externo.

## Tabla resumen

| Herramienta | Prueba realizada | Resultado | Evidencia | Estado |
|---|---|---|---|---|
| PHPUnit | Batería completa en SQLite | 242/242 aprobadas | `evidencias-calidad/phpunit-salida.txt` | ✅ Implementado y comprobado |
| PHPUnit | Batería completa en MariaDB real | 242/242 aprobadas, 60,2 s | `evidencias-calidad/phpunit-mariadb-salida.txt` | ✅ Implementado y comprobado |
| PHPUnit | Login, logout, CSRF, roles | Aprobadas | `AuthModuleTest`, `AccesoPanelOficialTest` | ✅ Implementado y comprobado |
| PHPUnit | Resultados ajenos | Aprobadas | `ResultadoServiceTest`, `ExamenPantallaTest` | ✅ Implementado y comprobado |
| PHPUnit | Alternativa de otra pregunta (nueva) | 422 sin persistir | `IntegridadRespuestasTest` | ✅ Implementado y comprobado |
| PHPUnit | Intento finalizado inmutable (nueva) | 422; nota sin cambios | `IntegridadRespuestasTest` | ✅ Implementado y comprobado |
| PHPUnit | Control de tiempo | Aprobadas | `ExamenPantallaTest`, `ExamenServiceTest` | ✅ Implementado y comprobado |
| k6 | 15 usuarios, 4 fases, panel autenticado | p95 0,18 s; 0 % de errores; 9,89 peticiones/s | `evidencias-calidad/k6-15-usuarios.txt` | ✅ Implementado y comprobado (antes sin OPcache: p95 3,09 s ❌) |
| k6 | Referencia con 1 usuario | p95 0,09 s | `evidencias-calidad/k6-1-usuario-referencia.txt` | ✅ Implementado y comprobado |
| k6 | Carga en producción o staging | No ejecutada (no autorizada) | — | ⏳ Pendiente |
| Playwright | Escenario 1: autenticación | Aprobado | `evidencias-calidad/playwright-salida.txt` | ✅ Implementado y comprobado |
| Playwright | Escenario 2: simulacro completo | Aprobado | ídem | ✅ Implementado y comprobado |
| Playwright | Escenario 3: resultados | Aprobado | ídem | ✅ Implementado y comprobado |
| Playwright | Escenarios 4 y 4b: resultado ajeno y sin sesión | 404 y redirección al login | ídem | ✅ Implementado y comprobado |
| Playwright | C1–C3: Centro de contingencia (panel listo, respaldo cifrado verificado, estudiante bloqueado con 403) | Aprobados | ídem | ✅ Implementado y comprobado |
| PHPUnit | Avisos del Centro de contingencia (falta sodium o clave) | Aprobadas | `ContingenciaPanelTest` | ✅ Implementado y comprobado |
| Diagnóstico | `php artisan cni:diagnostico` en el hosting | Funcionando (solo lectura) | `DiagnosticoCniTest` | ✅ Implementado y comprobado |
| Playwright | E2E existente `tests/e2e/browser.mjs` | Selectores y rutas obsoletos | Análisis estático | ❌ Error detectado |
| CI/CD | `tests.yml` en `deploy/hostinger` | Ejecución 43175141851 en verde: SQLite (39 s) y MariaDB 11.4 (1 min 42 s) | GitHub Actions, run 43175141851 | ✅ Implementado y comprobado |
| — | Disponibilidad (contingencia y monitoreo) | Sin pruebas | `config/cni.php` | 🟡 Implementado, no comprobado |

## Cómo reproducir

```powershell
# Todo con un comando (PHPUnit + k6 + Playwright y resumen final)
.\tests\quality\ejecutar-pruebas.ps1
.\tests\quality\ejecutar-pruebas.ps1 -Solo e2e -VerNavegador   # ver Chrome haciendo el examen

# Por separado
php artisan test
$env:APP_ENV="qa"; php artisan serve --host=127.0.0.1 --port=8001   # otra terminal
k6 run tests/performance/cni-load.js
cd tests/quality; npx playwright test; npx playwright show-report playwright-report
```

Preparación de la base QA (solo la primera vez): crear una base vacía `simulador_cni_qa` y un `.env.qa` (copia de `.env` con `APP_ENV=qa`, `DB_DATABASE=simulador_cni_qa` y `APP_URL=http://127.0.0.1:8001`), y luego:

```powershell
$env:APP_ENV="qa"
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=UnfvPreguntasSeeder --force
php tests/quality/preparar-qa.php
Remove-Item Env:APP_ENV
```
