<?php

/*
 * Configuración visual del portal del alumno (variante "derecho" del layout).
 * Aquí solo va lo que NO viene de la base de datos: menú, colores e íconos.
 */
return [

    /*
     * Menú lateral. "ruta" es el nombre de la ruta de Laravel: si todavía no existe,
     * el enlace queda en "#", y se marca como activo cuando la página actual coincide.
     * "icono" es un nombre de resources/views/components/alumno/icono.blade.php.
     */
    'menu' => [
        ['texto' => 'Inicio', 'icono' => 'inicio', 'ruta' => 'alumno.inicio'],
        ['texto' => 'Mis intentos', 'icono' => 'intentos', 'ruta' => 'alumno.intentos'],
        ['texto' => 'Banco de preguntas', 'icono' => 'banco', 'ruta' => 'alumno.preguntas'],
        ['texto' => 'Temas de estudio', 'icono' => 'libro', 'ruta' => 'alumno.temas'],
        ['texto' => 'Historial', 'icono' => 'historial', 'ruta' => 'alumno.historial'],
        ['texto' => 'Mi perfil', 'icono' => 'perfil', 'ruta' => 'alumno.perfil'],
    ],

    /*
     * Colores de las tarjetas de temas: se asignan en orden y se repiten
     * si hay más temas que colores.
     */
    'colores_temas' => [
        ['acento' => '#7a1f2b', 'fondo' => '#f8e9eb'], // guinda
        ['acento' => '#1f5fa8', 'fondo' => '#e7f0fb'], // azul
        ['acento' => '#b4461b', 'fondo' => '#fcece3'], // terracota
        ['acento' => '#2f7d4f', 'fondo' => '#e5f4eb'], // verde
        ['acento' => '#6b3fa0', 'fondo' => '#efe8f8'], // morado
        ['acento' => '#127575', 'fondo' => '#e2f3f2'], // turquesa
    ],

    /*
     * Ícono de cada tema según una palabra de su nombre (sin tildes, en minúsculas).
     * Se usa la primera coincidencia, por eso "procesal" va antes que "penal".
     */
    'iconos_temas' => [
        'constituc' => 'constitucion',
        'procesal' => 'procesal',
        'penal' => 'penal',
        'civil' => 'civil',
        'laboral' => 'laboral',
        'internacional' => 'internacional',
    ],
    'icono_tema_por_defecto' => 'libro',

];
