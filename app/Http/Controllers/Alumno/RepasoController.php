<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Alumno\RepasoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Preguntas guardadas y reportes. La regla de acceso vive en RepasoService::puedeVer. */
class RepasoController extends Controller
{
    public function __construct(private readonly RepasoService $repaso) {}

    public function guardar(Request $request, int $pregunta): RedirectResponse
    {
        $ok = $this->repaso->guardar($request->user('web'), $pregunta);

        return $this->volver($request, $pregunta, $ok ? 'Pregunta guardada en tu repaso.' : null,
            $ok ? null : 'Esa pregunta no está disponible para guardarla.');
    }

    public function quitar(Request $request, int $pregunta): RedirectResponse
    {
        $this->repaso->quitar($request->user('web'), $pregunta);

        return $this->volver($request, $pregunta, 'Pregunta quitada de tu repaso.');
    }

    public function reportar(Request $request, int $pregunta): RedirectResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', Rule::in(array_keys(RepasoService::MOTIVOS))],
            'comentario' => ['nullable', 'string', 'max:300'],
            'intento_id' => ['nullable', 'integer'],
            'practica_id' => ['nullable', 'integer'],
        ]);

        $resultado = $this->repaso->reportar($request->user('web'), $pregunta, $datos['motivo'], $datos['comentario'] ?? null,
            isset($datos['intento_id']) ? (int) $datos['intento_id'] : null, isset($datos['practica_id']) ? (int) $datos['practica_id'] : null);

        return match ($resultado) {
            'creado' => $this->volver($request, $pregunta, 'Gracias. Tu reporte quedó registrado y un docente lo revisará.'),
            'duplicado' => $this->volver($request, $pregunta, 'Ya reportaste esta pregunta; tu reporte sigue en revisión.'),
            default => $this->volver($request, $pregunta, null, 'No se pudo registrar el reporte de esa pregunta.'),
        };
    }

    public function guardadas(Request $request, ContextoAlumnoService $contexto): View
    {
        $usuario = $request->user('web');
        $filtros = $request->validate([
            'asignatura' => ['nullable', 'string', 'max:150'],
            'tema' => ['nullable', 'string', 'max:150'],
        ]);
        $todas = $this->repaso->guardadas($usuario);
        $lista = $todas
            ->when($filtros['asignatura'] ?? null, fn ($c, $a) => $c->where('asignatura', $a))
            ->when($filtros['tema'] ?? null, fn ($c, $t) => $c->where('tema', $t))
            ->values();

        return view('alumno.estudio.guardadas', [
            'usuario' => $usuario,
            'ctx' => $contexto->contexto($usuario),
            'filtros' => $filtros,
            'asignaturas' => $todas->pluck('asignatura')->unique()->sort()->values(),
            'temas' => $todas->when($filtros['asignatura'] ?? null, fn ($c, $a) => $c->where('asignatura', $a))->pluck('tema')->unique()->sort()->values(),
            // Explicación completa: la pregunta ya era visible para el alumno al guardarla.
            'preguntas' => $lista->take(50)->map(fn ($g) => ['guardada' => $g, 'datos' => $this->repaso->explicacion($usuario, $g->pregunta_id)])
                ->filter(fn ($p) => $p['datos'] !== null)->values(),
            'total' => $lista->count(),
        ]);
    }

    /** Vuelve a la misma pregunta de la página anterior con un mensaje de confirmación. */
    private function volver(Request $request, int $pregunta, ?string $ok, ?string $error = null): RedirectResponse
    {
        $destino = url()->previous() ?: route('alumno.guardadas');

        return redirect()->to(strtok($destino, '#').'#pregunta-'.$pregunta)
            ->with($error ? 'repaso_error' : 'repaso_ok', $error ?? $ok)
            ->with('repaso_pregunta', $pregunta);
    }
}
