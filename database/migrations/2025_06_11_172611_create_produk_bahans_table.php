<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('produk_bahans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')
                ->constrained('produks')
                ->onDelete('cascade');
            $table->foreignId('bahan_baku_id')
                ->constrained('bahan_bakus')
                ->onDelete('cascade');
            $table->integer('qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk_bahans');
    }
};
