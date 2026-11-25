# Plantilla reutilizable de resultados de simulacros

Archivo: [plantilla-resultados.html](plantilla-resultados.html)

Este HTML es una **maqueta fija independiente**: se abre con doble clic en cualquier navegador y **no necesita Laravel, MySQL ni internet**. Incluye resumen del puntaje, aciertos, errores, estadísticas por asignatura y una pregunta corregida con la alternativa correcta en verde y la selección incorrecta en rojo.

## Para adaptar al simulador de tu universidad

1. Cambia "NOMBRE DE LA UNIVERSIDAD" por tu institución y adapta colores e insignia.
2. Sustituye los datos de demostración por resultados reales del examen terminado.
3. En Laravel, utiliza las tablas \`intentos\`, \`respuestas_estudiante\`, \`preguntas\`, \`alternativas\`, \`areas\` y \`resultados_area\`; no publiques la clave correcta en las respuestas de Livewire durante el examen.
4. Repite las secciones de asignatura y preguntas con bucles Blade según el número de resultados.
5. Asegura que el usuario autenticado sea propietario del intento y que el intento haya terminado **antes** de revelar soluciones.
6. Mantén los demás paneles independientes y la calificación bajo el servicio existente.

La versión **dinámica conectada a los datos UNDC** está en:
- \`resources/views/alumno/undc/resultados.blade.php\`
- \`public/css/alumno/resultados-undc.css\`
- \`app/Http/Controllers/Alumno/OrientacionController.php\`

La plantilla fija es un ejemplo visual para compartir; no sustituye la implementación dinámica.
