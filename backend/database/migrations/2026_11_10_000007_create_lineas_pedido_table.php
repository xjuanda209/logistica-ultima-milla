<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->string('producto', 120);
            $table->integer('cantidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_pedido');
    }
};
