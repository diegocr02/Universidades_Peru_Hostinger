<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Support\PerfilExamenCni;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos reales del panel UNI: estado de cada día, intentos, historial y resumen.
 * Solo lectura; no recalifica intentos. Los puntajes se calculan en CalificacionUniService.
 */
class PanelUniService
{
    private const ESTADOS_CERRADOS = ['finalizado', 'enviado_auto', 'anulado'];

    public function __construct(private readonly CalificacionUniService $calificacion) {}

    public function obtener(Usuario $usuario): array
    {
        $intentos = $this->intentos($usuario);
        // Estado y resumen solo con la plantilla vigente de cada día: no se mezclan
        // intentos calificados con escalas anteriores (esos quedan en el historial).
        $vigentes = $intentos->filter(fn ($i) => $i->vigente);

        $dias = collect(PerfilExamenCni::UNI_DIAS)->map(function (array $config, int $dia) use ($vigentes) {
            $delDia = $vigentes->where('dia', $dia);
            $enCurso = $delDia->first(fn ($i) => $i->en_curso);
            $ultimoCerrado = $delDia->first(fn ($i) => $i->cerrado);
            $escala = $this->calificacion->escala($dia);

            return [
                'numero' => $dia,
                'titulo' => $config['titulo'],
                'cuotas' => collect($config['cuotas'])
                    ->map(fn (int $cantidad, string $prefijo) => [
                        'curso' => PerfilExamenCni::CURSOS[$prefijo][0], 'cantidad' => $cantidad,
                    ])->values(),
                'total' => array_sum($config['cuotas']),
                'minutos' => PerfilExamenCni::UNI_MINUTOS,
                'escala' => $escala,
                'estado' => $enCurso ? 'en_curso' : ($ultimoCerrado ? 'finalizado' : 'disponible'),
                'en_curso' => $enCurso,
                'ultimo' => $ultimoCerrado,
                // Puntaje del último intento calificado del día (null si aún no hay).
                'calificacion' => $ultimoCerrado?->calificacion,
                'cantidad_intentos' => $delDia->count(),
            ];
        });

        return [
            'dias' => $dias,
            'total_preguntas' => $dias->sum('total'),
            'dias_completados' => $dias->filter(fn ($d) => $d['calificacion'] !== null)->count(),
            'intentos' => $intentos,
            'historial' => $intentos->filter(fn ($i) => $i->cerrado)->values(),
            'resumen' => $this->calificacion->acumulado(
                $dias->mapWithKeys(fn ($d) => [$d['numero'] => $d['calificacion']])->all()
            ),
        ];
    }

    /** Calificación UNI de un intento ya calificado, con la escala de su propia plantilla. */
    public function calificacionDeIntento(object $intento): ?array
    {
        if ($intento->puntaje_total === null || $intento->dia === null) {
            return null;
        }
        $maximo = (int) $intento->total_preguntas * (float) $intento->puntaje_correcta;

        return $this->calificacion->puntajeDia(
            (int) $intento->correctas,
            (int) $intento->incorrectas,
            // La doble marca no suma ni resta: se muestra junto a las preguntas en blanco.
            (int) $intento->en_blanco + (int) $intento->doble_marca,
            ['correcta' => (float) $intento->puntaje_correcta, 'incorrecta' => (float) $intento->puntaje_incorrecta],
            $maximo,
        );
    }

    /** Intento UNI de un alumno con su día y su calificación, para la pantalla de resultados. */
    public function intentoDelAlumno(Usuario $usuario, int $intentoId): ?object
    {
        return $this->intentos($usuario, $intentoId)->first(fn ($i) => $i->dia !== null);
    }

    /** Intentos de simulacro del alumno (los más recientes primero), con su día UNI si lo tiene. */
    private function intentos(Usuario $usuario, ?int $soloIntento = null): Collection
    {
        $ahora = now();
        $plantillasVigentes = collect(array_keys(PerfilExamenCni::UNI_DIAS))
            ->mapWithKeys(fn (int $dia) => [$dia => PerfilExamenCni::nombreDiaUni(
                $dia, $this->calificacion->valoresPlantilla($dia)
            )]);

        return DB::table('intentos as i')
            ->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            ->when($soloIntento !== null, fn ($q) => $q->where('i.id', $soloIntento))
            ->orderByDesc('i.fecha_inicio')->orderByDesc('i.id')
            ->limit(200)
            ->get([
                'i.id', 'i.simulacro_id', 'i.fecha_inicio', 'i.fecha_limite', 'i.fecha_fin', 'i.estado',
                'i.total_preguntas', 'i.puntaje_total', 'i.correctas', 'i.incorrectas', 'i.en_blanco',
                'i.doble_marca', 's.nombre as simulacro_nombre', 's.puntaje_correcta', 's.puntaje_incorrecta',
            ])
            ->map(function ($intento) use ($ahora, $plantillasVigentes) {
                $intento->dia = PerfilExamenCni::diaUniDeNombre($intento->simulacro_nombre);
                $intento->vigente = $intento->dia !== null
                    && $intento->simulacro_nombre === $plantillasVigentes[$intento->dia];
                $intento->examen = $intento->dia
                    ? 'Día '.$intento->dia.' · '.PerfilExamenCni::UNI_DIAS[$intento->dia]['titulo']
                    : ($intento->simulacro_nombre ?? 'Simulacro');
                $limite = $intento->fecha_limite ? Carbon::parse($intento->fecha_limite) : null;
                // Un intento abierto pero vencido se considera cerrado; el servidor lo cierra al consultarlo.
                $vencido = $intento->estado === 'en_curso' && $limite && $ahora->gte($limite);
                $intento->en_curso = $intento->estado === 'en_curso' && ! $vencido;
                $intento->cerrado = in_array($intento->estado, self::ESTADOS_CERRADOS, true) || $vencido;
                $intento->segundos_restantes = $intento->en_curso && $limite
                    ? (int) max(0, $ahora->diffInSeconds($limite, false)) : null;
                $intento->respondidas = null;
                $intento->calificado = $intento->puntaje_total !== null;
                $intento->maximo = (int) $intento->total_preguntas * (float) ($intento->puntaje_correcta ?? 0);
                $intento->calificacion = $this->calificacionDeIntento($intento);

                return $intento;
            })
            ->pipe(function (Collection $intentos) {
                // Respondidas reales por intento, en una sola consulta.
                $conteo = DB::table('respuestas_estudiante')
                    ->whereIn('intento_id', $intentos->pluck('id'))
                    ->whereNotNull('alternativa_id')
                    ->selectRaw('intento_id, COUNT(*) as total')->groupBy('intento_id')
                    ->pluck('total', 'intento_id');

                return $intentos->each(fn ($i) => $i->respondidas = (int) ($conteo[$i->id] ?? 0));
            });
    }
}
