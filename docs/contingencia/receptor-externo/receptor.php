<?php

declare(strict_types=1);

// Instalar ÚNICAMENTE en un dominio HTTPS de un servidor INDEPENDIENTE.
// Archivo de configuración fuera de public_html (ver receptor-config.example.php).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function salida(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') salida(405, ['error' => 'Método no permitido']);
$config = dirname(__DIR__).'/backup-receptor-config.php';
if (!is_file($config)) salida(503, ['error' => 'Receptor no configurado']);
$datos = require $config;
$token = (string) ($datos['token'] ?? '');
$dir = (string) ($datos['directorio'] ?? '');
if (strlen($token) < 32 || $dir === '') salida(503, ['error' => 'Configuración incompleta']);
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!hash_equals('Bearer '.$token, $auth)) salida(401, ['error' => 'No autorizado']);

$nombre = (string) ($_SERVER['HTTP_X_CNI_FILENAME'] ?? '');
$sha = strtolower((string) ($_SERVER['HTTP_X_CNI_SHA256'] ?? ''));
if (!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}\.cni$/D', $nombre)
    || !preg_match('/^[0-9a-f]{64}$/D', $sha)) salida(400, ['error' => 'Encabezados incorrectos']);
$limite = max(1, (int) ($datos['max_bytes'] ?? 262144000));
$long = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($long <= 0 || $long > $limite) salida(413, ['error' => 'Archivo vacío o excede el límite']);
if (!is_dir($dir) && !@mkdir($dir, 0700, true)) salida(500, ['error' => 'No se pudo preparar almacenamiento']);
@chmod($dir, 0700);
$final = rtrim($dir, '/').'/'.$nombre;
if (file_exists($final)) {
    if (hash_equals((string) hash_file('sha256', $final), $sha)) salida(200, ['ok' => true, 'sha256' => $sha, 'duplicado' => true]);
    salida(409, ['error' => 'Ya existe otro archivo con ese nombre']);
}
$tmp = $final.'.tmp.'.bin2hex(random_bytes(5));
$in = fopen('php://input', 'rb');
$out = fopen($tmp, 'xb');
if (!$in || !$out) salida(500, ['error' => 'No se pudo crear archivo temporal']);
@chmod($tmp, 0600);
$hash = hash_init('sha256');
$bytes = 0;
try {
    while (!feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false) throw new RuntimeException('Lectura interrumpida');
        if ($chunk === '') break;
        $bytes += strlen($chunk);
        if ($bytes > $limite) throw new RuntimeException('Límite excedido');
        hash_update($hash, $chunk);
        if (fwrite($out, $chunk) !== strlen($chunk)) throw new RuntimeException('Escritura incompleta');
    }
    if (!fflush($out)) throw new RuntimeException('Error al confirmar escritura');
    fclose($in); fclose($out);
    if ($bytes !== $long || !hash_equals($sha, hash_final($hash))) throw new RuntimeException('Contenido incompleto o checksum inválido');
    if (!rename($tmp, $final)) throw new RuntimeException('Error almacenando archivo');
    salida(200, ['ok' => true, 'sha256' => $sha]);
} catch (Throwable $e) {
    if (is_resource($in)) fclose($in);
    if (is_resource($out)) fclose($out);
    @unlink($tmp);
    salida(400, ['error' => 'Transferencia rechazada']);
}