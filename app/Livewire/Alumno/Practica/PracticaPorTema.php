<?php

namespace App\Livewire\Alumno\Practica;

use App\Models\Usuario;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Alumno\PracticaService;
use App\Services\Alumno\RepasoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Práctica por temas: elegir asignatura, tema, dificultad y cantidad; responder con
 * retroalimentación inmediata y ver un resumen. Toda la calificación ocurre en PracticaService.
 */
class PracticaPorTema extends Component
{
    #[Url(as: 'asignatura', except: '')]
    public string $asignatura = '';

    public ?int $temaId = null;

    public string $nivel = '';

    public int $cantidad = 10;

    // Ids controlados por el servidor; igualmente se valida la propiedad en cada acción.
    #[Locked]
    public ?int $practicaId = null;

    #[Locked]
    public int $orden = 1;

    public ?string $aviso = null;

    public ?string $error = null;

    public string $motivo = 'clave_incorrecta';

    public string $comentario = '';

    public function mount(): void
    {
        [$usuario] = $this->sesion();
        // Retoma la última práctica sin terminar (al recargar o volver a la página).
        $abierta = DB::table('practicas')->where('usuario_id', $usuario->id)->where('origen', '!=', 'tutor')
            ->whereNull('finalizada_en')->where('updated_at', '>=', now()->subDay())->latest('id')->first();
        if ($abierta) {
            $this->practicaId = $abierta->id;
            $this->orden = (int) (DB::table('practica_respuestas')->where('practica_id', $abierta->id)
                ->whereNull('respondida_en')->min('orden') ?? $abierta->total_preguntas);
        }
    }

    public function updatedAsignatura(): void
    {
        $this->temaId = null;
        $this->nivel = '';
    }

    public function comenzar(PracticaService $practicas, ContextoAlumnoService $contextos): void
    {
        [$usuario, $ctx] = $this->sesion();
        $this->reset('aviso', 'error');
        if ($contextos->examenActivo($usuario)) {
            $this->error = 'Tienes un examen en curso. Termínalo antes de practicar.';

            return;
        }

        $asignatura = $practicas->catalogo($ctx['codigo'])->firstWhere('nombre', $this->asignatura);
        $this->validate([
            'asignatura' => ['required', Rule::in($practicas->catalogo($ctx['codigo'])->pluck('nombre')->all())],
            'temaId' => ['nullable', 'integer', Rule::in(array_column($asignatura['temas'] ?? [], 'id'))],
            'nivel' => ['nullable', Rule::in(array_merge([''], $asignatura['niveles'] ?? []))],
            'cantidad' => ['required', 'integer', Rule::in(config('panel_alumno.practica.cantidades'))],
        ], [], ['temaId' => 'tema']);

        $registro = $practicas->crear($usuario, $ctx['codigo'], $this->asignatura, $this->temaId, $this->nivel ?: null, $this->cantidad);
        if (! $registro) {
            $this->error = 'No hay preguntas disponibles con esa selección. Prueba con otro tema o dificultad.';

            return;
        }
        if ($registro->total_preguntas < $this->cantidad) {
            $this->aviso = "Solo hay {$registro->total_preguntas} preguntas disponibles con esa selección; las practicarás todas.";
        }
        $this->practicaId = $registro->id;
        $this->orden = 1;
    }

    public function responder(string $letra, PracticaService $practicas): void
    {
        [$usuario] = $this->sesion();
        $this->reset('error');
        // Un examen pudo empezar en otra pestaña: durante el examen no se califica ni se muestran claves.
        if (app(ContextoAlumnoService::class)->examenActivo($usuario)) {
            $this->error = 'Tienes un examen en curso. Termínalo antes de seguir practicando.';

            return;
        }
        $practica = $this->practicaActual($usuario, $practicas);
        $pregunta = $practica ? $practicas->pregunta($practica, $this->orden) : null;
        if (! $pregunta || ! preg_match('/^[A-Z]$/', $letra)) {
            $this->error = 'Esa respuesta ya no es válida.';

            return;
        }
        try {
            $practicas->responder($usuario, $practica->id, $pregunta['pregunta_id'], $letra);
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();
        }
    }

    public function siguiente(PracticaService $practicas): void
    {
        [$usuario] = $this->sesion();
        $practica = $this->practicaActual($usuario, $practicas);
        if ($practica && $this->orden <= $practica->total_preguntas) {
            $this->orden++;
            $this->reset('error', 'aviso');
        }
    }

