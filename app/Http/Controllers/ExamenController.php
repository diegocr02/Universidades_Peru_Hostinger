<?php

namespace App\Http\Controllers;

use App\Services\IntentoService;
use App\Services\Alumno\ExamenIndependienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamenController extends Controller
{
    /** El área se resuelve en el servidor; el navegador no elige cuotas ni alumno. */
    public function independiente(Request $request, ExamenIndependienteService $examenes): RedirectResponse
    {
        abort_unless($request->user('web')?->rol === 'estudiante', 403);
        if ($request->user('web')->area_postulacion_id === null && $request->user('web')->carrera_id === null) {
            return redirect()->route('alumno.elegir-area');
        }
        try {
            $intento = $examenes->iniciar($request->user('web'));
        } catch (ValidationException $e) {
            if (! array_key_exists('preguntas', $e->errors())) {
                throw $e;
            }

            return redirect()->route('examen.vacio');
        }

        return redirect()->route('examen', $intento->id);
    }

    /** Jornada UNI (1–3). El servidor valida el área B; si el banco no alcanza, vuelve al panel con el motivo. */
    public function diaUni(Request $request, int $dia, ExamenIndependienteService $examenes): RedirectResponse
    {
        abort_unless($request->user('web')?->rol === 'estudiante', 403);
        if ($request->user('web')->area_postulacion_id === null && $request->user('web')->carrera_id === null) {
            return redirect()->route('alumno.elegir-area');
        }
        try {
            $intento = $examenes->iniciarDiaUni($request->user('web'), $dia);
        } catch (ValidationException $e) {
            return redirect()->route('alumno.panel')->withErrors($e->errors());
        }

        return redirect()->route('examen', $intento->id);
    }

    /** Examen San Marcos del área autorizada; si falta la distribución o el banco, vuelve al panel con el motivo. */
    public function unmsm(Request $request, ExamenIndependienteService $examenes): RedirectResponse
    {
        abort_unless($request->user('web')?->rol === 'estudiante', 403);
        if ($request->user('web')->area_postulacion_id === null && $request->user('web')->carrera_id === null) {
            return redirect()->route('alumno.elegir-area');
        }
        try {
            $intento = $examenes->iniciarUnmsm($request->user('web'));
        } catch (ValidationException $e) {
            return redirect()->route('alumno.panel')->withErrors($e->errors());
        }

        return redirect()->route('examen', $intento->id);
    }

    /** Reanuda el intento en curso del simulacro o inicia uno nuevo, y abre el examen. */
    public function iniciar(Request $request, int $simulacro, IntentoService $intentos): RedirectResponse
    {
        abort_unless($request->user('web')?->rol === 'estudiante' && ($request->user('web')?->area_postulacion_id !== null || $request->user('web')?->carrera_id !== null), 403);
        $usuarioId = (int) $request->user('web')->getAuthIdentifier();
        $intento = $intentos->enCurso($usuarioId, $simulacro)
            ?? $intentos->iniciar($usuarioId, ['tipo' => 'simulacro', 'simulacro_id' => $simulacro]);

        return redirect()->route('examen', $intento->id);
    }
}
