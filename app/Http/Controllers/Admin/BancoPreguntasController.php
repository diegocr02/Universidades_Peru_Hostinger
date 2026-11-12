<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PreguntaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BancoPreguntasController extends Controller
{
    public function __construct(private readonly PreguntaService $preguntas) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'tema_id' => ['nullable', 'integer', 'exists:temas,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'buscar' => ['nullable', 'string', 'max:200'],
        ]);
        $consulta = DB::table('preguntas as p')->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->select('p.id', 'p.codigo', 'p.enunciado', 'p.imagen', 'p.activo', 'p.created_at',
                't.nombre as tema', 'a.nombre as asignatura');
        if (! empty($filtros['tema_id'])) {
            $consulta->where('t.id', $filtros['tema_id']);
        }
        if (! empty($filtros['area_id'])) {
            $consulta->where('a.id', $filtros['area_id']);
        }
        if (! empty($filtros['estado'])) {
            $consulta->where('p.activo', $filtros['estado'] === 'activo');
        }
        $buscar = trim($filtros['buscar'] ?? '');
        if ($buscar !== '') {
            $consulta->where(function ($query) use ($buscar) {
                $query->where('p.enunciado', 'like', '%'.$buscar.'%')
                    ->orWhere('p.codigo', 'like', '%'.$buscar.'%');
                if (ctype_digit($buscar)) {
                    $query->orWhere('p.id', $buscar);
                }
            });
        }

        return view('admin.preguntas.index', [
            'preguntas' => $consulta->orderByDesc('p.id')->paginate(15)->withQueryString(),
            'temas' => DB::table('temas')->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => DB::table('areas')->orderBy('nombre')->get(['id', 'nombre']),
            'totalPreguntas' => DB::table('preguntas')->count(),
            'totalIntentos' => DB::table('intentos')->count(),
            'puedeVaciarBanco' => $request->user('web')?->usuario === 'Super_Admin',
        ]);
    }

    public function show(int $pregunta): View
    {
        $registro = DB::table('preguntas as p')->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->select('p.*', 't.nombre as tema', 'a.nombre as asignatura')
            ->where('p.id', $pregunta)->first();
        abort_unless($registro, 404);

        return view('admin.preguntas.ver', [
            'pregunta' => $registro,
            'alternativas' => DB::table('alternativas')->where('pregunta_id', $pregunta)->orderBy('letra')->get(),
        ]);
    }

    public function destroy(int $pregunta): RedirectResponse
    {
        $this->preguntas->eliminar($pregunta);

        return redirect()->route('admin.preguntas.index')
            ->with('status', 'Pregunta desactivada sin borrar su historial.');
    }
}
