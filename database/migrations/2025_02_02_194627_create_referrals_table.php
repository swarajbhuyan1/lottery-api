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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users');
            $table->foreignId('referee_id')->constrained('users');
            $table->decimal('commission', 10, 2)->default(0);
            $table->enum('status', ['pending', 'credited'])->default('pending');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate referrals
            $table->unique(['referrer_id', 'referee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
