<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->foreignId('tipo_incidencia_id')->constrained('tipos_incidencia');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->text('descripcion');
            $table->string('ruta_foto', 255)->nullable();
            $table->string('estado', 20)->default('abierta');
            $table->string('prioridad', 20)->nullable();
            $table->text('resolucion')->nullable();
            $table->text('nota_comercio')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
