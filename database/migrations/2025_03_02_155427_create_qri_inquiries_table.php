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
            $table->foreignId('qris_transaction_id')->nullable()->constrained('qris_transactions');
            $table->string('reference_no');
            $table->string('terminal_id');
            $table->string('response_code');
            $table->string('response_message');
            $table->string('transaction_status');
            $table->string('transaction_status_code');
            $table->string('transaction_status_desc')->nullable();
            $table->json('raw_response');
            $table->timestamps();

            $table->index('reference_no');
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
