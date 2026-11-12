<?php

/*
 | Habilidad Verbal y preguntas actitudinales del simulador San Marcos.
 | Habilidad Verbal: [enunciado, [correcta, 4 distractores], explicación].
 | Actitudinal: [situación, [5 alternativas de mayor a menor puntaje]]. La rúbrica (20, 15, 10, 5, 0)
 | es del simulador y NO es la rúbrica oficial de la UNMSM.
 */
$texto1 = "Lea el texto:\n«Los bosques amazónicos regulan las lluvias de buena parte de Sudamérica. Cuando se talan, no solo desaparecen árboles: se altera el ciclo del agua del que dependen la agricultura y las ciudades, incluso a miles de kilómetros.»\n";
$texto2 = "Lea el texto:\n«Leer no consiste en pasar los ojos por las palabras. El buen lector formula preguntas, relaciona lo que lee con lo que ya sabe y discute con el autor. Por eso, dos personas pueden leer el mismo libro y salir de él con ideas muy distintas.»\n";
$texto3 = "Lea el texto:\n«El quipu fue un sistema de cuerdas anudadas que los incas usaron para registrar información numérica, como censos y tributos. La posición y el tipo de cada nudo indicaban unidades, decenas o centenas.»\n";

return [
    'Razonamiento Verbal' => [
        ['Señale el sinónimo de EFÍMERO.', ['Pasajero', 'Eterno', 'Sólido', 'Valioso', 'Lejano'], 'Efímero significa que dura poco tiempo.'],
        ['Señale el antónimo de DILIGENTE.', ['Negligente', 'Esmerado', 'Activo', 'Rápido', 'Cuidadoso'], 'Diligente es quien actúa con cuidado y prontitud; lo contrario es negligente.'],
        ['PEZ es a AGUA como…', ['ave es a aire', 'perro es a gato', 'árbol es a fruto', 'barco es a mar', 'nube es a lluvia'], 'Relación ser vivo – medio en el que se desplaza.'],
        ['Señale el término que no pertenece al grupo: frágil, quebradizo, endeble, deleznable, robusto.', ['Robusto', 'Frágil', 'Quebradizo', 'Endeble', 'Deleznable'], 'Los demás significan que se rompe con facilidad; robusto es lo contrario.'],
        ['Señale el sinónimo de UBÉRRIMO.', ['Muy fértil', 'Muy pobre', 'Muy seco', 'Muy lejano', 'Muy pequeño'], 'Ubérrimo significa muy abundante y fértil.'],
        ['Señale el antónimo de PARCO.', ['Locuaz', 'Sobrio', 'Moderado', 'Callado', 'Escaso'], 'Parco es quien habla o gasta poco; lo contrario es locuaz.'],
        ['Complete: «El investigador fue ______ al revisar los datos: no dejó pasar ni un solo detalle.»', ['minucioso', 'descuidado', 'apresurado', 'indiferente', 'optimista'], 'La segunda parte de la oración explica que fue muy detallista.'],
        ['MÉDICO es a HOSPITAL como…', ['docente es a colegio', 'paciente es a medicina', 'juez es a ley', 'chofer es a auto', 'libro es a biblioteca'], 'Relación profesional – lugar donde trabaja.'],
        ['Complete con el conector adecuado: «Estudió durante semanas; ______, no logró aprobar el examen.»', ['sin embargo', 'por lo tanto', 'además', 'es decir', 'por ejemplo'], 'Se necesita un conector adversativo porque el resultado contradice lo esperado.'],
        [$texto1.'¿Cuál es la idea principal del texto?', ['La tala amazónica afecta el ciclo del agua del que dependen zonas muy lejanas.', 'La agricultura sudamericana depende solo de los ríos.', 'Los árboles amazónicos son los más altos del mundo.', 'Las ciudades provocan la tala de bosques.', 'Las lluvias de Sudamérica son impredecibles.'], 'El texto destaca que talar el bosque altera las lluvias a gran distancia.'],
        [$texto2.'Se infiere del texto que:', ['La comprensión de un libro depende también de lo que aporta el lector.', 'Todos los libros tienen una sola interpretación correcta.', 'Leer rápido garantiza comprender mejor.', 'Los buenos lectores no discuten con el autor.', 'Leer es una actividad pasiva.'], 'Si dos lectores sacan ideas distintas del mismo libro, el lector participa en el sentido.'],
        [$texto3.'Es incompatible con el texto afirmar que el quipu:', ['Servía únicamente para escribir poemas.', 'Registraba información numérica.', 'Usaba nudos con valor posicional.', 'Fue utilizado por los incas.', 'Permitía llevar cuentas de tributos.'], 'El texto dice que registraba datos numéricos como censos y tributos, no poemas.'],
    ],
    'Actitudinal' => [
        ['Durante un examen, un compañero te pide que le dejes copiar tus respuestas. ¿Qué haces?', ['Le explico que no es correcto y le ofrezco estudiar juntos después.', 'Me niego sin darle explicaciones.', 'Aviso al docente sin hablar antes con él.', 'Lo ignoro y sigo con mi examen.', 'Le dejo copiar para no perder su amistad.']],
        ['En un trabajo grupal, un integrante no cumple con su parte a pocos días de la entrega.', ['Converso con él para saber qué pasa y reorganizamos las tareas en equipo.', 'Le pido al grupo que lo ayudemos sin hablar con él.', 'Informo al docente para que lo sancione.', 'Hago yo su parte sin decir nada.', 'Pongo su nombre en el trabajo aunque no haya aportado y me quejo luego.']],
        ['Encuentras una billetera con dinero y documentos en el aula.', ['La entrego a la autoridad del colegio o busco al dueño con sus documentos.', 'La guardo y pregunto en el salón si alguien perdió algo.', 'La dejo donde estaba para no meterme en problemas.', 'Saco el dinero para devolverlo después.', 'Me quedo con ella porque nadie me vio.']],
        ['Obtienes una nota baja en un curso que consideras importante.', ['Reviso mis errores, pido orientación al docente y organizo un plan de estudio.', 'Estudio más por mi cuenta para el siguiente examen.', 'Le pido a un compañero sus apuntes.', 'Pienso que el examen fue injusto y no hago nada.', 'Dejo de esforzarme en ese curso.']],
        ['Un compañero nuevo es objeto de burlas por su forma de hablar.', ['Lo integro a mi grupo y digo con respeto a los demás que paren las burlas.', 'Converso con él para que se sienta acompañado.', 'Informo a un docente sin intervenir.', 'Me mantengo al margen.', 'Me río para no quedar mal con el grupo.']],
        ['Debes elegir entre salir con tus amigos o terminar un trabajo que vence mañana.', ['Termino el trabajo y propongo salir con mis amigos otro día.', 'Avanzo lo más importante y salgo un rato corto.', 'Salgo y pienso terminarlo de madrugada.', 'Pido a un compañero que me pase su trabajo.', 'Salgo y no entrego el trabajo.']],
        ['En tu barrio se organiza una campaña de limpieza un domingo.', ['Participo y animo a mis vecinos a sumarse.', 'Participo durante un tiempo.', 'Dono materiales, aunque no asisto.', 'Pienso que eso le corresponde a la municipalidad.', 'Critico la campaña porque no sirve de nada.']],
        ['Tu grupo propone presentar como propio un trabajo que encontraron en internet.', ['Propongo hacer un trabajo original y citar las fuentes que usemos.', 'Sugiero modificar el texto y citar la fuente.', 'Acepto, pero no participo en la exposición.', 'Acepto porque es lo que decidió la mayoría.', 'Lo presento como propio sin ningún problema.']],
        ['Un docente comete un error al corregir tu examen y te asigna más puntos de los que corresponden.', ['Le aviso del error, aunque baje mi nota.', 'Lo comento con mis padres antes de decidir.', 'Espero a ver si alguien más lo nota.', 'No digo nada porque no fue mi error.', 'Comento a mis compañeros cómo aprovechar el error.']],
        ['Estás en desacuerdo con una decisión que tomó tu equipo.', ['Expongo mis razones con respeto y acepto la decisión si la mayoría lo confirma.', 'Explico mi opinión y luego colaboro.', 'Colaboro, aunque sin mucho entusiasmo.', 'Me quejo con otros sin decir nada al equipo.', 'Dejo de colaborar con el equipo.']],
        ['Ves que en un espacio público una persona mayor necesita ayuda para cruzar la calle.', ['Me ofrezco a ayudarla con amabilidad.', 'Aviso a alguien cercano para que la ayude.', 'Espero a que otra persona la ayude.', 'Sigo mi camino porque tengo prisa.', 'Me burlo de su lentitud.']],
        ['Te piden representar a tu salón en un concurso, pero sientes miedo de hablar en público.', ['Acepto y me preparo practicando con ayuda de mis compañeros y docentes.', 'Acepto, aunque practico poco.', 'Propongo a otro compañero y ofrezco ayudarlo.', 'Digo que no puedo sin explicar el motivo.', 'Acepto y luego no me presento.']],
    ],
];
