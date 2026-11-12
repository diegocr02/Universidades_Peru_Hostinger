# Banco San Marcos en JSON

La fuente de importación es `database/data/unmsm_preguntas.json`. No se generan
preguntas durante los exámenes. El documento contiene **390 registros**: 230 del
banco anterior y 160 problemas matemáticos nuevos (80 para C y 80 para E).
Hay 176 figuras SVG en el documento, con descripción de su contenido.

Los problemas nuevos son originales del simulador. Su orientación es preuniversitaria,
con razonamiento y varias operaciones, no una reproducción de preguntas oficiales ni
una certificación académica de dificultad. Se consultaron las
[clases modelo de CEPUSM](https://cepre.unmsm.edu.pe/clases-modelo/) y el enfoque de
destrezas cognitivas descrito en el
[reglamento público UNMSM 2021](https://viceacademico.unmsm.edu.pe/wp-content/uploads/2021/03/Reglamento-de-Admisi%C3%B3n-2021-UNMSM.pdf)
como referencia temática, sin copiar sus preguntas ni atribuirles los problemas creados.
No se usa el reglamento de 2021 como distribución vigente.

## Contenido de C y E

Cada área dispone de 16 preguntas nuevas por cada asignatura:

| Asignatura | Familias de problemas |
| --- | --- |
| Aritmética | Mezclas con extracción sucesiva, conjuntos, divisores, promedio ponderado |
| Álgebra | Relaciones entre raíces, logaritmos con dominio, inecuaciones, composición de funciones |
| Geometría | Altura a la hipotenusa, semejanza en trapecios, tangente y secante, distancia de punto a recta |
| Trigonometría | Elevación con dos observadores, identidades, ley de cosenos, ángulo mitad |
| Habilidad Matemática | Caminos restringidos, probabilidad sin reposición, trabajo conjunto, conteo de rectángulos |

Las otras asignaturas y las preguntas actitudinales se conservan desde el banco base.
La selección de matemática C/E usa asignaturas separadas por área y versión. No sortea
estos problemas en UNI ni mezcla matemática C con E. La asignatura visible se normaliza
para que el alumno vea el nombre del curso, no los identificadores del importador.

## Formato

Cada registro contiene `codigo`, `asignatura`, `areas`, `origen`, `tema`, `nivel`,
`enunciado`, cinco `alternativas` A–E, `correcta`, `rubrica`, `solucion` e `imagen`.
En las actitudinales, `correcta` es null y la rúbrica contiene los valores por alternativa.
En problemas ordinarios hay una sola clave y `rubrica` es null. Una imagen tiene el SVG
y una descripción; nunca una URL de un tercero. Las soluciones solo se muestran al finalizar.

Los problemas nuevos incluyen `verificacion`, con familia y parámetros exactos. Ese
campo sirve para comprobar el JSON y no se envía al examen. El comando de verificación
recalcula respuestas mediante fracciones exactas, enumeración, identidades alternativas
y programación dinámica, según el tipo de problema:

```powershell
python scripts/preparar_banco_unmsm_json.py --verificar
php artisan db:seed --class=BancoUnmsmSeeder
```

`preparar_banco_unmsm_json.py` sin argumentos vuelve a materializar el banco de trabajo
a partir de los registros base del JSON. Cambiarlo modifica la versión de importación.
Para editar preguntas manualmente, editar directamente el JSON y verificar las claves.
Una nueva revisión requiere validar sus enunciados, distractores y soluciones académicamente.

## Persistencia e imágenes

El seeder valida todo antes de escribir, incluyendo unicidad, cinco alternativas,
clave, rúbrica y SVG sin contenido activo ni referencias externas. Una pregunta ya
importada conserva texto, clave e identificadores. Los códigos de matemática C/E incluyen
la versión del documento, por lo que una revisión nueva conserva los intentos anteriores.
Las imágenes usan rutas por hash en `storage/app/public/unmsm`.

```powershell
php artisan storage:link
php artisan test --filter="BancoUnmsmJsonTest|ExamenUnmsmTest"
```

En el examen de San Marcos, **Ampliar figura** abre la imagen en un diálogo que permite
cerrar con el botón, Escape o al tocar el fondo. El reloj sigue su curso. Los resultados
conservan la figura y permiten abrirla en otra pestaña. No se borra ni recalifica ningún
intento anterior al importar este banco.
