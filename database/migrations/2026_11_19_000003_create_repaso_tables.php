<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Preguntas guardadas, reportes de preguntas y meta semanal del alumno. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preguntas_guardadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('pregunta_id');
            $table->timestamps();

            // Una pregunta solo se guarda una vez por alumno.
            $table->unique(['usuario_id', 'pregunta_id'], 'uq_guardada_usuario_pregunta');
            $table->foreign('usuario_id', 'fk_guardada_usuario')->references('id')->on('usuarios')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pregunta_id', 'fk_guardada_pregunta')->references('id')->on('preguntas')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('reportes_pregunta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('pregunta_id');
            // Contexto opcional del reporte: un intento de simulacro o una práctica.
            $table->unsignedBigInteger('intento_id')->nullable();
            $table->unsignedBigInteger('practica_id')->nullable();
            $table->enum('motivo', ['clave_incorrecta', 'enunciado_confuso', 'imagen_ilegible', 'otro']);
            $table->string('comentario', 300)->nullable();
            // Un reporte nunca cambia la pregunta: un administrador lo revisa primero.
            $table->enum('estado', ['pendiente', 'en_revision', 'resuelto', 'descartado'])->default('pendiente');
            $table->unsignedBigInteger('revisado_por')->nullable();
            $table->dateTime('revisado_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'id'], 'idx_reportes_estado');
            $table->index(['usuario_id', 'pregunta_id'], 'idx_reportes_usuario_pregunta');
            $table->foreign('usuario_id', 'fk_reporte_usuario')->references('id')->on('usuarios')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pregunta_id', 'fk_reporte_pregunta')->references('id')->on('preguntas')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('intento_id', 'fk_reporte_intento')->references('id')->on('intentos')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('practica_id', 'fk_reporte_practica')->references('id')->on('practicas')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('revisado_por', 'fk_reporte_revisor')->references('id')->on('usuarios')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('metas_estudio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->unique();
            // Métrica: preguntas de práctica respondidas por semana (lunes a domingo).
            $table->unsignedSmallInteger('preguntas_semana');
            $table->timestamps();

            $table->foreign('usuario_id', 'fk_meta_usuario')->references('id')->on('usuarios')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_estudio');
        Schema::dropIfExists('reportes_pregunta');
        Schema::dropIfExists('preguntas_guardadas');
    }
};
