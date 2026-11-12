<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Services\IntentoService;
use App\Support\PerfilExamenUnmsm;
use App\Support\UniversidadExamen;
use App\Support\BancoUnmsmJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos reales del panel San Marcos: áreas A, C y E, disponibilidad, intentos, historial y estadísticas.
 * Solo lectura; no inventa resultados ni recalcula calificaciones.
 */
class PanelUnmsmService
{
    private const ESTADOS_CERRADOS = ['finalizado', 'enviado_auto'];

    public function __construct(private readonly IntentoService $intentoService) {}

    public function obtener(Usuario $usuario): array
    {
        $areaAlumno = PerfilExamenUnmsm::esArea($usuario->area_unmsm) ? $usuario->area_unmsm : null;
        $todos = $this->intentos($usuario);
        $intentos = $todos->filter(fn ($i) => $i->area_unmsm !== null)->values();
        $otros = $todos->filter(fn ($i) => $i->area_unmsm === null && $i->cerrado)->values();

        $areas = collect(PerfilExamenUnmsm::AREAS)->map(function (string $nombre, string $codigo) use ($intentos, $areaAlumno) {
            $delArea = $intentos->where('area_unmsm', $codigo);
            $calificados = $delArea->filter(fn ($i) => $i->calificado);
            $cuotas = PerfilExamenUnmsm::cuotas($codigo);
            $autorizada = $codigo === $areaAlumno;

            return [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'autorizada' => $autorizada,
                'total' => PerfilExamenUnmsm::TOTAL_PREGUNTAS,
                'minutos' => PerfilExamenUnmsm::minutos(),
                'maximo' => PerfilExamenUnmsm::PUNTAJE_MAXIMO,
                'minimo' => PerfilExamenUnmsm::minimoReferencial($codigo),
                'distribucion' => $cuotas === null ? null : collect($cuotas)->map(fn (int $cantidad, string $asignatura) => [
                    'asignatura' => PerfilExamenUnmsm::etiqueta($asignatura), 'cantidad' => $cantidad,
                ])->values(),
                // El banco solo se revisa para el área del alumno: es la única que puede iniciar.
                'disponibilidad' => $autorizada ? $this->disponibilidad($codigo, $cuotas) : null,
                'en_curso' => $delArea->first(fn ($i) => $i->en_curso),
                'ultimo' => $calificados->first(),
                'mejor' => $calificados->sortByDesc(fn ($i) => (float) $i->puntaje_total)->first(),
                'cantidad_intentos' => $delArea->count(),
            ];
        });

        $propios = $intentos->where('area_unmsm', $areaAlumno)->values();

        return [
            'area_alumno' => $areaAlumno,
            'areas' => $areas,
            'intentos' => $intentos,
            'historial' => $intentos->filter(fn ($i) => $i->cerrado)->values(),
            'otros' => $otros,
            'evolucion' => $propios->filter(fn ($i) => $i->calificado)->sortBy('numero')->values(),
            'asignaturas' => $areaAlumno ? $this->porAsignatura($propios->filter(fn ($i) => $i->calificado)->pluck('id')) : collect(),
        ];
    }

    /** Estado del examen del área: pendiente de distribución, banco insuficiente o disponible. */
    private function disponibilidad(string $area, ?array $cuotas): array
    {
        if ($cuotas === null) {
            return [
                'estado' => 'pendiente',
                'mensaje' => 'El examen del Área '.$area.' aún no está disponible: falta configurar la distribución oficial '
                    .'de las '.PerfilExamenUnmsm::TOTAL_CONOCIMIENTOS.' preguntas de conocimientos (UNMSM '.config('unmsm.proceso').').',
                'faltantes' => [],
            ];
        }
        $cuotas = BancoUnmsmJson::mapearCuotas($area, $cuotas);
        $asignaturas = DB::table('areas')->where('activo', true)->pluck('id', 'nombre');
        $faltantes = [];
        foreach ($cuotas as $asignatura => $cantidad) {
            $hay = isset($asignaturas[$asignatura])
                ? $this->intentoService->consultaElegibles((int) $asignaturas[$asignatura], 5)->count() : 0;
            if ($hay < $cantidad) {
                $faltantes[] = ['asignatura' => PerfilExamenUnmsm::etiqueta($asignatura), 'requiere' => $cantidad, 'hay' => $hay];
            }
        }

        return $faltantes === []
            ? ['estado' => 'disponible', 'mensaje' => null, 'faltantes' => []]
            : ['estado' => 'banco', 'faltantes' => $faltantes,
                'mensaje' => 'El banco de preguntas todavía no alcanza para armar el examen completo de 100 preguntas.'];
    }

