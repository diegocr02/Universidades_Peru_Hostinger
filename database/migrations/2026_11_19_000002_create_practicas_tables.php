<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Práctica por temas. Tablas propias (no `intentos`): los paneles, los reportes de
 * docentes y las estadísticas existentes leen `intentos` y no deben cambiar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            // Universidad (areas_postulacion.codigo) cuyo banco se usó.
            $table->char('area_codigo', 1);
            $table->string('asignatura', 150);
            $table->unsignedBigInteger('tema_id')->nullable();
            $table->string('nivel', 20)->nullable();
            // tema = práctica por temas; tutor = práctica dentro del chat; repaso = repetir errores.
            $table->string('origen', 20)->default('tema');
            $table->unsignedSmallInteger('total_preguntas');
            $table->unsignedSmallInteger('correctas')->default(0);
            $table->unsignedSmallInteger('incorrectas')->default(0);
            $table->dateTime('finalizada_en')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'id'], 'idx_practicas_usuario');
            $table->foreign('usuario_id', 'fk_practicas_usuario')->references('id')->on('usuarios')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('tema_id', 'fk_practicas_tema')->references('id')->on('temas')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('practica_respuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('practica_id');
            $table->unsignedBigInteger('pregunta_id');
            $table->unsignedSmallInteger('orden');
            $table->unsignedBigInteger('alternativa_id')->nullable();
            $table->enum('estado_respuesta', ['correcta', 'incorrecta', 'en_blanco'])->nullable();
            // Solo la primera respuesta cuenta: recargar o reintentar no suma dos veces.
            $table->dateTime('respondida_en')->nullable();
            $table->timestamps();

            $table->unique(['practica_id', 'pregunta_id'], 'uq_practica_pregunta');
            $table->unique(['practica_id', 'orden'], 'uq_practica_orden');
            $table->index(['pregunta_id'], 'idx_practica_resp_pregunta');
            $table->index(['respondida_en'], 'idx_practica_resp_fecha');
            $table->foreign('practica_id', 'fk_practica_resp_practica')->references('id')->on('practicas')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pregunta_id', 'fk_practica_resp_pregunta')->references('id')->on('preguntas')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('alternativa_id', 'fk_practica_resp_alternativa')->references('id')->on('alternativas')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practica_respuestas');
        Schema::dropIfExists('practicas');
    }
};
