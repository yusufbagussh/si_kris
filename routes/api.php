<?php

use App\Http\Controllers\APM\QRIS\QRISController;
use App\Http\Controllers\APM\QRIS\QRISNotificationController;
use App\Http\Controllers\Kasir\KasirCetakanController;
use App\Http\Controllers\Kasir\KasirMainController;
use App\Http\Controllers\Kasir\KasirPembayaranController;
use App\Http\Controllers\Kasir\KasirTagihanController;
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

//Main QRIS Route
Route::post('/snap/qris/generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
Route::post('/snap/qris/query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.inquiry');
Route::post('/snap/list/payment-info', [QRISController::class, 'getListInfoPatientPayment'])->name('qris.notification');

//Webhook Notification For BRI
Route::post('/snap/v1.0/access-token/b2b', [QRISNotificationController::class, 'generateToken']);
Route::post('/snap/v1.1/qr/qr-mpm-notify', [QRISNotificationController::class, 'paymentNotification']);

//Generate Token & Signature
Route::post('/snap/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/snap/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);

Route::group(['prefix' => 'medinfras'], function () {
    Route::group(['prefix' => 'outpatient'], function () {
        Route::post('clinic', [KasirMainController::class, 'klinikRawatJalan']);
        Route::post('doctor', [KasirMainController::class, 'dokterRawatJalan']);
        Route::post('payment-info', [KasirMainController::class, 'kasirRajalPembayaranInfo']);
        Route::post('list-mesin-edc', [KasirMainController::class, 'listMesinEDCBank']);
        Route::post('list-tipe-kartu', [KasirMainController::class, 'listTipeKartuBank']);

        Route::post('list-patient', [KasirTagihanController::class, 'listPatient']);
        Route::post('detail-payment', [KasirTagihanController::class, 'detailPayment']);
        Route::post('list-patient-bill', [KasirTagihanController::class, 'listTagihanPasien']);
        Route::post('generate-payment-bill', [KasirTagihanController::class, 'generatePaymentBill']);
        Route::post('lock-transaction', [KasirTagihanController::class, 'lockTransaction']);

        Route::post('get-patient-bill', [KasirPembayaranController::class, 'getPatientBill']);
        Route::post('pay-bill', [KasirPembayaranController::class, 'doPaymentBill']);


        Route::post('list-patient-transaction', [KasirTagihanController::class, 'getListTransaksiByRegistrationNo']);
        Route::post('list-patient-bill', [KasirPembayaranController::class, 'getPatientBillByRegistrationNo']);
    });
});

// Main Route
Route::get('/kasir/rawat-jalan', [KasirMainController::class, 'index'])->name('kasir.rajal.index');

// Tagihan (Billing) Routes
Route::prefix('ajax/kasir/rawat_jalan/tagihan')->group(function () {
    Route::post('/pasien', [KasirTagihanController::class, 'listPatient'])->name('kasir.rajal.tagihan.pasien');
    Route::post('/detail', [KasirTagihanController::class, 'detailPayment'])->name('kasir.rajal.tagihan.detail');
    Route::post('/transaksi', [KasirTagihanController::class, 'listTagihanPasien'])->name('kasir.rajal.tagihan.transaksi');
    Route::post('/kunci', [KasirTagihanController::class, 'lockTransaction'])->name('kasir.rajal.tagihan.kunci');
    Route::post('/generate', [KasirTagihanController::class, 'generatePaymentBill'])->name('kasir.rajal.tagihan.generate');
});

// Pembayaran (Payment) Routes
Route::prefix('ajax/kasir/rawat_jalan/pembayaran')->group(function () {
    Route::post('/detail', [KasirPembayaranController::class, 'getPatientBill'])->name('kasir.rajal.pembayaran.detail');
    Route::post('/bayar_tagihan', [KasirPembayaranController::class, 'doPaymentBill'])->name('kasir.rajal.pembayaran.bayar');
});

// Cetakan (Printing) Routes
Route::prefix('ajax/kasir/rawat_jalan/cetakan')->group(function () {
    Route::post('/transaksi_pembayaran', [KasirCetakanController::class, 'getTransactionPayment'])->name('kasir.rajal.cetakan.transaksi');
    Route::post('/daftar_kwitansi', [KasirCetakanController::class, 'getListReceipt'])->name('kasir.rajal.cetakan.kwitansi');
    Route::post('/cetak_kwitansi', [KasirCetakanController::class, 'printReceipt'])->name('kasir.rajal.cetakan.cetak');
    Route::get('/cetak', [KasirCetakanController::class, 'printCetakanKwitansi'])->name('kasir.rajal.cetakan.print');
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
