<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Support\UniversidadExamen;
use Illuminate\Support\Facades\DB;

/**
 * Historial de simulacros CALIFICADOS del usuario autenticado.
 * Usa su puntaje real y el máximo real de cada evaluación.
 * No presume niveles de admisión ni umbrales institucionales.
 */
class ProgresoAlumnoService
{
    /** @param  string|null  $codigoArea  universidad del panel; null usa la del alumno */
    public function obtener(Usuario $usuario, ?string $codigoArea = null): array
    {
        $codigoArea ??= UniversidadExamen::codigoDelAlumno($usuario);

        $filas = DB::table('intentos as i')
            ->join('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            // La gráfica solo compara exámenes de la misma universidad.
            ->when($codigoArea !== null, fn ($q) => UniversidadExamen::filtrar($q, $codigoArea))
            ->whereIn('i.estado', ['finalizado', 'enviado_auto'])
            ->whereNotNull('i.puntaje_total')
            ->where('i.total_preguntas', '>', 0)
            ->where('s.puntaje_correcta', '>', 0)
            ->orderByDesc('i.fecha_inicio')->orderByDesc('i.id')
            ->limit(61)
            ->get([
                'i.id', 'i.fecha_inicio', 'i.puntaje_total', 'i.total_preguntas',
                's.nombre as simulacro', 's.puntaje_correcta',
            ]);

        $truncado = $filas->count() > 60;
        $filas = $filas->take(60)->reverse()->values();
        $cantidad = $filas->count();

        $puntos = $filas->map(function ($fila, $indice) use ($cantidad) {
            $maximo = (int) $fila->total_preguntas * (float) $fila->puntaje_correcta;
            $puntaje = (float) $fila->puntaje_total;
            // Mostrar también puntajes negativos reales; la gráfica empieza en 0%.
            $porcentaje = round(max(0, min(100, $puntaje / $maximo * 100)), 1);

            return [
                'id' => (int) $fila->id,
                'nombre' => $fila->simulacro,
                'fecha' => $fila->fecha_inicio,
                'puntaje' => round($puntaje, 2),
                'maximo' => round($maximo, 2),
                'porcentaje' => $porcentaje,
                'x' => round($cantidad === 1 ? 460 : 52 + $indice * 816 / ($cantidad - 1), 2),
                'y' => round(242 - $porcentaje * 1.9, 2),
            ];
        })->all();

        $ultimo = $cantidad ? $puntos[$cantidad - 1] : null;
        $anterior = $cantidad > 1 ? $puntos[$cantidad - 2] : null;
        $diferencia = $anterior ? round($ultimo['porcentaje'] - $anterior['porcentaje'], 1) : null;
        $trayectoria = implode(' ', array_map(
            fn (array $p, int $i) => ($i === 0 ? 'M ' : 'L ').$p['x'].' '.$p['y'],
            $puntos,
            array_keys($puntos)
        ));

        return [
            'puntos' => $puntos,
            'ultimo' => $ultimo,
            'cantidad' => $cantidad,
            'truncado' => $truncado,
            'diferencia' => $diferencia,
            'trayectoria' => $trayectoria,
        ];
    }
}
