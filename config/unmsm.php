<?php

/*
 | Modalidad San Marcos (UNMSM). Vive dentro del área de postulación E del simulador.
 | Las claves de "fijas" y "conocimientos" son nombres de asignatura de la tabla `areas`
 | (el banco se genera con: php artisan db:seed --class=BancoUnmsmSeeder).
 */
return [
    'proceso' => '2027-I',
    'universidad' => 'Universidad Nacional Mayor de San Marcos',

    'minutos' => 90,

    // 10 actitudinales + 10 Habilidad Verbal + 10 Habilidad Lógico-Matemática.
    // Clave = asignatura en el banco; "etiqueta" = nombre que ve el alumno.
    'fijas' => [
        'Actitudinal' => ['cantidad' => 10, 'etiqueta' => 'Actitudinal'],
        'Razonamiento Verbal' => ['cantidad' => 10, 'etiqueta' => 'Habilidad Verbal'],
        'Habilidad Matemática' => ['cantidad' => 10, 'etiqueta' => 'Habilidad Lógico-Matemática'],
    ],

    /*
     | DISTRIBUCIÓN PROVISIONAL DEL SIMULADOR (no es la oficial). Cada área suma 70.
     | - Área A: tomada de un temario público no oficial (formatom.app, consultado el 11/11/2026).
     | - Áreas C y E: propuesta del simulador según el perfil de cada área.
     | Reemplazar por el cuadro del reglamento oficial UNMSM 2027-I y poner 'provisional' => false.
     */
    'provisional' => true,
    'conocimientos' => [
        'A' => [
            'Biología' => 8, 'Lenguaje' => 7, 'Química' => 7, 'Psicología' => 6, 'Física' => 5,
            'Aritmética' => 4, 'Literatura' => 4, 'Educación Cívica' => 4, 'Geografía' => 4,
            'Economía' => 4, 'Filosofía y Lógica' => 4, 'Geometría' => 3, 'Álgebra' => 3,
            'Historia del Perú' => 3, 'Trigonometría' => 2, 'Historia Universal' => 2,
        ],
        'C' => [
            'Física' => 9, 'Álgebra' => 8, 'Geometría' => 7, 'Aritmética' => 6, 'Trigonometría' => 6,
            'Química' => 6, 'Lenguaje' => 5, 'Biología' => 3, 'Literatura' => 3, 'Psicología' => 3,
            'Educación Cívica' => 3, 'Geografía' => 3, 'Economía' => 2, 'Filosofía y Lógica' => 2,
            'Historia del Perú' => 2, 'Historia Universal' => 2,
        ],
        'E' => [
            'Lenguaje' => 8, 'Literatura' => 6, 'Educación Cívica' => 6, 'Filosofía y Lógica' => 6,
            'Historia del Perú' => 6, 'Psicología' => 5, 'Geografía' => 5, 'Historia Universal' => 5,
            'Economía' => 4, 'Aritmética' => 4, 'Biología' => 3, 'Álgebra' => 3, 'Geometría' => 3,
            'Trigonometría' => 2, 'Física' => 2, 'Química' => 2,
        ],
    ],

    // Puntaje mínimo referencial del proceso indicado (dato proporcionado; no es un corte por carrera).
    'minimo_referencial' => [
        'A' => 900,
        'C' => 900,
        'E' => 700,
    ],
];
