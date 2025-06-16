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
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // Nomor invoice unik
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('meja_id')
                ->nullable()
                ->constrained('mejas')
                ->nullOnDelete();
            $table->string('payment_method')
                ->default('cash');
            $table->string('type')->default('dine_in'); // cash, credit, etc.
            $table->bigInteger('sub_total')->default(0);
            $table->integer('ppn')->default(0); // Persentase PPN
            $table->bigInteger('biaya_layanan')->default(0); // Biaya layanan
            $table->bigInteger('total')->default(0); // Total setelah PPN dan biaya layanan
            $table->bigInteger('laba')->default(0); // Laba dari penjualan
            $table->string('duitku_reference')->nullable(); // Duitku's reference number
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penjualans');
    }
};
