<?php

namespace Database\Seeders;

use App\Support\PerfilExamenUnmsm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Agrega la asignatura "Actitudinal" (y su tema) que usa la modalidad San Marcos.
 * Idempotente: no modifica ni borra asignaturas, preguntas ni intentos existentes.
 * No crea preguntas ni puntajes de rúbrica: deben cargarse con la rúbrica validada.
 *   php artisan db:seed --class=UnmsmCatalogoSeeder
 */
class UnmsmCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $nombre = PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL;
        $areaId = DB::table('areas')->where('nombre', $nombre)->value('id');
        if (! $areaId) {
            // El enum de bloque no tiene "actitudinal"; la calificación la reconoce por el nombre.
            $areaId = DB::table('areas')->insertGetId([
                'nombre' => $nombre, 'bloque' => 'conocimientos', 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (! DB::table('temas')->where('area_id', $areaId)->where('nombre', $nombre)->exists()) {
            DB::table('temas')->insert([
                'area_id' => $areaId, 'nombre' => $nombre, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
