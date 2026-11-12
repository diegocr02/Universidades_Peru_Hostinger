<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contingencia\RespaldoService;
use Illuminate\Console\Command;

final class ContingenciaDescifrar extends Command
{
    protected $signature = 'contingencia:descifrar {id : Identificador del respaldo} {destino : Ruta ABSOLUTA de .sql.gz fuera de public}';
    protected $description = 'Descifra un respaldo para restaurarlo manualmente en una BD de pruebas vacía';

    public function handle(RespaldoService $respaldos): int
    {
        $destino = (string) $this->argument('destino');
        $absoluta = str_starts_with($destino, '/')
            || (strlen($destino) >= 3 && ctype_alpha($destino[0]) && $destino[1] === ':'
                && ($destino[2] === '/' || $destino[2] === chr(92)));
        if (!str_ends_with(strtolower($destino), '.sql.gz') || !$absoluta) {
            $this->error('Usa una ruta absoluta terminada en .sql.gz, FUERA del sitio web.');
            return self::FAILURE;
        }
        if (str_starts_with($destino, public_path()) || str_contains($destino, '..')) {
            $this->error('No se permite descifrar en public/ ni usar .. en la ruta.');
            return self::FAILURE;
        }
        try {
            $respaldos->descifrar((string) $this->argument('id'), $destino);
            $this->info('Archivo generado: '.$destino);
            $this->warn('Contiene datos personales SIN CIFRAR. Restaura solo en una BD vacía y elimínalo al terminar.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('No se pudo descifrar o verificar el respaldo: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}