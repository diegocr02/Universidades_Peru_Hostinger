# Informe de calidad y evaluación ATAM — Simulador CNI

**Fecha de ejecución:** 12/11/2026 · **Entorno:** equipo local Windows 11, PHP 8.4.25, MariaDB local, Chrome instalado.
**Código evaluado:** rama `deploy/hostinger` del repositorio `diegocr02/Universidades_Peru_Hostinger`, en el commit que incorpora este informe (base anterior: `0e0728c`).
El repositorio `JIMMY1012102/rsu-simulador-cni` no se auditó directamente.

> Convención de estados usada en todo el informe:
> **✅ Implementado y comprobado** · **🟡 Implementado, no comprobado** · **⏳ Pendiente de implementación** · **❌ Error detectado**

**Resultado en una línea:** el sistema es **seguro, íntegro y funcional** (PHPUnit 234/234 en SQLite y en MariaDB; Playwright 5/5). Con 15 estudiantes a la vez **no tuvo errores**, pero **no alcanzó la meta de rapidez** en el servidor local de desarrollo (p95 de 3,09 s frente a una meta de 2 s).

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
| SQLite en memoria (`php artisan test`) | **234** | **234** | **0** | 10.589 | 22,5–40,8 s según la carga del equipo | `docs/evidencias-calidad/phpunit-salida.txt` |
| **MariaDB real** (base desechable `simulador_cni_phpunit`) | **234** | **234** | **0** | 10.589 | 44,4 s | `docs/evidencias-calidad/phpunit-mariadb-salida.txt` |

Con la ejecución en MariaDB, los bloqueos de fila (`lockForUpdate`) y las transacciones se ejercitan en el motor real, no solo en SQLite.

Las pruebas nuevas no detectaron defectos en las reglas existentes: las validaciones de `IntentoService` se comportan como dice el código.

## 7. Resultados reales de k6

Comando: `k6 run tests/performance/cni-load.js` contra `http://127.0.0.1:8001/alumno/panel` (servidor QA local, `php artisan serve`).

Etapas: 15 s → 5 usuarios, 15 s → 15, 30 s sostenidos con 15 y 10 s de bajada (unos 70 s).

| Métrica | **15 usuarios concurrentes** | Referencia: 1 usuario (20 s) |
|---|---|---|
| Solicitudes atendidas | 258 (228 paneles más 30 de login) | 18 |
| Solicitudes por segundo | **3,64** | 0,88 |
| Tiempo promedio | **1,92 s** | 0,24 s |
| Más rápida / más lenta | 0,18 s / **3,28 s** | 0,19 s / 0,44 s |
| **p95** | **3,09 s** | **0,32 s** |
| Solicitudes con error | **0,0 %** | 0,0 % |
| Verificaciones funcionales (login y panel correcto) | **100 %** | 100 % |

Evidencia: `docs/evidencias-calidad/k6-15-usuarios.txt` y `k6-1-usuario-referencia.txt`.

**Repetibilidad:** en tres ejecuciones con 15 usuarios, el p95 estuvo entre **3,04 y 3,25 s**, siempre con 0 % de errores. El resultado es estable.

| Meta propuesta | Resultado |
|---|---|
| 95 % de solicitudes en menos de 2 s | ❌ **No cumple** (p95 = 3,09 s) |
| Menos del 1 % de solicitudes fallidas | ✅ **Cumple** (0 %) |

**Interpretación** (k6 la imprime en su "CONCLUSIÓN"):
- **Una petición sola es rápida** (0,32 s de p95 con 1 usuario). Con 15 usuarios, el tiempo sube unas 10 veces y la capacidad se estanca en unas 3,6 peticiones por segundo.
- **La demora es espera en cola:** `php artisan serve`, el servidor de desarrollo, en Windows **atiende una petición a la vez**.
- **Es un límite del modo de ejecución local, no un error:** no falló ninguna petición.
- **No se midió el hosting de producción** y estos números **no lo representan**. Un servidor real (Apache, LiteSpeed o Nginx con PHP-FPM) atiende varias peticiones en paralelo, pero eso debe medirse en un entorno de pruebas (staging).

