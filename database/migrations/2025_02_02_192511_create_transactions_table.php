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
        Schema::create('transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained()->onDelete('cascade');
                    $table->decimal('amount', 10, 2);
                    $table->enum('type', ['deposit', 'withdraw']);
                    $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
                    $table->string('transaction_id')->nullable(); // For payment gateway reference
                    $table->string('method')->nullable(); // paytm, upi, etc
                    $table->text('meta')->nullable(); // Additional payment data
                    $table->timestamps();

                    // Indexes for faster queries
                    $table->index(['user_id', 'type']);
                    $table->index('created_at');
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