    public function terminar(PracticaService $practicas): void
    {
        [$usuario] = $this->sesion();
        if ($this->practicaId) {
            $practicas->finalizar($usuario, $this->practicaId);
            $practica = $practicas->propia($usuario, $this->practicaId);
            $this->orden = (int) ($practica?->total_preguntas ?? 0) + 1;
        }
    }

    /** Nueva práctica solo con las preguntas falladas (mismo banco y universidad). */
    public function repetirErrores(PracticaService $practicas): void
    {
        [$usuario, $ctx] = $this->sesion();
        $practica = $this->practicaActual($usuario, $practicas);
        $errores = $practica ? $practicas->resumen($practica)['errores'] : [];
        if ($errores === []) {
            return;
        }
        $nueva = $practicas->crear($usuario, $ctx['codigo'], $practica->asignatura, null, null, count($errores), 'repaso', $errores);
        if ($nueva) {
            $this->practicaId = $nueva->id;
            $this->orden = 1;
            $this->reset('aviso', 'error');
        }
    }

    public function otraPractica(): void
    {
        $this->reset('practicaId', 'orden', 'aviso', 'error', 'temaId', 'nivel');
    }

    public function alternarGuardada(int $preguntaId, RepasoService $repaso): void
    {
        [$usuario] = $this->sesion();
        if ($repaso->idsGuardados($usuario, [$preguntaId])) {
            $repaso->quitar($usuario, $preguntaId);
            $this->aviso = 'Pregunta quitada de tu repaso.';
        } else {
            $this->aviso = $repaso->guardar($usuario, $preguntaId) ? 'Pregunta guardada en tu repaso.' : 'Responde la pregunta antes de guardarla.';
        }
    }

    public function reportar(int $preguntaId, RepasoService $repaso): void
    {
        [$usuario] = $this->sesion();
        $this->validate([
            'motivo' => ['required', Rule::in(array_keys(RepasoService::MOTIVOS))],
            'comentario' => ['nullable', 'string', 'max:300'],
        ]);
        $resultado = $repaso->reportar($usuario, $preguntaId, $this->motivo, $this->comentario, null, $this->practicaId);
        $this->aviso = match ($resultado) {
            'creado' => 'Gracias. Tu reporte quedó registrado y un docente lo revisará.',
            'duplicado' => 'Ya reportaste esta pregunta; tu reporte sigue en revisión.',
            default => 'No se pudo registrar el reporte.',
        };
        $this->reset('comentario');
        $this->dispatch('reporte-enviado');
    }

    public function render(PracticaService $practicas, RepasoService $repaso, ContextoAlumnoService $contextos)
    {
        [$usuario, $ctx] = $this->sesion();
        $catalogo = $practicas->catalogo($ctx['codigo']);
        $practica = $this->practicaActual($usuario, $practicas);
        $pregunta = $practica && $this->orden <= $practica->total_preguntas ? $practicas->pregunta($practica, $this->orden) : null;

        return view('livewire.alumno.practica.practica-por-tema', [
            'usuario' => $usuario,
            'ctx' => $ctx,
            'catalogo' => $catalogo,
            'seleccion' => $catalogo->firstWhere('nombre', $this->asignatura),
            'practica' => $practica,
            'pregunta' => $pregunta,
            'resumen' => $practica && ! $pregunta ? $practicas->resumen($practica) : null,
            'guardada' => $pregunta && $repaso->idsGuardados($usuario, [$pregunta['pregunta_id']]) !== [],
            'examenActivo' => $contextos->examenActivo($usuario),
            'meta' => $practicas->respondidasEnSemana($usuario),
            'motivos' => RepasoService::MOTIVOS,
        ])->layout('layouts.app');
    }

    private function practicaActual(Usuario $usuario, PracticaService $practicas): ?object
    {
        return $this->practicaId ? $practicas->propia($usuario, $this->practicaId) : null;
    }

    /** @return array{0: Usuario, 1: array} */
    private function sesion(): array
    {
        $usuario = Auth::guard('web')->user();
        abort_unless($usuario instanceof Usuario && $usuario->activo && $usuario->rol === 'estudiante', 403);
        $ctx = app(ContextoAlumnoService::class)->contexto($usuario);
        abort_unless($ctx !== null, 403);

        return [$usuario, $ctx];
    }
}
