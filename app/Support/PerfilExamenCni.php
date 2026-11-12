<?php

namespace App\Support;

/** Una sola distribución para el importador y los exámenes independientes A–E. */
final class PerfilExamenCni
{
    public const CURSOS = [
        'AM' => ['Habilidad Matemática', 'habilidad_logico_matematica'],
        'AV' => ['Razonamiento Verbal', 'habilidad_verbal'],
        'IN' => ['Inglés', 'habilidad_verbal'],
        'AR' => ['Aritmética', 'conocimientos'],
        'AL' => ['Álgebra', 'conocimientos'],
        'GE' => ['Geometría', 'conocimientos'],
        'TR' => ['Trigonometría', 'conocimientos'],
        'FI' => ['Física', 'conocimientos'],
        'QU' => ['Química', 'conocimientos'],
        'BI' => ['Biología', 'conocimientos'],
        'LE' => ['Lenguaje', 'conocimientos'],
        'LI' => ['Literatura', 'conocimientos'],
        'PS' => ['Psicología', 'conocimientos'],
        'CV' => ['Educación Cívica', 'conocimientos'],
        'EC' => ['Economía', 'conocimientos'],
        'HP' => ['Historia del Perú', 'conocimientos'],
        'HU' => ['Historia Universal', 'conocimientos'],
        'GG' => ['Geografía', 'conocimientos'],
        'FL' => ['Filosofía y Lógica', 'conocimientos'],
        'CG' => ['Cultura General', 'conocimientos'],
    ];

    public const DISTRIBUCION = [
        'A' => ['AM' => 10, 'AV' => 8, 'IN' => 2, 'AR' => 4, 'AL' => 4, 'GE' => 3, 'TR' => 2,
            'BI' => 20, 'QU' => 14, 'FI' => 8,
            'LE' => 7, 'LI' => 3, 'PS' => 5, 'CV' => 2, 'HP' => 2, 'HU' => 2, 'GG' => 2, 'EC' => 1, 'FL' => 1],
        'B' => ['AM' => 10, 'AV' => 8, 'IN' => 2, 'AR' => 6, 'AL' => 7, 'GE' => 6, 'TR' => 4,
            'FI' => 12, 'QU' => 12, 'BI' => 12,
            'LE' => 6, 'LI' => 3, 'PS' => 3, 'CV' => 2, 'HP' => 2, 'HU' => 2, 'GG' => 2, 'FL' => 1],
        'C' => ['AM' => 10, 'AV' => 8, 'IN' => 2, 'AR' => 6, 'AL' => 8, 'GE' => 8, 'TR' => 6,
            'FI' => 14, 'QU' => 10, 'BI' => 4,
            'LE' => 6, 'LI' => 3, 'PS' => 3, 'CV' => 2, 'HP' => 2, 'HU' => 2, 'GG' => 3, 'EC' => 1, 'FL' => 2],
        'D' => ['AM' => 10, 'AV' => 8, 'IN' => 2, 'AR' => 4, 'AL' => 4, 'GE' => 4, 'TR' => 2,
            'LE' => 8, 'LI' => 4, 'PS' => 6, 'CV' => 3,
            'EC' => 8, 'HP' => 3, 'HU' => 3, 'GG' => 4, 'FL' => 4,
            'FI' => 8, 'QU' => 7, 'BI' => 8],
        'E' => ['AM' => 10, 'AV' => 8, 'IN' => 2, 'AR' => 4, 'AL' => 3, 'GE' => 3, 'TR' => 1,
            'FI' => 3, 'QU' => 3, 'BI' => 4,
            'LE' => 10, 'LI' => 6, 'PS' => 5, 'CV' => 5, 'HP' => 6, 'HU' => 6, 'GG' => 6, 'EC' => 4, 'FL' => 6, 'CG' => 5],
    ];

    /**
     * Formato UNI en tres jornadas independientes (solo área B).
     * Cada día es una plantilla propia con 180 minutos; la calificación no cambia.
     */
    public const UNI_MINUTOS = 180;

    public const UNI_DIAS = [
        1 => [
            'titulo' => 'Aptitud Académica y Humanidades',
            'cuotas' => ['AM' => 30, 'AV' => 30, 'IN' => 5, 'LE' => 8, 'LI' => 5, 'HP' => 4, 'HU' => 4,
                'GG' => 4, 'EC' => 3, 'FL' => 3, 'PS' => 2, 'CV' => 2],
        ],
        2 => [
            'titulo' => 'Matemática',
            'cuotas' => ['AR' => 10, 'AL' => 10, 'GE' => 10, 'TR' => 10],
        ],
        3 => [
            'titulo' => 'Física y Química',
            'cuotas' => ['FI' => 20, 'QU' => 20],
        ],
    ];

    /** Nombre fijo de la plantilla de un día; permite reconocer el día de cada intento. */
    public static function prefijoDiaUni(int $dia): string
    {
        return 'Examen UNI · Día '.$dia.' · ';
    }

    /**
     * Nombre de la plantilla vigente de un día. La versión incluye cuotas, tiempo y escala,
     * así un cambio de escala crea otra plantilla y los intentos anteriores no se recalifican.
     */
    public static function nombreDiaUni(int $dia, array $valores): string
    {
        $firma = [$dia, self::UNI_DIAS[$dia]['cuotas'], self::ORDEN, self::UNI_MINUTOS, 5,
            array_map(fn ($v) => round((float) $v, 3), $valores)];

        return self::prefijoDiaUni($dia).substr(hash('sha256', json_encode($firma)), 0, 10);
    }

    /** Día UNI (1–3) a partir del nombre del simulacro, o null si no es una jornada UNI. */
    public static function diaUniDeNombre(?string $nombre): ?int
    {
        foreach (array_keys(self::UNI_DIAS) as $dia) {
            if ($nombre !== null && str_starts_with($nombre, self::prefijoDiaUni($dia))) {
                return $dia;
            }
        }

        return null;
    }

    // Orden fijo de cursos; las preguntas de cada curso se sortean por intento.
    public const ORDEN = [
        'AV', 'IN', 'AM',
        'AR', 'AL', 'GE', 'TR',
        'LE', 'LI', 'PS',
        'CV', 'HP', 'HU', 'GG', 'EC',
        'FL', 'FI', 'QU', 'BI', 'CG',
    ];

    public static function posicion(string $asignatura): int
    {
        // Compatibilidad con bancos anteriores sin alterar sus preguntas ni intentos.
        if (in_array($asignatura, ['Razonamiento Matemático', 'Habilidad Lógico-Matemática'], true)) {
            $asignatura = 'Habilidad Matemática';
        }
        foreach (self::ORDEN as $indice => $prefijo) {
            if (self::CURSOS[$prefijo][0] === $asignatura) {
                return $indice;
            }
        }

        return count(self::ORDEN);
    }
}
