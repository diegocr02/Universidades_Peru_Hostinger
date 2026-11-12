<?php
// Copiar como ../backup-receptor-config.php fuera del directorio público del receptor.
// TOKEN distinto por cada servidor; usar 32+ caracteres generados aleatoriamente.
return [
    'token' => 'REEMPLAZAR_CON_UN_TOKEN_ALEATORIO_MUY_LARGO',
    'directorio' => '/home/usuario/respaldos-cni-privados',
    'max_bytes' => 262144000,
];