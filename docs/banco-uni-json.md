# Banco UNI en JSON

El banco editable está en `database/data/uni_preguntas.json`, en UTF-8. Contiene las 180 preguntas originales, con los mismos códigos, alternativas, claves y soluciones. Es material de práctica; no es un examen oficial.

Cada elemento de `preguntas` tiene esta estructura:

```json
{
  "codigo": "UNI_AM_01",
  "asignatura": "AM",
  "enunciado": "¿Qué número continúa la serie 2; 6; 12; 20; 30; …?",
  "nivel": "intermedio",
  "alternativas": {
    "A": "40",
    "B": "44",
    "C": "42",
    "D": "36",
    "E": "48"
  },
  "correcta": "C",
  "solucion": "Diferencias 4, 6, 8, 10, 12: 30 + 12 = 42."
}
```

El ejemplo ilustra el formato. La clave siempre es la letra que corresponde a la respuesta correcta en ese objeto.

- `codigo`: identificador único y estable, como `UNI_AM_01`. No lo cambies para reordenar una pregunta.
- `asignatura`: código de curso de `App\Support\PerfilExamenCni::CURSOS`, como `AM`, `AV`, `AR`, `FI` o `QU`.
- `nivel`: `basico`, `intermedio` o `avanzado`.
- `alternativas`: exactamente cinco textos, identificados por `A`, `B`, `C`, `D` y `E` (hasta 500 caracteres cada uno).
- `correcta`: letra de la única respuesta correcta.
- `solucion`: explicación de la respuesta.

Después de guardar el archivo, carga las preguntas nuevas desde la raíz de Laravel:

```bash
php artisan db:seed --class=BancoUniSeeder --force
```

El cargador valida todo el JSON antes de escribir. Agrega códigos nuevos y omite los existentes: editar o eliminar una pregunta del JSON no modifica ni elimina la pregunta ya cargada. Así se conservan los exámenes y las respuestas registradas. Las correcciones de preguntas existentes requieren una revisión separada; no uses otro código para modificar una pregunta histórica sin valorar ese efecto.

El JSON contiene las respuestas. Consérvalo fuera de `public/` y de `public_html/`; no lo sirvas mediante una ruta pública.
