<?php

namespace App\Services\Tutor;

use App\Models\Usuario;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Alumno\PracticaService;
use App\Services\Alumno\ProgresoComparableService;
use App\Services\Alumno\RepasoService;
use App\Support\PerfilExamenCni;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Herramientas del tutor. Son las únicas vías de acceso a datos para la IA: funciones
 * fijas, argumentos validados y el alumno siempre tomado de la sesión. Sin SQL libre,
 * archivos ni comandos. Reutilizan los servicios de práctica, repaso y progreso.
 */
class TutorTools
{
    public function __construct(
        private readonly ContextoAlumnoService $contexto,
        private readonly PracticaService $practicas,
        private readonly RepasoService $repaso,
        private readonly ProgresoComparableService $progreso,
    ) {}

    /** Esquemas compactos (menos tokens por llamada). */
    public function definiciones(): array
    {
        $funcion = fn (string $nombre, string $descripcion, array $propiedades = [], array $requeridas = []) => [
            'type' => 'function',
            'function' => [
                'name' => $nombre,
                'description' => $descripcion,
                'parameters' => ['type' => 'object', 'properties' => (object) $propiedades, 'required' => $requeridas, 'additionalProperties' => false],
            ],
        ];

        return [
            $funcion('consultar_mis_resultados', 'Puntaje, aciertos, errores, en blanco y rendimiento por asignatura de un intento finalizado del alumno. Sin intento_id usa el más reciente.',
                ['intento_id' => ['type' => 'integer']]),
            $funcion('consultar_mis_errores', 'Preguntas incorrectas o en blanco de un intento finalizado del alumno (sin claves).',
                ['intento_id' => ['type' => 'integer'], 'asignatura' => ['type' => 'string']], ['intento_id']),
            $funcion('buscar_explicacion', 'Enunciado, clave y solución revisada de una pregunta que el alumno ya respondió en un intento finalizado o en una práctica.',
                ['pregunta_id' => ['type' => 'integer']], ['pregunta_id']),
            $funcion('buscar_preguntas_practica', 'Asignaturas, temas y cantidad de preguntas disponibles para practicar en la universidad del alumno.',
                ['asignatura' => ['type' => 'string']]),
            $funcion('consultar_mi_progreso', 'Evolución del alumno entre intentos comparables (misma universidad, examen y escala).'),
            $funcion('consultar_info_simulador', 'Formato y reglas de calificación de los exámenes de la universidad del alumno.'),
        ];
    }

    /** Ejecuta una herramienta pedida por la IA. Nunca lanza: devuelve un error legible. */
    public function ejecutar(string $nombre, array $argumentos, Usuario $usuario, string $codigo): array
    {
        $entero = fn (string $clave) => isset($argumentos[$clave]) && is_numeric($argumentos[$clave]) ? (int) $argumentos[$clave] : null;
        $texto = fn (string $clave) => isset($argumentos[$clave]) && is_string($argumentos[$clave]) ? mb_substr(trim($argumentos[$clave]), 0, 80) : null;

        $resultado = match ($nombre) {
            'consultar_mis_resultados' => $this->resultadosIntento($usuario, $codigo, $entero('intento_id')),
            'consultar_mis_errores' => $entero('intento_id') === null
                ? ['error' => 'Falta intento_id.']
                : ['preguntas' => $this->errores($usuario, $codigo, $entero('intento_id'), $texto('asignatura'))],
            'buscar_explicacion' => $entero('pregunta_id') === null
                ? ['error' => 'Falta pregunta_id.']
                : ($this->repaso->explicacion($usuario, $entero('pregunta_id')) ?? ['error' => 'Pregunta no disponible para este alumno.']),
            'buscar_preguntas_practica' => ['asignaturas' => $this->practicas->catalogo($codigo)
                ->when($texto('asignatura'), fn ($c, $a) => $c->filter(fn ($x) => mb_stripos($x['nombre'], $a) !== false))
                ->map(fn ($a) => ['nombre' => $a['nombre'], 'cantidad' => $a['cantidad'], 'temas' => array_column($a['temas'], 'nombre')])
                ->values()->all(), 'nota' => 'El alumno practica desde «Práctica» o con «Practicar un tema».'],
            'consultar_mi_progreso' => $this->progresoParaIa($usuario, $codigo),
            'consultar_info_simulador' => $this->infoSimulador($codigo),
            default => ['error' => 'Herramienta no permitida.'],
        };
        // Nunca se envían rutas de archivos ni datos internos a la IA.
        unset($resultado['imagen'], $resultado['activa']);

        return $resultado;
    }

    // ───────────────────────── Resultados de simulacros ─────────────────────────

    public function intentosCalificados(Usuario $usuario, string $codigo, int $limite = 6): Collection
    {
        return $this->progreso->intentos($usuario, $codigo)->take($limite)->map(fn ($i) => $this->formatear($i))->values();
    }

    public function intentoPropio(Usuario $usuario, string $codigo, int $intentoId): ?array
    {
        $intento = $this->progreso->intentos($usuario, $codigo)->firstWhere('id', $intentoId);

        return $intento ? $this->formatear($intento) : null;
    }

