# Modalidad San Marcos (UNMSM)

El área de postulación **E** del simulador corresponde a San Marcos. Dentro de ella, cada alumno
elige su área de práctica San Marcos (**A** Ciencias de la Salud, **C** Ingeniería o
**E** Humanidades y Ciencias Jurídicas y Sociales). Puede cambiar entre ellas desde
las tarjetas del panel. Los intentos anteriores conservan sus preguntas y resultados.

| Dato | Valor |
|---|---|
| Preguntas | 100: 10 actitudinales + 10 Habilidad Verbal + 10 Habilidad Lógico-Matemática + 70 conocimientos |
| Tiempo | 90 minutos (`config/unmsm.php` → `minutos`), controlado por el servidor |
| Ordinarias | +20 correcta · −1.125 incorrecta · 0 en blanco |
| Actitudinales | 0 a 20 según la rúbrica de cada alternativa; nunca descuentan |
| Máximo | 2 000 puntos |

## Puesta en marcha (local u Hostinger)

```bash
php artisan migrate            # 2 columnas opcionales: usuarios.area_unmsm y alternativas.puntaje_actitudinal
php artisan storage:link       # sirve las imágenes de storage/app/public en /storage
php artisan db:seed --class=BancoUnmsmSeeder
```

`BancoUnmsmSeeder` también se ejecuta dentro de `DatabaseSeeder`. Es idempotente: cada pregunta
tiene un código fijo (`SM-ALG-01`, `SM-FIS-07`, …), así que volver a ejecutarlo actualiza el banco
sin duplicar ni borrar nada.

## Banco generado (230 preguntas)

- **Numéricas, con datos y respuestas calculados** (`database/seeders/Unmsm/GeneradorPreguntas.php`):
  Aritmética, Álgebra, Geometría, Trigonometría, Física y Habilidad Lógico-Matemática, 16 cada una.
  48 llevan figura SVG (`database/seeders/Unmsm/FigurasSvg.php`): triángulos, circunferencias,
  trapecios, plano cartesiano, gráficas v–t, circuitos, plano inclinado, barras y sectores. Se
  guardan en `storage/app/public/unmsm/<código>.svg` y la pregunta apunta a `storage/unmsm/<código>.svg`.
- **Teoría** (`database/seeders/data/unmsm/teoria.php`): Biología, Química, Lenguaje, Literatura,
  Psicología, Educación Cívica, Geografía, Economía, Filosofía y Lógica, Historia del Perú e
  Historia Universal, 10 cada una, con explicación.
- **Habilidad Verbal y actitudinales** (`database/seeders/data/unmsm/habilidades.php`), 12 cada una.

## Pendiente de datos oficiales

- `config/unmsm.php` → `conocimientos`: la distribución por asignatura es **provisional**
  (`'provisional' => true`). El área A sigue un temario público no oficial; C y E son una propuesta
  del simulador. Al reemplazarla por el reglamento oficial, poner `'provisional' => false`.
  Una distribución nueva crea una plantilla nueva: los intentos anteriores no cambian.
- La rúbrica actitudinal (20 / 15 / 10 / 5 / 0) es del simulador, no la oficial de la UNMSM.
- Los puntajes mínimos (900 / 900 / 700) se muestran solo como referencia del proceso 2027-I.
