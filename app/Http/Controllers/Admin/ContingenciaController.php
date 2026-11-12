<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Contingencia\DistribucionService;
use App\Services\Contingencia\RespaldoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ContingenciaController extends Controller
{
    public function __construct(
        private readonly RespaldoService $respaldos,
        private readonly DistribucionService $distribucion,
    ) {}

    public function index(): View
    {
        return view('admin.contingencia.index', [
            'respaldos' => $this->respaldos->listar(),
            'activo' => (bool) config('contingencia.habilitado'),
            'destinos' => array_map(fn ($d): bool => !empty($d['url']) && !empty($d['token']),
                (array) config('contingencia.destinos')),
        ]);
    }

    public function crear(Request $request): RedirectResponse
    {
        try {
            $manifest = $this->respaldos->crear();
            $this->distribucion->enviar($manifest['id']);
            return back()->with('status', 'Copia local cifrada creada. Revisa el estado de los destinos en la tabla.');
        } catch (\Throwable $e) {
            Log::error('Error de respaldo desde el panel', ['tipo' => get_class($e), 'detalle' => $e->getMessage()]);
            return back()->with('error', 'No se pudo generar el respaldo. Revisa el log seguro de Laravel.');
        }
    }

    public function enviar(string $id): RedirectResponse
    {
        try {
            $this->distribucion->enviar($id);
            return back()->with('status', 'Transferencia solicitada. Revisa los estados de entrega.');
        } catch (\Throwable $e) {
            Log::warning('Fallo transferencia manual', ['tipo' => get_class($e)]);
            return back()->with('error', 'No se pudo completar la transferencia.');
        }
    }

    public function verificar(string $id): RedirectResponse
    {
        try {
            $this->respaldos->comprobar($id);
            return back()->with('status', 'SHA-256 y autenticidad del respaldo confirmados.');
        } catch (\Throwable $e) {
            Log::warning('Fallo verificación manual', ['tipo' => get_class($e)]);
            return back()->with('error', 'La verificación falló. No uses esta copia.');
        }
    }

    public function descargar(string $id): BinaryFileResponse
    {
        $this->respaldos->comprobar($id);
        return response()->download($this->respaldos->archivo($id), $id.'.cni', [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}