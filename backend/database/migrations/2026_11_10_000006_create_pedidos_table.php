<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('repartidor_id')->nullable()->constrained('usuarios');
            $table->foreignId('creado_por')->constrained('usuarios');
            $table->string('estado', 20)->default('creado');
            $table->string('prioridad', 20);
            $table->string('direccion', 200);
            $table->string('barrio', 80);
            $table->text('indicaciones')->nullable();
            $table->foreignId('zona_id')->constrained('zonas');
            $table->timestampTz('franja_inicio');
            $table->timestampTz('franja_fin');
            $table->text('observaciones')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