    /** Intentos de simulacro del alumno (recientes primero) con su área San Marcos, si la tienen. */
    private function intentos(Usuario $usuario): Collection
    {
        $ahora = now();
        $intentos = DB::table('intentos as i')
            ->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->leftJoin('carreras as c', 'c.id', '=', 'i.carrera_id')
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            // Solo exámenes de San Marcos: no se mezclan intentos de otras universidades.
            ->tap(fn ($q) => UniversidadExamen::filtrar($q, 'E'))
            ->orderByDesc('i.fecha_inicio')->orderByDesc('i.id')
            ->limit(300)
            ->get([
                'i.id', 'i.fecha_inicio', 'i.fecha_limite', 'i.fecha_fin', 'i.estado', 'i.total_preguntas',
                'i.puntaje_total', 'i.correctas', 'i.incorrectas', 'i.en_blanco',
                's.nombre as simulacro_nombre', 's.puntaje_correcta', 'c.nombre as carrera',
            ]);

        $respondidas = DB::table('respuestas_estudiante')->whereIn('intento_id', $intentos->pluck('id'))
            ->whereNotNull('alternativa_id')->selectRaw('intento_id, COUNT(*) as total')
            ->groupBy('intento_id')->pluck('total', 'intento_id');
        $actitudinal = $this->actitudinalPorIntento($intentos->pluck('id'));
        $numeros = [];

        return $intentos->sortBy([['fecha_inicio', 'asc'], ['id', 'asc']])
            ->map(function ($i) use ($ahora, $respondidas, $actitudinal, &$numeros) {
                $i->area_unmsm = PerfilExamenUnmsm::areaDeNombre($i->simulacro_nombre);
                // Número de intento dentro de su área (1, 2, 3…), en orden cronológico.
                $clave = $i->area_unmsm ?? '-';
                $i->numero = $numeros[$clave] = ($numeros[$clave] ?? 0) + 1;
                $i->examen = $i->area_unmsm
                    ? 'Área '.$i->area_unmsm.' · '.PerfilExamenUnmsm::AREAS[$i->area_unmsm]
                    : ($i->simulacro_nombre ?? 'Simulacro');
                $limite = $i->fecha_limite ? Carbon::parse($i->fecha_limite) : null;
                // Un intento abierto pero vencido se muestra cerrado; el servidor lo cierra al consultarlo.
                $vencido = $i->estado === 'en_curso' && $limite && $ahora->gte($limite);
                $i->en_curso = $i->estado === 'en_curso' && ! $vencido;
                $i->cerrado = in_array($i->estado, self::ESTADOS_CERRADOS, true) || $vencido;
                $i->segundos_restantes = $i->en_curso && $limite ? (int) max(0, $ahora->diffInSeconds($limite, false)) : null;
                $i->respondidas = (int) ($respondidas[$i->id] ?? 0);
                $i->calificado = $i->puntaje_total !== null;
                $i->actitudinal = isset($actitudinal[$i->id]) ? round((float) $actitudinal[$i->id], 3) : null;
                $i->maximo = $i->area_unmsm ? PerfilExamenUnmsm::PUNTAJE_MAXIMO
                    : (int) $i->total_preguntas * (float) ($i->puntaje_correcta ?? 0);

                return $i;
            })
            ->sortBy([['fecha_inicio', 'desc'], ['id', 'desc']])->values();
    }

    /** Suma de los puntajes actitudinales calificados de cada intento. */
    public function actitudinalPorIntento(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('respuestas_estudiante as re')
            ->join('preguntas as p', 'p.id', '=', 're.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->whereIn('re.intento_id', $ids)
            ->where('a.nombre', PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL)
            ->whereNotNull('re.puntaje_obtenido')
            ->groupBy('re.intento_id')
            ->selectRaw('re.intento_id, SUM(re.puntaje_obtenido) as total')
            ->pluck('total', 'intento_id');
    }

    /** Rendimiento acumulado por asignatura en los intentos calificados del área. */
    private function porAsignatura(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('resultados_area as ra')
            ->join('areas as a', 'a.id', '=', 'ra.area_id')
            ->whereIn('ra.intento_id', $ids)
            ->groupBy('a.id', 'a.nombre')
            ->selectRaw('a.nombre, SUM(ra.total_preguntas) as total, SUM(ra.aciertos) as aciertos,
                SUM(ra.incorrectas) as incorrectas, SUM(ra.en_blanco) as en_blanco, SUM(ra.puntaje) as puntaje')
            ->orderBy('a.id')
            ->get()
            ->map(function ($fila) {
                $fila->etiqueta = PerfilExamenUnmsm::etiqueta($fila->nombre);
                $fila->actitudinal = $fila->nombre === PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL;
                $fila->porcentaje = $fila->total > 0 ? round(($fila->actitudinal
                    ? max(0, (float) $fila->puntaje) / ((int) $fila->total * PerfilExamenUnmsm::MAXIMO_ACTITUDINAL)
                    : (int) $fila->aciertos / (int) $fila->total) * 100, 1) : 0.0;

                return $fila;
            });
    }
}
