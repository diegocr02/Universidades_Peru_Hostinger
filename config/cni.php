<?php

return [
    // La integración llega a develop sin activar un flujo incompatible con esquemas legados.
    // Activar solamente tras auditar A-E, aplicar la migración y preparar simulacros reales.
    'alumno_areas_enabled' => (bool) env('CNI_ALUMNO_AREAS_ENABLED', false),
    'administracion_academica_enabled' => (bool) env('CNI_ADMIN_ACADEMICO_ENABLED', false),
    // Permite cargar el banco y los exámenes UNDC fuera de local/testing (UndcExamenSeeder y UndcPreguntasSeeder).
    'undc_banco_produccion' => (bool) env('CNI_UNDC_BANCO_PRODUCCION', false),
];
