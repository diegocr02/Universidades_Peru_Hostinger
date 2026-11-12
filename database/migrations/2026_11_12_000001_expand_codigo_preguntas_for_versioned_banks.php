<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preguntas', function (Blueprint $table) {
            $table->string('codigo', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Conservar el tamaño para no truncar códigos de bancos ya importados.
    }
};
