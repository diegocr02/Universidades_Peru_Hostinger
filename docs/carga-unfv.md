# Banco de práctica Villarreal — JSON + seeder

Destino: diegocr02/Universidades_Peru_Hostinger, rama deploy/hostinger.
Preparado sobre c4b12e82912499eeac9347f166bb8e501994037d.

Incluye 150 preguntas únicas del Excel de Jimmy, 750 alternativas y 153 imágenes
originales (150 enunciados y 3 alternativas). Conserva las claves originales,
sin inventar soluciones. La clasificación básico corresponde a la selección de
preguntas fáciles solicitada; las claves no se han revisado académicamente.
El JSON contiene las imágenes en base64 con verificación SHA-256.

## Subir desde GitHub (sin terminal)

1. Descomprimir Carga_UNFV_JSON_Seeder.zip en tu computadora.
2. Abrir diegocr02/Universidades_Peru_Hostinger y seleccionar deploy/hostinger.
3. Pulsar Add file → Upload files.
4. Arrastrar las cuatro carpetas app, database, docs y tests que están dentro
   del ZIP descomprimido. No arrastrar el ZIP ni la carpeta contenedora.
5. Comprobar que GitHub conserva las rutas y muestra seis archivos:
   - app/Support/BancoPracticaUnfv.php
   - app/Services/Alumno/ExamenIndependienteService.php
   - database/data/unfv_preguntas_practica.json
   - database/seeders/UnfvPreguntasSeeder.php
   - tests/Feature/BancoPracticaUnfvTest.php
   - docs/carga-unfv.md
6. Escribir «feat(unfv): cargar 150 preguntas de práctica desde JSON» y pulsar
   Commit changes en deploy/hostinger.

El reemplazo del servicio corresponde al commit base indicado. Revisar el diff
si alguien modificó ese archivo posteriormente. Los otros cinco archivos son nuevos.

## Aplicar

1. En un checkout del repositorio, revisar y copiar los archivos de este paquete
   respetando sus rutas. ExamenIndependienteService.php reemplaza un archivo
   existente: si cambió después del commit indicado, aplicar manualmente ese diff.
2. Ejecutar desde la raíz de Laravel:

```bash
php artisan db:seed --class=UnfvPreguntasSeeder --force
php artisan storage:link
php artisan optimize:clear
```

Si el enlace storage ya existe, no hace falta recrearlo. No ejecutar migrate:fresh.
El seeder se ejecuta expresamente; no se añade a DatabaseSeeder. Funciona también
en producción con --force, a diferencia del seeder preliminar de UNDC.

## Comportamiento

El código C corresponde a Villarreal según config/panel_alumno.php. El botón de
examen independiente de esa universidad usa un banco separado y selecciona 50
preguntas por intento de las 150, según las cuotas de práctica del Excel. Los
tres bloques originales quedan identificados en el JSON; el examen sortea entre
ellos por curso, no crea tres botones nuevos. Se conserva el tiempo y la escala
actual del motor. Es una práctica general, no una distribución oficial UNFV.

El banco usa asignaturas y códigos versionados exclusivos de UNFV. Repetir el
seeder omite preguntas ya importadas; no reescribe intentos ni claves previas.
Una nueva versión del JSON crea otro banco y otra plantilla. Los intentos de
plantillas anteriores se conservan en el historial.

## Validación

Se verificaron 150 códigos únicos, 3 bloques de 50 con cuotas iguales,
750 alternativas completas, 150 claves válidas A–E y 153 imágenes decodificables
con sus hashes. Se conservaron exactamente texto, claves y bytes del Excel.
No se ejecutaron PHP ni pruebas Laravel porque este entorno no dispone de PHP.
Antes de producción, ejecutar en una copia de la base y comprobar: importar dos
veces mantiene 150 preguntas; estudiante de C inicia 50 preguntas exclusivamente
de este banco; imágenes accesibles; respuesta correcta suma; otras universidades
mantienen sus bancos; intentos previos siguen disponibles.

## Activación y compatibilidad

Los archivos están preparados para deploy/hostinger. Subirlos a GitHub no ejecuta
el seeder en Hostinger: ejecutar los comandos de Aplicar en el servidor. Mientras
el banco nuevo no esté importado, Villarreal conserva su comportamiento previo.
