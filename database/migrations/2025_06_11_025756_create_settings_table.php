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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name');
            $table->string('keyword');
            $table->string('description');
            $table->string('email');
            $table->string('phone');
            $table->string('address');
            $table->string('fb');
            $table->string('ig');
            $table->string('tiktok');
            $table->integer('ppn')->default(0);
            $table->bigInteger('biaya_lainnya');
            $table->string('logo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
