<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversaciones del Tutor CNI. Tablas nuevas e independientes:
 * no modifican ninguna tabla existente del simulador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            // Universidad (areas_postulacion.codigo) con la que se inició la conversación.
            $table->char('area_codigo', 1);
            // Estado del flujo (práctica en curso, tema actual, preguntas ya reveladas).
            $table->json('contexto')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'area_codigo', 'id'], 'idx_tutor_conv_usuario');
            $table->foreign('usuario_id', 'fk_tutor_conv_usuario')
                ->references('id')->on('usuarios')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('tutor_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversacion_id');
            $table->enum('rol', ['alumno', 'tutor']);
            // texto, pregunta (práctica), explicacion, aviso
            $table->string('tipo', 20)->default('texto');
            $table->text('contenido');
            // Datos estructurados: opciones a elegir, pregunta, alternativas, etc.
            $table->json('datos')->nullable();
            // sistema = datos del simulador; ia = generado por el modelo; demo = ejemplo.
            $table->enum('origen', ['sistema', 'ia', 'demo'])->default('sistema');
            $table->unsignedInteger('tokens')->nullable();
            $table->timestamps();

            $table->index(['conversacion_id', 'id'], 'idx_tutor_msg_conversacion');
            $table->index(['origen', 'created_at'], 'idx_tutor_msg_origen_fecha');
            $table->foreign('conversacion_id', 'fk_tutor_msg_conversacion')
                ->references('id')->on('tutor_conversaciones')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_mensajes');
        Schema::dropIfExists('tutor_conversaciones');
    }
};
