<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use App\Models\AreaPostulacion;
use App\Models\Carrera;
use App\Models\Usuario;
use App\Services\ResultadoService;
use App\Services\SimulacroService;
use App\Services\Alumno\DashboardEstudianteService;
use App\Services\Alumno\PanelUniService;
use App\Services\Alumno\ProgresoAlumnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrientacionController extends Controller
{
    private function estudiante(Request $request): Usuario
    {
        $usuario = $request->user('web');
        abort_unless($usuario && $usuario->activo && $usuario->rol === 'estudiante', 403);
        return $usuario;
    }

    public function elegirArea(Request $request): View|RedirectResponse
    {
        $usuario = $this->estudiante($request);
        if ($usuario->area_postulacion_id !== null || $usuario->carrera_id !== null) {
            return redirect()->route('alumno.panel');
        }

        return view('alumno.elegir-area', [
            'areas' => AreaPostulacion::query()->where('activo', true)->orderBy('codigo')->get(),
        ]);
    }

    public function guardarArea(Request $request): RedirectResponse
    {
        $usuario = $this->estudiante($request);
        abort_if($usuario->area_postulacion_id !== null || $usuario->carrera_id !== null, 403,
            'Ya tienes un área de preparación asignada.');

        $datos = $request->validate([
            'area_id' => ['required', 'integer', Rule::exists('areas_postulacion', 'id')->where('activo', true)],
        ]);

        // La primera elección se guarda en MariaDB, no solo en sesión, para futuros ingresos.
        $guardado = Usuario::query()->whereKey($usuario->id)
            ->whereNull('area_postulacion_id')->whereNull('carrera_id')
            ->update(['area_postulacion_id' => (int) $datos['area_id']]);
        abort_unless($guardado === 1, 409, 'La selección ya se realizó desde otra sesión.');

        $usuario->refresh();

        return redirect()->route('alumno.panel')
            ->with('status', 'Área de preparación seleccionada.');
    }

    public function panel(Request $request, SimulacroService $servicio, DashboardEstudianteService $dashboard, ProgresoAlumnoService $progresoService): View|RedirectResponse
    {
        $usuario = $this->estudiante($request);
        $carrera = $usuario->carrera()->with('areaPostulacion')->first();

        // Las cuentas antiguas con carrera y sin área explícita mantienen su acceso.
        $area = $usuario->areaPostulacion()->first() ?? $carrera?->areaPostulacion;

        if ($carrera && (! $carrera->activo || ! $carrera->areaPostulacion?->activo)) {
            abort(403, 'La carrera del estudiante está inactiva.');
        }
        if ($carrera && $area && (int) $carrera->area_postulacion_id !== (int) $area->id) {
            abort(403, 'La carrera no corresponde al área de preparación asignada.');
        }
        if ($area && ! $area->activo) {
            abort(403, 'El área de preparación está inactiva.');
        }
        if (! $area) {
            return redirect()->route('alumno.elegir-area');
        }

        // KISS: el mismo panel funciona para A-E. Solo cambian identidad y datos del área.
        // No se reasigna carrera ni se cambia la selección persistida en MariaDB.
        abort_unless(config('panel_alumno.areas.'.$area->codigo), 404);

        // UNI (área B): panel propio con el formato de examen en tres días.
        if ($area->codigo === 'B') {
            return view('alumno.uni.panel', [
                'usuario' => $usuario,
                'area' => $area,
                'carrera' => $carrera,
                'uni' => app(PanelUniService::class)->obtener($usuario),
            ]);
        }

        // No hace falta elegir carrera: el área guardada determina qué examen se ofrece.
        $simulacros = $this->simulacrosDelArea($usuario, $area, $carrera, $servicio);

        $buscarValor = $request->query('buscar', '');
        $busqueda = is_string($buscarValor) ? mb_substr(trim($buscarValor), 0, 100) : '';
        $estado = in_array($request->query('estado'), ['en_curso', 'calificado', 'pendiente'], true)
            ? $request->query('estado') : '';
        $orden = in_array($request->query('orden'), ['reciente', 'antiguo', 'puntaje'], true)
            ? $request->query('orden') : 'reciente';

        $historial = DB::table('intentos as i')
            ->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.usuario_id', $usuario->id)
            ->where('i.tipo', 'simulacro')
            ->select('i.*', 's.nombre as simulacro_nombre', 's.puntaje_correcta')
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('s.nombre', 'like', '%'.$busqueda.'%');
                    $numero = ltrim($busqueda, '#0');
                    if (ctype_digit($numero)) {
                        $q->orWhere('i.id', (int) $numero);
                    }
                });
            });

        if ($estado === 'en_curso') {
            $historial->where('i.estado', 'en_curso');
        } elseif ($estado === 'calificado') {
            $historial->where('i.estado', '!=', 'en_curso')->whereNotNull('i.puntaje_total');
        } elseif ($estado === 'pendiente') {
            $historial->where('i.estado', '!=', 'en_curso')->whereNull('i.puntaje_total');
        }

        if ($orden === 'antiguo') {
            $historial->orderBy('i.fecha_inicio')->orderBy('i.id');
        } elseif ($orden === 'puntaje') {
            $historial->orderByRaw('i.puntaje_total IS NULL')
                ->orderByDesc('i.puntaje_total')->orderByDesc('i.id');
        } else {
            $historial->orderByDesc('i.fecha_inicio')->orderByDesc('i.id');
        }
        $intentos = $historial->paginate(10)->withQueryString()->fragment('historial');

        // La primera tarjeta del selector es UNDC. Las otras cuatro áreas
        // siguen usando el panel compartido sin ninguna modificación visual.
        $vistaPanel = $area->codigo === 'A' ? 'alumno.undc.panel' : 'alumno.panel';

        return view($vistaPanel, [
            'usuario' => $usuario,
            'area' => $area,
            'carrera' => $carrera,
            'carreras' => $carrera ? collect() : Carrera::query()->where('activo', true)
                ->where('area_postulacion_id', $area->id)->orderBy('nombre')->get(),
            'simulacros' => $simulacros,
            'intentos' => $intentos,
            'dashboard' => $dashboard->obtener($usuario, $simulacros),
            'progreso' => $progresoService->obtener($usuario),
            'esVistaPrevia' => false,
        ]);
    }


    /**
     * Misma autorización de áreas y carreras usada en el panel y en el detalle
     * del tema. Nunca exponemos contenidos de otras áreas al navegar por URL.
     */
    private function simulacrosDelArea(
        Usuario $usuario,
        AreaPostulacion $area,
        ?Carrera $carrera,
        SimulacroService $servicio
    ): \Illuminate\Support\Collection {
        return $servicio->listar($usuario)->filter(function (array $simulacro) use ($area, $carrera) {
            $incluyeArea = collect($simulacro['areas_postulacion'])->contains(
                fn (array $asociada) => (int) $asociada['id'] === (int) $area->id
            );
            $permiteCarrera = $simulacro['carrera_id'] === null
                || ($carrera && (int) $carrera->id === (int) $simulacro['carrera_id']);

            return $incluyeArea && $permiteCarrera && $simulacro['activo']
                && $simulacro['fecha_inicio'] && $simulacro['fecha_fin']
                && now()->gte($simulacro['fecha_inicio']) && now()->lt($simulacro['fecha_fin']);
        })->values();
    }

    /**
     * Detalle de estudio de un tema real y permitido para la sección académica
     * que eligió el alumno. Solo muestra agregados, nunca respuestas correctas.
     */
    public function tema(
        Request $request,
        int $tema,
        SimulacroService $servicio,
        DashboardEstudianteService $dashboard
    ): View|RedirectResponse {
        $usuario = $this->estudiante($request);
        $carrera = $usuario->carrera()->with('areaPostulacion')->first();
        $area = $usuario->areaPostulacion()->first() ?? $carrera?->areaPostulacion;

        if (! $area) {
            return redirect()->route('alumno.elegir-area');
        }
        abort_unless($area->activo && config('panel_alumno.areas.'.$area->codigo), 404);
        abort_if($carrera && (! $carrera->activo || ! $carrera->areaPostulacion?->activo
            || (int) $carrera->area_postulacion_id !== (int) $area->id), 403);

        $simulacros = $this->simulacrosDelArea($usuario, $area, $carrera, $servicio);
        $resumen = $dashboard->obtener($usuario, $simulacros);
        $temaActual = $resumen['temas']->firstWhere('id', $tema);
        abort_unless($temaActual, 404);

        return view('alumno.tema', [
            'usuario' => $usuario,
            'area' => $area,
            'tema' => $temaActual,
        ]);
    }

    // Carrera opcional: sirve para personalizar el perfil sin bloquear el examen por área.
    public function guardarCarrera(Request $request): RedirectResponse
    {
        $usuario = $this->estudiante($request);
        abort_if($usuario->carrera_id !== null, 403, 'La carrera ya fue seleccionada.');

        $datos = $request->validate([
            'carrera_id' => ['required', 'integer', Rule::exists('carreras', 'id')->where('activo', true)],
        ]);
        $carrera = Carrera::with('areaPostulacion')->findOrFail($datos['carrera_id']);

        $areaId = $usuario->area_postulacion_id;
        if (! $areaId || ! $carrera->areaPostulacion?->activo
            || (int) $carrera->area_postulacion_id !== (int) $areaId) {
            throw ValidationException::withMessages([
                'carrera_id' => 'Selecciona una carrera activa de tu área de preparación.',
            ]);
        }

        $guardado = Usuario::query()->whereKey($usuario->id)->whereNull('carrera_id')
            ->where('area_postulacion_id', $areaId)
            ->update(['carrera_id' => $carrera->id]);
        abort_unless($guardado === 1, 409, 'La carrera ya fue asignada.');

        $usuario->refresh();
        return redirect()->route('alumno.panel')->with('status', 'Carrera guardada correctamente.');
    }

    public function resultados(Request $request, int $intento, ResultadoService $resultados): View
    {
        $usuario = $this->estudiante($request);

        // Autorizar por propietario ANTES de obtener cualquier solución o clave.
        $intentoActual = DB::table('intentos as i')
            ->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.id', $intento)
            ->where('i.usuario_id', $usuario->id)
            ->select('i.id', 's.nombre as simulacro_nombre')
            ->firstOrFail();

        // El servicio rechaza los intentos sin finalizar o todavía sin calificación.
        $resultado = $resultados->mostrar($intento);

        // Las soluciones sólo se consultan después de validar propietario y finalización.
        [$preguntas, $alternativas] = $this->revisionDelIntento($intento);

        if (! str_starts_with((string) ($intentoActual->simulacro_nombre ?? ''), 'UNDC - Admisión Ordinaria Tipo ')) {
            // Día UNI: desglose con la escala de su propia plantilla (CalificacionUniService).
            $intentoUni = app(PanelUniService::class)->intentoDelAlumno($usuario, $intento);

            return view('alumno.resultados', [
                'usuario' => $usuario,
                'resultado' => $resultado,
                'intentoUni' => $intentoUni,
                'simulacroNombre' => $intentoActual->simulacro_nombre,
                'preguntas' => $preguntas,
                'alternativasPorPregunta' => $alternativas,
            ]);
        }

        return view('alumno.undc.resultados', [
            'usuario' => $usuario,
            'resultado' => $resultado,
            'simulacroNombre' => $intentoActual->simulacro_nombre,
            'preguntas' => $preguntas,
            'alternativasPorPregunta' => $alternativas,
        ]);
    }

    /**
     * Preguntas del intento con la respuesta del alumno, la clave y la solución.
     * Incluye las que dejó en blanco. Llamar solo con un intento propio y ya calificado.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function revisionDelIntento(int $intento): array
    {
        $preguntas = DB::table('respuestas_estudiante as re')
            ->join('preguntas as p', 'p.id', '=', 're.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('re.intento_id', $intento)
            ->orderBy('re.orden')
            ->select(
                're.orden', 're.alternativa_id', 're.estado_respuesta',
                'p.id as pregunta_id', 'p.enunciado', 'p.imagen', 'p.solucion',
                'a.nombre as asignatura'
            )
            ->get();

        $alternativas = DB::table('alternativas')
            ->whereIn('pregunta_id', $preguntas->pluck('pregunta_id'))
            ->orderBy('letra')
            ->get(['id', 'pregunta_id', 'letra', 'texto', 'imagen', 'es_correcta'])
            ->groupBy('pregunta_id');

        return [$preguntas, $alternativas];
    }
}
