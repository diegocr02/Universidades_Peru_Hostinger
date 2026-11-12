<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Support\BancoPracticaUnfv;
use App\Support\PerfilExamenCni;
use App\Support\UniversidadExamen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Datos reales del panel Villarreal (área C): práctica general UNFV, intento vigente,
 * resumen e historial. Solo lectura; la calificación la hace el motor de intentos.
 */
class PanelUnfvService
{
    public const MINUTOS = 60;

    private const ESTADOS_CERRADOS = ['finalizado', 'enviado_auto', 'anulado'];

    public function obtener(Usuario $usuario): array
    {
        // Misma decisión que ExamenIndependienteService::iniciar(): banco UNFV si está cargado.
        $bancoUnfv = DB::table('areas')->where('nombre', BancoPracticaUnfv::area('AV'))->exists();
        $cuotas = $this->cuotas($bancoUnfv);
        $intentos = $this->intentos($usuario);
        $calificados = $intentos->filter(fn ($i) => $i->calificado);
        $mejor = $calificados->sortByDesc('porcentaje')->first();
        $escala = ExamenIndependienteService::VALORES_CNI;
        $total = $cuotas->sum('cantidad');

        return [
            'banco_unfv' => $bancoUnfv,
            'cuotas' => $cuotas,
            'grupos' => $cuotas->groupBy('grupo')->map(fn (Collection $c) => $c->sum('cantidad')),
            'total' => $total,
            'minutos' => self::MINUTOS,
            'escala' => [
                'correcta' => (float) $escala['puntaje_correcta'],
                'incorrecta' => abs((float) $escala['puntaje_incorrecta']),
                'maximo' => $total * (float) $escala['puntaje_correcta'],
            ],
            'en_curso' => $intentos->first(fn ($i) => $i->en_curso),
            'ultimo' => $calificados->first(),
            'mejor' => $mejor,
            'promedio' => $calificados->isEmpty() ? null : round($calificados->avg('porcentaje'), 1),
            'cantidad_calificados' => $calificados->count(),
            'historial' => $intentos,
        ];
    }

    /** Reparto de la práctica por curso, en el orden fijo del examen. */
    private function cuotas(bool $bancoUnfv): Collection
    {
        $ruta = database_path('data/unfv_preguntas_practica.json');
        // El JSON pesa ~1,7 MB por las imágenes: solo se lee de nuevo cuando cambia el archivo.
        $cuotas = $bancoUnfv
            ? Cache::rememberForever('unfv_cuotas_practica_'.filemtime($ruta),
                fn () => BancoPracticaUnfv::documento()['cuotas_practica'])
            : PerfilExamenCni::DISTRIBUCION['C'];

        $grupos = [
            'habilidad_verbal' => 'Habilidades',
            'habilidad_logico_matematica' => 'Habilidades',
            'conocimientos' => 'Conocimientos',
        ];

        return collect(PerfilExamenCni::ORDEN)
            ->filter(fn (string $prefijo) => isset($cuotas[$prefijo]))
            ->map(fn (string $prefijo) => [
                'curso' => PerfilExamenCni::CURSOS[$prefijo][0],
                'cantidad' => (int) $cuotas[$prefijo],
                'grupo' => $grupos[PerfilExamenCni::CURSOS[$prefijo][1]] ?? 'Conocimientos',
            ])->values();
    }

    /** Intentos de la práctica UNFV del alumno, los más recientes primero. */
    private function intentos(Usuario $usuario): Collection
    {
        $ahora = now();

        $intentos = DB::table('intentos as i')
            ->join('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            // Solo exámenes de la Villarreal: no se mezclan intentos de otras universidades.
            ->tap(fn ($q) => UniversidadExamen::filtrar($q, 'C'))
            ->orderByDesc('i.fecha_inicio')->orderByDesc('i.id')
            ->limit(50)
            ->get([
                'i.id', 'i.fecha_inicio', 'i.fecha_limite', 'i.fecha_fin', 'i.estado', 'i.total_preguntas',
                'i.puntaje_total', 'i.correctas', 'i.incorrectas', 'i.en_blanco', 's.puntaje_correcta',
                's.nombre as simulacro_nombre',
            ]);

        $respondidas = DB::table('respuestas_estudiante')
            ->whereIn('intento_id', $intentos->pluck('id'))
            ->whereNotNull('alternativa_id')
            ->selectRaw('intento_id, COUNT(*) as total')->groupBy('intento_id')
            ->pluck('total', 'intento_id');

        return $intentos->map(function ($intento) use ($ahora, $respondidas) {
            $limite = $intento->fecha_limite ? Carbon::parse($intento->fecha_limite) : null;
            // Un intento abierto pero vencido se trata como cerrado; el servidor lo cierra al abrirlo.
            $vencido = $intento->estado === 'en_curso' && $limite && $ahora->gte($limite);
            $intento->en_curso = $intento->estado === 'en_curso' && ! $vencido;
            $intento->cerrado = in_array($intento->estado, self::ESTADOS_CERRADOS, true) || $vencido;
            $intento->segundos_restantes = $intento->en_curso && $limite
                ? (int) max(0, $ahora->diffInSeconds($limite, false)) : null;
            $intento->respondidas = (int) ($respondidas[$intento->id] ?? 0);
            // Las plantillas automáticas llevan un sufijo técnico; se muestra un nombre legible.
            $intento->examen = match (true) {
                str_starts_with($intento->simulacro_nombre, 'Práctica general UNFV · ') => 'Práctica general UNFV',
                str_starts_with($intento->simulacro_nombre, 'Examen independiente CNI · Área C · ') => 'Examen general · Área C',
                default => $intento->simulacro_nombre,
            };
            $intento->calificado = ! $intento->en_curso && $intento->puntaje_total !== null;
            $intento->maximo = (int) $intento->total_preguntas * (float) ($intento->puntaje_correcta ?? 0);
            $intento->porcentaje = $intento->calificado && $intento->maximo > 0
                ? round(max(0, (float) $intento->puntaje_total) / $intento->maximo * 100, 1) : null;

            return $intento;
        });
    }
}
