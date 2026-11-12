<?php

declare(strict_types=1);

namespace App\Services\Contingencia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Envío HTTP cifrado a una o dos instancias receptoras independientes. */
final class DistribucionService
{
    public function __construct(private readonly RespaldoService $respaldos) {}

    /** @return array<string, array<string, string>> */
    public function enviar(string $id): array
    {
        $manifest = $this->respaldos->obtener($id);
        $ruta = $this->respaldos->archivo($id);
        $this->respaldos->comprobar($id);
        $destinos = (array) config('contingencia.destinos', []);
        foreach ($destinos as $nombre => $destino) {
            if (empty($destino['url']) || empty($destino['token'])) continue;
            // Si ya llegó, nunca se vuelve a subir automáticamente.
            if (($manifest['destinos'][$nombre]['estado'] ?? '') === 'enviado') continue;
            $url = (string) $destino['url'];
            if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $manifest['destinos'][$nombre] = ['estado' => 'error', 'detalle' => 'Se requiere HTTPS'];
                $this->respaldos->guardar($manifest);
                continue;
            }
            $recurso = fopen($ruta, 'rb');
            if (!$recurso) throw new RuntimeException('No se pudo leer el respaldo cifrado.');
            try {
                $respuesta = Http::withToken((string) $destino['token'])
                    ->withHeaders([
                        'X-CNI-Filename' => $manifest['archivo'],
                        'X-CNI-SHA256' => $manifest['sha256'],
                        'Content-Length' => (string) filesize($ruta),
                        'Accept' => 'application/json',
                    ])
                    ->connectTimeout(15)->timeout(180)
                    ->withBody($recurso, 'application/octet-stream')->put($url);
                if (!$respuesta->successful()
                    || $respuesta->json('sha256') !== $manifest['sha256']) {
                    throw new RuntimeException('El receptor no confirmó integridad (HTTP '.$respuesta->status().').');
                }
                $manifest['destinos'][$nombre] = [
                    'estado' => 'enviado',
                    'fecha' => now()->toIso8601String(),
                    'sha256' => $manifest['sha256'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Fallo enviando copia externa', ['id' => $id, 'destino' => $nombre, 'tipo' => get_class($e)]);
                $manifest['destinos'][$nombre] = [
                    'estado' => 'error',
                    'fecha' => now()->toIso8601String(),
                    'detalle' => 'No se pudo confirmar la entrega',
                ];
            } finally {
                fclose($recurso);
                $this->respaldos->guardar($manifest);
            }
        }
        return $manifest['destinos'];
    }

    public function enviarPendientes(): array
    {
        $resultado = ['revisados' => 0, 'fallidos' => 0];
        foreach ($this->respaldos->listar() as $manifest) {
            if (!$manifest['disponible']) continue;
            $resultado['revisados']++;
            try {
                $estados = $this->enviar($manifest['id']);
                foreach ($estados as $s) {
                    if (($s['estado'] ?? '') === 'error') $resultado['fallidos']++;
                }
            } catch (\Throwable $e) {
                Log::error('Fallo procesando respaldo pendiente', ['id' => $manifest['id'], 'tipo' => get_class($e)]);
                $resultado['fallidos']++;
            }
        }
        return $resultado;
    }
}