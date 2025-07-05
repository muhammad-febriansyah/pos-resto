<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // public function up(): void
    // {
    //     Schema::create('ratings', function (Blueprint $table) {
    //         $table->id();
    //         $table->foreignId('produk_id')->constrained('produks')->cascadeOnDelete();
    //         $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // pemberi rating
    //         $table->foreignId('transaction_id')->constrained()->cascadeOnDelete(); // jika rating untuk transaksi
    //         $table->tinyInteger('rating'); // 1 sampai 5
    //         $table->text('comment')->nullable(); // opsional komentar

    //         $table->timestamps();
    //     });
    // }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
