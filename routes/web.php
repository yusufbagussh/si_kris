<?php

use App\Http\Controllers\ECRLinkController;
use App\Http\Controllers\KasirRajalCetakanController;
use App\Http\Controllers\KasirRajalMainController;
use App\Http\Controllers\KasirRajalPembayaranController;
use App\Http\Controllers\KasirRajalTagihanController;
use App\Http\Controllers\QRISController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/**
 * QRIS Feature Routes
 */
Route::get('/qr', function () {
    return view('qris.generate-qr');
});

Route::get('/inquiry', function () {
    return view('qris.inquiry');
});

Route::post('/snap/qris/generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
Route::post('/snap/qris/query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.inquiry');

Route::post('/snap/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/snap/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);

Route::get('/snap/generate/client-credential', function () {
    return response()->json([
        'client_id' => \Illuminate\Support\Str::random(32),
        'client_secret' => \Illuminate\Support\Str::random(16),
    ]);
});

Route::get('/test-medinfras', function () {
    try {
        DB::connection('medinfras_dev')->getPdo();
        echo "Connected successfully to the database sql server!";
    } catch (\Exception $e) {
        die("Could not connect to the database. Error: " . $e->getMessage());
    }
});

// Main Route
Route::get('/kasir/rawat-jalan', [KasirRajalMainController::class, 'index'])->name('kasir.rajal.index');

// Tagihan (Billing) Routes
Route::prefix('ajax/kasir/rawat_jalan/tagihan')->group(function () {
    Route::post('/pasien', [KasirRajalTagihanController::class, 'listPatient'])->name('kasir.rajal.tagihan.pasien');
    Route::post('/detail', [KasirRajalTagihanController::class, 'detailPayment'])->name('kasir.rajal.tagihan.detail');
    Route::post('/transaksi', [KasirRajalTagihanController::class, 'listTagihanPasien'])->name('kasir.rajal.tagihan.transaksi');
    Route::post('/kunci', [KasirRajalTagihanController::class, 'lockTransaction'])->name('kasir.rajal.tagihan.kunci');
    Route::post('/generate', [KasirRajalTagihanController::class, 'generatePaymentBill'])->name('kasir.rajal.tagihan.generate');
});

// Pembayaran (Payment) Routes
Route::prefix('ajax/kasir/rawat_jalan/pembayaran')->group(function () {
    Route::post('/detail', [KasirRajalPembayaranController::class, 'getPatientBill'])->name('kasir.rajal.pembayaran.detail');
    Route::post('/bayar_tagihan', [KasirRajalPembayaranController::class, 'doPaymentBill'])->name('kasir.rajal.pembayaran.bayar');
});

// Cetakan (Printing) Routes
Route::prefix('ajax/kasir/rawat_jalan/cetakan')->group(function () {
    Route::post('/transaksi_pembayaran', [KasirRajalCetakanController::class, 'getTransactionPayment'])->name('kasir.rajal.cetakan.transaksi');
    Route::post('/daftar_kwitansi', [KasirRajalCetakanController::class, 'getListReceipt'])->name('kasir.rajal.cetakan.kwitansi');
    Route::post('/cetak_kwitansi', [KasirRajalCetakanController::class, 'printReceipt'])->name('kasir.rajal.cetakan.cetak');
    Route::get('/cetak', [KasirRajalCetakanController::class, 'printCetakanKwitansi'])->name('kasir.rajal.cetakan.print');
});

Route::post('/ecrlink', [ECRLinkController::class, 'sale']);
Route::post('/contactless', [ECRLinkController::class, 'contactless']);
