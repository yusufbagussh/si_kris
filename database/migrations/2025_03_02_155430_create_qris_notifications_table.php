<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('qris_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qris_transaction_id')->nullable()->constrained('qris_payments');
            $table->string('original_reference_no');
            $table->string('partner_reference_no');
            $table->string('external_id')->unique();
            $table->string('latest_transaction_status')->nullable();
            $table->string('transaction_status_desc')->nullable();
            $table->string('customer_number');
            $table->string('account_type')->nullable();
            $table->string('destination_account_name');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('bank_code')->nullable();
            $table->string('session_id')->nullable();
            $table->string('external_store_id')->nullable();
            $table->string('reff_id')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_rrn')->nullable();
            $table->json('raw_request');
            $table->json('raw_header');
            $table->timestamps();
            //$table->text('raw_data');

            $table->index('original_reference_no');
            $table->index('partner_reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qris_notifications');
    }
};
