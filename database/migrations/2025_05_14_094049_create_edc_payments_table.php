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
        Schema::create('edc_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_payment_id')->comment('ID Nomer Registrasi pembayaran pasien');
            $table->string('transaction_id')->nullable()->unique()->comment('ID transaksi dari POS');
            $table->decimal('amount', 15, 2)->comment('Jumlah transaksi');
            $table->enum('method', ['purchase', 'brizzi', 'qris', 'contactless'])->default('purchase')->comment('Metode pembayaran');
            $table->enum('status', ['pending', 'success', 'failed', 'canceled'])->default('pending')->comment('Status transaksi');
            $table->string('action')->nullable()->comment('Nama Service EcrLink');
            $table->string('pos_address')->nullable()->comment('Alamat IP POS');
            $table->string('edc_address')->nullable()->comment('Alamat IP EDC');
            $table->string('trace_number')->nullable()->comment('Nomor trace dari EDC');
            $table->string('rc')->nullable()->comment('Kode respons dari EDC');
            $table->string('reference_number')->nullable()->comment('Nomor referensi dari host BRI');
            $table->string('reff_id')->nullable()->comment('Nomor referensi dari host BRI');
            $table->string('approval_code')->nullable()->comment('Kode persetujuan transaksi');
            $table->string('response_code')->nullable()->comment('Kode respons dari EDC');
            $table->string('batch_number')->nullable()->comment('Nomor batch transaksi');
            $table->string('card_type')->nullable()->comment('Tipe kartu yang digunakan');
            $table->string('card_name')->nullable()->comment('Nama kartu yang digunakan');
            $table->string('pan')->nullable()->comment('Nomor kartu masking');
            $table->string('card_category')->nullable()->comment('Kategori kartu');
            $table->boolean('is_credit')->nullable()->comment('Apakah kartu kredit atau debit');
            $table->boolean('is_off_us')->nullable()->comment('Apakah kartu non-BRI');
            $table->dateTime('transaction_date')->nullable()->comment('Waktu transaksi di EDC');
            $table->text('error')->nullable()->comment('Pesan error jika terjadi kegagalan');
            $table->text('message')->nullable()->comment('Pesan response dari ECRLink');
            $table->text('response_data')->nullable()->comment('Data respons lengkap dari EDC');
            $table->timestamps();
            $table->foreign('patient_payment_id')->references('id')->on('patient_payments')->comment('Foreign key ke tabel pembayaran pasien');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edc_payments');
    }
};
