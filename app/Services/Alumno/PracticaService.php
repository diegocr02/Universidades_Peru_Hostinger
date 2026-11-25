<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Services\ResultadoService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Práctica por temas con el banco de la universidad del alumno.
 * Se guarda en `practicas`/`practica_respuestas` (no en `intentos`) para no alterar
 * historiales, reportes de docentes ni estadísticas de simulacros.
 */
class PracticaService
{
    public function __construct(
        private readonly ContextoAlumnoService $contexto,
        private readonly ResultadoService $resultados,
    ) {}

    /**
     * Asignaturas practicables con sus temas y niveles: [{nombre, cantidad, temas: [{id, nombre, cantidad}], niveles: []}].
     * Las asignaturas de distintos bancos («UNDC A · Álgebra», «UNDC B · Álgebra») se agrupan por nombre visible.
     */
    public function catalogo(string $codigo): Collection
    {
        return $this->consultaBanco($codigo)
            ->groupBy('a.nombre', 't.id', 't.nombre', 'p.nivel')
            ->selectRaw('a.nombre as asignatura, t.id as tema_id, t.nombre as tema, p.nivel, COUNT(*) as total')
            ->get()
            ->groupBy(fn ($f) => ContextoAlumnoService::asignaturaVisible($f->asignatura))
            ->map(fn (Collection $filas, string $nombre) => [
                'nombre' => $nombre,
                'cantidad' => (int) $filas->sum('total'),
                'temas' => $filas->groupBy('tema_id')->map(fn ($t) => [
                    'id' => (int) $t->first()->tema_id, 'nombre' => ContextoAlumnoService::asignaturaVisible($t->first()->tema), 'cantidad' => (int) $t->sum('total'),
                ])->sortBy('nombre')->values()->all(),
                'niveles' => $filas->pluck('nivel')->filter()->unique()->values()->all(),
            ])
            ->sortBy('nombre')->values();
    }

    /** Cantidad de preguntas disponibles para una selección (para avisar si hay menos de las pedidas). */
    public function disponibles(string $codigo, string $asignatura, ?int $temaId, ?string $nivel): int
    {
        return $this->seleccion($codigo, $asignatura, $temaId, $nivel)->count();
    }

