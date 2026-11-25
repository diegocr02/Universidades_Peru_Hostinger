# Tutor CNI (Tutor IA)

Asistente de estudio en los paneles de alumnos de las cinco universidades (UNDC, UNI, Villarreal, área D y San Marcos). Ruta: `/alumno/tutor`. Solo estudiantes con sesión; los paneles de docentes y administración no lo muestran.

## Qué hace

| Función | ¿Usa IA? | Fuente |
|---|---|---|
| Entender mis resultados | No | `intentos` + `resultados_area` del alumno |
| Explicar un ejercicio | No (IA solo si falta la solución revisada y el alumno la pide) | `respuestas_estudiante`, `preguntas.solucion` |
| Practicar un tema | No | Banco de la universidad; se registra como práctica (`PracticaService`, cuenta para la meta semanal) |
| Ver mi progreso | No | Intentos comparables: mismo examen base, cantidad de preguntas y escala |
| Dame una pista | No | Descarta una alternativa incorrecta |
| Explícalo más fácil | Sí (se guarda 30 días en caché por pregunta) | Enunciado, clave y solución revisada |
| Pregunta libre | Sí, con herramientas | Las 6 herramientas de `TutorTools` |

Las respuestas generadas por IA se rotulan «Generado con IA»; las del banco, «Solución revisada».

## Reglas de seguridad

- **Examen activo:** si el alumno tiene un intento `en_curso` vigente, el tutor no responde ninguna acción (`ContextoAlumnoService::examenActivo`). Se valida en el servidor en cada petición.
- **Propiedad:** cada intento, pregunta y conversación se valida contra el alumno de la sesión. Una pregunta solo se explica si `RepasoService::puedeVer` lo permite (intento propio calificado o práctica propia respondida).
- **Herramientas de la IA:** `consultar_mis_resultados`, `consultar_mis_errores`, `buscar_explicacion`, `buscar_preguntas_practica`, `consultar_mi_progreso`, `consultar_info_simulador`. Sin SQL libre, archivos ni comandos; máximo `TUTOR_HERRAMIENTAS_TURNO` llamadas por mensaje.
- **Privacidad:** a OpenAI solo se envía la universidad, el área y el contenido académico; nunca nombres ni usuarios. Los registros de error guardan el código HTTP o de cURL, no la clave ni la conversación.
- **Límites:** `TUTOR_CONSULTAS_DIA` consultas con IA por alumno al día (las tarjetas no cuentan), `TUTOR_ACCIONES_MINUTO` acciones por minuto y un solo proceso a la vez por alumno.

## Configuración (`.env`)

```dotenv
TUTOR_ENABLED=true
TUTOR_PROVIDER=openai          # demo = respuestas de ejemplo rotuladas, solo para pruebas
OPENAI_API_KEY=                # nunca en el repositorio
TUTOR_MODEL=gpt-5.4-nano       # gpt-5.4-mini: algo más rápido y capaz, más costoso
TUTOR_REASONING_EFFORT=none    # vacío si el modelo no admite el parámetro
TUTOR_TIMEOUT=25
TUTOR_MAX_TOKENS=600
TUTOR_CONSULTAS_DIA=25
TUTOR_RETENCION_DIAS=120
```

`TUTOR_CERTIFICADOS_SISTEMA` vale `true` por defecto solo en Windows: el PHP de Windows no trae certificados raíz y cURL falla con el error 60. Usa el almacén de Windows y la verificación HTTPS sigue activa. En Hostinger no hace falta.

El resto de opciones (bancos de práctica por universidad, menús, niveles y cantidades) está en `config/tutor.php`.

## Instalación

```bash
php artisan migrate --path=database/migrations/2026_11_19_000001_create_tutor_tables.php
php artisan config:clear
```

La migración solo crea `tutor_conversaciones` y `tutor_mensajes`; no modifica tablas existentes.

**Retención:** `tutor:limpiar` corre cada día a las 03:30 mediante el cron de Laravel ya documentado en `DESPLIEGUE-HOSTINGER.md`. Para ejecutarla a mano: `php artisan tutor:limpiar --dias=120`.

## Archivos

- `app/Livewire/Alumno/Tutor/TutorChat.php`: pantalla (solo estado de interfaz).
- `app/Services/Tutor/`: `TutorService` (flujos), `TutorTools` (herramientas de la IA), `TutorIa` (OpenAI), `TutorIaException`. Reutiliza `app/Services/Alumno/` (`ContextoAlumnoService`, `PracticaService`, `RepasoService`, `ProgresoComparableService`).
- `resources/views/livewire/alumno/tutor/tutor-chat.blade.php`, `resources/views/components/tutor/mascota.blade.php`, `public/css/alumno/tutor.css`.
- Mascota: `public/images/mascotas/gatito-cni.webp` (fondo transparente, 331×380).
- Pruebas: `tests/Feature/TutorCniTest.php`. Usan IA simulada; `phpunit.xml` apaga el tutor y deja la clave vacía.

## Limitaciones conocidas

- La IA no ve las figuras de las preguntas con imagen. Se le indica y lo comunica cuando hace falta.
- Las fórmulas se muestran como texto simple (x^2, √x, a/b).
