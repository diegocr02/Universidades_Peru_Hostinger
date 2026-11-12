<?php

declare(strict_types=1);

return [
    // Concurso ordinario usado como referencia visual del simulador.
    'proceso' => '2026-II',

    // IMPORTANTE: NO se ha verificado un mínimo oficial de puntos del ordinario 2026-II.
    // 118,5 corresponde a CEPRE (2020); 120 a cobertura del ordinario (2018).
    // Nunca trasladar directamente esos valores a la convocatoria 2026-II.
    'minimo_oficial' => env('UNDC_ADMISION_MINIMO_2026_II') ?: null,

    // Además del mínimo se debe comprobar que la escala de calificación oficial
    // coincida exactamente con la escala del simulacro antes de comparar puntajes.
    'maximo_escala_oficial' => env('UNDC_ADMISION_MAXIMO_2026_II') ?: null,

    // Documento a validar: Prospecto de admisión 2026-II aprobado por RCO 382-2026-UNDC.
    'fuente' => 'https://www.gob.pe/institucion/unicanete/normas-legales/tipos/235-resolucion-de-comision-organizadora',
];