    /**
     * Crea una práctica. Sin $ids, sortea priorizando preguntas que el alumno aún no respondió.
     * Con $ids (repetir errores), solo acepta preguntas del banco de su universidad.
     */
    public function crear(Usuario $usuario, string $codigo, string $asignatura, ?int $temaId, ?string $nivel,
        int $cantidad, string $origen = 'tema', ?array $ids = null): ?object
    {
        $cantidad = max(1, min($cantidad, (int) config('panel_alumno.practica.maximo', 20)));
        $consulta = $this->seleccion($codigo, $asignatura, $temaId, $nivel);
        if ($ids !== null) {
            $consulta->whereIn('p.id', array_map('intval', $ids));
        }
        $elegidas = $consulta
            // Primero las no respondidas antes por este alumno; luego al azar.
            ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM practica_respuestas pr JOIN practicas pc ON pc.id = pr.practica_id
                WHERE pr.pregunta_id = p.id AND pc.usuario_id = ? AND pr.respondida_en IS NOT NULL) THEN 1 ELSE 0 END', [$usuario->id])
            ->inRandomOrder()
            ->limit($cantidad)
            ->pluck('p.id');
        if ($elegidas->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($usuario, $codigo, $asignatura, $temaId, $nivel, $origen, $elegidas) {
            $ahora = now();
            $id = DB::table('practicas')->insertGetId([
                'usuario_id' => $usuario->id, 'area_codigo' => $codigo, 'asignatura' => $asignatura,
                'tema_id' => $temaId, 'nivel' => $nivel, 'origen' => $origen,
                'total_preguntas' => $elegidas->count(), 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            DB::table('practica_respuestas')->insert($elegidas->values()->map(fn ($pregunta, $i) => [
                'practica_id' => $id, 'pregunta_id' => $pregunta, 'orden' => $i + 1,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ])->all());

            return DB::table('practicas')->find($id);
        });
    }

    /** Práctica del alumno autenticado (o null si no es suya). */
    public function propia(Usuario $usuario, int $practicaId): ?object
    {
        return DB::table('practicas')->where('id', $practicaId)->where('usuario_id', $usuario->id)->first();
    }

    /** Pregunta N de la práctica. La clave y la solución solo se incluyen si ya fue respondida. */
    public function pregunta(object $practica, int $orden): ?array
    {
        $fila = DB::table('practica_respuestas as pr')
            ->join('preguntas as p', 'p.id', '=', 'pr.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->leftJoin('alternativas as al', 'al.id', '=', 'pr.alternativa_id')
            ->where('pr.practica_id', $practica->id)->where('pr.orden', $orden)
            ->first(['p.id', 'p.enunciado', 'p.imagen', 'p.solucion', 'p.nivel', 't.nombre as tema', 'a.nombre as asignatura',
                'pr.estado_respuesta', 'pr.respondida_en', 'al.letra as elegida']);
        if (! $fila) {
            return null;
        }
        $alternativas = $this->alternativas((int) $fila->id);
        $respondida = $fila->respondida_en !== null;
        $solucion = trim((string) $fila->solucion);

        return [
            'pregunta_id' => (int) $fila->id,
            'orden' => $orden,
            'total' => (int) $practica->total_preguntas,
            'asignatura' => ContextoAlumnoService::asignaturaVisible($fila->asignatura),
            'tema' => ContextoAlumnoService::asignaturaVisible($fila->tema),
            'nivel' => $fila->nivel,
            'enunciado' => (string) $fila->enunciado,
            'imagen' => $fila->imagen,
            'alternativas' => $alternativas->map(fn ($a) => ['letra' => $a['letra'], 'texto' => $a['texto'], 'imagen' => $a['imagen']])->all(),
            'respondida' => $respondida,
            'elegida' => $fila->elegida,
            'estado' => $fila->estado_respuesta,
            'clave' => $respondida ? ($alternativas->firstWhere('es_correcta', true)['letra'] ?? null) : null,
            'solucion' => $respondida && $solucion !== '' ? $solucion : null,
        ];
    }

    /**
     * Registra la respuesta y la califica con la regla del simulador. Idempotente:
     * si ya estaba respondida, devuelve el resultado guardado sin volver a contarla.
     *
     * @return array{estado: string, clave: ?string, nueva: bool}
     */
    public function responder(Usuario $usuario, int $practicaId, int $preguntaId, string $letra): array
    {
        return DB::transaction(function () use ($usuario, $practicaId, $preguntaId, $letra) {
            $practica = DB::table('practicas')->where('id', $practicaId)->where('usuario_id', $usuario->id)
                ->lockForUpdate()->first();
            $fila = $practica ? DB::table('practica_respuestas')->where('practica_id', $practicaId)
                ->where('pregunta_id', $preguntaId)->lockForUpdate()->first() : null;
            if (! $fila) {
                throw ValidationException::withMessages(['pregunta' => 'La pregunta no pertenece a esta práctica.']);
            }
            $alternativas = $this->alternativas($preguntaId);
            $clave = $alternativas->firstWhere('es_correcta', true)['letra'] ?? null;
            if ($fila->respondida_en !== null) {
                return ['estado' => $fila->estado_respuesta, 'clave' => $clave, 'nueva' => false];
            }
            if ($practica->finalizada_en !== null) {
                throw ValidationException::withMessages(['practica' => 'La práctica ya terminó.']);
            }
            $elegida = $alternativas->firstWhere('letra', $letra);
            if (! $elegida) {
                throw ValidationException::withMessages(['letra' => 'Esa alternativa no existe.']);
            }

            $estado = $this->resultados->clasificarRespuesta((object) [
                'estado_respuesta' => null, 'alternativa_id' => $elegida['id'], 'alternativa_pregunta_id' => $preguntaId,
                'pregunta_id' => $preguntaId, 'es_correcta' => $elegida['es_correcta'] ? 1 : 0,
                'claves' => $alternativas->where('es_correcta', true)->count(),
            ]);
            $ahora = now();
            DB::table('practica_respuestas')->where('id', $fila->id)->update([
                'alternativa_id' => $elegida['id'], 'estado_respuesta' => $estado, 'respondida_en' => $ahora, 'updated_at' => $ahora,
            ]);
            $pendientes = DB::table('practica_respuestas')->where('practica_id', $practicaId)->whereNull('respondida_en')->count();
            DB::table('practicas')->where('id', $practicaId)->update([
                'correctas' => $practica->correctas + ($estado === 'correcta' ? 1 : 0),
                'incorrectas' => $practica->incorrectas + ($estado === 'incorrecta' ? 1 : 0),
                'finalizada_en' => $pendientes === 0 ? $ahora : null,
                'updated_at' => $ahora,
            ]);

            return ['estado' => $estado, 'clave' => $clave, 'nueva' => true];
        });
    }

    public function finalizar(Usuario $usuario, int $practicaId): void
    {
        DB::table('practicas')->where('id', $practicaId)->where('usuario_id', $usuario->id)
            ->whereNull('finalizada_en')->update(['finalizada_en' => now(), 'updated_at' => now()]);
    }

    /** Resumen final: respondidas, correctas, incorrectas, sin responder e ids a repasar. */
    public function resumen(object $practica): array
    {
        $filas = DB::table('practica_respuestas')->where('practica_id', $practica->id)->get(['pregunta_id', 'estado_respuesta']);

        return [
            'total' => $filas->count(),
            'correctas' => $filas->where('estado_respuesta', 'correcta')->count(),
            'incorrectas' => $filas->where('estado_respuesta', 'incorrecta')->count(),
            'sin_responder' => $filas->whereNull('estado_respuesta')->count(),
            'errores' => $filas->where('estado_respuesta', 'incorrecta')->pluck('pregunta_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /** Letras incorrectas, en orden estable, para pistas por descarte. */
    public function letrasIncorrectas(int $preguntaId): array
    {
        return $this->alternativas($preguntaId)->where('es_correcta', false)->pluck('letra')->values()->all();
    }

    /**
     * Meta semanal: preguntas de práctica respondidas de lunes a domingo en la zona horaria
     * de la aplicación. Cada pregunta cuenta una sola vez por práctica (índice único y
     * `respondida_en` solo se escribe en la primera respuesta).
     */
    public function respondidasEnSemana(Usuario $usuario): array
    {
        $inicio = now()->startOfWeek();
        $fin = now()->endOfWeek();
        $total = DB::table('practica_respuestas as pr')
            ->join('practicas as pc', 'pc.id', '=', 'pr.practica_id')
            ->where('pc.usuario_id', $usuario->id)
            ->whereBetween('pr.respondida_en', [$inicio, $fin])
            ->count();

        return ['total' => $total, 'inicio' => $inicio, 'fin' => $fin];
    }

    private function seleccion(string $codigo, string $asignatura, ?int $temaId, ?string $nivel): Builder
    {
        return $this->consultaBanco($codigo)
            ->where(fn (Builder $q) => $q->where('a.nombre', $asignatura)
                ->orWhere('a.nombre', 'like', '% · '.addcslashes($asignatura, '%_\\')))
            ->when($temaId, fn ($q, $id) => $q->where('t.id', $id))
            ->when($nivel, fn ($q, $n) => $q->where('p.nivel', $n));
    }

    /** Preguntas activas del banco de la universidad con una única clave. */
    public function consultaBanco(string $codigo): Builder
    {
        return DB::table('preguntas as p')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('p.activo', true)
            ->whereRaw('(SELECT COUNT(*) FROM alternativas al WHERE al.pregunta_id = p.id AND al.es_correcta = 1) = 1')
            ->tap(fn ($q) => $this->contexto->bancoDeUniversidad($q, $codigo));
    }

    public function alternativas(int $preguntaId): Collection
    {
        return DB::table('alternativas')->where('pregunta_id', $preguntaId)->orderBy('letra')
            ->get(['id', 'letra', 'texto', 'imagen', 'es_correcta'])
            ->map(fn ($a) => ['id' => (int) $a->id, 'letra' => (string) $a->letra, 'texto' => (string) $a->texto,
                'imagen' => $a->imagen, 'es_correcta' => (bool) $a->es_correcta]);
    }
}
