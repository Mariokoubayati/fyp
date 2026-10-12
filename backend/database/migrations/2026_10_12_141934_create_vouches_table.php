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
        Schema::create('vouches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vouchee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['voucher_id', 'vouchee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouches');
    }
};
