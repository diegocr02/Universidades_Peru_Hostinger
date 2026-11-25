<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Repaso del alumno: qué preguntas puede ver, explicaciones, preguntas guardadas y reportes.
 *
 * Regla de acceso (única para revisión, tutor, guardadas y reportes): una pregunta se puede
 * ver con su clave si está en un simulacro propio ya cerrado y calificado, o si el alumno
 * ya la respondió en una práctica propia. Nunca se exponen preguntas de un examen en curso.
 */
class RepasoService
{
    public const MOTIVOS = [
        'clave_incorrecta' => 'Posible clave incorrecta',
        'enunciado_confuso' => 'Enunciado confuso',
        'imagen_ilegible' => 'Imagen ilegible',
        'otro' => 'Otro problema',
    ];

    public function __construct(private readonly PracticaService $practicas) {}

    public function puedeVer(Usuario $usuario, int $preguntaId): bool
    {
        return $this->respuestaEnIntento($usuario, $preguntaId) !== null
            || $this->respuestaEnPractica($usuario, $preguntaId) !== null;
    }

    /** Enunciado, alternativas, clave, respuesta del alumno y solución revisada (o null si no puede verla). */
    public function explicacion(Usuario $usuario, int $preguntaId, ?int $intentoId = null): ?array
    {
        $respuesta = $this->respuestaEnIntento($usuario, $preguntaId, $intentoId)
            ?? ($intentoId === null ? $this->respuestaEnPractica($usuario, $preguntaId) : null);
        if (! $respuesta) {
            return null;
        }

        $pregunta = DB::table('preguntas as p')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('p.id', $preguntaId)
            ->first(['p.id', 'p.enunciado', 'p.imagen', 'p.solucion', 'p.activo', 't.nombre as tema', 'a.nombre as asignatura']);
        if (! $pregunta) {
            return null;
        }

        $alternativas = $this->practicas->alternativas($preguntaId);
        $elegida = $respuesta->alternativa_id ? $alternativas->firstWhere('id', (int) $respuesta->alternativa_id) : null;
        $solucion = trim((string) $pregunta->solucion);

        return [
            'pregunta_id' => (int) $pregunta->id,
            'asignatura' => ContextoAlumnoService::asignaturaVisible($pregunta->asignatura),
            'tema' => ContextoAlumnoService::asignaturaVisible($pregunta->tema),
            'enunciado' => (string) $pregunta->enunciado,
            'imagen' => $pregunta->imagen,
            'alternativas' => $alternativas->map(fn ($a) => ['letra' => $a['letra'], 'texto' => $a['texto'], 'imagen' => $a['imagen']])->all(),
            'clave' => $alternativas->firstWhere('es_correcta', true)['letra'] ?? null,
            'respuesta_alumno' => $elegida['letra'] ?? null,
            'estado' => $respuesta->estado_respuesta,
            'solucion' => $solucion !== '' ? $solucion : null,
            'revisada' => $solucion !== '',
            'activa' => (bool) $pregunta->activo,
        ];
    }

    // ───────────────────────── Preguntas guardadas ─────────────────────────

