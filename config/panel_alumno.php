<?php

/*
 | Una sola plantilla de panel para las cinco áreas de postulación.
 | Los códigos A–E deben coincidir con areas_postulacion.codigo en MariaDB.
 | La carrera se consulta del perfil; nunca se asigna a partir de este mapa.
 | 'logo' = imagen del panel (logo de la universidad; en el área D, su mascota).
 */
return [
    'areas' => [
        'A' => [
            'titulo' => 'UNDC',
            'icono' => '✚',
            'descripcion' => 'Prepárate para ingresar a la Universidad Nacional de Cañete.',
            'lema' => 'Principio activo, innovador y humanista.',
            'logo' => 'images/universidades/undc-logo.webp',
        ],
        'B' => [
            'titulo' => 'UNI',
            'icono' => '⚙',
            'descripcion' => 'Prepárate para ingresar a la Universidad Nacional de Ingeniería.',
            'lema' => 'Scientia et Labor: ciencia y trabajo para construir el Perú.',
            'logo' => 'images/universidades/uni-logo.webp',
        ],
        'C' => [
            'titulo' => 'Villarreal',
            'icono' => '⌘',
            'descripcion' => 'Prepárate para ingresar a la Universidad Nacional Federico Villarreal.',
            'lema' => 'Cada desafío es una oportunidad para avanzar.',
            'logo' => 'images/universidades/villarreal-logo.webp',
        ],
        'D' => [
            'icono' => '▥',
            'descripcion' => 'Desarrolla tus habilidades para liderar y emprender.',
            'lema' => 'Las grandes decisiones empiezan con una buena preparación.',
            'logo' => 'images/mascotas/aguila-area-d-economicas.webp',
        ],
        'E' => [
            'titulo' => 'San Marcos',
            'icono' => '✦',
            'descripcion' => 'Prepárate para ingresar a la Universidad Nacional Mayor de San Marcos.',
            'lema' => 'Decana de América: la universidad más antigua del continente.',
            'logo' => 'images/universidades/san-marcos-logo.webp',
        ],
    ],

    /*
     | Menú lateral de las páginas de estudio (práctica, tutor, historial, guardadas):
     | replica el del panel de cada universidad. [texto, ícono, ancla del panel].
     | «Práctica» y «Tutor IA» se insertan después de Historial (UNDC: «Mis resultados»).
     */
    'menus' => [
        'A' => [['Inicio', '⌂', 'inicio'], ['Exámenes', '▤', 'examenes'], ['Mis resultados', '◷', 'resultados']],
        'B' => [['Examen UNI', '▶', 'examen'], ['Historial', '◷', 'historial'], ['Mi perfil', '♙', 'perfil']],
        'C' => [['Examen UNFV', '▶', 'examen'], ['Mi progreso', '▤', 'progreso'], ['Historial', '◷', 'historial'], ['Mi perfil', '♙', 'perfil']],
        'D' => [['Mi perfil', '♙', 'perfil'], ['Mi progreso', '▤', 'progreso'], ['Historial', '◷', 'historial']],
        'E' => [['Inicio', '⌂', 'inicio'], ['Mis intentos', '▶', 'intentos'], ['Historial', '◷', 'historial'], ['Mi perfil', '♙', 'perfil']],
    ],

    // Práctica por temas: cantidades permitidas por sesión.
    'practica' => [
        'cantidades' => [5, 10, 15, 20],
        'maximo' => 20,
    ],

    // Meta semanal sugerida (preguntas de práctica respondidas de lunes a domingo).
    'meta_semanal' => ['sugerida' => 30, 'minimo' => 5, 'maximo' => 500],
];