**Nota:** cuando una meta no se cumple, k6 termina con la línea `ERRO … thresholds … crossed`. No es un fallo del script, sino el aviso de k6 de que la meta no se alcanzó.

**Corrección durante la prueba:** la primera ejecución dio 59 % de verificaciones porque k6 borra las cookies entre iteraciones por defecto. Se corrigió en el script (`noCookiesReset: true`); era un error del script, no de la aplicación.

## 8. Resultados reales de Playwright

Comando: `npx playwright test` en `tests/quality` (Chrome local, contra QA :8001). **Pasó 5 de 5 en las cinco ejecuciones finales** (unos 15 s cada una).

Evidencia: `docs/evidencias-calidad/playwright-salida.txt`, reporte HTML local en `tests/quality/playwright-report/index.html` y capturas en `tests/quality/test-results/`.

| Escenario | Pasos comprobados | Resultado |
|---|---|---|
| 1 · Autenticación | Abrir el login → escribir las credenciales de prueba → llegar a `/alumno/panel` con su nombre y "Examen UNFV" | ✅ |
| 2 · Simulacro | Botón del panel → examen de 50 preguntas → marcar una alternativa (el contador sube) → **recargar: la respuesta sigue guardada en el servidor** → "Finalizar examen" → confirmar → resultados | ✅ |
| 3 · Resultados | "Resultados de tu examen" con **Puntaje total** → el mismo intento aparece en su historial con sus respondidas y el enlace correcto | ✅ |
| 4 · Seguridad | Abrir el resultado de otro estudiante → **HTTP 404** y ninguna calificación visible | ✅ |
| 4b · Seguridad | Abrir `/alumno/panel` sin sesión → redirige a `/login` | ✅ |

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
| Integridad | Transacciones y bloqueos en el motor real | PHPUnit en MariaDB | ✅ 234/234 |
| Confiabilidad funcional | Recorrido login → examen → envío → resultado | Playwright y PHPUnit | ✅ |
| Rendimiento | 15 estudiantes simultáneos → p95 < 2 s, < 1 % de errores | k6 | ❌ p95 3,09 s · ✅ 0 % de errores (servidor local) |
| Mantenibilidad | Un cambio no rompe funciones → la batería lo detecta | PHPUnit (234) y CI | ✅ local · 🟡 CI activado en `deploy/hostinger`, pendiente de su primera ejecución en GitHub |
| Disponibilidad | Caída del servidor o de la base → recuperación | — | 🟡 Contingencia implementada pero desactivada; **no comprobada** |

### 9.2 Puntos sensibles

- **Modelo de concurrencia del servidor PHP** (número de workers): determina directamente el p95 bajo carga, como demuestra la comparación de 1 usuario (0,32 s) con 15 usuarios (3,09 s).
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
| R1 | El p95 supera 2 s con 15 usuarios en un servidor de un solo proceso; no se midió producción | k6 (sección 7) | Abierto: medir en staging |
| R2 | La E2E antigua está desactualizada y falla en CI de `develop` | Sección 8 | Abierto (cubierta por `tests/quality`) |
| R3 | La rama de despliegue no ejecutaba CI | `.github/workflows/tests.yml` | **Mitigado:** ahora se ejecuta al hacer push a `deploy/hostinger` |
| R4 | Concurrencia no probada en MariaDB | `phpunit.xml` | **Mitigado:** 234/234 en MariaDB real |
| R5 | La disponibilidad no tiene pruebas ni monitoreo; la contingencia está desactivada | `config/cni.php` | Abierto |
| R6 | `APP_DEBUG=true` en el `.env` local | `.env` | Verificar `APP_DEBUG=false` en el hosting (ver `docs/DESPLIEGUE-HOSTINGER.md`) |

### 9.5 No riesgos (decisiones comprobadas como adecuadas)

Control de propietario en resultados; validación de alternativas y de estado del intento; CSRF en todos los formularios; envío automático al vencer el tiempo; calificación coherente con la escala; separación del historial por universidad.

## 10. Recomendaciones

