<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Support\UniversidadExamen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Historial y evolución de simulacros calificados de la universidad actual del alumno.
 *
 * Solo se comparan intentos equivalentes: misma universidad (filtro del panel), mismo examen
 * base (plantilla sin su versión), misma cantidad de preguntas y misma escala
 * (+correcta / −incorrecta). Grupos distintos se muestran por separado, nunca mezclados.
 */
class ProgresoComparableService
{
    /** Intentos calificados (recientes primero) con su grupo de comparación. */
    public function intentos(Usuario $usuario, string $codigo, ?string $desde = null, ?string $hasta = null): Collection
    {
        return DB::table('intentos as i')
            ->join('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            ->whereIn('i.estado', ['finalizado', 'enviado_auto'])
            ->whereNotNull('i.puntaje_total')
            ->where('i.total_preguntas', '>', 0)
            ->tap(fn ($q) => UniversidadExamen::filtrar($q, $codigo))
            ->when($desde, fn ($q, $d) => $q->where('i.fecha_inicio', '>=', Carbon::parse($d)->startOfDay()))
            ->when($hasta, fn ($q, $h) => $q->where('i.fecha_inicio', '<=', Carbon::parse($h)->endOfDay()))
            ->orderByDesc('i.fecha_inicio')->orderByDesc('i.id')
            ->limit(200)
            ->get(['i.id', 'i.fecha_inicio', 'i.puntaje_total', 'i.total_preguntas', 'i.correctas', 'i.incorrectas',
                'i.en_blanco', 'i.doble_marca', 's.nombre as simulacro', 's.puntaje_correcta', 's.puntaje_incorrecta'])
            ->map(function ($i) {
                $maximo = (int) $i->total_preguntas * (float) $i->puntaje_correcta;
                $puntaje = (float) $i->puntaje_total;
                $examen = self::examenBase($i->simulacro);

                return (object) [
                    'id' => (int) $i->id,
                    'fecha' => Carbon::parse($i->fecha_inicio),
                    'examen' => $examen,
                    'puntaje' => $puntaje,
                    'maximo' => $maximo,
                    // Porcentaje del máximo; puede ser 0 si hubo penalización mayor a lo ganado.
                    'porcentaje' => $maximo > 0 ? round(max(0, min(100, $puntaje / $maximo * 100)), 1) : 0.0,
                    'total' => (int) $i->total_preguntas,
                    'correctas' => (int) $i->correctas,
                    'incorrectas' => (int) $i->incorrectas,
                    'en_blanco' => (int) $i->en_blanco + (int) $i->doble_marca,
                    'escala' => ['correcta' => (float) $i->puntaje_correcta, 'incorrecta' => (float) $i->puntaje_incorrecta],
                    'grupo' => md5(implode('|', [$examen, (int) $i->total_preguntas, (float) $i->puntaje_correcta, (float) $i->puntaje_incorrecta])),
                ];
            });
    }

    /**
     * Grupos comparables con su evolución (antiguo → reciente), cambio y puntos del gráfico SVG.
     *
     * @return array{grupos: array, total_intentos: int, comparables: bool}
     */
    public function grupos(Usuario $usuario, string $codigo, ?string $desde = null, ?string $hasta = null): array
    {
        $intentos = $this->intentos($usuario, $codigo, $desde, $hasta);
        $grupos = $intentos->groupBy('grupo')->map(function (Collection $grupo, string $clave) {
            $orden = $grupo->sortBy(fn ($i) => [$i->fecha->timestamp, $i->id])->values();
            $primero = $orden->first();
            $ultimo = $orden->last();

            return [
                'clave' => $clave,
                'examen' => $primero->examen,
                'intentos' => $orden->count(),
                'escala' => [
                    'preguntas' => $primero->total,
                    'correcta' => $primero->escala['correcta'],
                    'incorrecta' => $primero->escala['incorrecta'],
                    'maximo' => $primero->maximo,
                ],
                'evolucion' => $orden->map(fn ($i) => ['id' => $i->id, 'fecha' => $i->fecha->format('d/m/Y'),
                    'puntaje' => $i->puntaje, 'maximo' => $i->maximo, 'porcentaje' => $i->porcentaje])->all(),
                'cambio' => $orden->count() > 1 ? round($ultimo->porcentaje - $primero->porcentaje, 1) : null,
                'mejor' => $orden->max('porcentaje'),
                'reforzar' => $this->asignaturasDebiles($orden->pluck('id')->all()),
                'grafico' => $this->grafico($orden->take(-30)->values()),
            ];
        })->sortByDesc('intentos')->values();

        return ['grupos' => $grupos->all(), 'total_intentos' => $intentos->count(), 'comparables' => $grupos->count() <= 1];
    }

    /** «Examen UNI · Día 1 · a1b2c3» → «Examen UNI · Día 1» (quita la versión de la plantilla). */
    public static function examenBase(?string $nombre): string
    {
        $partes = array_filter(explode(' · ', (string) $nombre),
            fn ($parte) => ! preg_match('/^[0-9a-f]{6,}$/i', trim($parte)));

        return trim(implode(' · ', $partes)) ?: 'Simulacro';
    }

    /** Hasta 3 asignaturas con menos del 60 % de aciertos acumulados en los intentos dados. */
    public function asignaturasDebiles(array $intentos): array
    {
        if ($intentos === []) {
            return [];
        }

        return DB::table('resultados_area as ra')
            ->join('areas as a', 'a.id', '=', 'ra.area_id')
            ->whereIn('ra.intento_id', $intentos)
            ->whereNotIn('a.nombre', config('tutor.asignaturas_excluidas', []))
            ->groupBy('a.nombre')
            ->selectRaw('a.nombre, SUM(ra.aciertos) as aciertos, SUM(ra.total_preguntas) as total')
            ->get()
            ->filter(fn ($f) => (int) $f->total >= 2)
            ->map(fn ($f) => ['asignatura' => ContextoAlumnoService::asignaturaVisible($f->nombre),
                'porcentaje' => round((int) $f->aciertos / (int) $f->total * 100, 1)])
            ->filter(fn ($f) => $f['porcentaje'] < 60)
            ->sortBy('porcentaje')->take(3)->values()->all();
    }

    /** Coordenadas para un SVG de 1000×200 (eje Y = % del máximo de la misma escala). */
    private function grafico(Collection $intentos): array
    {
        $n = $intentos->count();
        $puntos = $intentos->map(fn ($i, $k) => [
            'x' => round($n === 1 ? 500 : 50 + $k * 920 / ($n - 1), 1),
            'y' => round(180 - $i->porcentaje * 1.6, 1),
            'porcentaje' => $i->porcentaje,
            'fecha' => $i->fecha->format('d/m'),
        ])->all();

        return [
            'puntos' => $puntos,
            'trazo' => implode(' ', array_map(fn ($p, $k) => ($k === 0 ? 'M' : 'L').$p['x'].' '.$p['y'], $puntos, array_keys($puntos))),
        ];
    }
}
