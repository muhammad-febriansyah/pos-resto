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
        Schema::create('pengeluarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreignId('kategori_catatan_id')
                ->constrained('kategori_catatans')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('nama_pengeluaran')->nullable();
            $table->bigInteger('jumlah')->default(0);
            $table->date('tanggal_pengeluaran')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengeluarans');
    }
};
