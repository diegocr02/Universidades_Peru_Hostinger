<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contingencia\DistribucionService;
use App\Services\Contingencia\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ContingenciaCrear extends Command
{
    protected $signature = 'contingencia:crear {--solo-local : No envía copias a los destinos externos}';
    protected $description = 'Genera un respaldo cifrado de MariaDB y lo distribuye a destinos configurados';

    public function handle(RespaldoService $respaldos, DistribucionService $distribucion): int
    {
        try {
            $copia = $respaldos->crear();
            $this->info('Respaldo cifrado creado: '.$copia['id']);
            $this->line('Tablas: '.$copia['tablas'].'; filas: '.$copia['filas'].'; SHA-256: '.$copia['sha256']);
            if ($this->option('solo-local')) return self::SUCCESS;
            $configurados = array_filter((array) config('contingencia.destinos', []),
                fn ($d) => !empty($d['url']) && !empty($d['token']));
            if (!$configurados) {
                $this->warn('Copia SOLO LOCAL. No hay destinos externos configurados.');
                return self::SUCCESS;
            }
            $estados = $distribucion->enviar($copia['id']);
            $fallas = 0;
            foreach ($configurados as $nombre => $_) {
                $s = $estados[$nombre]['estado'] ?? 'pendiente';
                $this->line("{$nombre}: {$s}");
                if ($s !== 'enviado') $fallas++;
            }
            if ($fallas) {
                $this->error('Al menos un destino no confirmó la recepción. Ejecuta contingencia:reenviar.');
                return self::FAILURE;
            }
            $respaldos->depurar();
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Falló tarea automática de contingencia', ['tipo' => get_class($e), 'detalle' => $e->getMessage()]);
            $this->error('No se pudo generar la copia. Revisa storage/logs/laravel.log.');
            return self::FAILURE;
        }
    }
}