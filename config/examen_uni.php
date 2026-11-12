<?php

/*
 | Escala referencial de entrenamiento para el examen UNI en tres días (área B).
 | No es una certificación del puntaje oficial de la UNI.
 | Cambiar estos valores crea plantillas nuevas: los intentos ya rendidos conservan
 | la escala con la que se calificaron.
 */
return [
    'dias' => [
        1 => ['correcta' => 6.00, 'incorrecta' => 1.20, 'blanco' => 0.00, 'maximo' => 600],
        2 => ['correcta' => 15.00, 'incorrecta' => 3.00, 'blanco' => 0.00, 'maximo' => 600],
        3 => ['correcta' => 15.00, 'incorrecta' => 3.00, 'blanco' => 0.00, 'maximo' => 600],
    ],

    // Suma de los tres días; base de la equivalencia vigesimal referencial.
    'maximo_total' => 1800,
    'escala_vigesimal' => 20,
];
