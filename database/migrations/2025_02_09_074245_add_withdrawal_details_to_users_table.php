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
        Schema::table('users', function (Blueprint $table) {
            $table->string('withdrawal_type')->nullable(); // phonepe, paytm, gpay, bharatpay, bank
            $table->string('withdrawal_id')->nullable(); // UPI ID, Mobile Number, or Bank Account
            $table->string('bank_name')->nullable(); // If bank transfer
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['withdrawal_type', 'withdrawal_id', 'bank_name', 'account_number', 'ifsc_code']);
        });
    }
};