    /** Guarda una pregunta visible para el alumno. Repetir la acción no crea duplicados. */
    public function guardar(Usuario $usuario, int $preguntaId): bool
    {
        if (! $this->puedeVer($usuario, $preguntaId)) {
            return false;
        }
        DB::table('preguntas_guardadas')->insertOrIgnore([
            'usuario_id' => $usuario->id, 'pregunta_id' => $preguntaId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }

    public function quitar(Usuario $usuario, int $preguntaId): void
    {
        DB::table('preguntas_guardadas')->where('usuario_id', $usuario->id)->where('pregunta_id', $preguntaId)->delete();
    }

    /** Ids guardados por el alumno entre los indicados (para pintar «Guardada» en listas). */
    public function idsGuardados(Usuario $usuario, iterable $preguntas): array
    {
        return DB::table('preguntas_guardadas')->where('usuario_id', $usuario->id)
            ->whereIn('pregunta_id', collect($preguntas)->map(fn ($id) => (int) $id)->all())
            ->pluck('pregunta_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Lista personal con filtros. Una pregunta retirada del banco (inactiva) se conserva
     * para repasar, marcada como «retirada», y ya no se ofrece para practicar.
     */
    public function guardadas(Usuario $usuario, ?string $asignatura = null, ?string $tema = null): Collection
    {
        return DB::table('preguntas_guardadas as g')
            ->join('preguntas as p', 'p.id', '=', 'g.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('g.usuario_id', $usuario->id)
            ->orderByDesc('g.id')
            ->get(['g.pregunta_id', 'g.created_at', 'a.nombre as asignatura', 't.nombre as tema'])
            ->map(fn ($f) => (object) ['pregunta_id' => (int) $f->pregunta_id, 'guardada_en' => $f->created_at,
                'asignatura' => ContextoAlumnoService::asignaturaVisible($f->asignatura), 'tema' => ContextoAlumnoService::asignaturaVisible($f->tema)])
            ->when($asignatura, fn ($c, $a) => $c->where('asignatura', $a))
            ->when($tema, fn ($c, $t) => $c->where('tema', $t))
            ->values();
    }

    // ───────────────────────── Reportes ─────────────────────────

    /**
     * Registra un reporte. Devuelve 'creado', 'duplicado' (ya tiene uno abierto de la misma
     * pregunta) o 'no_disponible'. El candado evita duplicados por doble envío simultáneo.
     */
    public function reportar(Usuario $usuario, int $preguntaId, string $motivo, ?string $comentario, ?int $intentoId = null, ?int $practicaId = null): string
    {
        if (! array_key_exists($motivo, self::MOTIVOS) || ! $this->vioLaPregunta($usuario, $preguntaId)) {
            return 'no_disponible';
        }
        // El contexto solo se guarda si pertenece al alumno y contiene la pregunta.
        $intentoId = $intentoId && DB::table('respuestas_estudiante as re')->join('intentos as i', 'i.id', '=', 're.intento_id')
            ->where('i.id', $intentoId)->where('i.usuario_id', $usuario->id)->where('re.pregunta_id', $preguntaId)->exists() ? $intentoId : null;
        $practicaId = $practicaId && DB::table('practica_respuestas as pr')->join('practicas as pc', 'pc.id', '=', 'pr.practica_id')
            ->where('pc.id', $practicaId)->where('pc.usuario_id', $usuario->id)->where('pr.pregunta_id', $preguntaId)->exists() ? $practicaId : null;

        return Cache::lock("reporte:{$usuario->id}:{$preguntaId}", 10)->block(5, function () use ($usuario, $preguntaId, $motivo, $comentario, $intentoId, $practicaId) {
            $abierto = DB::table('reportes_pregunta')->where('usuario_id', $usuario->id)->where('pregunta_id', $preguntaId)
                ->whereIn('estado', ['pendiente', 'en_revision'])->exists();
            if ($abierto) {
                return 'duplicado';
            }
            DB::table('reportes_pregunta')->insert([
                'usuario_id' => $usuario->id, 'pregunta_id' => $preguntaId, 'intento_id' => $intentoId, 'practica_id' => $practicaId,
                'motivo' => $motivo, 'comentario' => $comentario !== null && trim($comentario) !== '' ? mb_substr(trim(strip_tags($comentario)), 0, 300) : null,
                'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return 'creado';
        });
    }

    // ───────────────────────── Internos ─────────────────────────

    private function respuestaEnIntento(Usuario $usuario, int $preguntaId, ?int $intentoId = null): ?object
    {
        return DB::table('respuestas_estudiante as re')
            ->join('intentos as i', 'i.id', '=', 're.intento_id')
            ->where('re.pregunta_id', $preguntaId)
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            ->whereIn('i.estado', ['finalizado', 'enviado_auto'])
            ->whereNotNull('i.puntaje_total')
            ->when($intentoId, fn ($q, $id) => $q->where('i.id', $id))
            ->orderByDesc('i.id')
            ->first(['re.alternativa_id', 're.estado_respuesta']);
    }

    private function respuestaEnPractica(Usuario $usuario, int $preguntaId): ?object
    {
        return DB::table('practica_respuestas as pr')
            ->join('practicas as pc', 'pc.id', '=', 'pr.practica_id')
            ->where('pr.pregunta_id', $preguntaId)
            ->where('pc.usuario_id', $usuario->id)
            ->whereNotNull('pr.respondida_en')
            ->orderByDesc('pr.id')
            ->first(['pr.alternativa_id', 'pr.estado_respuesta']);
    }

    /** Para reportar basta con haber visto la pregunta: también una de práctica aún sin responder. */
    private function vioLaPregunta(Usuario $usuario, int $preguntaId): bool
    {
        return $this->puedeVer($usuario, $preguntaId)
            || DB::table('practica_respuestas as pr')->join('practicas as pc', 'pc.id', '=', 'pr.practica_id')
                ->where('pr.pregunta_id', $preguntaId)->where('pc.usuario_id', $usuario->id)->exists();
    }
}
