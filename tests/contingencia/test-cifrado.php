<?php
// Prueba independiente del framework: php tests/test-cifrado.php
// NO usa datos del proyecto ni conecta a ninguna base de datos.

declare(strict_types=1);

require dirname(__DIR__).'/app/Support/Contingencia/CifradoRespaldo.php';

$key = base64_encode(random_bytes(32));
function config(string $key): string { return $GLOBALS['cni_prueba_clave']; }
$GLOBALS['cni_prueba_clave'] = $key;

$dir = sys_get_temp_dir().'/cni_crypto_test_'.bin2hex(random_bytes(5));
mkdir($dir, 0700);
$input = $dir.'/input.gz';
$enc = $dir.'/input.cni';
$out = $dir.'/out.gz';
$altered = $dir.'/altered.cni';
try {
    file_put_contents($input, gzencode('Datos ficticios: '.random_bytes(400000)));
    $c = new \App\Support\Contingencia\CifradoRespaldo();
    $c->cifrar($input, $enc);
    if (!$c->verificar($enc)) throw new RuntimeException('Verificación fallida');
    $c->descifrar($enc, $out);
    if (hash_file('sha256', $input) !== hash_file('sha256', $out)) throw new RuntimeException('No coincide el contenido');
    $raw = file_get_contents($enc);
    $raw[50] = chr(ord($raw[50]) ^ 1);
    file_put_contents($altered, $raw);
    $rechazo = false;
    try { $c->verificar($altered); } catch (RuntimeException $e) { $rechazo = true; }
    if (!$rechazo) throw new RuntimeException('No se detectó la alteración');
    file_put_contents($altered, substr($raw, 0, -15));
    $rechazo = false;
    try { $c->verificar($altered); } catch (RuntimeException $e) { $rechazo = true; }
    if (!$rechazo) throw new RuntimeException('No se detectó el truncamiento');
    echo "OK: cifrado, descifrado, detección de manipulación y truncamiento.\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $f) unlink($f);
    rmdir($dir);
}