    public function resultadosIntento(Usuario $usuario, string $codigo, ?int $intentoId = null): array
    {
        $intento = $intentoId !== null
            ? $this->intentoPropio($usuario, $codigo, $intentoId)
            : $this->intentosCalificados($usuario, $codigo, 1)->first();
        if (! $intento) {
            return ['error' => $intentoId ? 'Intento no encontrado o sin calificar.' : 'sin_intentos'];
        }

        $asignaturas = DB::table('resultados_area as ra')
            ->join('areas as a', 'a.id', '=', 'ra.area_id')
            ->where('ra.intento_id', $intento['id'])
            ->whereNotIn('a.nombre', config('tutor.asignaturas_excluidas', []))
            ->get(['a.nombre', 'ra.total_preguntas', 'ra.aciertos', 'ra.incorrectas', 'ra.en_blanco', 'ra.porcentaje'])
            ->map(fn ($f) => [
                'asignatura' => ContextoAlumnoService::asignaturaVisible($f->nombre),
                'total' => (int) $f->total_preguntas, 'aciertos' => (int) $f->aciertos,
                'incorrectas' => (int) $f->incorrectas, 'en_blanco' => (int) $f->en_blanco,
                'porcentaje' => round((float) $f->porcentaje, 1),
            ])
            ->sortBy('porcentaje')->values();

        // Reforzar: asignaturas con al menos 2 preguntas y menos del 60 % de aciertos.
        $reforzar = $asignaturas->filter(fn ($a) => $a['total'] >= 2 && $a['porcentaje'] < 60)->take(3)->pluck('asignatura')->all();
        $limitaciones = $asignaturas->isEmpty() ? ['sin_desglose'] : ($intento['total'] < 10 ? ['pocas_preguntas'] : []);

        return ['intento' => $intento, 'asignaturas' => $asignaturas->all(), 'reforzar' => $reforzar, 'limitaciones' => $limitaciones];
    }

    /** Preguntas incorrectas o en blanco del intento propio (sin clave). */
    public function errores(Usuario $usuario, string $codigo, int $intentoId, ?string $asignatura = null, int $limite = 12): array
    {
        if (! $this->intentoPropio($usuario, $codigo, $intentoId)) {
            return [];
        }

        return DB::table('respuestas_estudiante as re')
            ->join('preguntas as p', 'p.id', '=', 're.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('re.intento_id', $intentoId)
            ->whereIn('re.estado_respuesta', ['incorrecta', 'en_blanco', 'doble_marca'])
            ->whereNotIn('a.nombre', config('tutor.asignaturas_excluidas', []))
            ->when($asignatura, fn ($q, $nombre) => $q->where('a.nombre', 'like', '%'.addcslashes($nombre, '%_\\').'%'))
            ->orderBy('re.orden')
            ->limit($limite)
            ->get(['p.id', 're.orden', 're.estado_respuesta', 'p.enunciado', 'a.nombre'])
            ->map(fn ($f) => [
                'pregunta_id' => (int) $f->id,
                'numero' => (int) $f->orden,
                'asignatura' => ContextoAlumnoService::asignaturaVisible($f->nombre),
                'estado' => $f->estado_respuesta === 'incorrecta' ? 'incorrecta' : 'en blanco',
                'enunciado' => $this->recortar($f->enunciado, 160),
            ])->all();
    }

    public function infoSimulador(string $codigo): array
    {
        $examenes = DB::table('simulacros as s')
            ->where('s.activo', true)
            ->tap(fn (Builder $q) => $this->contexto->soloUniversidad($q, $codigo))
            ->orderByDesc('s.id')
            ->limit(20)
            ->get(['s.nombre', 's.total_preguntas', 's.tiempo_limite', 's.puntaje_correcta', 's.puntaje_incorrecta', 's.puntaje_blanco'])
            ->unique(fn ($s) => ProgresoComparableService::examenBase($s->nombre))
            ->take(5)
            ->map(fn ($s) => [
                'examen' => ProgresoComparableService::examenBase($s->nombre),
                'preguntas' => (int) $s->total_preguntas, 'minutos' => (int) $s->tiempo_limite,
                'correcta' => (float) $s->puntaje_correcta, 'incorrecta' => (float) $s->puntaje_incorrecta, 'en_blanco' => (float) $s->puntaje_blanco,
            ])->values()->all();

        $info = ['universidad' => config('panel_alumno.areas.'.$codigo.'.titulo') ?? 'CNI', 'examenes' => $examenes];
        if ($codigo === 'B') {
            $info['formato'] = 'Tres días independientes de '.PerfilExamenCni::UNI_MINUTOS.' minutos; se toma el último intento calificado de cada día.';
        }

        return $info;
    }

    /** Progreso sin coordenadas de gráfico (menos tokens). */
    private function progresoParaIa(Usuario $usuario, string $codigo): array
    {
        $datos = $this->progreso->grupos($usuario, $codigo);
        $datos['grupos'] = array_map(fn ($g) => array_diff_key($g, ['grafico' => 1, 'clave' => 1]), $datos['grupos']);

        return $datos;
    }

    private function formatear(object $i): array
    {
        return [
            'id' => $i->id, 'nombre' => $i->examen, 'fecha' => $i->fecha->format('d/m/Y'),
            'puntaje' => round($i->puntaje, 2), 'maximo' => round($i->maximo, 2), 'porcentaje' => $i->porcentaje,
            'total' => $i->total, 'correctas' => $i->correctas, 'incorrectas' => $i->incorrectas, 'en_blanco' => $i->en_blanco,
        ];
    }

    private function recortar(?string $texto, int $limite): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $texto)));

        return mb_strlen($limpio) > $limite ? mb_substr($limpio, 0, $limite - 1).'…' : $limpio;
    }
}
