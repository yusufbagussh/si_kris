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
            $table->foreignId('qris_transaction_id')->nullable()->constrained('qris_transactions');
            $table->string('reference_no');
            $table->string('partner_reference_no');
            $table->string('transaction_status')->nullable();
            $table->string('transaction_status_desc')->nullable();
            $table->string('customer_number');
            $table->string('account_type')->nullable();
            $table->string('destination_account_name');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('bank_code')->nullable();
            $table->text('raw_data');
            $table->timestamps();

            $table->index('reference_no');
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
