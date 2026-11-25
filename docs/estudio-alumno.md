# Herramientas de estudio del alumno

Funciones para que el alumno sepa qué estudiar, revise sus errores, practique y vea su avance. Están disponibles en los paneles de las cinco universidades (A–E) y se adaptan a la universidad y el área del alumno.

| Función | Ruta | Dónde está |
|---|---|---|
| Resumen de inicio + meta semanal | Panel del alumno (primera sección) | `alumno/partials/resumen-inicio.blade.php`, `InicioAlumnoService`, `MetaEstudioController` |
| Revisión de errores (tema, guardar, reportar, practicar, tutor, PDF) | `/alumno/resultados/{intento}` | Las 3 vistas de resultados + `alumno/partials/acciones-pregunta.blade.php` |
| Práctica por temas | `/alumno/practica` | `Livewire/Alumno/Practica/PracticaPorTema.php`, `PracticaService` |
| Historial visual | `/alumno/progreso` | `HistorialVisualController`, `ProgresoComparableService` |
| Preguntas guardadas | `/alumno/guardadas` | `RepasoController@guardadas`, `RepasoService` |
| Reportar pregunta | Acción en revisión, práctica y guardadas | `RepasoController@reportar`; administración en `/administracion/reportes` |
| PDF del resultado | `/alumno/resultados/{intento}/pdf` | `ResultadoPdfController`, `alumno/pdf/resultado.blade.php` (barryvdh/laravel-dompdf) |
| Accesibilidad | Botón «Aa» en todas las páginas del alumno | `components/alumno/preferencias-accesibilidad.blade.php`, `css/alumno/accesibilidad.css` |
| Estados de guardado del examen | Pantalla del examen | `public/js/examen.js`, `Livewire/Examen::responder` |
| Tutor IA | `/alumno/tutor` | Ver `docs/tutor-cni.md` |

Las páginas de estudio comparten el menú y la barra superior mediante `components/alumno/pagina.blade.php`. Los menús de cada universidad están en `config/panel_alumno.php` (`menus`). Los estilos están en `public/css/alumno/`.

## Reglas importantes

- **Prácticas fuera de `intentos`.** Se guardan en `practicas` y `practica_respuestas`. Así no alteran el historial, los reportes de docentes ni las estadísticas de simulacros, que leen `intentos`. Se califican con `ResultadoService::clasificarRespuesta`.
- **Una pregunta se puede ver con su clave** si está en un simulacro propio ya calificado o si el alumno la respondió en una práctica (`RepasoService::puedeVer`). La misma regla rige para la revisión, el tutor, las guardadas y los reportes.
- **Examen en curso:** la práctica y el tutor se bloquean desde el servidor (`ContextoAlumnoService::examenActivo`).
- **Comparaciones:** solo entre intentos con la misma universidad, el mismo examen base, la misma cantidad de preguntas y la misma escala (+correcta / −incorrecta). Los demás se muestran por separado.
- **Meta semanal:** preguntas de práctica respondidas de lunes a domingo, en la zona horaria de `config/app.php`. Cada pregunta cuenta una vez por práctica, aunque se recargue o se reintente.
- **Reportes:** un alumno no puede tener dos reportes abiertos de la misma pregunta. Cambiar el estado no modifica la pregunta, que se corrige aparte desde el banco.
- **Preguntas retiradas** (`preguntas.activo = 0`): siguen en «Guardadas», marcadas como retiradas, y ya no aparecen en las prácticas.

## Instalación

```bash
composer install            # incluye barryvdh/laravel-dompdf
php artisan migrate --path=database/migrations/2026_11_19_000002_create_practicas_tables.php
php artisan migrate --path=database/migrations/2026_11_19_000003_create_repaso_tables.php
```

Las migraciones solo crean tablas nuevas. Pruebas: `tests/Feature/EstudioAlumnoTest.php`.
