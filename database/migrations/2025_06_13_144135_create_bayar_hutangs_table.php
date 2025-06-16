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
        Schema::create('bayar_hutangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hutang_id')->constrained('hutangs')->onDelete('cascade');
            $table->bigInteger('jml')->default(0);
            $table->date('tanggal')->nullable();
            $table->string('bukti')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bayar_hutangs');
    }
};
