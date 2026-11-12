<?php

declare(strict_types=1);

return [
    // Activar solamente después de definir CNI_BACKUP_KEY en el archivo .env.
    'habilitado' => (bool) env('CNI_BACKUP_ENABLED', false),
    'panel_habilitado' => (bool) env('CNI_BACKUP_PANEL_ENABLED', false),
    'administrador_usuario' => (string) env('CNI_BACKUP_ADMIN_USER', 'Super_Admin'),
    'clave' => env('CNI_BACKUP_KEY', ''),
    'directorio' => storage_path('app/private/contingencia'),
    'retencion_dias' => (int) env('CNI_BACKUP_RETENTION_DAYS', 14),
    'destinos' => [
        'destino_a' => [
            'url' => (string) env('CNI_BACKUP_ENDPOINT_A', ''),
            'token' => (string) env('CNI_BACKUP_TOKEN_A', ''),
        ],
        'destino_b' => [
            'url' => (string) env('CNI_BACKUP_ENDPOINT_B', ''),
            'token' => (string) env('CNI_BACKUP_TOKEN_B', ''),
        ],
    ],
];