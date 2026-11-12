<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Modalidad San Marcos: tres exámenes (áreas A, C y E) de 100 preguntas, 90 minutos y 2 000 puntos.
 * Vive dentro del área de postulación E del simulador; las cantidades se leen de config/unmsm.php.
 */
final class PerfilExamenUnmsm
{
    /** Código de areas_postulacion que corresponde a San Marcos en este proyecto. */
    public const MODALIDAD = 'E';

    public const AREAS = [
        'A' => 'Ciencias de la Salud',
        'C' => 'Ingeniería',
        'E' => 'Humanidades y Ciencias Jurídicas y Sociales',
    ];

    public const TOTAL_PREGUNTAS = 100;

    public const TOTAL_CONOCIMIENTOS = 70;

    public const PUNTAJE_CORRECTA = 20.0;

    public const PUNTAJE_INCORRECTA = -1.125;

    public const PUNTAJE_BLANCO = 0.0;

    public const MAXIMO_ACTITUDINAL = 20.0;

    public const PUNTAJE_MAXIMO = 2000.0;

    /** Asignatura del banco que se califica con rúbrica en lugar de correcta/incorrecta. */
    public const ASIGNATURA_ACTITUDINAL = 'Actitudinal';

    public static function esArea(?string $codigo): bool
    {
        return $codigo !== null && isset(self::AREAS[$codigo]);
    }

    public static function minutos(): int
    {
        return (int) config('unmsm.minutos', 90);
    }

    /** @return array<string, int> asignatura => cantidad de las 30 preguntas comunes. */
    public static function fijas(): array
    {
        return array_map(fn (array $f) => (int) $f['cantidad'], config('unmsm.fijas', []));
    }

    /** Nombre visible de una asignatura (p. ej. "Razonamiento Verbal" se muestra como "Habilidad Verbal"). */
    public static function etiqueta(string $asignatura): string
    {
        $asignatura = preg_replace('/^San Marcos [CE] [a-f0-9]{10} · /u', '', $asignatura);
        return config('unmsm.fijas.'.$asignatura.'.etiqueta') ?? $asignatura;
    }

    /** @return array<string, int> */
    public static function conocimientos(string $area): array
    {
        return array_map('intval', config('unmsm.conocimientos.'.$area, []));
    }

    /**
     * Distribución completa (100 preguntas, en orden de examen) o null si todavía no está configurada.
     *
     * @return array<string, int>|null
     */
    public static function cuotas(string $area): ?array
    {
        if (! self::esArea($area)) {
            return null;
        }
        $conocimientos = self::conocimientos($area);
        if (array_sum($conocimientos) !== self::TOTAL_CONOCIMIENTOS || min($conocimientos ?: [0]) < 1) {
            return null;
        }
        $cuotas = self::fijas();
        foreach ($conocimientos as $asignatura => $cantidad) {
            if (isset($cuotas[$asignatura])) {
                return null; // Una asignatura no puede estar en dos bloques.
            }
            $cuotas[$asignatura] = $cantidad;
        }

        return array_sum($cuotas) === self::TOTAL_PREGUNTAS ? $cuotas : null;
    }

    public static function minimoReferencial(string $area): ?int
    {
        $valor = config('unmsm.minimo_referencial.'.$area);

        return $valor === null ? null : (int) $valor;
    }

    /** Prefijo fijo del nombre de la plantilla; permite reconocer modalidad y área de cada intento. */
    public static function prefijo(string $area): string
    {
        return 'Examen San Marcos · Área '.$area.' · ';
    }

    /** Área San Marcos (A, C o E) a partir del nombre del simulacro, o null si no es de San Marcos. */
    public static function areaDeNombre(?string $nombre): ?string
    {
        foreach (array_keys(self::AREAS) as $area) {
            if ($nombre !== null && str_starts_with($nombre, self::prefijo($area))) {
                return $area;
            }
        }

        return null;
    }

    /** Las bases importadas sin la migración nueva siguen funcionando sin preguntas actitudinales. */
    public static function hayRubrica(): bool
    {
        static $existe = null;

        return $existe ??= Schema::hasColumn('alternativas', 'puntaje_actitudinal');
    }
}
