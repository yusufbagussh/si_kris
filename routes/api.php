<?php

use App\Http\Controllers\ECRLinkController;
use App\Http\Controllers\KasirRajalCetakanController;
use App\Http\Controllers\KasirRajalMainController;
use App\Http\Controllers\KasirRajalPembayaranController;
use App\Http\Controllers\KasirRajalTagihanController;
use App\Http\Controllers\QRISController;
use App\Http\Controllers\QRISNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/snap/v1.0/access-token/b2b', [QRISNotificationController::class, 'generateToken']);
Route::post('/snap/v1.1/qr/qr-mpm-notify', [QRISNotificationController::class, 'paymentNotification']);

Route::post('/snap/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/snap/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);

Route::group(['prefix' => 'medinfras'], function () {
    Route::group(['prefix' => 'outpatient'], function () {
        Route::post('clinic', [KasirRajalMainController::class, 'klinikRawatJalan']);
        Route::post('doctor', [KasirRajalMainController::class, 'dokterRawatJalan']);
        Route::post('payment-info', [KasirRajalMainController::class, 'kasirRajalPembayaranInfo']);
        Route::post('list-mesin-edc', [KasirRajalMainController::class, 'listMesinEDCBank']);
        Route::post('list-tipe-kartu', [KasirRajalMainController::class, 'listTipeKartuBank']);

        Route::post('list-patient', [KasirRajalTagihanController::class, 'listPatient']);
        Route::post('detail-payment', [KasirRajalTagihanController::class, 'detailPayment']);
        Route::post('list-patient-bill', [KasirRajalTagihanController::class, 'listTagihanPasien']);
        Route::post('generate-payment-bill', [KasirRajalTagihanController::class, 'generatePaymentBill']);
        Route::post('lock-transaction', [KasirRajalTagihanController::class, 'lockTransaction']);

        Route::post('get-patient-bill', [KasirRajalPembayaranController::class, 'getPatientBill']);
        Route::post('pay-bill', [KasirRajalPembayaranController::class, 'doPaymentBill']);


        Route::post('list-patient-transaction', [KasirRajalTagihanController::class, 'getListTransaksiByRegistrationNo']);
        Route::post('list-patient-bill', [KasirRajalPembayaranController::class, 'getPatientBillByRegistrationNo']);
    });
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


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


