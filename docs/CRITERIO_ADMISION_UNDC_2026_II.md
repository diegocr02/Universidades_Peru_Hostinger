# UNDC — Indicador de admisión ordinaria 2026-II

## Qué hace el sistema
Después de finalizar un simulacro UNDC A/B, se presenta el **puntaje real del intento**,
su rendimiento relativo a la escala de ese examen y una orientación sobre admisión.
Se muestra también en "Mis resultados". No se recalifican ni modifican las tablas.

**No mostrar "INGRESASTE" con un simple número:** una vacante real depende de
las reglas de la convocatoria, el orden de mérito de los postulantes por carrera
y la cantidad oficial de vacantes. Un resultado de práctica NO equivale a un resultado oficial.

## Fuentes y distinciones importantes
- UNDC, RCO 382-2026-UNDC, 24/06/2026: aprobó el prospecto ordinario 2026-II (51 folios).
  https://www.gob.pe/institucion/unicanete/normas-legales/tipos/235-resolucion-de-comision-organizadora
- UNDC, preguntas por tipo A/B 2026-II: 100 preguntas, dos tipos de examen.
  https://admision.undc.edu.pe/public/docs/PREGUNTAS%202026-II.pdf
- UNDC, información sobre cobertura de admisión **2018**: mínimo de **120**.
  https://web.undc.edu.pe/ingresantes-por-cobertura-de-la-carrera-de-administracion-admision-2018/
- UNDC, CEPRE **2020**: mínimo **118,5**, SOLO para ingreso directo CEPRE,
  además de obtener vacante según orden de mérito.
  https://www.undc.edu.pe/cepre/wp-content/uploads/2021/02/PLAN_CEPRE_Virtual_2020.pdf

No se pudo confirmar un puntaje mínimo **oficial de ordinario 2026-II** en el
prospecto íntegro. En consecuencia, la configuración predeterminada es **null**.
**Ni 118, ni 118,5 ni 120 se aplican automáticamente al ordinario 2026-II.**

## Activación solo después de conseguir y comprobar el prospecto oficial vigente
Configurar en .env únicamente si el mínimo y la escala oficial están documentados
para la **misma convocatoria y modalidad**, por ejemplo:

\`\`\`dotenv
UNDC_ADMISION_MINIMO_2026_II=
UNDC_ADMISION_MAXIMO_2026_II=
\`\`\`

Ambos valores deben pertenecer a la misma escala que usa realmente el examen
del sistema. La configuración de demostración existente de UNDC es 100 preguntas,
+1 por correcta y -0,2 por incorrecta (máximo 100); un mínimo en otra escala
no puede compararse con ella directamente.

Después de verificar las reglas y configurar ambos valores, limpiar configuración:
\`php artisan config:clear\`. Si las escalas no coinciden, la pantalla mantiene
"pendiente de verificación" sin emitir resultados de admisión inventados.

## Pruebas
\`\`\`bash
php artisan test --filter=IndicadorAdmisionUndcServiceTest
php artisan view:cache
\`\`\`
