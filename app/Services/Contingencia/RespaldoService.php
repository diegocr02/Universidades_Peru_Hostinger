<?php

declare(strict_types=1);

namespace App\Services\Contingencia;

use App\Support\Contingencia\CifradoRespaldo;
use RuntimeException;

final class RespaldoService
{
    public function __construct(
        private readonly ExportadorMariaDb $exportador,
        private readonly CifradoRespaldo $cifrado,
    ) {}

    public function directorio(): string
    {
        $carpeta = (string) config('contingencia.directorio');
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear el directorio privado de respaldos.');
        }
        @chmod($carpeta, 0700);
        return $carpeta;
    }

    /** Genera una copia local cifrada y su manifiesto; no altera datos existentes. */
    public function crear(): array
    {
        if (!config('contingencia.habilitado')) {
            throw new RuntimeException('Activa CNI_BACKUP_ENABLED=true tras configurar la clave.');
        }
        $dir = $this->directorio();
        $bloqueo = fopen($dir.'/operacion.lock', 'c');
        if (!$bloqueo || !flock($bloqueo, LOCK_EX | LOCK_NB)) {
            if (is_resource($bloqueo)) fclose($bloqueo);
            throw new RuntimeException('Ya hay un respaldo en proceso.');
        }
        $id = now()->format('Ymd_His').'_'.bin2hex(random_bytes(4));
        $temporal = $dir.'/'.$id.'.sql.gz.tmp';
        $archivo = $dir.'/'.$id.'.cni';
        try {
            $metricas = $this->exportador->exportar($temporal);
            $this->cifrado->cifrar($temporal, $archivo);
            if (!$this->cifrado->verificar($archivo)) {
                throw new RuntimeException('No se pudo verificar el archivo cifrado.');
            }
            $manifest = [
                'id' => $id,
                'fecha' => now()->toIso8601String(),
                'archivo' => $id.'.cni',
                'bytes' => filesize($archivo),
                'sha256' => hash_file('sha256', $archivo),
                'tablas' => $metricas['tablas'],
                'filas' => $metricas['filas'],
                'cifrado' => 'libsodium-secretstream-xchacha20poly1305',
                'verificado' => true,
                'destinos' => [],
            ];
            $this->guardar($manifest);
            return $manifest;
        } catch (\Throwable $e) {
            @unlink($archivo);
            throw $e;
        } finally {
            @unlink($temporal);
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    public function guardar(array $registro): void
    {
        $id = $this->validarId((string) ($registro['id'] ?? ''));
        $dir = $this->directorio();
        $json = json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp = $dir.'/'.$id.'.json.tmp.'.bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('No se pudo guardar el manifiesto.');
        @chmod($tmp, 0600);
        if (!rename($tmp, $dir.'/'.$id.'.json')) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo publicar el manifiesto.');
        }
    }

    public function obtener(string $id): array
    {
        $id = $this->validarId($id);
        $ruta = $this->directorio().'/'.$id.'.json';
        if (!is_file($ruta)) throw new RuntimeException('Respaldo no encontrado.');
        $datos = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($datos) || ($datos['id'] ?? '') !== $id) {
            throw new RuntimeException('El manifiesto del respaldo es inválido.');
        }
        return $datos;
    }

    public function archivo(string $id): string
    {
        $ruta = $this->directorio().'/'.$this->validarId($id).'.cni';
        if (!is_file($ruta)) throw new RuntimeException('Archivo de respaldo no disponible.');
        return $ruta;
    }

    public function comprobar(string $id): bool
    {
        $manifest = $this->obtener($id);
        $ruta = $this->archivo($id);
        if (!hash_equals((string) $manifest['sha256'], hash_file('sha256', $ruta))) {
            throw new RuntimeException('La suma SHA-256 no coincide.');
        }
        return $this->cifrado->verificar($ruta);
    }

    public function descifrar(string $id, string $destino): void
    {
        $this->comprobar($id);
        $this->cifrado->descifrar($this->archivo($id), $destino);
    }

    public function listar(): array
    {
        $archivos = glob($this->directorio().'/*.json') ?: [];
        rsort($archivos);
        $salida = [];
        foreach (array_slice($archivos, 0, 50) as $archivo) {
            try {
                $id = basename($archivo, '.json');
                $m = $this->obtener($id);
                $m['disponible'] = is_file($this->directorio().'/'.$id.'.cni');
                $salida[] = $m;
            } catch (\Throwable $e) {
                // No permitir que un manifiesto corrupto derribe el panel.
                report($e);
            }
        }
        return $salida;
    }

    /** Elimina solo copias locales antiguas que llegaron a al menos un destino. */
    public function depurar(): int
    {
        $dias = max(7, (int) config('contingencia.retencion_dias', 14));
        $cantidad = 0;
        foreach ($this->listar() as $m) {
            if (strtotime((string) $m['fecha']) >= time() - 86400 * $dias) continue;
            $confirmadas = array_filter((array) ($m['destinos'] ?? []),
                fn ($estado) => ($estado['estado'] ?? '') === 'enviado');
            if (!$confirmadas) continue;
            @unlink($this->directorio().'/'.$m['id'].'.cni');
            @unlink($this->directorio().'/'.$m['id'].'.json');
            $cantidad++;
        }
        return $cantidad;
    }

    private function validarId(string $id): string
    {
        if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/D', $id)) {
            throw new RuntimeException('Identificador de respaldo inválido.');
        }
        return $id;
    }
}