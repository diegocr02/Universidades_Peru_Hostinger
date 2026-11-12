<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Database\Query\Builder;

/**
 * Universidad a la que pertenece cada examen, según el nombre de su plantilla.
 * Los historiales y la gráfica de progreso solo muestran intentos de la universidad
 * del panel abierto: un alumno que cambia de universidad no mezcla resultados.
 */
final class UniversidadExamen
{
    /** Prefijos de nombre de simulacro por código de área de postulación. */
    public const PREFIJOS = [
        'A' => ['UNDC - Admisión Ordinaria Tipo ', 'Examen independiente CNI · Área A · '],
        'B' => ['Examen UNI · Día ', 'Examen independiente CNI · Área B · '],
        'C' => ['Práctica general UNFV · ', 'Examen independiente CNI · Área C · '],
        'E' => ['Examen San Marcos · Área ', 'Examen independiente CNI · Área E · '],
    ];

    /**
     * Restringe una consulta a los simulacros de la universidad indicada:
     *  - su plantilla propia (UNI, San Marcos, UNFV, UNDC), o
     *  - un simulacro asignado a esa área que no sea plantilla de ninguna universidad.
     * Así los exámenes UNDC, asignados a las cinco áreas, solo cuentan para la UNDC.
     */
    public static function filtrar(Builder $consulta, string $codigo, string $columnaNombre = 's.nombre', string $columnaId = 's.id'): Builder
    {
        // Los prefijos son constantes sin comodines (% o _): no requieren escape en MariaDB ni SQLite.
        $propios = self::PREFIJOS[$codigo] ?? [];
        $todos = array_merge(...array_values(self::PREFIJOS));

        return $consulta->where(function (Builder $q) use ($propios, $todos, $codigo, $columnaNombre, $columnaId) {
            foreach ($propios as $prefijo) {
                $q->orWhere($columnaNombre, 'like', $prefijo.'%');
            }
            $q->orWhere(function (Builder $asignado) use ($todos, $codigo, $columnaNombre, $columnaId) {
                foreach ($todos as $prefijo) {
                    $asignado->where($columnaNombre, 'not like', $prefijo.'%');
                }
                $asignado->whereExists(fn (Builder $e) => $e->selectRaw('1')
                    ->from('area_postulacion_simulacro as aps_u')
                    ->join('areas_postulacion as ap_u', 'ap_u.id', '=', 'aps_u.area_postulacion_id')
                    ->whereColumn('aps_u.simulacro_id', $columnaId)
                    ->where('ap_u.codigo', $codigo));
            });
        });
    }

    /** Código de área (universidad) actual del alumno, o null si aún no eligió. */
    public static function codigoDelAlumno(Usuario $usuario): ?string
    {
        return ($usuario->areaPostulacion()->first() ?? $usuario->carrera?->areaPostulacion)?->codigo;
    }
}
