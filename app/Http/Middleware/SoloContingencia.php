<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SoloContingencia
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('contingencia.panel_habilitado'), 404);
        $usuario = $request->user('web');
        abort_unless($usuario && $usuario->activo && $usuario->rol === 'admin', 403);
        $permitido = trim((string) config('contingencia.administrador_usuario', 'Super_Admin'));
        abort_unless($permitido !== '' && hash_equals($permitido, (string) $usuario->usuario), 403);
        return $next($request);
    }
}