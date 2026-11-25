<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\Alumno\ContextoAlumnoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Páginas de estudio: solo estudiantes activos que ya eligieron universidad. */
class SoloEstudiante
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user('web');
        abort_unless($usuario instanceof Usuario && $usuario->activo && $usuario->rol === 'estudiante', 403);
        if (app(ContextoAlumnoService::class)->contexto($usuario) === null) {
            return redirect()->route('alumno.panel');
        }

        return $next($request);
    }
}
