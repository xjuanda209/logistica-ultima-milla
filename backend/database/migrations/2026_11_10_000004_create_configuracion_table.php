<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion', function (Blueprint $table) {
            $table->smallInteger('id')->default(1)->primary();
            $table->smallInteger('max_pedidos_activos')->default(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
    }
};
