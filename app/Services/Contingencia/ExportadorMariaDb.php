<?php

declare(strict_types=1);

namespace App\Services\Contingencia;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Exportación lógica de tablas InnoDB en instantánea consistente.
 * No necesita mysqldump ni funciones exec() (hosting compartido).
 * No exporta triggers, procedimientos, eventos ni vistas.
 * Restaurar únicamente en una BD NUEVA/VACÍA.
 */
final class ExportadorMariaDb
{
    /** @return array{tablas:int,filas:int} */
    public function exportar(string $archivoGz): array
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('El respaldo requiere conexión MariaDB/MySQL.');
        }
        $salida = @gzopen($archivoGz, 'wb6');
        if ($salida === false) throw new RuntimeException('No se pudo crear el archivo SQL temporal.');
        @chmod($archivoGz, 0600);
        $iniciado = false;
        $tablas = 0;
        $filas = 0;
        try {
            $this->escribir($salida, "-- Simulador CNI: respaldo lógico de tablas InnoDB.\n"
                ."-- Restaurar en una base de datos NUEVA y VACÍA.\n"
                ."SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            // SHOW TABLE STATUS se ejecuta antes de abrir la instantánea.
            $status = $pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
            $nombres = [];
            foreach ($status as $fila) {
                if (($fila['Comment'] ?? '') === 'VIEW' || ($fila['Engine'] ?? '') === null) {
                    continue;
                }
                if (strtoupper((string) $fila['Engine']) !== 'INNODB') {
                    throw new RuntimeException('Tabla no InnoDB detectada. No es posible garantizar una instantánea consistente: '. $fila['Name']);
                }
                $nombres[] = (string) $fila['Name'];
            }
            if (!$nombres) throw new RuntimeException('No hay tablas InnoDB para respaldar.');
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $iniciado = true;
            // Lectura por cursor no almacenado en memoria, posible con PDO MySQL.
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            foreach ($nombres as $nombre) {
                $id = $this->identificador($nombre);
                $consultaEstructura = $pdo->query("SHOW CREATE TABLE {$id}");
                $estructura = $consultaEstructura->fetch(PDO::FETCH_ASSOC);
                $consultaEstructura->closeCursor();
                if (!is_array($estructura) || !isset($estructura['Create Table'])) {
                    throw new RuntimeException('No se pudo leer la estructura: '.$nombre);
                }
                $this->escribir($salida, "\n-- Tabla {$nombre}\n".$estructura['Create Table'].";\n");
                $columnas = $pdo->query("SHOW FULL COLUMNS FROM {$id}")->fetchAll(PDO::FETCH_ASSOC);
                $columnasInsertables = array_values(array_filter($columnas,
                    fn (array $c): bool => !str_contains(strtoupper((string) ($c['Extra'] ?? '')), 'GENERATED')));
                $cols = array_map(fn (array $c): string => $this->identificador((string) $c['Field']), $columnasInsertables);
                $query = $pdo->query("SELECT ".implode(', ', $cols)." FROM {$id}");
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $valores = [];
                    foreach ($columnasInsertables as $col) {
                        $valor = $row[$col['Field']];
                        $tipo = strtolower((string) $col['Type']);
                        $valores[] = $valor === null ? 'NULL'
                            : (preg_match('/^(binary|varbinary|tinyblob|blob|mediumblob|longblob|bit|geometry|point|linestring|polygon|multilinestring|multipolygon)/', $tipo)
                                ? '0x'.bin2hex((string) $valor)
                                : $pdo->quote((string) $valor));
                    }
                    $this->escribir($salida, "INSERT INTO {$id} (".implode(', ', $cols).") VALUES (".implode(', ', $valores).");\n");
                    $filas++;
                }
                $query->closeCursor();
                $tablas++;
            }
            $this->escribir($salida, "\nSET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->exec('COMMIT');
            $iniciado = false;
        } catch (\Throwable $e) {
            if ($iniciado) {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable $ignored) { /* cleanup */ }
            }
            gzclose($salida);
            @unlink($archivoGz);
            throw $e;
        } finally {
            // Esta conexión es gestionada por Laravel; restablecer siempre la opción.
            try { $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); } catch (\Throwable $ignored) { /* cleanup */ }
        }
        if (!gzclose($salida)) {
            @unlink($archivoGz);
            throw new RuntimeException('Error cerrando la exportación comprimida.');
        }
        return ['tablas' => $tablas, 'filas' => $filas];
    }

    private function identificador(string $valor): string
    {
        return '`'.str_replace('`', '``', $valor).'`';
    }

    private function escribir($gz, string $texto): void
    {
        $largo = strlen($texto);
        $escrito = gzwrite($gz, $texto);
        if ($escrito !== $largo) throw new RuntimeException('No se pudo escribir completamente el archivo SQL.');
    }
}