1. **Rendimiento:** medir en un **entorno de staging** igual a producción (PHP-FPM con varios workers, `APP_DEBUG=false`, `php artisan optimize`). Se usa el mismo script: `$env:BASE_URL="https://staging…"; $env:K6_ENTORNO_AUTORIZADO="1"; k6 run tests/performance/cni-load.js`.
2. **CI:** ya aplicado: `tests.yml` corre en `deploy/hostinger`. Falta confirmar en GitHub Actions que la primera ejecución termine en verde.
3. **E2E:** retirar o actualizar `tests/e2e/browser.mjs` y llevar `tests/quality` al CI.
4. **Disponibilidad:** monitorear `/up` (incluido en Laravel) con un servicio externo y probar periódicamente la restauración del módulo de contingencia.
5. **Prefijos de exámenes:** guardar la universidad como columna del simulacro en lugar de deducirla del nombre (requiere migración; se propone, no se aplicó).

## 11. Conclusiones de la evaluación arquitectónica

- **Seguridad, integridad y confiabilidad funcional:** se cumplen con evidencia reproducible: PHPUnit 234/234 en SQLite y en MariaDB, y Playwright 5/5.
- **Rendimiento:** **no se alcanzó la meta de p95 < 2 s** con 15 usuarios en el servidor local (3,09 s), aunque **sin ningún error**. La referencia con 1 usuario (0,32 s) muestra que el cuello de botella es el modelo de un solo proceso del servidor de desarrollo: un **punto sensible de despliegue y no de diseño**. La conclusión sobre producción **queda pendiente** de medir en staging.
- **Disponibilidad:** analizada como atributo adicional; **no está comprobada**.

## Tabla resumen

| Herramienta | Prueba realizada | Resultado | Evidencia | Estado |
|---|---|---|---|---|
| PHPUnit | Batería completa en SQLite | 234/234 aprobadas | `evidencias-calidad/phpunit-salida.txt` | ✅ Implementado y comprobado |
| PHPUnit | Batería completa en MariaDB real | 234/234 aprobadas, 44,4 s | `evidencias-calidad/phpunit-mariadb-salida.txt` | ✅ Implementado y comprobado |
| PHPUnit | Login, logout, CSRF, roles | Aprobadas | `AuthModuleTest`, `AccesoPanelOficialTest` | ✅ Implementado y comprobado |
| PHPUnit | Resultados ajenos | Aprobadas | `ResultadoServiceTest`, `ExamenPantallaTest` | ✅ Implementado y comprobado |
| PHPUnit | Alternativa de otra pregunta (nueva) | 422 sin persistir | `IntegridadRespuestasTest` | ✅ Implementado y comprobado |
| PHPUnit | Intento finalizado inmutable (nueva) | 422; nota sin cambios | `IntegridadRespuestasTest` | ✅ Implementado y comprobado |
| PHPUnit | Control de tiempo | Aprobadas | `ExamenPantallaTest`, `ExamenServiceTest` | ✅ Implementado y comprobado |
| k6 | 15 usuarios, 4 fases, panel autenticado | p95 3,09 s; 0 % de errores; 3,64 peticiones/s | `evidencias-calidad/k6-15-usuarios.txt` | ❌ Meta p95 no cumplida · ✅ meta de errores cumplida |
| k6 | Referencia con 1 usuario | p95 0,32 s | `evidencias-calidad/k6-1-usuario-referencia.txt` | ✅ Implementado y comprobado |
| k6 | Carga en producción o staging | No ejecutada (no autorizada) | — | ⏳ Pendiente |
| Playwright | Escenario 1: autenticación | Aprobado | `evidencias-calidad/playwright-salida.txt` | ✅ Implementado y comprobado |
| Playwright | Escenario 2: simulacro completo | Aprobado | ídem | ✅ Implementado y comprobado |
| Playwright | Escenario 3: resultados | Aprobado | ídem | ✅ Implementado y comprobado |
| Playwright | Escenarios 4 y 4b: resultado ajeno y sin sesión | 404 y redirección al login | ídem | ✅ Implementado y comprobado |
| Playwright | E2E existente `tests/e2e/browser.mjs` | Selectores y rutas obsoletos | Análisis estático | ❌ Error detectado |
| CI/CD | `tests.yml` en `deploy/hostinger` | Activado en esta entrega | `.github/workflows/tests.yml` | 🟡 Implementado, no comprobado (pendiente de la primera ejecución) |
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
