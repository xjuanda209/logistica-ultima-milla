<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->string('nombre', 120);
            $table->string('cedula', 15);
            $table->string('telefono', 15);
            $table->string('correo', 120)->nullable();
            $table->string('direccion', 200);
            $table->string('barrio', 80);
            $table->text('indicaciones')->nullable();
            $table->foreignId('zona_id')->constrained('zonas');
            $table->timestampsTz();

            $table->unique(['comercio_id', 'cedula']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
