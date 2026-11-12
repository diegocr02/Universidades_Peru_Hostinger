<?php

declare(strict_types=1);

namespace App\Support\Contingencia;

use RuntimeException;

/**
 * Formato CNI1: 4 bytes de versión, cabecera secretstream de 24 bytes,
 * y registros [longitud uint32 BE][cifrado autenticado].
 * El flujo usa memoria constante incluso con archivos grandes.
 */
final class CifradoRespaldo
{
    private const MAGIC = 'CNI1';
    private const CHUNK = 65536;

    private function clave(): string
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('La extensión PHP sodium no está habilitada.');
        }
        $raw = base64_decode((string) config('contingencia.clave'), true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Configura CNI_BACKUP_KEY (32 bytes en base64) antes de generar respaldos.');
        }
        return $raw;
    }

    public function cifrar(string $origen, string $destino): void
    {
        $entrada = @fopen($origen, 'rb');
        $salida = @fopen($destino, 'xb');
        if ($entrada === false || $salida === false) {
            if (is_resource($entrada)) fclose($entrada);
            if (is_resource($salida)) fclose($salida);
            throw new RuntimeException('No se pudo abrir el archivo temporal para cifrar.');
        }
        @chmod($destino, 0600);
        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->clave());
            $this->escribir($salida, self::MAGIC.$header);
            $actual = fread($entrada, self::CHUNK);
            if ($actual === false) throw new RuntimeException('Error leyendo respaldo temporal.');
            while (true) {
                $siguiente = fread($entrada, self::CHUNK);
                if ($siguiente === false) throw new RuntimeException('Error leyendo respaldo temporal.');
                $final = $siguiente === '' && feof($entrada);
                $cifrado = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state, $actual, '', $final
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
                );
                $this->escribir($salida, pack('N', strlen($cifrado)).$cifrado);
                if ($final) break;
                $actual = $siguiente;
            }
            if (!fflush($salida)) throw new RuntimeException('No se pudo vaciar el búfer cifrado.');
        } catch (\Throwable $e) {
            fclose($entrada);
            fclose($salida);
            @unlink($destino);
            throw $e;
        }
        fclose($entrada);
        fclose($salida);
    }

    /** Verifica autenticidad de todo el archivo, sin escribir SQL en disco. */
    public function verificar(string $ruta): bool
    {
        $this->procesar($ruta, null);
        return true;
    }

    /** Descifra a un archivo .sql.gz para restaurar en una BD NUEVA. */
    public function descifrar(string $ruta, string $destino): void
    {
        if (file_exists($destino)) throw new RuntimeException('El destino ya existe: no se sobrescribe.');
        $salida = @fopen($destino, 'xb');
        if ($salida === false) throw new RuntimeException('No se pudo crear el destino.');
        @chmod($destino, 0600);
        try {
            $this->procesar($ruta, $salida);
            if (!fflush($salida)) throw new RuntimeException('Fallo de escritura del archivo descifrado.');
        } catch (\Throwable $e) {
            fclose($salida);
            @unlink($destino);
            throw $e;
        }
        fclose($salida);
    }

    private function procesar(string $ruta, $salida): void
    {
        $entrada = @fopen($ruta, 'rb');
        if ($entrada === false) throw new RuntimeException('No se pudo abrir el respaldo cifrado.');
        try {
            $prefijo = $this->leer($entrada, 4 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (substr($prefijo, 0, 4) !== self::MAGIC) {
                throw new RuntimeException('Formato de respaldo no reconocido.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($prefijo, 4), $this->clave());
            $final = false;
            while (!feof($entrada)) {
                $tamanio = fread($entrada, 4);
                if ($tamanio === '') break;
                if (strlen($tamanio) !== 4) throw new RuntimeException('Respaldo truncado.');
                $n = unpack('N', $tamanio)[1];
                if ($n < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                    || $n > self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new RuntimeException('Tamaño de bloque cifrado inválido.');
                }
                $bloque = $this->leer($entrada, $n);
                $resultado = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $bloque);
                if ($resultado === false) throw new RuntimeException('Respaldo alterado o clave incorrecta.');
                [$texto, $tag] = $resultado;
                if (is_resource($salida)) $this->escribir($salida, $texto);
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $final = true;
                    if (fread($entrada, 1) !== '') throw new RuntimeException('Datos inesperados después del final.');
                    break;
                }
            }
            if (!$final) throw new RuntimeException('Respaldo incompleto: falta el bloque final.');
        } finally {
            fclose($entrada);
        }
    }

    private function leer($f, int $n): string
    {
        $contenido = '';
        while (strlen($contenido) < $n) {
            $bloque = fread($f, $n - strlen($contenido));
            if ($bloque === false || $bloque === '') throw new RuntimeException('Respaldo truncado.');
            $contenido .= $bloque;
        }
        return $contenido;
    }

    private function escribir($f, string $contenido): void
    {
        $total = strlen($contenido);
        for ($i = 0; $i < $total;) {
            $n = fwrite($f, substr($contenido, $i));
            if ($n === false || $n === 0) throw new RuntimeException('No se pudo completar la escritura del respaldo.');
            $i += $n;
        }
    }
}