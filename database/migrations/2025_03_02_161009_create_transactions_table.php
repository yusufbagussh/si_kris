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
        Schema::create('bri.transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->after('id')->constrained('bri.patients');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 20, 2);
            $table->string('description')->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'CANCELED', 'EXPIRED', 'FAILED'])->default('PENDING');
            $table->enum('type', ['REGISTRATION', 'CONSULTATION', 'MEDICINE', 'LAB_TEST', 'OTHER'])->default('OTHER');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index('invoice_number');
            $table->index(['patient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bri.transactions');
    }
};
