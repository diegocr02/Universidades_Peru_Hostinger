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
            'problema' => self::problemaDeConfiguracion(),
            'destinos' => array_map(fn ($d): bool => !empty($d['url']) && !empty($d['token']),
                (array) config('contingencia.destinos')),
        ]);
    }

    /**
     * Requisitos del servidor para cifrar, explicados en lenguaje simple.
     * Devuelve null si todo está listo.
     */
    public static function problemaDeConfiguracion(): ?string
    {
        if (!extension_loaded('sodium')) {
            return 'Falta activar la extensión PHP "sodium" en el hosting (hPanel → Avanzado → Configuración de PHP → Extensiones PHP). Sin ella no se pueden cifrar los respaldos.';
        }
        $clave = base64_decode((string) config('contingencia.clave'), true);
        if ($clave === false || strlen($clave) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            return 'CNI_BACKUP_KEY no es válida en el .env: debe ser una clave de 32 bytes en base64.';
        }

        return null;
    }

    public function crear(Request $request): RedirectResponse
    {
        if ($problema = self::problemaDeConfiguracion()) {
            return back()->with('error', $problema);
        }
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