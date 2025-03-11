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
        Schema::create('qris_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->string('partner_reference_no')->index();
            $table->decimal('amount', 20, 2);
            $table->string('currency')->default('IDR');
            $table->string('merchant_id');
            $table->string('terminal_id');
            $table->text('qr_content');
            $table->string('status');
            $table->string('status_code')->nullable();
            $table->string('status_description')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_rrn')->nullable();
            $table->string('response_code');
            $table->string('response_message');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_inquiry_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qris_transactions');
    }
};
