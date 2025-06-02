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
        Schema::create('qris_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qris_transaction_id')->constrained('qris_payments');
            $table->string('original_reference_no');
            $table->string('terminal_id');
            $table->string('response_code');
            $table->string('response_message');
            $table->tinyInteger('service_code');
            $table->string('latest_transaction_status');
            $table->string('transaction_status_desc');
            $table->string('customer_name');
            $table->string('customer_number');
            $table->string('invoice_number');
            $table->string('issuer_name');
            $table->string('issuer_rrn');
            $table->string('mpan');
            //$table->json('raw_response');
            $table->timestamps();

            $table->index('original_reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qri_inquiries');
    }
};
