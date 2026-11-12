<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contingencia\DistribucionService;
use Illuminate\Console\Command;

final class ContingenciaReenviar extends Command
{
    protected $signature = 'contingencia:reenviar';
    protected $description = 'Reintenta la entrega de respaldos pendientes cuando vuelve la conexión';

    public function handle(DistribucionService $distribucion): int
    {
        if (!config('contingencia.habilitado')) {
            $this->error('El sistema de respaldos no está habilitado.');
            return self::FAILURE;
        }
        $resultado = $distribucion->enviarPendientes();
        $this->line('Respaldos revisados: '.$resultado['revisados'].'; fallas: '.$resultado['fallidos']);
        return $resultado['fallidos'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}