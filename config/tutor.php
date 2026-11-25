<?php

/*
 | Tutor CNI: asistente de estudio para los paneles de alumnos.
 |
 | Ahorro de consumo: las tarjetas (resultados, progreso, explicaciones revisadas,
 | práctica con calificación y pistas por descarte) se resuelven en el servidor con
 | datos reales y NO llaman a la IA. La IA solo se usa para preguntas libres,
 | «Explícalo más fácil» y explicaciones de preguntas sin solución revisada.
 */
return [
    // Muestra «Tutor IA» en los paneles y habilita la ruta /alumno/tutor.
    'enabled' => (bool) env('TUTOR_ENABLED', false),

    // openai: llamadas reales. demo: respuestas de ejemplo rotuladas (solo pruebas locales).
    'provider' => env('TUTOR_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // Modelo pequeño y económico; sin razonamiento extra para responder rápido.
        'model' => env('TUTOR_MODEL', 'gpt-5.4-nano'),
        // Vacío = no se envía el parámetro (modelos que no lo admiten).
        'reasoning_effort' => env('TUTOR_REASONING_EFFORT', 'none'),
    ],

    // Usa los certificados raíz del sistema operativo (necesario en PHP para Windows,
    // que no trae un archivo de certificados). La verificación HTTPS sigue activa.
    'certificados_sistema' => (bool) env('TUTOR_CERTIFICADOS_SISTEMA', PHP_OS_FAMILY === 'Windows'),

    // Segundos máximos de espera por cada llamada al proveedor.
    'timeout' => (int) env('TUTOR_TIMEOUT', 25),
    // Tokens máximos de cada respuesta (respuestas cortas = menor costo).
    'max_tokens_respuesta' => (int) env('TUTOR_MAX_TOKENS', 600),
    // Consultas con IA por alumno y día. Las tarjetas no cuentan.
    'consultas_ia_por_dia' => (int) env('TUTOR_CONSULTAS_DIA', 25),
    // Acciones por minuto (con o sin IA), contra clics repetidos.
    'acciones_por_minuto' => (int) env('TUTOR_ACCIONES_MINUTO', 20),
    // Llamadas a herramientas permitidas en un mismo turno de la IA.
    'herramientas_por_turno' => (int) env('TUTOR_HERRAMIENTAS_TURNO', 3),
    // Mensajes anteriores que se envían como contexto (menos = más barato).
    'historial_mensajes' => 6,
    // Días sin actividad tras los que se borra una conversación (comando tutor:limpiar, diario).
    'retencion_dias' => (int) env('TUTOR_RETENCION_DIAS', 120),
    'max_caracteres_mensaje' => 600,

    'practica' => [
        'cantidades' => [3, 5, 10],
        'niveles' => ['basico' => 'Básico', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'],
    ],

    // Mascota del tutor: gatito CNI (WebP con fondo transparente; el mismo del login).
    'mascota' => 'images/mascotas/gatito-cni.webp',

    /*
     | Banco de práctica de cada universidad, por el nombre de la asignatura (tabla areas).
     | Lista vacía = banco general CNI (asignaturas sin prefijo, p. ej. «Álgebra»).
     */
    'bancos' => [
        'A' => ['UNDC %'],
        'B' => [],
        'C' => ['UNFV %'],
        'D' => [],
        'E' => ['San Marcos %'],
    ],

    // Asignaturas que nunca se usan para practicar (no tienen una clave única).
    'asignaturas_excluidas' => ['Actitudinal'],
];
