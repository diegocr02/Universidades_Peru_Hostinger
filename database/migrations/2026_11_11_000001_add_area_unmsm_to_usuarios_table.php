<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Área académica de San Marcos (A, C o E) del alumno de la modalidad San Marcos.
 * Columna opcional y aditiva: no cambia areas_postulacion ni ningún dato existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->char('area_unmsm', 1)->nullable()->after('area_postulacion_id')
                ->comment('Área San Marcos autorizada (A, C o E); nulo si no aplica');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('area_unmsm');
        });
    }
};
