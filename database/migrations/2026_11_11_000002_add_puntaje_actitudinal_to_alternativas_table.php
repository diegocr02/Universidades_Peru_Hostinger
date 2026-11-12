<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rúbrica de las preguntas actitudinales: puntaje (0 a 20) de cada alternativa.
 * Nulo en todas las preguntas ordinarias, que siguen calificándose con es_correcta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alternativas', function (Blueprint $table) {
            $table->decimal('puntaje_actitudinal', 7, 3)->nullable()->after('es_correcta')
                ->comment('Puntaje de la rúbrica actitudinal; nulo en preguntas ordinarias');
        });
    }

    public function down(): void
    {
        Schema::table('alternativas', function (Blueprint $table) {
            $table->dropColumn('puntaje_actitudinal');
        });
    }
};
