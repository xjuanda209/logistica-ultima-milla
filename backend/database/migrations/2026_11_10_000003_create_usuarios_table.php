<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('correo', 120)->unique();
            $table->string('password', 255);
            $table->string('rol', 20);
            $table->boolean('activo')->default(true);
            $table->foreignId('comercio_id')->nullable()->constrained('comercios');
            $table->foreignId('zona_id')->nullable()->constrained('zonas');
            $table->string('disponibilidad', 20)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
