# Comparación de puntajes de admisión

Consulta de fuentes: 12 de noviembre de 2026.

## Qué muestra el resultado

El resultado compara el puntaje del entrenamiento con una referencia documentada,
indica el mínimo y los puntos que faltan, y distingue un resultado parcial de uno completo.
No otorga la condición de ingresante. Para confirmar ingreso se necesita el proceso
oficial, la carrera, la modalidad, las vacantes y el orden de mérito. No se inventa un
puntaje de corte por carrera ni una clasificación contra los otros alumnos del CNI.

Las referencias están en `config/admision_referencial.php` y la comparación en
`ReferenciaIngresoService`. El cálculo conserva milésimas y admite puntajes negativos.

## UNI

- [Reglamento aprobado por RR 1993-2025-UNI](https://cdnuni.uni.edu.pe/dn/laravel/storage/app/archivos_dn/R.R.%20N%C2%B01993-2025-UNI-DN.pdf), arts. 22, 54, 77 y 78.
- El requisito general de puntaje es alcanzar al menos la mitad del puntaje del primer
  ingresante de la modalidad ordinaria. Además se requiere vacante por orden de mérito.
- El art. 54 asigna 600 puntos a cada una de las tres pruebas de Ciencias e Ingeniería.
  Arquitectura incluye una prueba vocacional adicional y no queda evaluada por este indicador.
- [La UNI identifica al primer puesto 2026-2 y publica una nota de 17.793](https://portal.uni.edu.pe/index.php/rss/item/9085-tres-talentos-17-anos-lideran-computo-general-concurso-admision-uni-2026-2).
- [Trome reporta su puntaje de 1601.400](https://trome.com/actualidad/uni-examen-de-admision-2026-ii-jovenes-de-17-anos-primer-y-segundo-puesto-computo-general-uni-maikel-fabrizio-alejos-y-javier-reyes-examen-universidad-nacional-de-ingenieria-2026-2-foto-video-noticia/).
  La referencia histórica calculada es 1601.400 / 2 = **800.700 puntos**. La fuente del
  puntaje es periodística y se identifica como tal en el resultado. No se deriva el
  puntaje exacto de la nota redondeada ni se presenta como mínimo fijo de una nueva convocatoria.
- Solo se compara cuando hay tres días calificados en una escala compatible. El
  acumulado corresponde al último intento calificado de cada día y se identifica como actual.
  La equivalencia sobre 20 y las cuotas del simulador siguen siendo de entrenamiento.

## San Marcos

- [Publicación oficial de mínimos 2027-I](https://www.unmsm.edu.pe/noticias-y-eventos/noticias/noticia-detalle/unmsm-establece-puntajes-minimos-diferenciados-para-el-examen-de-admision-2027-i).
- El cuerpo completo de la noticia se verificó a través de su API pública:
  `https://www.unmsm.edu.pe/api/noticias?populate=banner&filters[slug][$eq]=unmsm-establece-puntajes-minimos-diferenciados-para-el-examen-de-admision-2027-i`.
- Lima: A (Salud) y C (Ingeniería), **900**; E (Humanidades), **700** puntos.
  La noticia distingue sedes de provincia, con 600 puntos. El simulador compara explícitamente con Lima.
- El mínimo de área no equivale al último puntaje admitido de cada carrera. Superarlo
  no permite certificar una vacante. La comparación usa el área del intento, no el área
  que el alumno tenga actualmente en su perfil.
- Las cuotas por materia y la duración de 90 minutos permanecen como configuración de
  práctica del proyecto. La distribución se sigue señalando como provisional. Este cambio
  no certifica equivalencia integral con el examen oficial ni cambia los intentos anteriores.

## Pruebas

```powershell
php artisan test --filter="ReferenciaIngresoTest|BancoUnmsmJsonTest|ExamenUnmsmTest|CalificacionUniTest"
```

Se incluyen los límites exactos, milésimas por debajo del mínimo, puntajes negativos,
los tres días incompletos de UNI y una escala incompatible. Ningún caso confirma ingreso oficial.
